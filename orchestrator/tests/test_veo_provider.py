import types

import pytest

from providers.veo_provider import VeoProvider, VeoProviderError


class DummyResponse:
    def __init__(self, payload, status_code=200):
        self._payload = payload
        self.status_code = status_code

    def json(self):
        return self._payload

    def raise_for_status(self):
        if self.status_code >= 400:
            raise RuntimeError(f"HTTP {self.status_code}")


def test_submit_generation_builds_expected_request(monkeypatch):
    provider = VeoProvider(api_key="abc123", model="veo-2.0-generate-001")
    captured = {}

    def fake_post(url, json=None, headers=None, timeout=None):
        captured["url"] = url
        captured["json"] = json
        captured["headers"] = headers
        captured["timeout"] = timeout
        return DummyResponse({"name": "operations/123"})

    monkeypatch.setattr("providers.veo_provider.requests.post", fake_post)

    operation_name = provider.submit_generation(prompt="A cinematic skyline", duration_seconds=6)

    assert operation_name == "operations/123"
    assert captured["url"].endswith(":generateVideo")
    assert captured["json"]["prompt"] == "A cinematic skyline"
    assert captured["json"]["durationSeconds"] == 6
    assert captured["headers"]["x-goog-api-key"] == "abc123"


def test_poll_generation_returns_completed_video(monkeypatch):
    provider = VeoProvider(api_key="abc123")

    responses = iter(
        [
            DummyResponse({"name": "operations/123", "done": False}),
            DummyResponse(
                {
                    "name": "operations/123",
                    "done": True,
                    "response": {
                        "generatedVideos": [
                            {
                                "video": {
                                    "uri": "https://example.test/video.mp4",
                                    "mimeType": "video/mp4",
                                }
                            }
                        ]
                    },
                }
            ),
        ]
    )

    monkeypatch.setattr("providers.veo_provider.requests.get", lambda url, headers=None, timeout=None: next(responses))

    result = provider.poll_generation("operations/123", poll_interval=0, max_polls=2)

    assert result["response"]["generatedVideos"][0]["video"]["uri"] == "https://example.test/video.mp4"


def test_poll_generation_raises_on_error(monkeypatch):
    provider = VeoProvider(api_key="abc123")

    monkeypatch.setattr(
        "providers.veo_provider.requests.get",
        lambda url, headers=None, timeout=None: DummyResponse({"error": {"message": "boom"}}, status_code=400),
    )

    with pytest.raises(VeoProviderError):
        provider.poll_generation("operations/123", poll_interval=0, max_polls=1)
