"""Liga e desliga sozinho o serviço de ingestão (Laya + runner), acompanhando o app.

O supervisor é um processo leve (não carrega o Laya nem o PyTorch) que fica sempre ligado, sem
janela. A cada poucos segundos, pergunta ao backend quais monitoramentos estão rodando:

- se há monitoramentos rodando e o serviço está desligado, liga o serviço e avisa esses
  monitoramentos que ele está subindo;
- se não há nenhum rodando por `idle_stop_seconds`, desliga o serviço (libera a memória do Laya);
- se o serviço caiu com monitoramentos rodando, mostra o erro neles e o liga de novo, esperando um
  pouco mais a cada queda seguida.

O serviço só é desligado depois que os monitoramentos foram pausados, quando o runner já fechou as
abas deles.
"""

from __future__ import annotations

import sys
import time
from typing import Callable, Protocol

from src.shared.backend_client import MonitorStopped

# Um serviço que ficou no ar por esse tempo antes de cair não estava em loop de falhas: é religado
# com a espera mais curta.
STABLE_RUN_SECONDS = 120


class WorkerProcess(Protocol):
    """O processo do serviço de ingestão, do jeito que o supervisor precisa enxergá-lo."""

    def is_running(self) -> bool: ...

    def stop(self) -> None: ...

    def log_tail(self) -> str: ...


class IngestionSupervisor:
    def __init__(
        self,
        backend,
        start_worker: Callable[[], WorkerProcess],
        *,
        external_worker_alive: Callable[[], bool] = lambda: False,
        idle_stop_seconds: float = 120,
        restart_delays: tuple[float, ...] = (5, 15, 30, 60),
        clock: Callable[[], float] = time.monotonic,
        log=None,
    ) -> None:
        self._backend = backend
        self._start_worker = start_worker
        self._external_worker_alive = external_worker_alive
        self._idle_stop_seconds = idle_stop_seconds
        self._restart_delays = restart_delays
        self._clock = clock
        self._log = log or (lambda text: print(text, file=sys.stderr, flush=True))
        self.worker: WorkerProcess | None = None
        self._idle_since: float | None = None
        self._crashes = 0
        self._worker_started_at = 0.0
        self._restart_at: float | None = None
        self._backend_down = False

    def tick(self) -> None:
        """Uma rodada: consulta o backend e liga, desliga ou religa o serviço conforme o caso."""
        try:
            running = self._backend.running_monitors()
        except Exception as error:  # noqa: BLE001 - o backend pode estar reiniciando: deixa tudo como está
            if not self._backend_down:
                self._log(f"backend unreachable: {error}")
            self._backend_down = True
            return
        self._backend_down = False
        now = self._clock()

        if self.worker is not None and not self.worker.is_running():
            self._on_worker_exit(running, now)

        if not running:
            self._restart_at = None
            self._crashes = 0
            if self.worker is None:
                return
            if self._idle_since is None:
                self._idle_since = now
            if now - self._idle_since >= self._idle_stop_seconds:
                self._log("no running monitors: stopping the ingestion service")
                self.worker.stop()
                self.worker = None
                self._idle_since = None
            return

        self._idle_since = None
        if self.worker is not None:
            return
        if self._restart_at is not None and now < self._restart_at:
            return
        if self._external_worker_alive():
            return  # alguém rodou o ingestion_service.py à mão: não mexe nele

        self._log(f"{len(running)} running monitor(s): starting the ingestion service")
        self.worker = self._start_worker()
        self._worker_started_at = now
        self._restart_at = None
        self._report(running, "starting", "Iniciando o serviço de ingestão (carregando o Laya, leva até 1 minuto)...")

    def _on_worker_exit(self, running: list[dict], now: float) -> None:
        """O serviço caiu: agenda a religação e mostra o erro nos monitoramentos em andamento."""
        tail = self.worker.log_tail() if self.worker else ""
        self.worker = None
        if not running:
            return
        if now - self._worker_started_at >= STABLE_RUN_SECONDS:
            self._crashes = 0  # estava funcionando bem: é uma falha nova, sem relação com as anteriores
        delay = self._restart_delays[min(self._crashes, len(self._restart_delays) - 1)]
        self._crashes += 1
        self._restart_at = now + delay
        self._log(f"ingestion service exited; restarting in {delay:.0f}s. {tail}")
        self._report(
            running,
            "error",
            f"O serviço de ingestão parou ({tail[-250:] or 'sem detalhes'}). Reiniciando em {delay:.0f} s.",
        )

    def stop(self) -> None:
        if self.worker is not None:
            self.worker.stop()
            self.worker = None

    def _report(self, monitors: list[dict], state: str, detail: str) -> None:
        for monitor in monitors:
            try:
                self._backend.report_state(monitor["id"], state, detail)
            except MonitorStopped:
                pass
            except Exception as error:  # noqa: BLE001
                self._log(f"could not report state of {monitor['id']}: {error}")
