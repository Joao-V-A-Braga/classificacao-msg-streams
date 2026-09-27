import threading

import httpx
import pytest

from src.modules.classification.laya import DEFAULT_LABELS, LockedModel, classify
from src.modules.classification.server import make_server, parse_labels

LABELS = [{"name": "dúvida", "description": "a question"}, {"name": "elogio", "description": None}, {"name": "spam"}]
PROBABILITIES = {"dúvida": 0.7, "elogio": 0.2, "spam": 0.1}


class FakeLaya:
    def __init__(self, choice="dúvida", probabilities=None, fail_with_descriptions=False):
        self.calls = []
        self.choice = choice
        self.probabilities = probabilities or PROBABILITIES
        self.fail_with_descriptions = fail_with_descriptions

    def system_one(self, state, questions):
        self.calls.append((state, questions))
        criteria = questions["label"]["criteria"]
        if self.fail_with_descriptions and any(criteria.values()):
            raise ValueError("question 'label' options exceed head_max_len=192")
        return {
            "answers": {"label": {"type": "choice", "choice": self.choice, "probabilities": self.probabilities,
                                  "confidence": 0.6, "action": {"act_probability": 1.0}}},
            "usage": {"input_tokens": 10},
        }


def test_classify_asks_laya_to_choose_between_the_given_tags():
    model = FakeLaya()

    result = classify(model, "Quanto custa?", LABELS)

    assert result == {"label": "dúvida", "probabilities": PROBABILITIES, "confidence": 0.6, "translatedText": None}
    state, questions = model.calls[0]
    assert "Quanto custa?" in state
    assert questions["label"]["type"] == "choice"
    assert questions["label"]["criteria"] == {"dúvida": "a question (dúvida)", "elogio": "elogio", "spam": "spam"}


def test_classify_fills_missing_probabilities_with_zero():
    result = classify(FakeLaya(probabilities={"dúvida": 1.0}), "oi", LABELS)

    assert result["probabilities"] == {"dúvida": 1.0, "elogio": 0.0, "spam": 0.0}


def test_classify_retries_with_names_only_when_descriptions_do_not_fit():
    model = FakeLaya(fail_with_descriptions=True)

    result = classify(model, "oi", LABELS)

    assert result["label"] == "dúvida"
    assert model.calls[-1][1]["label"]["criteria"] == {"dúvida": None, "elogio": None, "spam": None}


def test_classify_uses_the_default_tags_when_none_are_given():
    model = FakeLaya(choice="pergunta", probabilities={"pergunta": 1.0})

    classify(model, "oi")

    assert list(model.calls[0][1]["label"]["criteria"]) == [label["name"] for label in DEFAULT_LABELS]


def test_locked_model_delegates_to_the_model():
    model = FakeLaya()

    LockedModel(model).system_one("s", {"label": {"criteria": {}}})

    assert model.calls == [("s", {"label": {"criteria": {}}})]


def test_parse_labels_validates_the_payload():
    assert parse_labels(None) is None
    assert parse_labels([{"name": " a "}, {"name": "b", "description": 3}]) == [
        {"name": "a", "description": None},
        {"name": "b", "description": None},
    ]
    for invalid in ("x", [{"name": "a"}], [{"name": ""}, {"name": "b"}], [{"description": "x"}, {"name": "b"}]):
        with pytest.raises(ValueError):
            parse_labels(invalid)


@pytest.fixture
def server_url():
    server = make_server(FakeLaya(), "127.0.0.1", 0)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    yield f"http://127.0.0.1:{server.server_address[1]}"
    server.shutdown()
    server.server_close()


def test_post_classify_returns_the_classification(server_url):
    response = httpx.post(f"{server_url}/classify", json={"text": "Quanto custa?", "labels": LABELS})

    assert response.status_code == 200
    assert response.json() == {"label": "dúvida", "probabilities": PROBABILITIES, "confidence": 0.6, "translatedText": None}


def test_post_classify_with_invalid_labels_returns_422(server_url):
    response = httpx.post(f"{server_url}/classify", json={"text": "oi", "labels": [{"name": "só uma"}]})

    assert response.status_code == 422
    assert response.json()["error"] == "invalid_labels"


def test_post_classify_without_text_returns_422(server_url):
    assert httpx.post(f"{server_url}/classify", json={"text": "  "}).status_code == 422


def test_post_classify_with_invalid_json_returns_400(server_url):
    response = httpx.post(f"{server_url}/classify", content=b"{nope", headers={"Content-Type": "application/json"})

    assert response.status_code == 400


def test_health_and_unknown_paths(server_url):
    assert httpx.get(f"{server_url}/health").json() == {"status": "ok"}
    assert httpx.get(f"{server_url}/nope").status_code == 404
    assert httpx.post(f"{server_url}/nope", json={}).json() == {"error": "not_found"}


class FakeTranslator:
    def __init__(self, translation):
        self.translation = translation
        self.calls = []

    def to_english(self, text):
        self.calls.append(text)
        return self.translation


def test_laya_reads_the_english_version_when_there_is_a_translator():
    model = FakeLaya()

    result = classify(model, "Quanto custa?", LABELS, FakeTranslator("How much is it?"))

    assert "How much is it?" in model.calls[0][0]
    assert "Quanto custa?" not in model.calls[0][0]
    assert result["translatedText"] == "How much is it?"


def test_the_original_text_is_classified_when_translation_fails_or_is_the_same():
    for translation in (None, "quanto custa?"):
        model = FakeLaya()
        result = classify(model, "Quanto custa?", LABELS, FakeTranslator(translation))
        assert "Quanto custa?" in model.calls[0][0]
        assert result["translatedText"] is None


def test_post_classify_skips_the_translation_when_the_monitor_turned_it_off():
    translator = FakeTranslator("How much is it?")
    server = make_server(FakeLaya(), "127.0.0.1", 0, translator)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    url = f"http://127.0.0.1:{server.server_address[1]}/classify"
    try:
        off = httpx.post(url, json={"text": "Quanto custa?", "labels": LABELS, "translate": False}).json()
        on = httpx.post(url, json={"text": "Quanto custa?", "labels": LABELS}).json()
    finally:
        server.shutdown()
        server.server_close()

    assert off["translatedText"] is None
    assert on["translatedText"] == "How much is it?"
    assert translator.calls == ["Quanto custa?"]
