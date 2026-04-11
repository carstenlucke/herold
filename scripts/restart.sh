#!/usr/bin/env bash
# Restart the Docker environment.
# Usage: ./scripts/restart.sh

set -euo pipefail
cd "$(dirname "$0")/.."

docker compose down
docker compose up -d --build
