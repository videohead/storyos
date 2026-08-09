#!/bin/bash
set -euo pipefail

BASE_DIR="/opt/comfyui"
MODELS_DIR="$BASE_DIR/models"
CACHE_DIR="$BASE_DIR/cache"

mkdir -p "$MODELS_DIR/checkpoints" \
  "$MODELS_DIR/loras" \
  "$MODELS_DIR/vae" \
  "$MODELS_DIR/clip" \
  "$MODELS_DIR/embeddings" \
  "$CACHE_DIR/huggingface" \
  "$CACHE_DIR/torch" \
  "$CACHE_DIR/xdg" \
  "$BASE_DIR/llm"

has_model_files() {
  find "$MODELS_DIR" -type f \( -name '*.safetensors' -o -name '*.ckpt' -o -name '*.pt' -o -name '*.pth' -o -name '*.bin' \) 2>/dev/null | grep -q .
}

download_spec() {
  local spec="$1"
  local target_dir="$2"
  local filename=""
  local repo_id=""
  local dest_path=""

  mkdir -p "$target_dir"

  if [[ "$spec" =~ ^https?:// ]]; then
    filename="$(basename "$spec")"
    dest_path="$target_dir/$filename"
    if [ -f "$dest_path" ]; then
      echo "Already present: $dest_path"
      return 0
    fi
    echo "Downloading $spec -> $dest_path"
    curl -fsSL "$spec" -o "$dest_path"
    return 0
  fi

  if [[ "$spec" == *":"* ]]; then
    repo_id="${spec%%:*}"
    filename="${spec#*:}"
  else
    repo_id="$spec"
  fi

  if [ -z "$filename" ]; then
    dest_path="$target_dir"
    echo "Downloading Hugging Face repository $repo_id to $dest_path"
    python3 - <<'PY' "$repo_id" "$dest_path"
import os
import sys
from huggingface_hub import snapshot_download
repo_id = sys.argv[1]
dest_dir = sys.argv[2]
os.makedirs(dest_dir, exist_ok=True)
snapshot_download(repo_id=repo_id, local_dir=dest_dir, local_dir_use_symlinks=False)
PY
    return 0
  fi

  dest_path="$target_dir/$filename"
  if [ -f "$dest_path" ]; then
    echo "Already present: $dest_path"
    return 0
  fi

  echo "Downloading $repo_id/$filename -> $dest_path"
  python3 - <<'PY' "$repo_id" "$filename" "$dest_path"
import os
import sys
from huggingface_hub import hf_hub_download
repo_id = sys.argv[1]
filename = sys.argv[2]
dest_path = sys.argv[3]
os.makedirs(os.path.dirname(dest_path), exist_ok=True)
hf_hub_download(repo_id=repo_id, filename=filename, local_dir=os.path.dirname(dest_path), local_dir_use_symlinks=False)
PY
}

if ! has_model_files; then
  echo "No model files found in $MODELS_DIR; checking for auto-download configuration."
  if [ -n "${COMFYUI_DOWNLOAD_MODELS:-}" ]; then
    IFS=',' read -r -a specs <<< "$COMFYUI_DOWNLOAD_MODELS"
    for spec in "${specs[@]}"; do
      spec="${spec//[[:space:]]/}"
      if [ -n "$spec" ]; then
        download_spec "$spec" "$MODELS_DIR/checkpoints"
      fi
    done
  elif [ -n "${COMFYUI_DOWNLOAD_MODEL:-}" ]; then
    download_spec "$COMFYUI_DOWNLOAD_MODEL" "$MODELS_DIR/checkpoints"
  else
    echo "No model files found and no auto-download target configured."
    echo "Set COMFYUI_DOWNLOAD_MODELS to a comma-separated list of Hugging Face repo ids or URLs to auto-download missing assets."
  fi
else
  echo "Found existing model files in $MODELS_DIR."
fi

if [ -z "${COMFYUI_SKIP_STARTUP:-}" ]; then
  exec python3 main.py --listen 0.0.0.0 "$@"
fi
