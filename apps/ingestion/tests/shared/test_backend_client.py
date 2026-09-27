import json
from datetime import datetime, timezone

import httpx
import pytest
import respx

from src.shared.backend_client import BackendClient, IngestionError, MonitorStopped


@pytest.fixture
def backend():
    client = BackendClient("http://backend.test/", "secret-key")
    yield client
    client.close()


@respx.mock
def test_running_monitors(backend):
    route = respx.get("http://backend.test/ingestion/monitors").mock(
        return_value=httpx.Response(200, json={"monitors": [{"id": "m1", "sourceType": "youtube_live"}]})
    )

    assert backend.running_monitors() == [{"id": "m1", "sourceType": "youtube_live"}]
    assert route.calls[0].request.headers["X-Ingestion-Api-Key"] == "secret-key"


@respx.mock
def test_post_message_sends_the_expected_payload(backend):
    route = respx.post("http://backend.test/ingestion/monitors/m1/messages").mock(
        return_value=httpx.Response(201, json={"message": {"id": "abc", "label": "pergunta"}})
    )

    result = backend.post_message(
        "m1",
        external_id="yt-1",
        author_name="viewer1",
        text="Quanto custa?",
        received_at=datetime(2026, 9, 25, 10, 0, tzinfo=timezone.utc),
    )

    assert result == {"id": "abc", "label": "pergunta"}
    assert json.loads(route.calls[0].request.content) == {
        "externalId": "yt-1",
        "authorName": "viewer1",
        "text": "Quanto custa?",
        "receivedAt": "2026-09-25T10:00:00+00:00",
    }


@respx.mock
def test_report_state(backend):
    route = respx.post("http://backend.test/ingestion/monitors/m1/state").mock(return_value=httpx.Response(200, json={}))

    backend.report_state("m1", "watching", "ok")

    assert json.loads(route.calls[0].request.content) == {"state": "watching", "detail": "ok"}


@respx.mock
@pytest.mark.parametrize("status", [404, 409])
def test_paused_or_deleted_monitor_raises_monitor_stopped(backend, status):
    respx.post("http://backend.test/ingestion/monitors/m1/messages").mock(return_value=httpx.Response(status, json={}))

    with pytest.raises(MonitorStopped):
        backend.post_message("m1", external_id=None, author_name="v", text="t")


@respx.mock
def test_other_failures_raise_ingestion_error(backend):
    respx.get("http://backend.test/ingestion/monitors").mock(return_value=httpx.Response(401, json={}))

    with pytest.raises(IngestionError, match="401") as error:
        backend.running_monitors()
    assert not isinstance(error.value, MonitorStopped)


@respx.mock
def test_error_messages_keep_only_the_start_of_the_body(backend):
    page = "<html>\n" + "<p>stack trace line</p>\n" * 2000 + "</html>"
    respx.get("http://backend.test/ingestion/monitors").mock(return_value=httpx.Response(500, text=page))

    with pytest.raises(IngestionError) as error:
        backend.running_monitors()
    assert len(str(error.value)) < 400
    assert "\n" not in str(error.value)
