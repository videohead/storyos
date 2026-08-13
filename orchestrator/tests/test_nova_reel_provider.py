import sys
import types

import pytest

from providers.nova_reel_provider import NovaReelProvider, NovaReelProviderError


class FakeBedrockRuntime:
    def __init__(self):
        self.submit_calls = []
        self.poll_responses = []

    def start_async_invoke(self, **kwargs):
        self.submit_calls.append(kwargs)
        return {
            "invocationArn": "arn:aws:bedrock:us-east-1:123456789012:async-invoke/abc123",
            "outputDataConfig": kwargs.get("outputDataConfig"),
        }

    def get_async_invoke(self, invocationArn):
        if self.poll_responses:
            return self.poll_responses.pop(0)
        return {
            "invocationArn": invocationArn,
            "status": "InProgress",
        }


class FakeBoto3Module:
    def __init__(self, runtime):
        self.runtime = runtime

    def client(self, service_name, region_name=None):
        assert service_name == "bedrock-runtime"
        assert region_name == "us-east-1"
        return self.runtime


def test_submit_text_video_builds_async_invoke(monkeypatch):
    runtime = FakeBedrockRuntime()
    monkeypatch.setitem(sys.modules, "boto3", FakeBoto3Module(runtime))

    provider = NovaReelProvider(region_name="us-east-1")

    result = provider.submit(
        {
            "task_type": "TEXT_VIDEO",
            "prompt": "A cinematic skyline at dusk",
            "seed": 42,
            "duration_seconds": 6,
            "output_s3_uri": "s3://nova-output-bucket",
        }
    )

    assert result["remote_job_ref"].endswith("/abc123")
    assert result["task_type"] == "TEXT_VIDEO"

    assert len(runtime.submit_calls) == 1
    call = runtime.submit_calls[0]
    assert call["modelId"] == "amazon.nova-reel-v1:1"
    assert call["modelInput"]["taskType"] == "TEXT_VIDEO"
    assert call["modelInput"]["videoGenerationConfig"]["fps"] == 24
    assert call["modelInput"]["videoGenerationConfig"]["dimension"] == "1280x720"
    assert call["outputDataConfig"]["s3OutputDataConfig"]["s3Uri"] == "s3://nova-output-bucket"


def test_submit_multishot_manual_validates_shots(monkeypatch):
    runtime = FakeBedrockRuntime()
    monkeypatch.setitem(sys.modules, "boto3", FakeBoto3Module(runtime))

    provider = NovaReelProvider(region_name="us-east-1")

    result = provider.submit(
        {
            "task_type": "MULTI_SHOT_MANUAL",
            "shots": [
                {"text": "Shot 1: Aerial view over ocean"},
                {"text": "Shot 2: Slow push-in on lighthouse"},
            ],
            "seed": 7,
            "output_s3_uri": "s3://nova-output-bucket/manual",
        }
    )

    assert result["task_type"] == "MULTI_SHOT_MANUAL"

    call = runtime.submit_calls[0]
    assert call["modelInput"]["taskType"] == "MULTI_SHOT_MANUAL"
    assert len(call["modelInput"]["multiShotManualParams"]["shots"]) == 2


def test_poll_and_download_artifacts(monkeypatch):
    runtime = FakeBedrockRuntime()
    runtime.poll_responses = [
        {
            "invocationArn": "arn:aws:bedrock:us-east-1:123456789012:async-invoke/xyz789",
            "status": "Completed",
            "outputDataConfig": {
                "s3OutputDataConfig": {
                    "s3Uri": "s3://nova-output-bucket/renders"
                }
            },
        },
        {
            "invocationArn": "arn:aws:bedrock:us-east-1:123456789012:async-invoke/xyz789",
            "status": "Completed",
            "outputDataConfig": {
                "s3OutputDataConfig": {
                    "s3Uri": "s3://nova-output-bucket/renders"
                }
            },
        },
    ]
    monkeypatch.setitem(sys.modules, "boto3", FakeBoto3Module(runtime))

    provider = NovaReelProvider(region_name="us-east-1")

    poll = provider.poll("arn:aws:bedrock:us-east-1:123456789012:async-invoke/xyz789")
    assert poll["status"] == "completed"
    assert poll["provider_status"] == "Completed"

    artifacts = provider.download_artifacts("arn:aws:bedrock:us-east-1:123456789012:async-invoke/xyz789")
    assert len(artifacts) == 1
    assert artifacts[0]["uri"] == "s3://nova-output-bucket/renders/xyz789/output.mp4"


def test_missing_output_uri_raises(monkeypatch):
    runtime = FakeBedrockRuntime()
    monkeypatch.setitem(sys.modules, "boto3", FakeBoto3Module(runtime))

    provider = NovaReelProvider(region_name="us-east-1")

    with pytest.raises(NovaReelProviderError):
        provider.submit({"task_type": "TEXT_VIDEO", "prompt": "hello"})
