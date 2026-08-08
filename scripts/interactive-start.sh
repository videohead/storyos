#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

prompt_yes_no() {
  local prompt="$1"
  local response=""
  while true; do
    read -r -p "$prompt [y/N] " response
    case "${response:-n}" in
      [Yy]|[Yy][Ee][Ss])
        return 0
        ;;
      [Nn]|"")
        return 1
        ;;
      *)
        echo "Please answer yes or no."
        ;;
    esac
  done
}

ENABLE_VLLM=false
ENABLE_COMFYUI=false

if prompt_yes_no "Start vLLM?"; then
  ENABLE_VLLM=true
fi

if prompt_yes_no "Start ComfyUI?"; then
  ENABLE_COMFYUI=true
fi

COMPOSE_ARGS=( -f docker-compose.yml up -d )
if [[ "$ENABLE_VLLM" == true ]]; then
  COMPOSE_ARGS+=( vllm llm-agents )
fi
if [[ "$ENABLE_COMFYUI" == true ]]; then
  COMPOSE_ARGS+=( comfyui )
fi

echo "Starting StoryOS with optional services: vLLM=$ENABLE_VLLM, ComfyUI=$ENABLE_COMFYUI"
docker compose "${COMPOSE_ARGS[@]}"
