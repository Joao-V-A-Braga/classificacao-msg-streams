"""Supervisor da ingestão: liga e desliga o ingestion_service.py sozinho, acompanhando o app.

Como rodar (o start.ps1 já o inicia escondido, sem janela de terminal):
  uv run --env-file .env python ingestion_supervisor.py

- É leve e fica sempre ligado: não carrega o Laya, só consulta o backend a cada poucos segundos.
- Liga o serviço de ingestão (Laya + runner, sem janela) quando um monitoramento é iniciado no app,
  desliga depois de SUPERVISOR_IDLE_STOP_SECONDS sem nenhum rodando e o religa se ele cair.
- Só roda uma instância: ele ocupa 127.0.0.1:SUPERVISOR_PORT (GET /health), e uma segunda cópia
  encerra ao encontrar a porta ocupada.
- Logs em logs/supervisor.log e logs/ingestion.log (a saída do serviço de ingestão).
"""

from __future__ import annotations

import os
import signal
import subprocess
import sys
import threading
from datetime import datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

import httpx

from src.modules.supervisor.supervisor import IngestionSupervisor
from src.shared.backend_client import BackendClient

ROOT = Path(__file__).resolve().parent
LOG_DIR = ROOT / "logs"
WORKER_LOG = LOG_DIR / "ingestion.log"
SUPERVISOR_LOG = LOG_DIR / "supervisor.log"


def log(text: str) -> None:
    line = f"{datetime.now():%Y-%m-%d %H:%M:%S} {text}"
    print(line, file=sys.stderr, flush=True)
    with SUPERVISOR_LOG.open("a", encoding="utf-8") as file:
        file.write(line + "\n")


class WorkerProcess:
    """O ingestion_service.py rodando como processo filho, sem janela, com a saída gravada no log."""

    def __init__(self) -> None:
        self._log_file = WORKER_LOG.open("a", encoding="utf-8")
        self._log_file.write(f"\n===== {datetime.now():%Y-%m-%d %H:%M:%S} starting ingestion_service.py =====\n")
        self._log_file.flush()
        self._process = subprocess.Popen(
            [sys.executable, str(ROOT / "ingestion_service.py")],
            cwd=ROOT,
            env={**os.environ, "PYTHONUNBUFFERED": "1", "PYTHONIOENCODING": "utf-8"},
            stdout=self._log_file,
            stderr=subprocess.STDOUT,
            creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0),
        )

    def is_running(self) -> bool:
        return self._process.poll() is None

    def stop(self) -> None:
        if self.is_running():
            self._process.terminate()
            try:
                self._process.wait(10)
            except subprocess.TimeoutExpired:
                self._process.kill()
        self._log_file.close()

    def log_tail(self) -> str:
        """As últimas 3 linhas do log do serviço, para explicar por que ele caiu."""
        try:
            lines = [line for line in WORKER_LOG.read_text(encoding="utf-8", errors="replace").splitlines() if line.strip()]
        except OSError:
            return ""
        return " | ".join(lines[-3:])


def classifier_port_answers(port: int) -> bool:
    """Se já há um serviço de ingestão respondendo nessa porta (por exemplo, iniciado à mão)."""
    try:
        return httpx.get(f"http://127.0.0.1:{port}/health", timeout=1).status_code == 200
    except httpx.HTTPError:
        return False


def hold_single_instance(port: int) -> ThreadingHTTPServer | None:
    """Ocupa a porta do supervisor com um /health. Devolve None se outro supervisor já a ocupa."""

    class Handler(BaseHTTPRequestHandler):
        def do_GET(self):
            status = 200 if self.path == "/health" else 404
            body = b'{"status": "ok"}' if status == 200 else b'{"error": "not_found"}'
            self.send_response(status)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)

        def log_message(self, format, *args):  # noqa: A002
            pass

    try:
        server = ThreadingHTTPServer(("127.0.0.1", port), Handler)
    except OSError:
        return None
    threading.Thread(target=server.serve_forever, daemon=True).start()
    return server


def main() -> None:
    LOG_DIR.mkdir(exist_ok=True)
    port = int(os.environ.get("SUPERVISOR_PORT", "8101"))
    classifier_port = int(os.environ.get("LAYA_CLASSIFIER_PORT", "8100"))
    api_key = os.environ.get("BACKEND_INGESTION_API_KEY")
    if not api_key:
        log("BACKEND_INGESTION_API_KEY is required (apps/ingestion/.env)")
        sys.exit(2)

    server = hold_single_instance(port)
    if server is None:
        log(f"another supervisor already holds 127.0.0.1:{port}; exiting")
        return

    backend = BackendClient(os.environ.get("BACKEND_BASE_URL", "http://localhost:8000"), api_key)
    supervisor = IngestionSupervisor(
        backend,
        WorkerProcess,
        external_worker_alive=lambda: classifier_port_answers(classifier_port),
        idle_stop_seconds=float(os.environ.get("SUPERVISOR_IDLE_STOP_SECONDS", "120")),
        log=log,
    )
    interval = float(os.environ.get("RUNNER_INTERVAL_SECONDS", "3"))
    stop = threading.Event()
    for name in ("SIGINT", "SIGTERM", "SIGBREAK"):
        if hasattr(signal, name):
            signal.signal(getattr(signal, name), lambda *_: stop.set())

    log(f"supervisor up (health on 127.0.0.1:{port}); waiting for monitors started in the web app")
    try:
        while not stop.is_set():
            supervisor.tick()
            stop.wait(interval)
    finally:
        supervisor.stop()
        server.shutdown()
        backend.close()
        log("supervisor stopped")


if __name__ == "__main__":
    main()
