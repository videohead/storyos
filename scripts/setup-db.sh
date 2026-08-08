#!/bin/bash
# Database bootstrap helper for the StoryOS Lando environment.
# This script is intended to run after the MariaDB service is available.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP_SQL="$ROOT_DIR/scripts/backup.sql"
DB_CONTAINER_NAME="storyos_database_1"

if [[ ! -f "$BACKUP_SQL" ]]; then
  echo "Backup file not found: $BACKUP_SQL"
  exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -qx "$DB_CONTAINER_NAME"; then
  echo "MariaDB container '$DB_CONTAINER_NAME' is not running yet."
  echo "Run this script again after the database service is up."
  exit 1
fi

echo "=== Bootstrapping StoryOS database ==="

docker exec "$DB_CONTAINER_NAME" bash -lc "
  mysql -uroot -proot -e \"CREATE DATABASE IF NOT EXISTS wordpress CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\"
  mysql -uroot -proot -e \"CREATE USER IF NOT EXISTS 'wordpress'@'%' IDENTIFIED BY 'wordpress'; GRANT ALL PRIVILEGES ON wordpress.* TO 'wordpress'@'%'; FLUSH PRIVILEGES;\"ILEGES;\"
"

docker cp "$BACKUP_SQL" "$DB_CONTAINER_NAME":/tmp/backup.sql

docker exec "$DB_CONTAINER_NAME" bash -lc "
  mysql -uwordpress -pwordpress wordpress < /tmp/backup.sql
"

echo "=== Database bootstrap complete ==="
