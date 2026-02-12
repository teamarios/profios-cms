#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="$ROOT_DIR/dist"
VERSION="${1:-$(date +%Y%m%d-%H%M%S)}"
PACKAGE_NAME="profios-cms-$VERSION"
PACKAGE_PATH="$DIST_DIR/$PACKAGE_NAME.tar.gz"

mkdir -p "$DIST_DIR"

tar \
  --exclude='.git' \
  --exclude='dist' \
  --exclude='storage/cache/*' \
  --exclude='storage/logs/*' \
  --exclude='.env' \
  -czf "$PACKAGE_PATH" \
  -C "$ROOT_DIR" .

echo "Package created: $PACKAGE_PATH"
