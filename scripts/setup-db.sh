#!/bin/bash
# Database bootstrap helper for the World Graph Studio Lando environment.
# This script is intended to run after the MariaDB service is available.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP_ARCHIVE="$ROOT_DIR/scripts/backup.sql.gz"

if [[ ! -f "$BACKUP_ARCHIVE" ]]; then
  echo "Backup file not found: $BACKUP_ARCHIVE"
  exit 1
fi

if ! command -v lando >/dev/null 2>&1; then
  echo "Lando is required to import the database backup."
  exit 1
fi

echo "=== Bootstrapping World Graph Studio database ==="

(
  cd "$ROOT_DIR"
  lando db-import "$BACKUP_ARCHIVE"
)

echo "=== Database bootstrap complete ==="
