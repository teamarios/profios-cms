#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 2 ]]; then
  echo "Usage: $0 <domain> <email>"
  exit 1
fi

DOMAIN="$1"
EMAIL="$2"

if command -v apt-get >/dev/null 2>&1; then
  sudo apt-get update
  sudo apt-get install -y certbot python3-certbot-apache
elif command -v dnf >/dev/null 2>&1; then
  sudo dnf -y install certbot python3-certbot-apache
else
  echo "Unsupported package manager. Install certbot manually."
  exit 1
fi

sudo certbot --apache -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect
if systemctl list-unit-files | grep -q '^certbot-renew.timer'; then
  sudo systemctl enable certbot-renew.timer
  sudo systemctl start certbot-renew.timer
else
  sudo systemctl enable certbot.timer
  sudo systemctl start certbot.timer
fi
sudo certbot renew --dry-run

echo "Let's Encrypt SSL configured for $DOMAIN"
