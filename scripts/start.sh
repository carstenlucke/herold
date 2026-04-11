#!/usr/bin/env bash
# Start the Docker environment.
# Usage: ./scripts/start.sh

set -euo pipefail
cd "$(dirname "$0")/.."

docker compose up -d "$@"
