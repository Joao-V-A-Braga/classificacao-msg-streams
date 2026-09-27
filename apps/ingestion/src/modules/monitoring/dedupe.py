"""Lembra quais mensagens ou comentários (pelo id) um watcher já viu, para não enviar nada duas vezes."""

from __future__ import annotations


class SeenIds:
    def __init__(self) -> None:
        self._seen: set[str] = set()

    def new_messages(self, messages: list[dict]) -> list[dict]:
        """Devolve só as mensagens (cada uma com a chave "id") que ainda não tinham sido vistas, e as
        marca como vistas."""
        fresh = [message for message in messages if message["id"] not in self._seen]
        self._seen.update(message["id"] for message in fresh)
        return fresh

    def mark_seen(self, messages: list[dict]) -> None:
        """Marca as mensagens como vistas sem devolvê-las.

        Usado na linha de base: o que já está na página quando o monitoramento começa nunca é enviado.
        """
        self._seen.update(message["id"] for message in messages)
