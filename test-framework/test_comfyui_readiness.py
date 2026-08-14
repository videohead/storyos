from orchestrator.comfyui_readiness import ComfyUIReadinessChecker


class FakeResponse:
    def __init__(self, payload=None, status_code=200, headers=None):
        self._payload = payload or {}
        self.status_code = status_code
        self.headers = headers or {}
        self.ok = 200 <= status_code < 300

    def json(self):
        return self._payload

    def raise_for_status(self):
        if not self.ok:
            raise RuntimeError(f"HTTP {self.status_code}")

    def iter_content(self, chunk_size=0):
        yield b"verified-artifact-bytes"


def test_static_readiness_proves_nodes_and_output_node(monkeypatch):
    def fake_get(url, **kwargs):
        if url.endswith("/history/"):
            return FakeResponse({}, 404)
        if url.endswith("/system_stats"):
            return FakeResponse({"system": {"device": "cpu"}})
        if url.endswith("/object_info"):
            return FakeResponse({"LoadImage": {}, "SaveImage": {}})
        raise AssertionError(url)

    monkeypatch.setattr("orchestrator.comfyui_readiness.requests.get", fake_get)
    result = ComfyUIReadinessChecker("http://comfyui:8188").check(
        {
            "1": {"class_type": "LoadImage"},
            "2": {"class_type": "SaveImage"},
        }
    )

    assert result["ready"] is True
    assert result["proof_level"] == "static"
    assert result["checks"]["nodes"]["missing"] == []


def test_smoke_test_proves_downloadable_artifact(monkeypatch):
    workflow = {"1": {"class_type": "SaveImage"}}
    calls = []

    def fake_get(url, **kwargs):
        calls.append(url)
        if url.endswith("/history/"):
            return FakeResponse({}, 200)
        if url.endswith("/system_stats"):
            return FakeResponse({"system": {"device": "cuda"}})
        if url.endswith("/object_info"):
            return FakeResponse({"SaveImage": {}})
        if "/history/prompt-1" in url:
            return FakeResponse(
                {"prompt-1": {"outputs": {"1": {"images": [{"filename": "render.png"}]}}}}
            )
        if "/view?" in url:
            return FakeResponse({}, 200, {"Content-Length": "42"})
        raise AssertionError(url)

    monkeypatch.setattr("orchestrator.comfyui_readiness.requests.get", fake_get)
    monkeypatch.setattr(
        "orchestrator.comfyui_readiness.requests.post",
        lambda *args, **kwargs: FakeResponse({"prompt_id": "prompt-1"}),
    )

    result = ComfyUIReadinessChecker("http://comfyui:8188").smoke_test(
        workflow, poll_interval=0, max_polls=1
    )

    assert result["ready"] is True
    assert result["proof_level"] == "end_to_end"
    assert result["artifacts"][0]["downloadable"] is True
