from providers.comfyui_provider import ComfyUIProvider


class FakeResponse:
    def __init__(self, payload, status_code=200):
        self.payload = payload
        self.status_code = status_code
        self.ok = status_code < 400

    def json(self):
        return self.payload

    def raise_for_status(self):
        if not self.ok:
            raise RuntimeError(f"HTTP {self.status_code}")


def test_comfyui_provider_submits_polls_and_returns_api_artifact_urls(monkeypatch):
    calls = []

    def fake_post(url, json=None, timeout=None):
        calls.append(("POST", url, json))
        return FakeResponse({"prompt_id": "prompt-123"})

    def fake_get(url, timeout=None):
        calls.append(("GET", url, None))
        return FakeResponse(
            {
                "prompt-123": {
                    "status": {"status_str": "success"},
                    "outputs": {
                        "8": {
                            "images": [
                                {
                                    "filename": "storyos_00001.png",
                                    "subfolder": "",
                                    "type": "output",
                                }
                            ]
                        }
                    },
                }
            }
        )

    monkeypatch.setattr("providers.comfyui_provider.requests.post", fake_post)
    monkeypatch.setattr("providers.comfyui_provider.requests.get", fake_get)

    provider = ComfyUIProvider("http://comfyui:8188")
    submitted = provider.submit({"workflow": {"8": {"class_type": "SaveImage"}}})
    assert submitted["remote_job_ref"] == "prompt-123"

    assert provider.poll("prompt-123")["status"] == "completed"
    artifacts = provider.download_artifacts("prompt-123")

    assert artifacts[0]["uri"] == (
        "http://comfyui:8188/view?filename=storyos_00001.png&subfolder=&type=output"
    )
    assert artifacts[0]["mime_type"] == "image/png"
    assert any(call[1].endswith("/view?filename=storyos_00001.png&subfolder=&type=output") for call in calls) is False
