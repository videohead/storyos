#!/bin/bash
# Database setup script for Lando environment
# Run with: ./scripts/setup-db.sh

set -e

echo "=== WordPress Comfy Database Setup ==="

# Create database user if not exists
lando mariadb -u root -proot <<'SQL'
CREATE USER IF NOT EXISTS 'wordpress'@'%' IDENTIFIED BY 'wordpress';
GRANT ALL PRIVILEGES ON *.* TO 'wordpress'@'%';
FLUSH PRIVILEGES;
SQL

# Create database if not exists
lando mariadb -u root -proot -e "CREATE DATABASE IF NOT EXISTS wordpress CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 || true

# Import backup.sql (skip if already imported)
TABLE_COUNT=$(lando mariadb -u wordpress -pwordpress -e "SELECT COUNT(*) FROM wordpress.information_schema.tables WHERE table_schema='wordpress';" 2>/dev/null | tail -1)
if [ "$TABLE_COUNT" = "0" ] || [ -z "$TABLE_COUNT" ]; then
    echo "Importing backup.sql..."
    lando mariadb -u root -proot wordpress < /opt/wp-comfy/backup.sql 2>&1
    echo "Backup imported successfully."
else
    echo "Database already has $TABLE_COUNT tables. Skipping import."
fi

# Update WordPress URLs to Lando site
lando mariadb -u wordpress -pwordpress <<'SQL'
UPDATE wordpress.wp_options SET option_value = 'https://storyos.lndo.site' WHERE option_name IN ('siteurl', 'home');
UPDATE wordpress.wp_options SET option_value = 'https://storyos.lndo.site' WHERE option_value LIKE 'http://%';
SQL

echo "=== Database setup complete ==="
echo "WordPress URL: https://storyos.lndo.site"
echo ""
echo "After lando rebuild, run: ./scripts/setup-db.sh"
