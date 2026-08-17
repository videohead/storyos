#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COMFY_DIR="$(dirname "$ROOT_DIR")/ComfyUI"
SMALLMOE_DIR="$(dirname "$ROOT_DIR")/smallMOE"

log() {
  printf '\n[%s] %s\n' "$(date '+%H:%M:%S')" "$*"
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "ERROR: Required command not found: $1" >&2
    exit 1
  fi
}

wait_for_http() {
  local url="$1"
  local label="$2"
  local attempts=0
  local max_attempts=60

  while (( attempts < max_attempts )); do
    if curl -fsS -o /dev/null "$url" >/dev/null 2>&1; then
      log "$label is reachable at $url"
      return 0
    fi
    attempts=$((attempts + 1))
    sleep 2
  done

  echo "ERROR: $label did not become reachable at $url" >&2
  return 1
}

require_cmd docker
require_cmd curl
require_cmd lando

log "Starting ComfyUI Docker stack"
cd "$COMFY_DIR"
docker compose up -d --build
wait_for_http "http://localhost:8188/" "ComfyUI"

log "Starting smallMOE Docker stack"
cd "$SMALLMOE_DIR"
docker compose up -d
wait_for_http "http://localhost:11434/v1/models" "smallMOE LLM"

log "Starting StoryOS Lando stack"
cd "$ROOT_DIR"
lando start
lando info

log "All required services were launched and verified successfully."
log "ComfyUI: http://localhost:8188/"
log "smallMOE LLM: http://localhost:11434/v1/models"
log "StoryOS: check 'lando info' for the local WordPress URLs"
