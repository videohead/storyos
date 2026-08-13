# Orchestrator System Dependencies

This file tracks runtime dependencies that are not installed by Python `requirements.txt`.

## Current Priority

The immediate deployment target is a local ComfyUI-backed Orchestrator. Local generation, connector readiness, provider API integration, artifact download, and WordPress asset ingestion take priority over VPS and shared-host portability. VPS and shared-host requirements remain documented so they are not forgotten, but they should not delay the first reliable local generation workflow.

The Orchestrator must keep Python packages and operating-system binaries separate:

- Python packages belong in `requirements.txt`.
- System binaries belong in the deployment image, operating-system package manifest, or hosting-provider prerequisite list.
- Provider-specific credentials never belong in either dependency file.

## Media Tooling

| Dependency | Required for | Provides | Current status |
| --- | --- | --- | --- |
| `ffmpeg` | Media normalization, thumbnails, frame extraction, audio extraction, transcoding | `ffmpeg` and `ffprobe` binaries | Planned for media discovery pipeline |
| `ffprobe` | Container and stream inspection, duration, dimensions, codecs, frame rate, metadata | Usually supplied by the `ffmpeg` package | Planned for media discovery pipeline |

`ffprobe` should perform inspection before any transformation. `ffmpeg` should only be invoked when a derivative, normalization, or transcode is required.

## Deployment Matrix

### Local Linux

Install the distribution package:

```bash
sudo apt-get update
sudo apt-get install --no-install-recommends ffmpeg
```

Verify both binaries:

```bash
ffprobe -version
ffmpeg -version
```

### Docker / Container Deployment

Install `ffmpeg` in the Orchestrator image rather than through `requirements.txt`:

```dockerfile
RUN apt-get update \\
    && apt-get install -y --no-install-recommends ffmpeg \\
    && rm -rf /var/lib/apt/lists/*
```

The image build must record the base image, operating-system package source, and installed FFmpeg version. Runtime health checks should fail clearly when media discovery is enabled but `ffprobe` is unavailable.

### VPS Deployment (Later Portability Phase)

Use the operating system package manager where possible. The deployment checklist must record:

- Linux distribution and release
- FFmpeg package version
- `ffprobe` path returned by `command -v ffprobe`
- Whether the worker and API processes share the same binary environment
- Whether transcoding is permitted by CPU, memory, disk, and execution-time limits

### Shared Hosting (Lowest-Priority Portability Phase)

Shared hosting may not permit system package installation or long-running media processes. Before enabling media discovery, verify that the host provides:

- `ffprobe` and `ffmpeg` on the process `PATH`, or configurable absolute paths
- A process execution mechanism such as `proc_open` or an equivalent worker service
- Sufficient execution time, temporary storage, and memory
- Permission to inspect and transform uploaded media

If those requirements cannot be met, the Orchestrator should retain provider metadata and hashes but delegate media probing and transformation to a separate worker or external media service. It must not silently assume that `ffprobe` exists.

## Version Policy

- Do not pin FFmpeg versions in Python `requirements.txt`.
- Record the installed FFmpeg version during deployment verification.
- Re-run media compatibility tests after an OS image or FFmpeg upgrade.
- Provider capability descriptors describe provider output constraints; FFmpeg availability describes local ingestion capabilities. These are separate compatibility checks.

## Runtime Verification Contract

The future media discovery service should expose or log:

```json
{
  "ffmpeg_available": true,
  "ffprobe_available": true,
  "ffmpeg_path": "/usr/bin/ffmpeg",
  "ffprobe_path": "/usr/bin/ffprobe",
  "version": "deployment-recorded-version"
}
```

A missing binary should produce a typed capability such as `media_probe_unavailable`, not a generic provider failure.
