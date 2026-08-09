# Qwen coder for 32GB Nvidia
Want to run this in a docker compose, with a yaml file and minimal compose file.
We want to be able to persist the model downloads.
We want it to run as BYOK OpenAI interface that can work with VSCode Copilot Chat BYOK configuration on port 11434. TLS is not necessary for this version, but will be added in the future.

The standard VLLM is:
vllm serve nvidia/Qwen3.6-35B-A3B-NVFP4 \
  --trust-remote-code \
  --max-model-len 131072 \
  --max-num-seqs 4 \
  --kv-cache-dtype fp8 \
  --attention-backend flashinfer \
  --enable-chunked-prefill \
  --enable-prefix-caching \
  --max-num-batched-tokens 4096 \
  --gpu-memory-utilization 0.95 \
  --tensor-parallel-size 1 \
  --enable-auto-tool-choice \
  --tool-call-parser qwen3_xml \
  --reasoning-parser qwen3 \
  --mm-encoder-tp-mode data

## Build, Run, and Logs

Build the image:

```bash
docker compose -f docker-compose.yaml build
```

Start the service in the background:

```bash
docker compose -f docker-compose.yaml up -d
```

Build and start in one command:

```bash
docker compose -f docker-compose.yaml up -d --build
```

Pre-pull the model into the mounted cache before starting the server:

```bash
docker compose -f docker-compose.yaml run --rm --entrypoint python3 qwen35moe-vllm -c "import os; from huggingface_hub import snapshot_download; snapshot_download(repo_id='nvidia/Qwen3.6-35B-A3B-NVFP4', token=os.environ['HUGGING_FACE_HUB_TOKEN'])"
```

If Hugging Face returns `Repository Not Found`, confirm the token in `.env` has access to the gated or private model.

Check container status:

```bash
docker compose -f docker-compose.yaml ps
```

Stream logs:

```bash
docker compose -f docker-compose.yaml logs -f qwen35moe-vllm
```

Persistent model cache directory on host:

```bash
ls -lah models
```

Show downloaded model files on host:

```bash
find models -maxdepth 4 -type d -name "models--nvidia--Qwen3.6-27B-NVFP4"
find models -maxdepth 4 -type d -name "models--nvidia--Qwen3.6-35B-A3B-NVFP4"
```

Persistent log file on host:

```bash
tail -f logs/server.log
```

Show recent logs:

```bash
docker compose -f docker-compose.yaml logs --tail=200 qwen35moe-vllm
```

Show recent persisted file logs:

```bash
tail -n 200 logs/server.log
```

## Curl Checks

List served models (also confirms API is reachable):

```bash
curl -sS http://localhost:11434/v1/models
```

Return only model IDs with jq:

```bash
curl -sS http://localhost:11434/v1/models | jq -r '.data[].id'
```

Quick health check against the models endpoint:

```bash
curl -fsS http://localhost:11434/v1/models >/dev/null && echo "OK: vLLM is healthy"
```

Docker health status from compose:

```bash
docker compose -f docker-compose.yaml ps
```

## Troubleshooting

If logs show errors like `vllm: error: unrecognized arguments: ...` or `unrecognized arguments: serve`, the compose command is likely passing `serve` twice.

For `vllm/vllm-openai` images, the entrypoint already runs `vllm serve`, so in `docker-compose.yaml` the `command:` list must start with the model name, not `serve`.

If the API container is recreated and you still need previous startup traces, check `logs/server.log` on the host.

Model downloads are persisted in `./models` via bind mount. Do not use `docker compose down -v` if you want to keep named volumes from older setups.



