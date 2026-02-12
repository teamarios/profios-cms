#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: bash remote-install.sh <package-url> [installer args...]"
  echo "Example: bash remote-install.sh https://example.com/profios-cms.tar.gz --mode docker"
  exit 1
fi

PACKAGE_URL="$1"
shift

WORKDIR="/tmp/profios-cms-install-$(date +%s)"
mkdir -p "$WORKDIR"

curl -fsSL "$PACKAGE_URL" -o "$WORKDIR/package.tar.gz"
tar -xzf "$WORKDIR/package.tar.gz" -C "$WORKDIR"

if [[ ! -f "$WORKDIR/installer/install.sh" ]]; then
  echo "installer/install.sh not found in package"
  exit 1
fi

sudo bash "$WORKDIR/installer/install.sh" "$@"
