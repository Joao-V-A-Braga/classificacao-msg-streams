import json

import httpx
import pytest
import respx

from src.modules.classification.translator import Translator, clean_translation

URL = "http://llm.test/v1/chat/completions"


def reply(content):
    return httpx.Response(200, json={"choices": [{"message": {"content": content}}]})


@pytest.fixture
def translator():
    return Translator("http://llm.test/v1/", "llama3.2:3b", log=lambda _: None)


@respx.mock
def test_asks_the_model_for_the_translation_of_the_wrapped_message(translator):
    route = respx.post(URL).mock(return_value=reply("How much does the course cost?"))

    assert translator.to_english("Quanto custa o curso?") == "How much does the course cost?"

    body = json.loads(route.calls[0].request.content)
    assert body["model"] == "llama3.2:3b"
    assert body["temperature"] == 0
    assert body["messages"][-1] == {"role": "user", "content": "<message>Quanto custa o curso?</message>"}
    # os exemplos ensinam o modelo a traduzir perguntas em vez de respondê-las
    assert any(message["role"] == "assistant" for message in body["messages"][1:-1])


@respx.mock
def test_caches_translations(translator):
    route = respx.post(URL).mock(return_value=reply("Good night"))

    translator.to_english("boa noite")
    translator.to_english("boa noite")

    assert route.call_count == 1


@respx.mock
def test_does_not_call_the_model_for_emojis_or_numbers_only(translator):
    route = respx.post(URL).mock(return_value=reply("x"))

    assert translator.to_english("😂😂😂") is None
    assert translator.to_english("13 13 13") is None
    assert route.call_count == 0


@respx.mock
def test_a_failing_model_means_no_translation(translator):
    respx.post(URL).mock(side_effect=httpx.ConnectError("refused"))

    assert translator.to_english("boa noite") is None


def test_clean_translation_strips_what_small_models_add():
    assert clean_translation('"Good night"', "boa noite") == "Good night"
    assert clean_translation("Translation: Good night", "boa noite") == "Good night"
    assert clean_translation("<message>Good night</message>", "boa noite") == "Good night"
    assert clean_translation("", "boa noite") is None
    assert clean_translation("x" * 500, "oi") is None
