import os
import importlib.util
import pathlib
import pytest


ROOT = pathlib.Path(__file__).resolve().parents[2]


def load_module(path, name):
    spec = importlib.util.spec_from_file_location(name, str(path))
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def test_upload_missing_file_raises():
    tasks = load_module(ROOT / "orchestrator" / "tasks.py", "orchestrator.tasks")
    with pytest.raises(FileNotFoundError):
        tasks.upload_media_to_wordpress("/path/that/does/not/exist.bin", "no.bin")


def test_upload_http_error(monkeypatch, tmp_path):
    tasks = load_module(ROOT / "orchestrator" / "tasks.py", "orchestrator.tasks")
    f = tmp_path / "f.bin"
    f.write_bytes(b"hello")

    class _Resp:
        def __init__(self, status_code=400):
            self.status_code = status_code

        def raise_for_status(self):
            raise Exception("HTTP error")

    def fake_post(url, headers=None, data=None, auth=None):
        return _Resp(400)

    monkeypatch.setattr("requests.post", fake_post)

    with pytest.raises(Exception):
        tasks.upload_media_to_wordpress(str(f), "f.bin")
