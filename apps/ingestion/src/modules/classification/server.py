"""Servidor HTTP que expõe o classificador Laya para o backend.

Quem chama é o `LayaMessageClassifierProvider` do backend Symfony.

    POST /classify  {"text": "...", "labels": [{"name": "...", "description": "..."}], "translate": true}
                    -> 200 {"label": "...", "probabilities": {tag: p}, "confidence": c, "translatedText": "..."}
    GET  /health    -> 200 {"status": "ok"}
"""

import json
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

from src.modules.classification.laya import classify


def parse_labels(value) -> list[dict] | None:
    """Valida as tags recebidas.

    Devolve None quando o campo não veio (e as tags padrão serão usadas). Lança ValueError quando ele
    veio, mas não é uma lista com pelo menos 2 tags, todas com nome.
    """
    if value is None:
        return None
    if not isinstance(value, list):
        raise ValueError("labels must be a list")
    labels = []
    for item in value:
        if not isinstance(item, dict) or not isinstance(item.get("name"), str) or not item["name"].strip():
            raise ValueError("every label needs a name")
        description = item.get("description")
        labels.append({"name": item["name"].strip(), "description": description if isinstance(description, str) else None})
    if len(labels) < 2:
        raise ValueError("at least 2 labels are needed")
    return labels


def make_server(model, host: str, port: int, translator=None) -> ThreadingHTTPServer:
    # O servidor atende cada requisição numa thread, para o /health responder mesmo durante uma
    # classificação. O Laya em si classifica uma mensagem por vez (ver laya.LockedModel).
    class Handler(BaseHTTPRequestHandler):
        def do_GET(self):
            if self.path == "/health":
                self._reply(200, {"status": "ok"})
            else:
                self._reply(404, {"error": "not_found"})

        def do_POST(self):
            if self.path != "/classify":
                self._reply(404, {"error": "not_found"})
                return
            try:
                body = json.loads(self.rfile.read(int(self.headers.get("Content-Length", 0))) or b"{}")
            except json.JSONDecodeError:
                self._reply(400, {"error": "invalid_json"})
                return
            text = body.get("text") if isinstance(body, dict) else None
            if not isinstance(text, str) or not text.strip():
                self._reply(422, {"error": "text_required"})
                return
            try:
                labels = parse_labels(body.get("labels"))
            except ValueError as error:
                self._reply(422, {"error": "invalid_labels", "detail": str(error)})
                return
            # O monitoramento pode ter desligado a tradução ("translate": false).
            use_translator = translator if body.get("translate", True) is not False else None
            self._reply(200, classify(model, text, labels, use_translator))

        def log_message(self, format, *args):  # noqa: A002 - o console fica livre para os logs do runner
            pass

        def _reply(self, status: int, payload: dict) -> None:
            data = json.dumps(payload, ensure_ascii=False).encode()
            self.send_response(status)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(data)))
            self.end_headers()
            self.wfile.write(data)

    server = ThreadingHTTPServer((host, port), Handler)
    server.daemon_threads = True
    return server
