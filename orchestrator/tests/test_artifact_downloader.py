import hashlib
import sys

import pytest

from artifact_downloader import ArtifactDownloadError, ArtifactDownloader


class FakeHttpResponse:
    headers = {"Content-Length": "11"}

    def raise_for_status(self):
        return None

    def iter_content(self, chunk_size):
        assert chunk_size == 1024 * 1024
        return [b"hello ", b"world"]


def test_download_https_artifact_with_headers(monkeypatch):
    captured = {}

    def fake_get(uri, headers, stream, timeout):
        captured.update(uri=uri, headers=headers, stream=stream, timeout=timeout)
        return FakeHttpResponse()

    monkeypatch.setattr("artifact_downloader.requests.get", fake_get)

    result = ArtifactDownloader().download(
        {
            "uri": "https://provider.example/video.mp4",
            "filename": "video.mp4",
            "headers": {"Authorization": "Bearer test-token"},
            "mime_type": "video/mp4",
        }
    )

    assert captured == {
        "uri": "https://provider.example/video.mp4",
        "headers": {"Authorization": "Bearer test-token"},
        "stream": True,
        "timeout": 120,
    }
    assert result["size_bytes"] == 11
    assert result["sha256"] == hashlib.sha256(b"hello world").hexdigest()
    assert "headers" not in result


def test_download_s3_artifact(monkeypatch):
    class FakeS3:
        def download_file(self, bucket, key, destination):
            with open(destination, "wb") as handle:
                handle.write(b"video")

    class FakeBoto3:
        def client(self, service):
            assert service == "s3"
            return FakeS3()

    monkeypatch.setitem(sys.modules, "boto3", FakeBoto3())

    result = ArtifactDownloader().download({"uri": "s3://bucket/renders/video.mp4"})

    assert result["filename"] == "video.mp4"
    assert result["size_bytes"] == 5


def test_download_rejects_oversized_artifact(monkeypatch):
    monkeypatch.setattr(
        "artifact_downloader.requests.get",
        lambda *args, **kwargs: FakeHttpResponse(),
    )

    with pytest.raises(ArtifactDownloadError, match="size limit"):
        ArtifactDownloader(max_bytes=4).download({"uri": "https://provider.example/video.mp4"})