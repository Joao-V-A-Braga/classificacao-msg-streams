"""Guarda num arquivo a lista de abas do Chrome que o serviço de ingestão abriu.

Se o serviço for encerrado à força (travou, o supervisor o reiniciou, alguém rodou o stop.ps1), ele
não tem chance de fechar as próprias abas. A próxima execução lê este arquivo e fecha o que sobrou
antes de abrir as suas. Só as abas anotadas aqui são fechadas: uma aba aberta pelo usuário nunca é
tocada.
"""

from __future__ import annotations

import json
import threading
from pathlib import Path
from typing import Callable


class TabRegistry:
    def __init__(self, path: Path) -> None:
        self._path = path
        self._lock = threading.Lock()

    def add(self, target_id: str) -> None:
        with self._lock:
            ids = self._read()
            if target_id not in ids:
                self._write([*ids, target_id])

    def remove(self, target_id: str) -> None:
        with self._lock:
            self._write([tab for tab in self._read() if tab != target_id])

    def close_leftovers(self, close: Callable[[str], None]) -> int:
        """Fecha todas as abas anotadas (ignorando as que já não existem), esvazia a lista e devolve
        quantas eram."""
        with self._lock:
            ids = self._read()
            for target_id in ids:
                try:
                    close(target_id)
                except Exception:  # noqa: BLE001 - a aba (ou o próprio Chrome) pode já ter sido fechada
                    pass
            self._write([])
            return len(ids)

    def _read(self) -> list[str]:
        try:
            data = json.loads(self._path.read_text(encoding="utf-8"))
        except (OSError, ValueError):
            return []
        return [item for item in data if isinstance(item, str)] if isinstance(data, list) else []

    def _write(self, ids: list[str]) -> None:
        self._path.parent.mkdir(parents=True, exist_ok=True)
        self._path.write_text(json.dumps(ids), encoding="utf-8")
