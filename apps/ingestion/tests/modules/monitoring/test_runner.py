"""Watcher e runner com uma página e um backend falsos (sem Chrome e sem HTTP)."""

import threading
import time

from src.modules.monitoring.runner import MonitorRunner
from src.modules.monitoring.watcher import MonitorWatcher, describe_error
from src.modules.sources.errors import WatchError
from src.shared.backend_client import MonitorStopped

MONITOR = {"id": "m1-aaaaaaaa", "sourceType": "youtube_live", "url": "https://youtu.be/x", "startedAt": "t1"}


def wait_for(condition, timeout=3.0):
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        if condition():
            return True
        time.sleep(0.01)
    return False


class FakeBackend:
    def __init__(self, running=None):
        self.running = running or []
        self.states = []
        self.posted = []
        self.stopped_ids = set()
        self.finished = []
        self.lock = threading.Lock()

    def running_monitors(self):
        return list(self.running)

    def report_state(self, monitor_id, state, detail=None):
        if monitor_id in self.stopped_ids:
            raise MonitorStopped("409")
        with self.lock:
            self.states.append(state)

    def finish(self, monitor_id, reason):
        self.finished.append((monitor_id, reason))

    def post_message(self, monitor_id, *, external_id, author_name, text, received_at=None):
        if monitor_id in self.stopped_ids:
            raise MonitorStopped("409")
        with self.lock:
            self.posted.append(external_id)
        return {"label": "pergunta"}


class FakePage:
    poll_interval = 0.01
    watching_detail = "watching"

    def __init__(self, reads):
        self.reads = list(reads)
        self.closed = False

    def prepare(self):
        pass

    def read(self):
        return self.reads.pop(0) if len(self.reads) > 1 else self.reads[0]

    def close(self, discard=False):
        self.closed = True


def messages(*ids):
    return [{"id": i, "author": "a", "text": f"text {i}"} for i in ids]


def make_watcher(backend, page_opener, **kwargs):
    options = {"ready_timeout": 0.3, "retry_delay": 0.05, "baseline_settle": 0.01, "log": lambda _: None}
    return MonitorWatcher(MONITOR, backend, page_opener=page_opener, **{**options, **kwargs})


def test_only_messages_that_show_up_after_the_start_are_posted():
    backend = FakeBackend()
    page = FakePage([None, messages("old1", "old2"), messages("old1", "old2"), messages("old2", "new1", "new2")])
    watcher = make_watcher(backend, lambda _: page)

    watcher.start()
    assert wait_for(lambda: len(backend.posted) == 2)
    watcher.stop()
    watcher.join(2)

    assert backend.posted == ["new1", "new2"]
    assert backend.states[:2] == ["starting", "watching"]
    assert page.closed


def test_an_error_is_reported_and_the_page_is_opened_again():
    backend = FakeBackend()
    attempts = []

    def opener(_monitor):
        attempts.append(1)
        if len(attempts) == 1:
            raise ConnectionRefusedError("daemon not running")
        return FakePage([messages("a"), messages("a"), messages("a", "b")])

    watcher = make_watcher(backend, opener)
    watcher.start()
    assert wait_for(lambda: backend.posted == ["b"])
    watcher.stop()
    watcher.join(2)

    assert "error" in backend.states
    assert len(attempts) == 2


def test_a_page_without_chat_becomes_an_error():
    backend = FakeBackend()
    watcher = make_watcher(backend, lambda _: FakePage([None]), retry_delay=5)

    watcher.start()
    assert wait_for(lambda: "error" in backend.states)
    watcher.stop()
    watcher.join(2)


def test_the_watcher_stops_when_the_backend_says_the_monitor_is_paused():
    backend = FakeBackend()
    backend.stopped_ids.add(MONITOR["id"])
    watcher = make_watcher(backend, lambda _: FakePage([messages("a")]))

    watcher.start()
    assert wait_for(lambda: not watcher.is_alive())
    assert watcher.stopped


def test_describe_error_explains_chrome_problems():
    assert "chrome://inspect" in describe_error(ConnectionRefusedError("refused"))
    assert describe_error(WatchError("sem chat")) == "sem chat"


class FakeWatcher:
    def __init__(self, monitor):
        self.monitor = monitor
        self.started = False
        self.stopped = False
        self.alive = True

    def start(self):
        self.started = True

    def stop(self):
        self.stopped = True

    def is_alive(self):
        return self.alive

    def join(self, timeout=None):
        pass


def test_runner_follows_the_running_monitors():
    backend = FakeBackend(running=[MONITOR])
    created = []
    runner = MonitorRunner(backend, make_watcher=lambda m: created.append(FakeWatcher(m)) or created[-1], log=lambda _: None)

    runner.reconcile()
    runner.reconcile()
    assert len(created) == 1 and created[0].started

    # Pausado e iniciado de novo: o startedAt muda, então entra um watcher novo (com linha de base nova).
    backend.running = [{**MONITOR, "startedAt": "t2"}]
    runner.reconcile()
    assert created[0].stopped
    assert len(created) == 2

    backend.running = []
    runner.reconcile()
    assert created[1].stopped
    assert runner.watchers == {}


def test_runner_keeps_watchers_while_the_backend_is_unreachable():
    backend = FakeBackend(running=[MONITOR])
    runner = MonitorRunner(backend, make_watcher=FakeWatcher, log=lambda _: None)
    runner.reconcile()

    def down():
        raise ConnectionError("down")

    backend.running_monitors = down
    runner.reconcile()

    assert len(runner.watchers) == 1


def test_runner_replaces_a_watcher_that_died():
    backend = FakeBackend(running=[MONITOR])
    created = []
    runner = MonitorRunner(backend, make_watcher=lambda m: created.append(FakeWatcher(m)) or created[-1], log=lambda _: None)
    runner.reconcile()
    created[0].alive = False

    runner.reconcile()

    assert len(created) == 2


class FakeCommentsPage(FakePage):
    can_load_all = True

    def __init__(self, reads, existing):
        super().__init__(reads)
        self.existing = existing

    def load_all(self, on_progress=None):
        if on_progress:
            on_progress(len(self.existing))
        return self.existing


def dated(identifier, at):
    return {"id": identifier, "author": "a", "text": f"text {identifier}", "at": at}


OLD = "2020-01-01T00:00:00Z"
NEW = "2099-01-01T00:00:00Z"


def test_comments_written_before_the_start_are_skipped_even_if_they_show_up_later():
    backend = FakeBackend()
    later = [dated("c1", OLD), dated("old-loaded-later", OLD), dated("new", NEW)]
    page = FakeCommentsPage([[dated("c1", OLD)], [dated("c1", OLD)], later], existing=[])
    watcher = make_watcher(backend, lambda _: page)

    watcher.start()
    assert wait_for(lambda: backend.posted == ["new"])
    watcher.stop()
    watcher.join(2)


def test_importing_existing_comments_sends_everything_the_post_had():
    backend = FakeBackend()
    existing = [dated("c1", OLD), dated("c2", OLD), dated("c3", OLD)]
    page = FakeCommentsPage([existing[:1], existing + [dated("c4", OLD), dated("new", NEW)]], existing=existing)
    monitor = {**MONITOR, "sourceType": "instagram_post", "includeExistingComments": True}
    watcher = MonitorWatcher(monitor, backend, page_opener=lambda _: page, ready_timeout=0.3, retry_delay=0.05,
                             baseline_settle=0.01, log=lambda _: None)

    watcher.start()
    assert wait_for(lambda: len(backend.posted) == 5)
    watcher.stop()
    watcher.join(2)

    assert backend.posted[:3] == ["c1", "c2", "c3"]
    assert sorted(backend.posted[3:]) == ["c4", "new"]


def test_a_failing_monitor_never_touches_another_monitors_tab():
    backend = FakeBackend()

    class BrokenPage(FakePage):
        def read(self):
            raise RuntimeError("Session with given id not found.")

    healthy_page = FakePage([messages("a"), messages("a"), messages("a", "b")])
    broken_pages = []

    def open_broken(_monitor):
        broken_pages.append(BrokenPage([None]))
        return broken_pages[-1]

    healthy = make_watcher(backend, lambda _: healthy_page)
    broken = MonitorWatcher({**MONITOR, "id": "m2-bbbbbbbb"}, backend, page_opener=open_broken, ready_timeout=0.3,
                            retry_delay=0.05, baseline_settle=0.01, log=lambda _: None)

    healthy.start()
    broken.start()
    assert wait_for(lambda: backend.posted == ["b"] and len(broken_pages) >= 3)
    broken.stop()
    healthy.stop()
    healthy.join(2)
    broken.join(2)

    assert all(page.closed for page in broken_pages)
    assert healthy_page.closed  # fechada só pelo próprio stop, depois de ter continuado lendo


def test_repeated_failures_wait_longer_each_time():
    backend = FakeBackend()
    watcher = make_watcher(backend, lambda _: FakePage([None]), ready_timeout=0.01, retry_delay=0.05)

    watcher.start()
    assert wait_for(lambda: sum(1 for state in backend.states if state == "error") >= 3, timeout=5)
    watcher.stop()
    watcher.join(2)

    assert watcher._failures >= 3


def test_a_monitor_whose_live_is_gone_is_finished_after_the_limit():
    backend = FakeBackend()
    watcher = make_watcher(backend, lambda _: FakePage([None]), ready_timeout=0.01, retry_delay=0.01,
                           source_gone_limit=3)

    watcher.start()
    assert wait_for(lambda: not watcher.is_alive(), timeout=5)

    assert watcher.stopped
    assert len(backend.finished) == 1
    monitor_id, reason = backend.finished[0]
    assert monitor_id == MONITOR["id"]
    assert "Pausado automaticamente após 3 tentativas" in reason


def test_chrome_problems_never_finish_the_monitor():
    backend = FakeBackend()

    def no_chrome(_monitor):
        raise ConnectionRefusedError("daemon not running")

    watcher = make_watcher(backend, no_chrome, retry_delay=0.01, source_gone_limit=2)
    watcher.start()
    assert wait_for(lambda: sum(1 for state in backend.states if state == "error") >= 4, timeout=5)
    watcher.stop()
    watcher.join(2)

    assert backend.finished == []
