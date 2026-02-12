#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cat <<'MSG'
Profios Stack Installer
Installs and configures:
- Nginx or Apache
- PHP + PHP-FPM (FastCGI)
- MySQL
- Redis
- Varnish
- Adminer
- Certbot (AutoSSL)
MSG

exec sudo bash "$ROOT_DIR/installer/install.sh" "$@"
