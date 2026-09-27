from src.modules.supervisor.supervisor import IngestionSupervisor
from src.shared.backend_client import MonitorStopped

MONITOR = {"id": "m1", "sourceType": "youtube_live", "url": "https://youtu.be/x", "startedAt": "t1"}


class FakeBackend:
    def __init__(self):
        self.running = []
        self.states = []
        self.down = False

    def running_monitors(self):
        if self.down:
            raise ConnectionError("down")
        return list(self.running)

    def report_state(self, monitor_id, state, detail=None):
        if monitor_id == "gone":
            raise MonitorStopped("404")
        self.states.append((monitor_id, state, detail))


class FakeWorker:
    def __init__(self):
        self.running = True
        self.stopped = False

    def is_running(self):
        return self.running

    def stop(self):
        self.stopped = True
        self.running = False

    def log_tail(self):
        return "Traceback: boom"


class Clock:
    def __init__(self):
        self.now = 0.0

    def __call__(self):
        return self.now


def make(backend, clock, **kwargs):
    workers = []

    def start():
        workers.append(FakeWorker())
        return workers[-1]

    supervisor = IngestionSupervisor(
        backend, start, clock=clock, idle_stop_seconds=60, restart_delays=(5, 30), log=lambda _: None, **kwargs
    )
    return supervisor, workers


def test_starts_the_service_only_when_a_monitor_runs():
    backend, clock = FakeBackend(), Clock()
    supervisor, workers = make(backend, clock)

    supervisor.tick()
    assert workers == []

    backend.running = [MONITOR]
    supervisor.tick()
    supervisor.tick()
    assert len(workers) == 1
    assert backend.states[0][:2] == ("m1", "starting")


def test_stops_the_service_after_being_idle():
    backend, clock = FakeBackend(), Clock()
    supervisor, workers = make(backend, clock)
    backend.running = [MONITOR]
    supervisor.tick()

    backend.running = []
    supervisor.tick()
    clock.now = 30
    supervisor.tick()
    assert not workers[0].stopped

    clock.now = 61
    supervisor.tick()
    assert workers[0].stopped
    assert supervisor.worker is None


def test_starting_a_monitor_again_while_idle_keeps_the_service():
    backend, clock = FakeBackend(), Clock()
    supervisor, workers = make(backend, clock)
    backend.running = [MONITOR]
    supervisor.tick()
    backend.running = []
    supervisor.tick()

    clock.now = 50
    backend.running = [MONITOR]
    supervisor.tick()
    backend.running = []
    clock.now = 100
    supervisor.tick()

    assert not workers[0].stopped
    assert len(workers) == 1


def test_restarts_a_crashed_service_with_a_delay_and_reports_the_error():
    backend, clock = FakeBackend(), Clock()
    supervisor, workers = make(backend, clock)
    backend.running = [MONITOR]
    supervisor.tick()

    workers[0].running = False
    supervisor.tick()
    assert len(workers) == 1
    assert backend.states[-1][1] == "error"
    assert "boom" in backend.states[-1][2]

    clock.now = 6
    supervisor.tick()
    assert len(workers) == 2

    workers[1].running = False
    clock.now = 7
    supervisor.tick()
    clock.now = 20
    supervisor.tick()
    assert len(workers) == 2  # a segunda queda espera 30 s
    clock.now = 38
    supervisor.tick()
    assert len(workers) == 3


def test_a_crash_after_a_long_stable_run_restarts_with_the_shortest_delay():
    backend, clock = FakeBackend(), Clock()
    supervisor, workers = make(backend, clock)
    backend.running = [MONITOR]
    supervisor.tick()
    workers[0].running = False
    supervisor.tick()  # primeira queda: 5 s
    clock.now = 6
    supervisor.tick()

    clock.now = 3600  # ficou no ar por uma hora e então caiu
    workers[1].running = False
    supervisor.tick()
    clock.now = 3606
    supervisor.tick()

    assert len(workers) == 3


def test_does_nothing_while_the_backend_is_down():
    backend, clock = FakeBackend(), Clock()
    supervisor, workers = make(backend, clock)
    backend.running = [MONITOR]
    supervisor.tick()

    backend.down = True
    clock.now = 500
    supervisor.tick()

    assert not workers[0].stopped


def test_leaves_a_manually_started_service_alone():
    backend, clock = FakeBackend(), Clock()
    supervisor, workers = make(backend, clock, external_worker_alive=lambda: True)
    backend.running = [MONITOR]

    supervisor.tick()

    assert workers == []


def test_reporting_ignores_monitors_that_were_just_paused():
    backend, clock = FakeBackend(), Clock()
    supervisor, workers = make(backend, clock)
    backend.running = [{**MONITOR, "id": "gone"}, MONITOR]

    supervisor.tick()

    assert [state[0] for state in backend.states] == ["m1"]
