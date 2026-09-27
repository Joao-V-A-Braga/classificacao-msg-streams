"""Uma aba em segundo plano no Chrome do usuário, controlada pelo Browser Harness.

Cada aba tem a sua própria sessão CDP (o protocolo de depuração do Chrome), por onde rodamos
JavaScript na página e enviamos comandos como "navegar para tal URL".
"""

from __future__ import annotations

import time

from browser_harness.admin import ensure_daemon
from browser_harness.helpers import cdp


class StalePage(ValueError):
    """A página mudou (navegou ou recarregou) enquanto um JavaScript estava rodando nela."""


class Browser:
    def __init__(self, url: str) -> None:
        ensure_daemon()
        self.target = cdp("Target.createTarget", url="about:blank", background=True)["targetId"]
        self.session = cdp("Target.attachToTarget", targetId=self.target, flatten=True)["sessionId"]
        self.call("Emulation.setDeviceMetricsOverride", width=1120, height=780, deviceScaleFactor=1, mobile=False)
        # Faz a aba se comportar como se estivesse em foco (animações e menus continuam funcionando)
        # sem trazê-la para a frente das abas do usuário.
        self.call("Emulation.setFocusEmulationEnabled", enabled=True)
        self.call("Page.navigate", url=url)
        deadline = time.monotonic() + 15
        while time.monotonic() < deadline:
            if self.evaluate("document.readyState") == "complete":
                break
            time.sleep(0.02)

    def call(self, method: str, **params):
        """Envia um comando CDP para esta aba."""
        return cdp(method, session_id=self.session, **params)

    def evaluate(self, expression: str):
        """Roda um JavaScript na página e devolve o resultado."""
        response = self.call("Runtime.evaluate", expression=expression, returnByValue=True)
        if response.get("exceptionDetails"):
            raise StalePage("Document changed during evaluation")
        return response.get("result", {}).get("value")

    def close(self) -> None:
        if self.target:
            cdp("Target.closeTarget", targetId=self.target)
            self.target = None
