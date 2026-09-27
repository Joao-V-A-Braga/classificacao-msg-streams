import pytest

from src.modules.sources.instagram import (
    EXTRACT_COMMENTS_JS,
    SCROLL_COMMENTS_JS,
    InstagramPostPage,
    post_url,
    read_comments,
    shortcode,
)


class FakeBrowser:

    def __init__(self, comments, pathnames=None):
        self.comments = comments
        self.calls = []
        self.pathnames = list(pathnames or [])

    def evaluate(self, expression):
        if expression == "document.readyState":
            return "complete"
        if expression == "location.pathname":
            return self.pathnames.pop(0) if self.pathnames else "/p/abc/"
        self.calls.append("scroll" if expression == SCROLL_COMMENTS_JS else "extract")
        return True if expression == SCROLL_COMMENTS_JS else self.comments

    def call(self, method, **params):
        self.calls.append(f"{method} {params.get('url', '')}".strip())

    def close(self):
        pass


@pytest.mark.parametrize(
    "url",
    [
        "https://www.instagram.com/reel/DdxRZ7_TyJ3/?utm_source=ig_web_copy_link&stkn=MzRlODBiNWFlZA==",
        "https://www.instagram.com/reels/DdxRZ7_TyJ3/",
        "https://www.instagram.com/p/DdxRZ7_TyJ3/",
        "https://www.instagram.com/jornalnacional/p/DdxRZ7_TyJ3/",
        "https://instagram.com/tv/DdxRZ7_TyJ3",
    ],
)
def test_every_post_and_reel_link_becomes_the_post_page(url):
    assert shortcode(url) == "DdxRZ7_TyJ3"
    assert post_url(url) == "https://www.instagram.com/p/DdxRZ7_TyJ3/"


def test_a_profile_link_is_kept_as_is():
    assert shortcode("https://www.instagram.com/jornalnacional/") is None
    assert post_url("https://www.instagram.com/jornalnacional/") == "https://www.instagram.com/jornalnacional/"


def test_read_comments_keeps_id_author_text_and_date():
    browser = FakeBrowser([
        {"id": "ig-1", "author": "ana", "text": "amei", "at": "2026-09-27T00:05:38.000Z"},
        {"id": "ig-2", "author": "bia", "text": "", "at": None},
        "garbage",
    ])

    assert read_comments(browser) == [{"id": "ig-1", "author": "ana", "text": "amei", "at": "2026-09-27T00:05:38.000Z"}]
    assert read_comments(FakeBrowser(None)) is None
    assert "/c/" in EXTRACT_COMMENTS_JS




def test_page_opens_the_post_page_and_reloads_and_scrolls_after_the_baseline(monkeypatch):
    monkeypatch.setattr("src.modules.sources.instagram.time.sleep", lambda _: None)
    opened = []
    browser = FakeBrowser([])
    page = InstagramPostPage("https://www.instagram.com/reel/abc/?x=1", opener=lambda url: opened.append(url) or browser)

    page.read()
    page.read()
    assert browser.calls == ["extract", "extract"]

    page.read()
    assert opened == ["https://www.instagram.com/p/abc/"]
    assert browser.calls[2:] == [
        "Page.navigate https://www.instagram.com/p/abc/",
        "scroll",
        "scroll",
        "scroll",
        "extract",
    ]


def test_page_goes_back_to_the_post_when_instagram_redirects_to_the_reels_viewer(monkeypatch):
    monkeypatch.setattr("src.modules.sources.instagram.time.sleep", lambda _: None)
    browser = FakeBrowser([], pathnames=["/reels/abc/", "/p/abc/"])
    page = InstagramPostPage("https://www.instagram.com/reel/abc/", opener=lambda url: browser)
    page.read()
    page.read()

    page.read()

    assert browser.calls[2:4] == ["Page.navigate https://www.instagram.com/p/abc/"] * 2




class GrowingBrowser(FakeBrowser):
    """Cada rolagem carrega mais 2 comentários, até chegar a `total`."""

    def __init__(self, total):
        super().__init__([])
        self.total = total
        self.loaded = 2

    def evaluate(self, expression):
        if expression == SCROLL_COMMENTS_JS:
            self.loaded = min(self.total, self.loaded + 2)
            self.calls.append("scroll")
            return True
        self.comments = [{"id": f"ig-{i}", "author": "a", "text": f"c{i}", "at": None} for i in range(self.loaded)]
        return super().evaluate(expression)


def test_load_all_scrolls_until_the_list_stops_growing(monkeypatch):
    monkeypatch.setattr("src.modules.sources.instagram.time.sleep", lambda _: None)
    browser = GrowingBrowser(total=9)
    page = InstagramPostPage("https://www.instagram.com/p/abc/", opener=lambda url: browser)
    progress = []

    comments = page.load_all(progress.append)

    assert len(comments) == 9
    assert progress[-1] == 9
    assert browser.calls.count("scroll") == 7  # 4 rolagens que aumentaram a lista + 3 sem aumento


def test_load_all_waits_for_the_comments_to_render(monkeypatch):
    monkeypatch.setattr("src.modules.sources.instagram.time.sleep", lambda _: None)
    browser = GrowingBrowser(total=4)
    renders = iter([[], [], None])
    original = browser.evaluate

    def evaluate(expression):
        if expression not in (SCROLL_COMMENTS_JS, "document.readyState", "location.pathname"):
            pending = next(renders, "rendered")
            if pending != "rendered":
                return pending
        return original(expression)

    browser.evaluate = evaluate
    page = InstagramPostPage("https://www.instagram.com/p/abc/", opener=lambda url: browser)

    assert len(page.load_all()) == 4
