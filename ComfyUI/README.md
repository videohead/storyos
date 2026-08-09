# ComfyUI

Standalone Docker deployment of [ComfyUI](https://github.com/comfyanonymous/ComfyUI) — a node-based GUI for Stable Diffusion image generation.

## Quick Start

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and Docker Compose installed
- (Optional) NVIDIA GPU with drivers + [nvidia-container-toolkit](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/install-guide.html) for GPU acceleration

### Launch the container

```bash
# CPU-only mode (for testing or machines without a GPU)
docker compose up

# GPU mode (recommended — uses the host GPU)
docker compose --profile gpu up
```

The ComfyUI web UI will be available at **http://localhost:8188**.

### Stop the container

```bash
docker compose --profile gpu down
```

## Configuration

### GPU mode

To enable GPU acceleration, uncomment the `deploy` block in `docker-compose.yaml`:

```yaml
deploy:
  resources:
    reservations:
      devices:
        - driver: nvidia
          count: all
          capabilities: [gpu]
```

Or pass the flag at runtime:

```bash
docker compose --profile gpu up
```

### VRAM mode flags

Recent ComfyUI builds do not support `--normalvram` as a CLI argument.
Normal VRAM mode is the default when no VRAM mode flag is provided.

Valid VRAM-related flags include:

- `--highvram`
- `--lowvram`
- `--novram`
- `--cpu`
- `--gpu-only`

You can temporarily pass one by overriding the service command at runtime, for example:

```bash
docker compose --profile gpu run --rm comfyui --listen 0.0.0.0 --lowvram --preview-method auto
```

Then restart the normal service command:

```bash
docker compose --profile gpu up -d
```

### Hugging Face token

Some models on Hugging Face require authentication. To use them, create a `.env` file in the `ComfyUI/` directory:

```bash
cp .env.example .env
```

Then edit `.env` and add your token:

```bash
HUGGING_FACE_HUB_TOKEN=hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Get your token from https://huggingface.co/settings/tokens (create a new token with `read` scope).

The token is automatically passed to the container via the `HUGGING_FACE_HUB_TOKEN` environment variable.

### Persisting models and assets

All ComfyUI data directories are bind-mounted to the host so they survive container rebuilds and are easily accessible:

| Directory | Host Path | What's stored |
|-----------|-----------|---------------|
| `models/` | `ComfyUI/models/` | Checkpoints, LoRAs, VAEs, clip, upscale models, etc. |
| `input/`  | `ComfyUI/input/` | Images you drag into ComfyUI |
| `output/` | `ComfyUI/output/` | Generated images and videos |

These directories are created automatically on first run. You can also manually place model files (e.g. `.safetensors` checkpoints) into `ComfyUI/models/` on the host — they will be visible inside the container immediately.

#### Downloading models

Models are large (1–7 GB each). Here are some quick ways to populate the `models/` directory:

```bash
# Create the directory structure
mkdir -p ComfyUI/models/checkpoints

# Download Stable Diffusion 1.5 checkpoint (~2.6 GB)
cd ComfyUI/models/checkpoints
wget https://huggingface.co/Comfy-Org/stable-diffusion-v1-5-archive/resolve/main/v1-5-pruned-emaonly-fp16.safetensors

# Download SDXL checkpoint (~7 GB) — alternative
wget https://huggingface.co/stabilityai/stable-diffusion-xl-base-1.0/resolve/main/sd_xl_base_1.0_0.9vae.safetensors
```

You can also use [huggingface-cli](https://huggingface.co/docs/huggingface_hub/en/guides/cli) or [koyeb/sd-downloader](https://github.com/akhaliq/sd-downloader) for bulk downloads:

```bash
pip install huggingface_hub
huggingface-cli download Comfy-Org/stable-diffusion-v1-5-archive v1-5-pruned-emaonly-fp16.safetensors --local-dir ComfyUI/models/checkpoints
```

#### Backing up models

Since models are stored in a regular host directory, you can back them up with standard tools:

```bash
# Create a compressed backup
tar czf comfyui_models_backup.tar.gz ComfyUI/models/

# Or use rsync for incremental backups
rsync -av ComfyUI/models/ /backup/comfyui_models/
```

### Custom build arguments

The `Dockerfile` installs PyTorch with CUDA 13.0 support. To change the CUDA version or other build parameters, add build args in `docker-compose.yaml`:

```yaml
services:
  comfyui:
    build:
      args:
        CUDA_VERSION: "12.1"
```

## Integration with StoryOS

ComfyUI is expected to run as an external Docker service when you use StoryOS. Start it from this directory and point the orchestrator at the published host URL.

```bash
docker compose --profile gpu up -d
```

The orchestrator connects to ComfyUI via the `COMFYUI_URL` environment variable (for example `http://127.0.0.1:8188` when running on the host, or `http://host.docker.internal:8188` from other Docker containers).

## Troubleshooting

| Problem | Solution |
|---------|----------|
| `nvidia-container-toolkit` not found | Install the toolkit: [installation guide](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/install-guide.html) |
| Port 8188 already in use | Change the port mapping in `docker-compose.yaml` (e.g., `"8189:8188"`) |
| Out of memory | Reduce batch size in ComfyUI settings or use a smaller model |
| `main.py: error: unrecognized arguments: --normalvram` | Remove `--normalvram` from container startup args. Normal mode is the default in current ComfyUI. |
| Models not persisting | Ensure the `comfyui_models` volume is not being pruned (`docker volume prune`) |
