#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
BACKUP_ROOT="${ROOT_DIR}/storage/backups"
LATEST_JSON="${BACKUP_ROOT}/latest.json"

if [[ ! -f "${ENV_FILE}" || ! -f "${LATEST_JSON}" ]]; then
  echo "Missing .env or backup manifest"
  exit 1
fi

source <(grep -E '^(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=' "${ENV_FILE}" | sed 's/"//g')
BACKUP_PATH="$(sed -n 's/.*"path"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "${LATEST_JSON}")"
DUMP_FILE="${BACKUP_PATH}/db.sql.gz"

if [[ ! -f "${DUMP_FILE}" ]]; then
  echo "Database dump not found in latest backup"
  exit 1
fi

TEST_DB="${DB_DATABASE}_restore_test_$(date +%s)"

MYSQL_PWD="${DB_PASSWORD:-}" mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME}" -e "CREATE DATABASE \`${TEST_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gzip -dc "${DUMP_FILE}" | MYSQL_PWD="${DB_PASSWORD:-}" mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME}" "${TEST_DB}"
MYSQL_PWD="${DB_PASSWORD:-}" mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME}" -e "SELECT COUNT(*) AS user_count FROM \`${TEST_DB}\`.users;"
MYSQL_PWD="${DB_PASSWORD:-}" mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME}" -e "DROP DATABASE \`${TEST_DB}\`;"

echo "Restore smoke test passed"
