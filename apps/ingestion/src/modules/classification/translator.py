"""Traduz uma mensagem para o inglês antes de ela ser classificada.

O Laya foi treinado em inglês, então classificar a versão em inglês de uma mensagem em português
acerta mais. A tradução é feita por um modelo de texto compatível com a API da OpenAI (por padrão,
um Ollama local).

A tradução serve só de entrada para o Laya (e é mostrada ao usuário como tal): a mensagem continua
salva com o texto original. Se a tradução falhar por qualquer motivo, o Laya classifica o texto
original. Assim, um modelo de texto lento ou desligado nunca faz uma mensagem se perder.
"""

from __future__ import annotations

import re
import sys
import threading
import time
from collections import OrderedDict

import httpx

SYSTEM_PROMPT = (
    "You are a translator. You receive one social media message (live chat or post comment) between "
    "<message> tags and reply with its English translation only. Never answer, comment on or complete the "
    "message: a question stays a question, a complaint stays a complaint. Keep slang, names, @handles, "
    "hashtags and emojis. No quotes, no notes. If it is already English, repeat it unchanged."
)

# Exemplos de tradução enviados junto com cada pedido. Sem eles, modelos pequenos tendem a *responder*
# a pergunta em vez de traduzi-la. Aconteceu com o llama3.2:3b: "Quanto custa a assinatura?" virou
# "The subscription cost is $9.99 a month."
EXAMPLES = [
    ("Quanto custa o curso?", "How much does the course cost?"),
    ("vitu perdeu né?", "Vitu lost, right?"),
    ("que produto ruim, quebrou em 2 dias", "What a bad product, it broke in 2 days"),
    ("boa noite galera 😍", "Good night everyone 😍"),
]

# Qualquer letra, de qualquer alfabeto (exclui números, emojis e pontuação).
LETTERS = re.compile(r"[^\W\d_]", re.UNICODE)


def _wrap(text: str) -> str:
    return f"<message>{text}</message>"


class Translator:
    def __init__(
        self,
        base_url: str,
        model: str,
        *,
        api_key: str | None = None,
        timeout: float = 20,
        client: httpx.Client | None = None,
        cache_size: int = 2000,
        log=None,
    ) -> None:
        self._url = f"{base_url.rstrip('/')}/chat/completions"
        self._model = model
        self._headers = {"Authorization": f"Bearer {api_key}"} if api_key else {}
        self._client = client or httpx.Client(timeout=timeout)
        # Mensagens repetidas (comuns em chat de live) não são traduzidas de novo.
        self._cache: OrderedDict[str, str | None] = OrderedDict()
        self._cache_size = cache_size
        self._lock = threading.Lock()
        self._log = log or (lambda text: print(text, file=sys.stderr, flush=True))
        self._last_failure_log = 0.0

    def to_english(self, text: str) -> str | None:
        """A versão em inglês de `text`. None quando não há o que traduzir ou a tradução falhou."""
        text = text.strip()
        if not LETTERS.search(text):
            return None  # só emojis ou números: nada a traduzir

        with self._lock:
            if text in self._cache:
                self._cache.move_to_end(text)
                return self._cache[text]

        translation = self._translate(text)

        with self._lock:
            self._cache[text] = translation
            if len(self._cache) > self._cache_size:
                self._cache.popitem(last=False)
        return translation

    def _translate(self, text: str) -> str | None:
        try:
            response = self._client.post(
                self._url,
                headers=self._headers,
                json={
                    "model": self._model,
                    "temperature": 0,
                    "messages": [
                        {"role": "system", "content": SYSTEM_PROMPT},
                        *[
                            message
                            for source, english in EXAMPLES
                            for message in (
                                {"role": "user", "content": _wrap(source)},
                                {"role": "assistant", "content": english},
                            )
                        ],
                        {"role": "user", "content": _wrap(text)},
                    ],
                },
            )
            response.raise_for_status()
            content = response.json()["choices"][0]["message"]["content"]
        except Exception as error:  # noqa: BLE001 - sem tradução, o texto original é classificado
            # No máximo um aviso por minuto, para não encher o log enquanto o modelo estiver fora do ar.
            now = time.monotonic()
            if now - self._last_failure_log > 60:
                self._last_failure_log = now
                self._log(f"translation unavailable, classifying the original text: {error}")
            return None
        return clean_translation(content, text)


def clean_translation(content: str, original: str) -> str | None:
    """Limpa o que modelos pequenos costumam acrescentar em volta da tradução ("Translation:", aspas,
    as tags <message>).

    Devolve None quando a resposta claramente não é uma tradução da mensagem: vazia, ou muito mais
    longa que o original.
    """
    translation = (content or "").strip()
    translation = re.sub(r"</?message>", "", translation).strip()
    translation = re.sub(r"^(here is the translation|translation|english)\s*:\s*", "", translation, flags=re.I)
    translation = translation.strip().strip('"“”\'').strip()
    if not translation or len(translation) > 4 * len(original) + 40:
        return None
    return translation
