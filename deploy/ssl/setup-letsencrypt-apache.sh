#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 2 ]]; then
  echo "Usage: $0 <domain> <email>"
  exit 1
fi

DOMAIN="$1"
EMAIL="$2"

sudo apt-get update
sudo apt-get install -y certbot python3-certbot-apache
sudo certbot --apache -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
sudo certbot renew --dry-run

echo "Let's Encrypt SSL configured for $DOMAIN"
