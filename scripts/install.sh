#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "=== Installing system dependencies ==="
sudo apt update
sudo apt install -y poppler-utils tesseract-ocr tesseract-ocr-eng

echo "=== Installing Python dependencies ==="
pip install -r "$SCRIPT_DIR/requirements.txt"

echo "=== Done ==="
