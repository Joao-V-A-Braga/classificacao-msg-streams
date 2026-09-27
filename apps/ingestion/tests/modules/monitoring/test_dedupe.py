from src.modules.monitoring.dedupe import SeenIds


def test_first_call_returns_all_messages():
    seen = SeenIds()
    messages = [{"id": "a", "text": "hi"}, {"id": "b", "text": "there"}]

    assert seen.new_messages(messages) == messages


def test_repeated_ids_are_not_returned_again():
    seen = SeenIds()
    seen.new_messages([{"id": "a", "text": "hi"}])

    fresh = seen.new_messages([{"id": "a", "text": "hi"}, {"id": "b", "text": "new"}])

    assert fresh == [{"id": "b", "text": "new"}]


def test_marked_messages_are_never_returned():
    seen = SeenIds()
    seen.mark_seen([{"id": "old", "text": "said before the start"}])

    assert seen.new_messages([{"id": "old", "text": "x"}, {"id": "new", "text": "y"}]) == [{"id": "new", "text": "y"}]


def test_empty_list_returns_empty_list():
    seen = SeenIds()

    assert seen.new_messages([]) == []
