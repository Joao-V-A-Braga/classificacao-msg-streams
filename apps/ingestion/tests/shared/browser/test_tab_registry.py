from src.shared.browser.tab_registry import TabRegistry


def test_closes_only_the_tabs_left_by_a_previous_run(tmp_path):
    previous_run = TabRegistry(tmp_path / "tabs.json")
    previous_run.add("tab-a")
    previous_run.add("tab-b")
    previous_run.add("tab-c")
    previous_run.remove("tab-b")  # fechada normalmente (monitoramento pausado)

    closed = []
    count = TabRegistry(tmp_path / "tabs.json").close_leftovers(closed.append)

    assert closed == ["tab-a", "tab-c"]
    assert count == 2
    assert TabRegistry(tmp_path / "tabs.json").close_leftovers(closed.append) == 0


def test_tabs_already_gone_do_not_stop_the_cleanup(tmp_path):
    registry = TabRegistry(tmp_path / "tabs.json")
    registry.add("gone")
    registry.add("open")
    closed = []

    def close(target_id):
        if target_id == "gone":
            raise RuntimeError("No target with given id found")
        closed.append(target_id)

    registry.close_leftovers(close)

    assert closed == ["open"]


def test_missing_or_corrupt_file_means_no_tabs(tmp_path):
    (tmp_path / "tabs.json").write_text("{not json", encoding="utf-8")

    assert TabRegistry(tmp_path / "tabs.json").close_leftovers(lambda _: None) == 0
    assert TabRegistry(tmp_path / "missing" / "tabs.json").close_leftovers(lambda _: None) == 0
