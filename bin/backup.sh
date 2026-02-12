#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
BACKUP_ROOT="${ROOT_DIR}/storage/backups"
NOW="$(date +%Y%m%d-%H%M%S)"
TARGET_DIR="${BACKUP_ROOT}/${NOW}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "Missing .env file"
  exit 1
fi

source <(grep -E '^(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=' "${ENV_FILE}" | sed 's/"//g')

mkdir -p "${TARGET_DIR}"
cp "${ENV_FILE}" "${TARGET_DIR}/env.backup"

if [[ -d "${ROOT_DIR}/storage/uploads" ]]; then
  tar -czf "${TARGET_DIR}/uploads.tar.gz" -C "${ROOT_DIR}/storage" uploads
fi

if command -v mysqldump >/dev/null 2>&1; then
  MYSQL_PWD="${DB_PASSWORD:-}" mysqldump -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME}" --single-transaction --quick --routines --triggers "${DB_DATABASE}" | gzip -9 > "${TARGET_DIR}/db.sql.gz"
else
  echo "mysqldump is not installed" >&2
  exit 1
fi

cat > "${TARGET_DIR}/manifest.json" <<JSON
{
  "created_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "path": "${TARGET_DIR}",
  "has_uploads": $( [[ -f "${TARGET_DIR}/uploads.tar.gz" ]] && echo true || echo false ),
  "has_db_dump": $( [[ -f "${TARGET_DIR}/db.sql.gz" ]] && echo true || echo false )
}
JSON

cp "${TARGET_DIR}/manifest.json" "${BACKUP_ROOT}/latest.json"
find "${BACKUP_ROOT}" -mindepth 1 -maxdepth 1 -type d -mtime +"${RETENTION_DAYS}" -exec rm -rf {} +

echo "Backup created at ${TARGET_DIR}"
