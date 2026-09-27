"""Vigia um monitoramento em andamento.

Abre a página, ignora o que já estava lá e envia cada mensagem nova ao backend, que a salva, classifica
com o Laya e mostra no navegador do usuário.

Cada monitoramento usa duas threads:
- a de leitura relê a página periodicamente e coloca as mensagens novas numa fila;
- a de envio tira as mensagens da fila e as envia uma por uma.

Assim, uma classificação demorada nunca atrasa a leitura da página.
"""

from __future__ import annotations

import queue
import sys
import threading
import time
from datetime import datetime, timezone

from src.modules.monitoring.dedupe import SeenIds
from src.modules.sources.errors import SourceGone, WatchError
from src.modules.sources.pages import open_page
from src.shared.backend_client import MonitorStopped

# Comentários escritos até este tempo antes do início ainda contam como "de antes". A folga cobre a
# diferença entre o relógio do Instagram e o desta máquina.
START_MARGIN_SECONDS = 60
# Espera máxima entre as tentativas de um monitoramento que está falhando.
MAX_RETRY_DELAY_SECONDS = 120
# Quantas falhas seguidas de "chat/comentários não encontrados" até o monitoramento ser pausado
# sozinho. Com as esperas entre tentativas, isso dá cerca de 8 minutos sem a live ou o post.
SOURCE_GONE_LIMIT = 5

SOURCE_NAMES = {"youtube_live": "a live do YouTube", "instagram_post": "o post do Instagram"}


def describe_error(error: Exception) -> str:
    """Transforma um erro numa mensagem que o usuário entenda, com a instrução de como resolver
    quando o problema é o Chrome."""
    if isinstance(error, WatchError):
        return str(error)
    text = f"{type(error).__name__}: {error}".strip()
    if "permission-blocked" in text or "remote debugging is turned off" in text:
        return (
            "O Chrome não liberou o acesso: abra chrome://inspect/#remote-debugging, marque "
            "\"Allow remote debugging for this browser instance\" e clique em Permitir (Allow) no aviso do Chrome."
        )
    if isinstance(error, (ConnectionError, TimeoutError, OSError)) or any(
        word in text.lower() for word in ("daemon", "chrome", "cdp", "target", "websocket")
    ):
        return (
            "Não consegui controlar o Chrome. Deixe o Chrome aberto com "
            "chrome://inspect/#remote-debugging → \"Allow remote debugging\" marcado. "
            f"({text[:200]})"
        )
    return text[:400]


class MonitorWatcher:
    def __init__(
        self,
        monitor: dict,
        backend,
        *,
        page_opener=open_page,
        ready_timeout: float = 45,
        retry_delay: float = 15,
        baseline_settle: float = 1.5,
        source_gone_limit: int = SOURCE_GONE_LIMIT,
        log=None,
    ) -> None:
        self.monitor = monitor
        self.key = (monitor["id"], monitor.get("startedAt"))
        self._backend = backend
        self._open_page = page_opener
        self._ready_timeout = ready_timeout
        self._retry_delay = retry_delay
        self._baseline_settle = baseline_settle
        self._source_gone_limit = source_gone_limit
        self._log = log or (lambda text: print(text, file=sys.stderr, flush=True))
        self._stop = threading.Event()
        self._last_progress_report = 0.0
        self._failures = 0
        self._gone_failures = 0
        self._queue: queue.Queue = queue.Queue()
        self._watch_thread = threading.Thread(target=self._watch, daemon=True, name=f"watch-{monitor['id'][:8]}")
        self._send_thread = threading.Thread(target=self._send, daemon=True, name=f"send-{monitor['id'][:8]}")

    @property
    def stopped(self) -> bool:
        return self._stop.is_set()

    def start(self) -> None:
        self._log(f"[{self._short}] starting: {self.monitor['url']}")
        self._watch_thread.start()
        self._send_thread.start()

    def stop(self) -> None:
        if not self._stop.is_set():
            self._log(f"[{self._short}] stopping")
        self._stop.set()
        self._queue.put(None)

    def is_alive(self) -> bool:
        return self._watch_thread.is_alive()

    def join(self, timeout: float | None = None) -> None:
        self._watch_thread.join(timeout)
        self._send_thread.join(timeout)

    @property
    def _short(self) -> str:
        return self.monitor["id"][:8]

    # ── leitura da página ─────────────────────────────────────────────────

    def _watch(self) -> None:
        """Abre a página e a vigia. Em caso de erro, mostra no painel, espera e tenta de novo."""
        while not self._stop.is_set():
            page = None
            try:
                source = SOURCE_NAMES.get(self.monitor["sourceType"], "a página")
                self._report(
                    "starting",
                    f"Abrindo {source} no Chrome... Se o Chrome perguntar \"Allow remote debugging?\", clique em Allow.",
                )
                page = self._open_page(self.monitor)
                if self._stop.is_set():
                    break
                self._watch_page(page)
            except MonitorStopped:
                self._stop.set()
            except Exception as error:  # noqa: BLE001 - qualquer falha vira um estado visível no painel e uma nova tentativa
                if page is not None:
                    # A própria aba pode ser o problema (fechada à mão, travou): fecha agora, não depois da espera.
                    page.close(discard=True)
                    page = None
                self._failures += 1
                # Cada falha seguida espera o dobro da anterior (por exemplo, quando a live acabou),
                # para não ficar abrindo e fechando abas a cada 15 s.
                delay = min(self._retry_delay * 2 ** (self._failures - 1), MAX_RETRY_DELAY_SECONDS)
                detail = describe_error(error)
                self._log(f"[{self._short}] error: {detail}")
                # Só "a live/post sumiu" conta para a pausa automática. Chrome inacessível é um
                # problema local, que o usuário resolve.
                self._gone_failures = self._gone_failures + 1 if isinstance(error, SourceGone) else 0
                if self._gone_failures >= self._source_gone_limit:
                    self._finish(detail)
                    break
                try:
                    self._report("error", f"{detail} Tentando de novo em {int(delay)} s.")
                except MonitorStopped:
                    self._stop.set()
                except Exception:  # noqa: BLE001 - backend fora do ar: continua tentando mesmo assim
                    pass
                self._stop.wait(delay)
            finally:
                if page is not None:
                    page.close()
        self._queue.put(None)

    def _watch_page(self, page) -> None:
        # Comentários que trazem a própria data (Instagram) são julgados por ela: escrito antes do
        # início é antigo. A folga cobre a diferença de relógio entre o Instagram e esta máquina.
        not_before = time.time() - START_MARGIN_SECONDS
        import_existing = bool(self.monitor.get("includeExistingComments")) and hasattr(page, "load_all")

        messages = self._wait_until_ready(page)
        if messages is None:
            return
        page.prepare()
        seen = SeenIds()

        if import_existing:
            # Tudo o que o post já tinha também vai para a classificação. O backend descarta, pelo id
            # do comentário, os que um início anterior já tinha salvado.
            self._report("starting", "Carregando os comentários existentes do post...")
            existing = page.load_all(
                lambda count: self._report_progress(f"Carregando os comentários existentes do post: {count}...")
            )
            fresh = seen.new_messages(existing)
            for message in fresh:
                self._queue.put((message, _received_at(message)))
            self._log(f"[{self._short}] watching (importing {len(fresh)} existing comments)")
            self._report(
                "watching",
                f"{page.watching_detail} {len(fresh)} comentários existentes lidos e enviados para classificação "
                "(os que já tinham sido importados não se repetem).",
            )
        else:
            # Linha de base: tudo o que a página já mostra foi dito antes de o monitoramento começar.
            # Lemos duas vezes, com uma pausa, porque a página pode ainda estar terminando de carregar.
            seen.mark_seen(messages)
            if self._stop.wait(self._baseline_settle):
                return
            seen.mark_seen(page.read() or [])
            self._log(f"[{self._short}] watching (ignored {len(seen._seen)} messages already on the page)")
            self._report("watching", page.watching_detail)

        self._failures = 0  # voltou a vigiar: a próxima falha recomeça da espera curta
        self._gone_failures = 0
        missing_since = None
        while not self._stop.wait(page.poll_interval):
            messages = page.read()
            if messages is None:
                missing_since = missing_since or time.monotonic()
                if time.monotonic() - missing_since > self._ready_timeout:
                    raise SourceGone("O chat/comentários sumiram da página (a live terminou?).")
                continue
            missing_since = None
            for message in seen.new_messages(messages):
                if not import_existing and _written_before(message.get("at"), not_before):
                    continue  # comentário antigo que só apareceu agora (o Instagram carrega por relevância)
                self._queue.put((message, _received_at(message)))

    def _report_progress(self, detail: str) -> None:
        """Como `_report`, mas no máximo uma vez a cada 3 s."""
        now = time.monotonic()
        if now - self._last_progress_report >= 3:
            self._last_progress_report = now
            self._report("starting", detail)

    def _wait_until_ready(self, page) -> list[dict] | None:
        """Espera o chat ou os comentários aparecerem na página e devolve o que estiver lá.

        None se o watcher for parado durante a espera.
        """
        deadline = time.monotonic() + self._ready_timeout
        while not self._stop.is_set():
            messages = page.read()
            if messages is not None:
                return messages
            if time.monotonic() > deadline:
                raise SourceGone(
                    "Não encontrei o chat/comentários na página. A live está no ar e com o chat ativado?"
                )
            self._stop.wait(0.5)
        return None

    def _finish(self, detail: str) -> None:
        """Pausa o monitoramento no backend depois de várias tentativas sem achar a live ou o post."""
        reason = (
            f"Pausado automaticamente após {self._gone_failures} tentativas seguidas sem encontrar o "
            f"chat/comentários ({detail}). Se a live/post voltar, é só iniciar de novo."
        )
        self._log(f"[{self._short}] source gone {self._gone_failures}x: finishing the monitor")
        try:
            self._backend.finish(self.monitor["id"], reason)
        except Exception as error:  # noqa: BLE001 - pausado/excluído nesse meio-tempo, ou backend fora do ar
            self._log(f"[{self._short}] could not finish the monitor: {error}")
        self._stop.set()

    def _report(self, state: str, detail: str | None = None) -> None:
        if not self._stop.is_set():
            self._backend.report_state(self.monitor["id"], state, detail)

    # ── envio ao backend ──────────────────────────────────────────────────

    def _send(self) -> None:
        """Envia as mensagens da fila, uma por uma, até o watcher parar.

        Um `None` na fila só acorda a thread para ela conferir se deve parar.
        """
        while True:
            item = self._queue.get()
            if item is None:
                if self._stop.is_set():
                    return
                continue
            if self._stop.is_set():
                continue
            message, received_at = item
            try:
                stored = self._backend.post_message(
                    self.monitor["id"],
                    external_id=message["id"],
                    author_name=message.get("author") or "desconhecido",
                    text=message["text"],
                    received_at=received_at,
                )
                self._log(f"[{self._short}] [{stored.get('label') or '-'}] {message['text'][:70]!r}")
            except MonitorStopped:
                self.stop()
            except Exception as error:  # noqa: BLE001 - perder uma mensagem não pode parar as outras
                self._log(f"[{self._short}] could not send message {message['id']}: {error}")


def _received_at(message: dict) -> datetime:
    """Quando a mensagem foi escrita, se a página informa (Instagram). Senão, quando foi vista."""
    at = message.get("at")
    if at:
        try:
            return datetime.fromisoformat(at.replace("Z", "+00:00"))
        except ValueError:
            pass
    return datetime.now(timezone.utc)


def _written_before(at: str | None, not_before: float) -> bool:
    if not at:
        return False
    try:
        return datetime.fromisoformat(at.replace("Z", "+00:00")).timestamp() < not_before
    except ValueError:
        return False
