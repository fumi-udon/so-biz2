#!/usr/bin/env bash
# Creates the dedicated PHPUnit / Sail test database (see .env.testing).
# Runs once on first MySQL container initialization (fresh volume).
set -euo pipefail

mysql --user=root --password="${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS soya_biz2_test
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci;
EOSQL

if [ -n "${MYSQL_USER:-}" ]; then
    mysql --user=root --password="${MYSQL_ROOT_PASSWORD}" <<-EOSQL
        GRANT ALL PRIVILEGES ON \`soya_biz2_test\`.* TO '${MYSQL_USER}'@'%';
        FLUSH PRIVILEGES;
EOSQL
fi
