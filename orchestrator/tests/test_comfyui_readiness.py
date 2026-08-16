from comfyui_readiness import ComfyUIReadinessChecker


class FakeResponse:
    def __init__(self, payload=None, status_code=200, ok=True, headers=None):
        self._payload = payload if payload is not None else {}
        self.status_code = status_code
        self.ok = ok
        self.headers = headers or {}

    def json(self):
        return self._payload

    def raise_for_status(self):
        if self.status_code >= 400:
            raise RuntimeError(f"HTTP {self.status_code}")

    def iter_content(self, chunk_size=8192):
        yield b"bytes"


def test_readiness_uses_connector_headers_and_paths(monkeypatch):
    calls = []

    def fake_get(url, headers=None, timeout=None, stream=False):
        calls.append(("GET", url, headers, stream))
        if url.endswith("/api/history/"):
            return FakeResponse({}, status_code=404, ok=False)
        if url.endswith("/api/system_stats"):
            return FakeResponse({"devices": []})
        if url.endswith("/api/object_info"):
            return FakeResponse({"SaveImage": {}})
        return FakeResponse({})

    monkeypatch.setattr("comfyui_readiness.requests.get", fake_get)

    checker = ComfyUIReadinessChecker(
        "https://cloud.example",
        connection={
            "connector": "comfyui_cloud",
            "token": "abc123",
            "history_probe_path": "/api/history/",
            "system_stats_path": "/api/system_stats",
            "object_info_path": "/api/object_info",
        },
    )

    workflow = {"1": {"class_type": "SaveImage"}}
    result = checker.check(workflow)
    assert result["ready"] is True

    history_probe = next(call for call in calls if call[1].endswith("/api/history/"))
    assert history_probe[2]["Authorization"].startswith("Bearer ")

    assert any(call[1].endswith("/api/system_stats") for call in calls)
    assert any(call[1].endswith("/api/object_info") for call in calls)
