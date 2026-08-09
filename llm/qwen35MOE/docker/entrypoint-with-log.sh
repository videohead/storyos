#!/usr/bin/env bash
set -euo pipefail

LOG_DIR="/var/log/vllm"
LOG_FILE="${LOG_DIR}/server.log"

mkdir -p "${LOG_DIR}"

# Persist full server output while preserving process exit status.
vllm serve "$@" 2>&1 | tee -a "${LOG_FILE}"
exit "${PIPESTATUS[0]}"
