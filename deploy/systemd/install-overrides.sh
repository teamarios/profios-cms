#!/usr/bin/env bash
set -euo pipefail

OVERRIDE_SOURCE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/override-template.conf"
SERVICES=(nginx php-fpm apache2 httpd mysql mariadb redis varnish)

for svc in "${SERVICES[@]}"; do
  target_dir="/etc/systemd/system/${svc}.service.d"
  if systemctl status "${svc}" >/dev/null 2>&1; then
    sudo mkdir -p "${target_dir}"
    sudo cp "${OVERRIDE_SOURCE}" "${target_dir}/override.conf"
    echo "Applied restart override to ${svc}"
  fi
done

sudo systemctl daemon-reload
for svc in "${SERVICES[@]}"; do
  if systemctl status "${svc}" >/dev/null 2>&1; then
    sudo systemctl restart "${svc}"
  fi
done

echo "Restart policies installed."
