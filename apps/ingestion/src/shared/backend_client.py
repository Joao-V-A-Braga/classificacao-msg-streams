"""Cliente HTTP da API de ingestão do backend (rotas /ingestion/*).

É uma comunicação entre máquinas: não há usuário logado, e cada requisição se autentica com a chave
fixa do header `X-Ingestion-Api-Key`.
"""

from __future__ import annotations

from datetime import datetime, timezone

import httpx


class IngestionError(RuntimeError):
    """O backend recusou a requisição ou falhou ao processá-la."""


class MonitorStopped(IngestionError):
    """O monitoramento foi pausado ou excluído (o backend respondeu 404 ou 409): pare de vigiá-lo."""


class BackendClient:
    def __init__(self, base_url: str, api_key: str, client: httpx.Client | None = None) -> None:
        self._base_url = base_url.rstrip("/")
        self._client = client or httpx.Client(timeout=30)
        self._headers = {"X-Ingestion-Api-Key": api_key}

    def running_monitors(self) -> list[dict]:
        """Monitoramentos que devem estar sendo vigiados agora, cada um no formato
        {id, sourceType, url, startedAt}."""
        return self._request("GET", "/ingestion/monitors")["monitors"]

    def report_state(self, monitor_id: str, state: str, detail: str | None = None) -> None:
        """Informa em que pé o monitoramento está (abrindo, monitorando, erro) para o painel mostrar."""
        self._request("POST", f"/ingestion/monitors/{monitor_id}/state", json={"state": state, "detail": detail})

    def finish(self, monitor_id: str, reason: str) -> None:
        """Pausa um monitoramento cuja live ou post não existe mais. O motivo aparece para o usuário."""
        self._request("POST", f"/ingestion/monitors/{monitor_id}/finish", json={"reason": reason})

    def post_message(
        self,
        monitor_id: str,
        *,
        external_id: str | None,
        author_name: str,
        text: str,
        received_at: datetime | None = None,
    ) -> dict:
        """Envia uma mensagem. O backend a salva, classifica e devolve já com a tag escolhida."""
        received_at = received_at or datetime.now(timezone.utc)
        return self._request(
            "POST",
            f"/ingestion/monitors/{monitor_id}/messages",
            json={
                "externalId": external_id,
                "authorName": author_name,
                "text": text,
                "receivedAt": received_at.isoformat(),
            },
        )["message"]

    def close(self) -> None:
        self._client.close()

    def _request(self, method: str, path: str, **kwargs) -> dict:
        response = self._client.request(method, f"{self._base_url}{path}", headers=self._headers, **kwargs)
        # Uma página de erro do Symfony tem milhares de linhas de HTML: guardamos só o começo.
        body = " ".join(response.text.split())[:300]
        if response.status_code in (404, 409) and path.startswith("/ingestion/monitors/"):
            raise MonitorStopped(f"{method} {path} answered {response.status_code}: {body}")
        if response.is_error:
            raise IngestionError(f"{method} {path} failed with HTTP {response.status_code}: {body}")
        return response.json()
