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

    sent = {}

    def fake_send_task(name, args=None, **kwargs):
        sent["name"] = name
        sent["args"] = args
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

    resp = client.post(
        "/generate",
        json={
            "post_id": 123,
            "provider_type": "comfyui",
            "connection_id": 1,
        },
    )
    assert resp.status_code == 200
    data = resp.json()
    assert data["status"] == "queued"
    assert data["job_id"] == "fake-task-1"
    assert data["provider_type"] == "comfyui"
    assert data["connection_id"] == 1
    assert sent["name"] == "tasks.generate_video_task"
    assert called["post_id"] == 123
    assert called["meta"]["video_status"] == "queued"


def test_post_generate_routes_nova_reel_task(tmp_path, monkeypatch):
    app_mod = load_module(ROOT / "orchestrator" / "app.py", "orchestrator.app.nova")

    def fake_get_post(post_id):
        return {"id": post_id, "meta": {}}

    monkeypatch.setattr(app_mod, "get_post", fake_get_post)

    class FakeTask:
        def __init__(self, id):
            self.id = id

    sent = {}

    def fake_send_task(name, args=None, **kwargs):
        sent["name"] = name
        sent["args"] = args
        return FakeTask("fake-task-nova-1")

    monkeypatch.setattr(app_mod.celery_app, "send_task", fake_send_task)

    def fake_update_post_meta(post_id, meta):
        return {"ok": True}

    monkeypatch.setattr(app_mod, "update_post_meta", fake_update_post_meta)

    from fastapi.testclient import TestClient

    client = TestClient(app_mod.app)

    resp = client.post(
        "/generate",
        json={
            "post_id": 321,
            "provider_type": "nova_reel",
            "connection_id": 77,
            "custom_params": {
                "prompt": "Aerial shot over a futuristic city",
                "task_type": "TEXT_VIDEO",
                "output_s3_uri": "s3://test-output-bucket",
            },
        },
    )

    assert resp.status_code == 200
    data = resp.json()
    assert data["job_id"] == "fake-task-nova-1"
    assert data["provider_type"] == "nova_reel"
    assert sent["name"] == "tasks.generate_nova_reel_task"


def test_post_generate_routes_veo_task(tmp_path, monkeypatch):
    app_mod = load_module(ROOT / "orchestrator" / "app.py", "orchestrator.app.veo")

    def fake_get_post(post_id):
        return {"id": post_id, "meta": {}}

    monkeypatch.setattr(app_mod, "get_post", fake_get_post)

    class FakeTask:
        def __init__(self, id):
            self.id = id

    sent = {}

    def fake_send_task(name, args=None, **kwargs):
        sent["name"] = name
        sent["args"] = args
        sent["kwargs"] = kwargs
        return FakeTask("fake-task-veo-1")

    monkeypatch.setattr(app_mod.celery_app, "send_task", fake_send_task)

    def fake_update_post_meta(post_id, meta):
        return {"ok": True}

    monkeypatch.setattr(app_mod, "update_post_meta", fake_update_post_meta)

    from fastapi.testclient import TestClient

    client = TestClient(app_mod.app)

    resp = client.post(
        "/generate",
        json={
            "post_id": 654,
            "provider_type": "veo",
            "connection_id": 88,
            "custom_params": {
                "prompt": "A neon-lit city timelapse",
            },
        },
    )

    assert resp.status_code == 200
    data = resp.json()
    assert data["job_id"] == "fake-task-veo-1"
    assert data["provider_type"] == "veo"
    assert sent["name"] == "tasks.generate_veo_task"
