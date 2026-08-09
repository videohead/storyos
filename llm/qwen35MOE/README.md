# Qwen3.6-35B-A3B-NVFP4 — Local LLM Service

> Part of [StoryOS](../../README.md) — the open-source AI storytelling operating system.

This directory contains a Docker Compose stack that runs **nvidia/Qwen3.6-35B-A3B-NVFP4** (a 35B MoE model with 3.5B active parameters) behind an **OpenAI-compatible API** on port `11434`. The `lite` profile is tuned for roughly **12 GB VRAM** by using vLLM CPU offload, a small batch size, and a reduced context window. A second `full` profile is available for higher-performance hardware with a larger context window and less offload. The model is served by [vLLM](https://docs.vllm.ai/) for use by the StoryOS orchestrator and multi-agent framework.

The bundled `.env` enables `COMPOSE_PROFILES=lite`, so a plain `docker compose up` starts the 12 GB preset by default.

## Architecture

```
┌──────────────────┐     OpenAI API      ┌─────────────────────┐
│  StoryOS         │  ──────────────────▶ │  Qwen3.6-35B vLLM  │
│  Orchestrator    │  port 11434          │  (GPU-accelerated) │
│  Multi-Agent     │  <─────────────────  │  nvidia/Qwen3.6-35B│
│  Framework       │   responses          │  A3B-NVFP4         │
└──────────────────┘                     └─────────────────────┘
```

**Use cases in StoryOS:**
- AI Advisor conversations (story, prompt, production, editorial, technical)
- Executive Orchestrator routing and decision-making
- Script analysis and summarization
- Story continuity checks
- Asset description generation

## Quick Start

### Option A — External Docker Compose (recommended)

StoryOS Lando does not host this model service. Run the vLLM server separately in Docker and point the orchestrator at the published endpoint.

```bash
# Set your Hugging Face token
export HUGGING_FACE_HUB_TOKEN=hf_your_token_here

# Start the external vLLM container
cd llm/qwen35MOE
docker compose up -d --build
```

Check health:

```bash
docker compose ps
docker compose logs -f
curl -fsS http://localhost:11434/v1/models >/dev/null && echo "OK: vLLM is healthy"
```

### Option B — Standalone Docker Compose

For machines with GPU access outside of Lando:

```bash
cd llm/qwen35MOE

# Create .env with your Hugging Face token
# Edit .env and set HUGGING_FACE_HUB_TOKEN=hf_your_token_here
# `COMPOSE_PROFILES=lite` is already set, so plain `docker compose up` uses the lite profile.

# Build and start the 12 GB VRAM lite profile
docker compose up -d --build

# Or start the higher-performance profile
docker compose --profile full up -d --build
```

## Prerequisites

| Requirement | Details |
|-------------|---------|
| **Docker** | v24+ with Docker Compose v2 |
| **NVIDIA GPU** | 12 GB+ VRAM can work with CPU offload; more VRAM improves speed |
| **nvidia-container-toolkit** | Required for GPU passthrough |
| **Hugging Face Token** | Required to download the gated model |
| **Lando** (optional) | For local StoryOS development integration |

### Hugging Face Token

The model `nvidia/Qwen3.6-35B-A3B-NVFP4` requires authentication. A `.env` file is already present in this directory with the token configured.


If you need to rotate the token, update it in `.env`. Get a new token from https://huggingface.co/settings/tokens (scope: `read`).

This stack is configured for the NVIDIA-hosted FP4 checkpoint. If you want the upstream Qwen checkpoint instead, change the model name in `docker-compose.yaml` to `Qwen/Qwen3.6-35B-A3B`, but expect materially higher host RAM usage because that release is BF16.

## Model Download

The model (~21 GB) downloads automatically on first startup into the bind-mounted `./models` directory.

### Pre-pull the model (recommended)

Avoid blocking the API server startup by pre-pulling:

```bash
# Via Docker Compose
docker compose run --rm \
  -e HUGGING_FACE_HUB_TOKEN=$HUGGING_FACE_HUB_TOKEN \
  qwen35moe-vllm python3 -c \
  "from huggingface_hub import snapshot_download; snapshot_download('nvidia/Qwen3.6-35B-A3B-NVFP4')"
```

### Verify download

```bash
find ./models -maxdepth 4 -type d -name "models--nvidia--Qwen3.6-35B-A3B-NVFP4"
```

## Configuration

### docker-compose.yaml

```yaml
services:
  qwen35moe-vllm:
    build:
      context: .
      dockerfile: Containerfile
    container_name: qwen35moe-vllm
    restart: unless-stopped
    gpus: all
    ports:
      - "11434:11434"
    environment:
      HF_HOME: /models/cache/huggingface
      TRANSFORMERS_CACHE: /models/cache/huggingface
      HUGGINGFACE_HUB_CACHE: /models/cache/huggingface
      VLLM_CACHE_ROOT: /models/cache/vllm
      HUGGING_FACE_HUB_TOKEN: ${HUGGING_FACE_HUB_TOKEN:-}
    volumes:
      - ./models:/models/cache          # Model cache (persists across restarts)
      - ./logs:/var/log/vllm           # Server logs
    command: [ ... vLLM serve arguments ... ]
    healthcheck:
      test: ["CMD", "curl", "-fsS", "http://localhost:11434/v1/models"]
      interval: 30s
      timeout: 10s
      retries: 10
      start_period: 120s
```

### Profiles

| Profile | Service | Use case |
|---------|---------|----------|
| `lite` | `qwen35moe-vllm` | 12 GB VRAM target with CPU offload |
| `full` | `qwen35moe-vllm-full` | Higher-performance hardware with more memory |

### vLLM Runtime Arguments

| Argument | Value | Purpose |
|----------|-------|---------|
| `--port` | `11434` | API server port |
| `--language-model-only` | (enabled) | Disables multimodal components to free memory |
| `--enforce-eager` | (enabled) | Disables torch.compile and CUDA graph capture |
| `--max-model-len` | `4096` | Maximum sequence length (tokens) |
| `--max-num-seqs` | `1` | Max concurrent sequences |
| `--kv-cache-dtype` | `fp8` | FP8 key-value cache for memory efficiency |
| `--attention-backend` | `flashinfer` | Fast attention implementation |
| `--gpu-memory-utilization` | `0.85` | Balances offload headroom with cache space |
| `--cpu-offload-gb` | `24` | CPU memory budget for model offload per GPU |
| `--cpu-offload-params` | `weight` | Only offloads weight tensors, leaving scale tensors on GPU |
| `--max-num-batched-tokens` | `2304` | Max tokens per batch |
| `--enable-chunked-prefill` | (enabled) | Reduces latency for long prompts |
| `--enable-prefix-caching` | (enabled) | Caches KV for repeated prefixes |
| `--enable-auto-tool-choice` | (enabled) | Auto-select tools in function calling |
| `--tool-call-parser` | `qwen3_xml` | Qwen3 XML-style tool call parsing |
| `--reasoning-parser` | `qwen3` | Qwen3 reasoning chain parsing |

The service also sets `TORCH_COMPILE_DISABLE=1` to force vLLM off the Torch compile path that can fail during CPU-offload startup.

The `full` profile uses the same model but relaxes the low-memory settings:

| Argument | Value | Purpose |
|----------|-------|---------|
| `--max-model-len` | `32768` | Larger context window |
| `--max-num-seqs` | `2` | Higher concurrency |
| `--max-num-batched-tokens` | `2304` | Larger batch size |
| `--gpu-memory-utilization` | `0.92` | Uses more GPU memory for throughput |

## Health Checks

### Container health check

The docker-compose health check queries the OpenAI-compatible models endpoint every 30 seconds:

```bash
# Check container health status
docker inspect --format='{{.State.Health.Status}}' qwen35moe-vllm

# Expected output: "healthy"
```

### API health check

```bash
# Quick health check (exit 0 = healthy)
curl -fsS http://localhost:11434/v1/models >/dev/null && echo "OK: vLLM is healthy"

# List available models
curl -sS http://localhost:11434/v1/models | jq '.data[].id'

# Full model info
curl -sS http://localhost:11434/v1/models | jq .
```

### Docker health check

```bash
docker compose logs -f | grep -i "healthy\|initialized\|started"
```

## Usage

### OpenAI-compatible API

The vLLM server exposes the standard OpenAI API surface. Any OpenAI SDK client works:

```python
from openai import OpenAI

client = OpenAI(base_url="http://localhost:11434/v1", api_key="not-needed")

response = client.chat.completions.create(
    model="nvidia/Qwen3.6-35B-A3B-NVFP4",
    messages=[{"role": "user", "content": "Explain the three-body problem."}],
    max_tokens=512,
    temperature=0.7,
)

print(response.choices[0].message.content)
```

### cURL example

```bash
curl -sS http://localhost:11434/v1/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "model": "nvidia/Qwen3.6-35B-A3B-NVFP4",
    "messages": [{"role": "user", "content": "What is a story graph?"}],
    "max_tokens": 256,
    "temperature": 0.7
  }'
```

### Integration with StoryOS Orchestrator

The orchestrator connects to vLLM via the `VLLM_URL` environment variable:

```bash
# In .lando.yml or .env
VLLM_URL=http://127.0.0.1:11434/v1   # external Docker service
VLLM_URL=http://host.docker.internal:11434/v1  # Docker Compose on macOS/Windows
```

## Directory Structure

```
llm/qwen35MOE/
├── Containerfile          # Docker image (vLLM base + curl)
├── docker-compose.yaml    # Service definition
├── .env                   # Hugging Face token (git-ignored)
├── .gitignore
├── README.md              # This file
├── docker/
│   └── entrypoint-with-log.sh   # Wrapper that logs to file
├── models/                # Model cache (bind-mounted, persists)
│   └── cache/
│       ├── huggingface/
│       └── vllm/
└── logs/                  # Server logs (bind-mounted)
    └── server.log
```

## Troubleshooting

### Model download fails with "Repository Not Found"

Your Hugging Face token may lack access. Verify:

```bash
curl -H "Authorization: Bearer $HUGGING_FACE_HUB_TOKEN" \
  https://huggingface.co/api/models/nvidia/Qwen3.6-35B-A3B-NVFP4
```

### Out of GPU memory

Reduce `--gpu-memory-utilization` (e.g. `0.75`) or `--max-model-len` (e.g. `32768`):

```yaml
command:
  - --gpu-memory-utilization
  - "0.75"
  - --max-model-len
  - "32768"
```

### Health check never becomes healthy

The model needs time to load on first startup (2–5 minutes). Check logs:

```bash
docker compose logs -f qwen35moe-vllm
```

Look for `Initializing engine` and `Startup complete` messages.

### Container won't start — no GPU detected

Ensure `nvidia-container-toolkit` is installed and `nvidia-smi` works on the host:

```bash
nvidia-smi
docker run --rm --gpus all nvidia/cuda:12.0-base nvidia-smi
```

## Cleanup

```bash
# Stop and remove containers (keeps models)
docker compose down

# Remove everything including models (re-download required)
docker compose down -v

# Clear logs
rm -f ./logs/*.log
```

## License

This configuration is part of StoryOS and follows the project's license.
The Qwen3.6-35B-A3B-NVFP4 model is subject to [NVIDIA's model license](https://huggingface.co/nvidia/Qwen3.6-35B-A3B-NVFP4).