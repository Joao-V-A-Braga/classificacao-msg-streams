"""Abrir, navegar, ler e fechar as abas que o serviço de ingestão usa no Chrome."""

from __future__ import annotations

import threading
import time
from pathlib import Path

from src.shared.browser.tab_registry import TabRegistry

# Quanto tempo esperar o Chrome aceitar a conexão antes de desistir e mostrar o erro ao usuário.
CHROME_CONNECT_SECONDS = 30

# apps/ingestion/logs/tabs.json
OPEN_TABS = TabRegistry(Path(__file__).resolve().parents[3] / "logs" / "tabs.json")

_browser_lock = threading.Lock()
_leftovers_closed = False


def open_browser(url: str):
    """Abre uma nova aba em segundo plano no Chrome do usuário e navega até `url`.

    As aberturas acontecem uma de cada vez, porque a primeira também liga o processo do Browser
    Harness, que não pode ser ligado duas vezes ao mesmo tempo.
    """
    global _leftovers_closed
    from browser_harness.admin import ensure_daemon
    from browser_harness.helpers import cdp

    from src.shared.browser.browser import Browser

    with _browser_lock:
        # O Browser() espera o Chrome para sempre. Conectando antes, com prazo, um "depuração remota
        # desligada" ou um "Allow" não clicado vira um erro que o watcher mostra e tenta de novo.
        try:
            ensure_daemon(wait=CHROME_CONNECT_SECONDS)
        except RuntimeError as error:
            raise RuntimeError(f"{error} {_daemon_log_tail()}".strip()) from error
        if not _leftovers_closed:
            OPEN_TABS.close_leftovers(lambda target: cdp("Target.closeTarget", targetId=target))
            _leftovers_closed = True
        browser = Browser(url)
        OPEN_TABS.add(browser.target)
        return browser


def close_browser(browser) -> None:
    target = getattr(browser, "target", None)
    try:
        browser.close()
    except Exception:  # noqa: BLE001 - a aba pode já ter sumido junto com o Chrome
        pass
    if target:
        OPEN_TABS.remove(target)


def navigate(browser, url: str, timeout: float = 15) -> None:
    """Navega a aba até `url` e espera a página terminar de carregar (ou o prazo acabar)."""
    browser.call("Page.navigate", url=url)
    time.sleep(0.5)
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        try:
            if browser.evaluate("document.readyState") == "complete":
                return
        except ValueError:  # a página trocou no meio da navegação (StalePage)
            pass
        time.sleep(0.2)


def read_safely(reader, browser):
    """Chama `reader(browser)`, devolvendo None se a página mudar durante a leitura."""
    try:
        return reader(browser)
    except ValueError:  # StalePage
        return None


def _daemon_log_tail() -> str:
    """Última linha do log do Browser Harness. Costuma dizer por que o Chrome recusou a conexão
    (por exemplo, depuração remota desligada)."""
    try:
        from browser_harness import _ipc

        lines = _ipc.log_path("default").read_text(encoding="utf-8", errors="replace").strip().splitlines()
        return lines[-1][:300] if lines else ""
    except Exception:  # noqa: BLE001 - é só uma dica para a mensagem de erro
        return ""
