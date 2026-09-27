"""Mantém um watcher para cada monitoramento em andamento, acompanhando o que o usuário inicia e
pausa no app.

A cada poucos segundos, pergunta ao backend quais monitoramentos estão rodando. Cada um é identificado
pelo par (id, startedAt): pausar e iniciar de novo muda o `startedAt`, então o watcher antigo é
trocado por um novo, que tira uma linha de base nova. Ou seja, cada início observa a live do zero.
"""

from __future__ import annotations

import sys
import threading

from src.modules.monitoring.watcher import MonitorWatcher


class MonitorRunner:
    def __init__(self, backend, *, make_watcher=None, interval: float = 3, log=None) -> None:
        self._backend = backend
        self._make_watcher = make_watcher or (lambda monitor: MonitorWatcher(monitor, backend))
        self._interval = interval
        self._log = log or (lambda text: print(text, file=sys.stderr, flush=True))
        self._backend_down = False
        self.watchers: dict[tuple, object] = {}

    def reconcile(self) -> None:
        """Deixa os watchers iguais à lista de monitoramentos em andamento: encerra os que sobram (ou
        morreram) e inicia os que faltam."""
        try:
            running = self._backend.running_monitors()
        except Exception as error:  # noqa: BLE001 - o backend pode estar reiniciando: mantém os watchers atuais
            if not self._backend_down:
                self._log(f"backend unreachable, keeping the current watchers: {error}")
            self._backend_down = True
            return
        if self._backend_down:
            self._log("backend reachable again")
        self._backend_down = False

        wanted = {(monitor["id"], monitor.get("startedAt")): monitor for monitor in running}

        for key, watcher in list(self.watchers.items()):
            if key not in wanted or watcher.stopped or not watcher.is_alive():
                watcher.stop()
                del self.watchers[key]

        for key, monitor in wanted.items():
            if key not in self.watchers:
                watcher = self._make_watcher(monitor)
                self.watchers[key] = watcher
                watcher.start()

    def run_forever(self, stop: threading.Event) -> None:
        while not stop.is_set():
            self.reconcile()
            stop.wait(self._interval)
        self.stop_all()

    def stop_all(self) -> None:
        for watcher in self.watchers.values():
            watcher.stop()
        for watcher in self.watchers.values():
            watcher.join(timeout=5)
        self.watchers.clear()
