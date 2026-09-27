"""Classifica uma mensagem em uma das tags do monitoramento usando o Laya.

Devolve a tag escolhida e a probabilidade de cada uma. O Laya roda localmente, na CPU.
"""

from __future__ import annotations

import os
import threading

# Tags usadas quando quem chama não manda nenhuma (o app também as sugere para monitoramentos novos).
# O Laya foi treinado em inglês, então as descrições em inglês funcionam melhor, mesmo com as
# mensagens em português.
DEFAULT_LABELS = [
    {"name": "pergunta", "description": "a question asking for information"},
    {"name": "elogio", "description": "praise or a compliment"},
    {"name": "reclamacao", "description": "a complaint or criticism"},
    {"name": "spam", "description": "spam, advertising, links or repeated promotional text"},
    {"name": "outro", "description": "anything else: greetings, casual chat, reactions"},
]

INSTRUCTIONS = "Classify this live chat message or social media comment."

# Pesos abertos no Hugging Face Hub, carregados na CPU pelo pacote `laya` (PyPI).
DEFAULT_MODEL = "convaiinnovations/laya"

_MODEL = None


def load_laya():
    """Carrega o Laya uma única vez por processo.

    Já faz uma primeira classificação de aquecimento: a primeira execução na CPU é lenta, e assim quem
    paga esse custo é a subida do serviço, não a primeira mensagem.
    """
    global _MODEL
    if _MODEL is None:
        from laya import load

        _MODEL = load(os.environ.get("LAYA_MODEL", DEFAULT_MODEL), device="cpu")
        _MODEL.system_one("Warm up.", {"q": {"type": "choice", "instructions": "Warm up.", "criteria": ["a", "b"]}})
    return _MODEL


class LockedModel:
    """Garante que o Laya classifique uma mensagem por vez.

    Na CPU ele não aguenta rodar em paralelo, e o servidor de classificação atende várias requisições
    ao mesmo tempo.
    """

    def __init__(self, model):
        self._model = model
        self._lock = threading.Lock()

    def system_one(self, state, questions):
        with self._lock:
            return self._model.system_one(state, questions)


def criteria_for(labels: list[dict], with_descriptions: bool = True) -> dict[str, str | None]:
    """Monta as opções da pergunta ao Laya: o nome de cada tag, com a descrição quando houver."""
    criteria: dict[str, str | None] = {}
    for label in labels:
        name = str(label["name"]).strip()
        description = (label.get("description") or "").strip()
        criteria[name] = (f"{description} ({name})" if description else name) if with_descriptions else None
    return criteria


def classify(model, text: str, labels: list[dict] | None = None, translator=None) -> dict:
    """Classifica `text` em uma das `labels` (ou nas tags padrão).

    `model` é o Laya já carregado. Com um `translator`, o Laya lê a versão em inglês do texto, o que
    costuma acertar mais.

    Devolve:
    - `label`: a tag escolhida;
    - `probabilities`: a probabilidade de cada tag;
    - `confidence`: a confiança do Laya na escolha;
    - `translatedText`: o texto em inglês que o Laya leu, ou None se ele leu o original.
    """
    labels = labels or DEFAULT_LABELS
    translated = translator.to_english(text) if translator else None
    if translated and translated.strip().lower() == text.strip().lower():
        translated = None  # o texto já estava em inglês
    laya_text = translated or text

    try:
        answer = _ask(model, laya_text, criteria_for(labels))
    except ValueError:
        # Descrições demais (ou longas demais) não cabem nas opções do Laya: só os nomes das tags cabem.
        answer = _ask(model, laya_text, criteria_for(labels, with_descriptions=False))

    names = [str(label["name"]).strip() for label in labels]
    return {
        "label": answer["choice"],
        "probabilities": {name: answer["probabilities"].get(name, 0.0) for name in names},
        "confidence": answer["confidence"],
        "translatedText": translated,
    }


def _ask(model, text: str, criteria: dict) -> dict:
    question = {"type": "choice", "instructions": INSTRUCTIONS, "criteria": criteria}
    return model.system_one(f"Message: {text}", {"label": question})["answers"]["label"]
