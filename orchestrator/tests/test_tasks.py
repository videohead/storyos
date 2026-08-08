import pathlib
import importlib.util


ROOT = pathlib.Path(__file__).resolve().parents[2]


def load_module(path, name):
    spec = importlib.util.spec_from_file_location(name, str(path))
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def test_generate_video_task(monkeypatch):
    tasks = load_module(ROOT / "orchestrator" / "tasks.py", "orchestrator.tasks")

    # Patch helpers
    monkeypatch.setattr(tasks, "submit_workflow", lambda post_id: "prompt-1")
    # `generate_video_task` expects outputs like {"1": [{"filename": "..."}]}
    monkeypatch.setattr(tasks, "poll_comfyui", lambda prompt_id: {"1": [{"filename": "/tmp/img.png"}]})
    monkeypatch.setattr(tasks, "upload_media_to_wordpress", lambda filepath, filename: {"id": 55, "source_url": "http://wp/img.png"})

    # Dummy self to capture update_state calls
    updates = []

    class DummySelf:
        def update_state(self, state=None, meta=None):
            updates.append((state, meta))

    # Stub update_state to avoid Celery backend calls during the test
    monkeypatch.setattr(tasks.generate_video_task, "update_state", lambda *a, **k: None)

    # Run the Celery task synchronously via .apply (conftest sets eager mode)
    res = tasks.generate_video_task.apply(args=(123,))
    result = res.get()
    assert result["result"]["id"] == 55
