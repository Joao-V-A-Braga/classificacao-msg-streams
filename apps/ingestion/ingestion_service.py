"""Serviço de ingestão: o classificador Laya usado pelo backend e o runner dos monitoramentos
iniciados no app.

Como rodar:
  uv run --env-file .env python ingestion_service.py

O que ele sobe:
- POST /classify em LAYA_CLASSIFIER_HOST:LAYA_CLASSIFIER_PORT (padrão 127.0.0.1:8100). O backend
  (no Docker, via host.docker.internal) classifica por ali cada mensagem recebida, com as tags do
  monitoramento dela.
- O runner, que consulta GET /ingestion/monitors no backend e mantém uma aba do Chrome do usuário
  para cada monitoramento em andamento, enviando só as mensagens que aparecem depois do início.

O Laya roda na CPU e é carregado uma única vez, antes de qualquer coisa começar a atender.
"""

from __future__ import annotations

import argparse
import os
import signal
import sys
import threading

from src.modules.classification.laya import LockedModel, load_laya
from src.modules.classification.server import make_server
from src.modules.classification.translator import Translator
from src.modules.monitoring.runner import MonitorRunner
from src.shared.backend_client import BackendClient


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--host", default=os.environ.get("LAYA_CLASSIFIER_HOST", "127.0.0.1"))
    parser.add_argument("--port", type=int, default=int(os.environ.get("LAYA_CLASSIFIER_PORT", "8100")))
    parser.add_argument("--backend-base-url", default=os.environ.get("BACKEND_BASE_URL", "http://localhost:8000"))
    parser.add_argument("--api-key", default=os.environ.get("BACKEND_INGESTION_API_KEY"))
    parser.add_argument("--runner-interval", type=float, default=float(os.environ.get("RUNNER_INTERVAL_SECONDS", "3")))
    args = parser.parse_args()
    if not args.api_key:
        parser.error("--api-key (or BACKEND_INGESTION_API_KEY) is required")
    return args


def main() -> None:
    args = parse_args()

    print("Loading Laya (CPU)...", file=sys.stderr, flush=True)
    model = LockedModel(load_laya())

    # A tradução para o inglês é opcional: sem um modelo de texto configurado, o Laya lê o original.
    translator = None
    if os.environ.get("TRANSLATE_FOR_LAYA", "true").lower() != "false" and os.environ.get("TEXT_MODEL_BASE_URL"):
        translator = Translator(
            os.environ["TEXT_MODEL_BASE_URL"],
            os.environ.get("TEXT_MODEL", "llama3.2:3b"),
            api_key=os.environ.get("TEXT_MODEL_API_KEY") or None,
            timeout=float(os.environ.get("TEXT_MODEL_TIMEOUT_SECONDS", "20")),
        )
        print(f"Messages are translated to English for Laya with {os.environ.get('TEXT_MODEL')}", file=sys.stderr, flush=True)

    server = make_server(model, args.host, args.port, translator)
    threading.Thread(target=server.serve_forever, daemon=True, name="classifier").start()
    print(f"Laya classifier listening on http://{args.host}:{args.port}", file=sys.stderr, flush=True)

    backend = BackendClient(args.backend_base_url, args.api_key)
    runner = MonitorRunner(backend, interval=args.runner_interval)
    stop = threading.Event()
    signal.signal(signal.SIGINT, lambda *_: stop.set())
    signal.signal(signal.SIGTERM, lambda *_: stop.set())

    print(
        f"Runner watching {args.backend_base_url} for monitors started in the web app. Ctrl+C to stop.",
        file=sys.stderr,
        flush=True,
    )
    try:
        runner.run_forever(stop)
    finally:
        server.shutdown()
        server.server_close()
        backend.close()


if __name__ == "__main__":
    main()
