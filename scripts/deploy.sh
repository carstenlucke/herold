#!/usr/bin/env bash
# Deploy to production via FTPS. Builds everything locally, then uploads.
# Requires: lftp, docker (for composer + npm build)
#
# FTP credentials are read from .env (FTP_HOST, FTP_USER, FTP_PASSWORD, FTP_BASE_PATH).
# Usage: ./scripts/deploy.sh

set -euo pipefail
cd "$(dirname "$0")/.."

# --- Load FTP credentials from .env ---

load_env_var() {
    local var="$1"
    local value
    value=$(grep -E "^${var}=" .env | head -1 | cut -d'=' -f2-)
    # Strip surrounding quotes if present
    value="${value%\"}"
    value="${value#\"}"
    value="${value%\'}"
    value="${value#\'}"
    echo "$value"
}

FTP_HOST=$(load_env_var FTP_HOST)
FTP_USER=$(load_env_var FTP_USER)
FTP_PASSWORD=$(load_env_var FTP_PASSWORD)
FTP_BASE_PATH=$(load_env_var FTP_BASE_PATH)

for var in FTP_HOST FTP_USER FTP_PASSWORD FTP_BASE_PATH; do
    if [[ -z "${!var}" ]]; then
        echo "Error: ${var} is not set in .env" >&2
        exit 1
    fi
done

echo "Target: ${FTP_USER}@${FTP_HOST}:${FTP_BASE_PATH}"

# --- Build ---

echo "Installing Composer dependencies (production)..."
docker compose exec -T app composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "Building frontend assets..."
docker compose exec -T node npm run build

if [[ ! -d "public/build" ]]; then
    echo "Error: public/build not found after frontend build" >&2
    exit 1
fi

echo "Build complete."

# --- Deploy ---

echo "Deploying via FTPS..."
export LFTP_PASSWORD="$FTP_PASSWORD"

# --- Safety guard: require deployment marker file in target directory ---
# Prevents destructive `mirror --delete` when FTP_BASE_PATH is misconfigured.
# Create the marker once after verifying the app root:
#   echo "herold deploy root" > .herold-deploy-root
# then upload it manually via FTP client.
echo "Verifying deployment marker in ${FTP_BASE_PATH}..."
if ! lftp -c "
  set ftp:ssl-force true
  set ssl:verify-certificate yes
  open --user \"$FTP_USER\" --env-password \"ftp://$FTP_HOST\"
  cd \"$FTP_BASE_PATH\"
  glob --exist .herold-deploy-root && exit 0 || exit 1
"; then
    echo "Error: deployment marker .herold-deploy-root not found in ${FTP_BASE_PATH}." >&2
    echo "  This is a safety check to prevent 'mirror --delete' from wiping the wrong directory." >&2
    echo "  If ${FTP_BASE_PATH} really is the Herold app root, upload an empty .herold-deploy-root file there and retry." >&2
    exit 1
fi

lftp -c "
  set ftp:ssl-force true
  set ssl:verify-certificate yes
  open --user \"$FTP_USER\" --env-password \"ftp://$FTP_HOST\"
  cd \"$FTP_BASE_PATH\"
  mkdir -p storage/framework/sessions
  mkdir -p storage/framework/cache/data
  mkdir -p storage/framework/views
  mkdir -p storage/logs
  mkdir -p storage/app/private/audio
  mkdir -p database/data
  mirror --reverse --delete --verbose \
    --exclude ^\.herold-deploy-root$ \
    --exclude ^\.git/ \
    --exclude ^\.github/ \
    --exclude ^\.claude/ \
    --exclude ^\.opencode/ \
    --exclude ^\.vite/ \
    --exclude ^node_modules/ \
    --exclude ^public/hot$ \
    --exclude ^\.env \
    --exclude ^database/data/ \
    --exclude ^storage/app/private/ \
    --exclude ^storage/logs/ \
    --exclude ^storage/framework/sessions/ \
    --exclude ^storage/framework/cache/ \
    --exclude ^tests/ \
    --exclude ^backups/ \
    --exclude ^Dockerfile$ \
    --exclude ^docker-compose\.yml$ \
    --exclude ^docker-entrypoint\.sh$ \
    --exclude ^package\.json$ \
    --exclude ^package-lock\.json$ \
    --exclude ^vite\.config\.ts$ \
    --exclude ^vitest\.config\.ts$ \
    --exclude ^phpunit\.xml$ \
    --exclude ^\.phpunit\.result\.cache$ \
    --exclude ^CLAUDE\.md$ \
    --exclude ^AGENTS\.md$ \
    --exclude ^DESIGN\.md$ \
    --exclude ^README\.md$ \
    --exclude ^adr/ \
    --exclude ^docs/ \
    --exclude ^prompts/ \
    --exclude ^poc-ui/ \
    --exclude ^icons/ \
    --exclude ^srs/ \
    --exclude ^sh/ \
    --exclude ^scripts/ \
    --exclude ^\.DS_Store$ \
    .
  bye
"

echo "Deployment complete."
