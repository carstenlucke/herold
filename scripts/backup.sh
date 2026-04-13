#!/usr/bin/env bash
# Create a timestamped backup of the SQLite database, audio recordings, and .env file.
# Output: backups/herold-backup-YYYYMMDD-HHMMSS.zip
# Usage: ./scripts/backup.sh

set -euo pipefail
cd "$(dirname "$0")/.."

TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR="backups"
BACKUP_FILE="${BACKUP_DIR}/herold-backup-${TIMESTAMP}.zip"

mkdir -p "$BACKUP_DIR"

FILES_TO_BACKUP=()

# SQLite database (use a safe copy to avoid locking issues)
DB_FILE="database/data/database.sqlite"
DB_SNAPSHOT="database/data/database-backup.sqlite"
if [[ -f "$DB_FILE" ]]; then
    if ! command -v sqlite3 &>/dev/null; then
        echo "Error: sqlite3 is required for a consistent backup (WAL-safe). Install it and retry." >&2
        exit 1
    fi
    sqlite3 "$DB_FILE" ".backup '${DB_SNAPSHOT}'"
    FILES_TO_BACKUP+=("$DB_SNAPSHOT")
else
    echo "Warning: Database file not found at ${DB_FILE}" >&2
fi

# Audio recordings
AUDIO_DIR="storage/app/private/audio"
if [[ -d "$AUDIO_DIR" ]] && [[ -n "$(ls -A "$AUDIO_DIR" 2>/dev/null)" ]]; then
    FILES_TO_BACKUP+=("$AUDIO_DIR")
else
    echo "Warning: No audio files found in ${AUDIO_DIR}" >&2
fi

# Environment file (contains API keys, app key, etc.)
if [[ -f ".env" ]]; then
    FILES_TO_BACKUP+=(".env")
else
    echo "Warning: .env file not found" >&2
fi

if [[ ${#FILES_TO_BACKUP[@]} -eq 0 ]]; then
    echo "Error: Nothing to back up." >&2
    exit 1
fi

zip -r -q "$BACKUP_FILE" "${FILES_TO_BACKUP[@]}"

# Clean up database snapshot
rm -f "$DB_SNAPSHOT"

SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
echo "Backup created: ${BACKUP_FILE} (${SIZE})"
