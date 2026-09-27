import pytest

from src.modules.sources.youtube import EXTRACT_CHAT_MESSAGES_JS, live_chat_url, read_chat_messages, video_id


class FakeBrowser:
    def __init__(self, result):
        self.result = result
        self.last_expression = None

    def evaluate(self, expression):
        self.last_expression = expression
        return self.result


def test_read_chat_messages_returns_the_browser_evaluate_result():
    browser = FakeBrowser([{"id": "a", "author": "x", "text": "hi"}])

    assert read_chat_messages(browser) == [{"id": "a", "author": "x", "text": "hi"}]
    assert browser.last_expression == EXTRACT_CHAT_MESSAGES_JS


def test_read_chat_messages_distinguishes_no_chat_from_an_empty_chat():
    assert read_chat_messages(FakeBrowser(None)) is None
    assert read_chat_messages(FakeBrowser([])) == []


@pytest.mark.parametrize(
    ("url", "expected"),
    [
        ("https://www.youtube.com/watch?v=jfKfPfyJRdk", "jfKfPfyJRdk"),
        ("https://youtube.com/watch?feature=share&v=jfKfPfyJRdk&t=3", "jfKfPfyJRdk"),
        ("https://m.youtube.com/watch?v=jfKfPfyJRdk", "jfKfPfyJRdk"),
        ("https://www.youtube.com/live/jfKfPfyJRdk?si=abc", "jfKfPfyJRdk"),
        ("https://youtu.be/jfKfPfyJRdk", "jfKfPfyJRdk"),
        ("https://www.youtube.com/shorts/jfKfPfyJRdk", "jfKfPfyJRdk"),
        ("https://www.youtube.com/@lofigirl/live", None),
        ("https://www.youtube.com/watch?v=short", None),
        ("https://example.com/watch?v=jfKfPfyJRdk", None),
    ],
)
def test_video_id(url, expected):
    assert video_id(url) == expected


def test_live_chat_url_is_the_popout():
    assert live_chat_url("jfKfPfyJRdk") == "https://www.youtube.com/live_chat?is_popout=1&v=jfKfPfyJRdk"


