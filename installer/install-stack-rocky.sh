#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cat <<'MSG'
Profios Rocky Stack Installer
Installs and configures:
- Nginx or Apache or Hybrid
- PHP + PHP-FPM
- MariaDB
- Redis
- Varnish
- Adminer
- Certbot (AutoSSL)
MSG

exec sudo bash "$ROOT_DIR/installer/install-rocky.sh" "$@"
