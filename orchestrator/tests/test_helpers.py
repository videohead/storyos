import pathlib
import importlib.util
import types


ROOT = pathlib.Path(__file__).resolve().parents[2]


def load_module(path, name):
    spec = importlib.util.spec_from_file_location(name, str(path))
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


class _MockResp:
    def __init__(self, json_data=None, status_code=200):
        self._json = json_data or {}
        self.status_code = status_code

    def raise_for_status(self):
        if not (200 <= self.status_code < 300):
            raise Exception("HTTP error")

    def json(self):
        return self._json


def test_submit_workflow(monkeypatch):
    tasks = load_module(ROOT / "orchestrator" / "tasks.py", "orchestrator.tasks")

    def fake_post(url, json=None):
        return _MockResp({"prompt_id": "prompt-xyz"}, 200)

    monkeypatch.setattr("requests.post", fake_post)

    prompt_id = tasks.submit_workflow(1)
    assert prompt_id == "prompt-xyz"


def test_poll_comfyui(monkeypatch):
    tasks = load_module(ROOT / "orchestrator" / "tasks.py", "orchestrator.tasks")

    calls = {"n": 0}

    def fake_get(url):
        calls["n"] += 1
        if calls["n"] < 2:
            return _MockResp({}, 202)
        return _MockResp({"outputs": {"1": [[{"filename": "/tmp/img.png"}]]}}, 200)

    monkeypatch.setattr("requests.get", fake_get)

    outputs = tasks.poll_comfyui("prompt-xyz")
    assert "1" in outputs


def test_upload_media_to_wordpress(tmp_path, monkeypatch):
    tasks = load_module(ROOT / "orchestrator" / "tasks.py", "orchestrator.tasks")

    # Create temp file
    fpath = tmp_path / "out.bin"
    fpath.write_bytes(b"binarydata")

    def fake_post(url, headers=None, data=None, auth=None):
        return _MockResp({"id": 10, "source_url": "http://wp/media.png"}, 201)

    monkeypatch.setattr("requests.post", fake_post)

    media = tasks.upload_media_to_wordpress(str(fpath), "media.png")
    assert media["id"] == 10
