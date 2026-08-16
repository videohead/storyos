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

    def fake_post(url, json=None, headers=None, timeout=None):
        calls.append(("POST", url, json, headers))
        return FakeResponse({"prompt_id": "prompt-123"})

    def fake_get(url, headers=None, timeout=None):
        calls.append(("GET", url, None, headers))
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


def test_comfyui_cloud_connector_uses_auth_headers_and_custom_paths(monkeypatch):
    calls = []

    def fake_post(url, json=None, headers=None, timeout=None):
        calls.append(("POST", url, json, headers))
        return FakeResponse({"prompt_id": "cloud-42"})

    def fake_get(url, headers=None, timeout=None):
        calls.append(("GET", url, None, headers))
        return FakeResponse(
            {
                "cloud-42": {
                    "status": {"status_str": "success"},
                    "outputs": {
                        "1": {
                            "images": [
                                {
                                    "filename": "asset.png",
                                    "subfolder": "cloud",
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

    provider = ComfyUIProvider("https://cloud.example")
    connection = {
        "connector": "comfyui_cloud",
        "token": "abc123",
        "submit_path": "/api/prompt",
        "history_path_template": "/api/history/{job_id}",
        "view_path": "/api/view",
    }
    submitted = provider.submit({"workflow": {"1": {"class_type": "SaveImage"}}}, connection)
    assert submitted["remote_job_ref"] == "cloud-42"

    poll = provider.poll("cloud-42", connection)
    assert poll["status"] == "completed"

    artifacts = provider.download_artifacts("cloud-42", connection)
    assert artifacts[0]["uri"] == (
        "https://cloud.example/api/view?filename=asset.png&subfolder=cloud&type=output"
    )

    post_call = next(call for call in calls if call[0] == "POST")
    assert post_call[1] == "https://cloud.example/api/prompt"
    assert post_call[3]["Authorization"].startswith("Bearer ")

    history_call = next(call for call in calls if call[0] == "GET")
    assert history_call[1] == "https://cloud.example/api/history/cloud-42"
