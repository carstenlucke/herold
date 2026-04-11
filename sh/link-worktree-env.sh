#!/usr/bin/env bash
# link-worktree-env.sh — Symlinkt .env.*.local und supabase/.temp/ vom Haupt-Repo
#                         in einen Git-Worktree. Idempotent.
# Aufruf: sh/link-worktree-env.sh [WORKTREE_PATH]
#         Ohne Argument wird das aktuelle Verzeichnis verwendet.

set -euo pipefail

INPUT="${1:-.}"

# Auf Git-Toplevel normalisieren (verhindert Fehlklassifikation aus Unterordnern)
WORKTREE_ROOT="$(git -C "$INPUT" rev-parse --show-toplevel 2>/dev/null)" || {
  echo "Fehler: $INPUT ist kein Git-Repository." >&2
  exit 1
}

# Haupt-Repo ermitteln via git-common-dir
GIT_COMMON_DIR="$(git -C "$WORKTREE_ROOT" rev-parse --git-common-dir)"
# git-common-dir kann relativ sein — in absoluten Pfad umwandeln
MAIN_REPO="$(cd "$WORKTREE_ROOT" && cd "$GIT_COMMON_DIR" && cd .. && pwd)"
if [ "$WORKTREE_ROOT" = "$MAIN_REPO" ]; then
  echo "Kein Worktree — bereits im Haupt-Repo. Nichts zu tun."
  exit 0
fi

CHANGED=0

# Symlink .env.*.local Dateien
for f in "$MAIN_REPO"/.env.*.local; do
  [ -f "$f" ] || continue
  NAME=$(basename "$f")
  LINK="$WORKTREE_ROOT/$NAME"
  if [ -L "$LINK" ]; then
    continue
  fi
  [ -f "$LINK" ] && rm "$LINK"
  ln -s "$f" "$LINK"
  echo "✓ $NAME verlinkt"
  CHANGED=1
done

# Symlink supabase/.temp/
if [ -d "$MAIN_REPO/supabase/.temp" ]; then
  LINK="$WORKTREE_ROOT/supabase/.temp"
  if [ ! -L "$LINK" ]; then
    if [ -d "$LINK" ]; then
      echo "Fehler: $LINK ist ein echtes Verzeichnis. Bitte manuell entfernen." >&2
      exit 1
    fi
    ln -s "$MAIN_REPO/supabase/.temp" "$LINK"
    echo "✓ supabase/.temp verlinkt"
    CHANGED=1
  fi
fi

if [ "$CHANGED" -eq 0 ]; then
  echo "Symlinks: alles bereits verlinkt."
fi
