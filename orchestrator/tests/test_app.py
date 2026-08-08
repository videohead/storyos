import json
import pathlib
import importlib.util


ROOT = pathlib.Path(__file__).resolve().parents[2]


def load_module(path, name):
    spec = importlib.util.spec_from_file_location(name, str(path))
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def test_post_generate_monkeypatched(tmp_path, monkeypatch):
    app_mod = load_module(ROOT / "orchestrator" / "app.py", "orchestrator.app")

    # monkeypatch get_post to return a sample post
    def fake_get_post(post_id):
        return {"id": post_id, "meta": {}}

    monkeypatch.setattr(app_mod, "get_post", fake_get_post)

    # monkeypatch celery_app.send_task to return a fake with id
    class FakeTask:
        def __init__(self, id):
            self.id = id

    def fake_send_task(name, args=None):
        return FakeTask("fake-task-1")

    monkeypatch.setattr(app_mod.celery_app, "send_task", fake_send_task)

    # monkeypatch update_post_meta to capture calls
    called = {}

    def fake_update_post_meta(post_id, meta):
        called["post_id"] = post_id
        called["meta"] = meta
        return {"ok": True}

    monkeypatch.setattr(app_mod, "update_post_meta", fake_update_post_meta)

    from fastapi.testclient import TestClient

    client = TestClient(app_mod.app)

    resp = client.post("/generate", json={"post_id": 123})
    assert resp.status_code == 200
    data = resp.json()
    assert data["status"] == "queued"
    assert data["job_id"] == "fake-task-1"
    assert called["post_id"] == 123
    assert called["meta"]["video_status"] == "queued"
