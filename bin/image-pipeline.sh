#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_DIR="${ROOT_DIR}/storage/uploads/originals"
OUT_DIR="${ROOT_DIR}/storage/uploads/variants"
WIDTHS=(480 768 1200)

mkdir -p "${OUT_DIR}"

if ! command -v magick >/dev/null 2>&1 && ! command -v convert >/dev/null 2>&1; then
  echo "ImageMagick not found (magick/convert)."
  exit 1
fi

CMD="magick"
if ! command -v magick >/dev/null 2>&1; then
  CMD="convert"
fi

shopt -s nullglob
for file in "${SOURCE_DIR}"/*.{jpg,jpeg,png,JPG,JPEG,PNG}; do
  base="$(basename "${file}")"
  name="${base%.*}"
  for w in "${WIDTHS[@]}"; do
    "${CMD}" "${file}" -strip -resize "${w}>" -quality 82 "${OUT_DIR}/${name}-${w}.jpg"
    "${CMD}" "${file}" -strip -resize "${w}>" -quality 80 "${OUT_DIR}/${name}-${w}.webp"
    if "${CMD}" -list format | grep -q AVIF; then
      "${CMD}" "${file}" -strip -resize "${w}>" -quality 60 "${OUT_DIR}/${name}-${w}.avif"
    fi
  done
done

echo "Image variants generated in ${OUT_DIR}"
