#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
APP_URL="$(grep -E '^APP_URL=' "${ENV_FILE}" | sed 's/APP_URL=//' | tr -d '"')"

check_url() {
  local url="$1"
  local name="$2"
  if curl -fsS --max-time 5 "$url" >/dev/null; then
    echo "[OK] $name"
  else
    echo "[FAIL] $name"
    return 1
  fi
}

check_url "${APP_URL}/healthz" "App health"
check_url "${APP_URL}/readyz" "App readiness"

echo "Monitoring checks completed"
