#!/usr/bin/env bash
# Stop the Docker environment.
# Usage: ./scripts/stop.sh

set -euo pipefail
cd "$(dirname "$0")/.."

docker compose down
