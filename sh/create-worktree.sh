#!/usr/bin/env bash
# create-worktree.sh — Erstellt einen Git-Worktree unter .claude/worktrees/
#                       und verlinkt .env-Dateien + supabase/.temp.
#                       Falls der Worktree bereits existiert, wird nur verlinkt.
# Aufruf: sh/create-worktree.sh <WT_NAME>

set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Aufruf: $0 <WT_NAME>" >&2
  exit 1
fi

WT_NAME="$1"
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# #5: WT_NAME validieren — kein Path Traversal, keine absoluten Pfade
if [[ "$WT_NAME" == *..* || "$WT_NAME" == /* ]]; then
  echo "Fehler: Ungueltiger Worktree-Name '$WT_NAME'." >&2
  exit 1
fi

# #2: Elternverzeichnis sicherstellen
mkdir -p "$ROOT_DIR/.claude/worktrees"

WT_DIR="$ROOT_DIR/.claude/worktrees/$WT_NAME"

if [[ -d "$WT_DIR" ]]; then
  echo "Worktree existiert bereits: $WT_DIR"
else
  # #1: Branch erstellen oder auschecken
  if git -C "$ROOT_DIR" show-ref --verify --quiet "refs/heads/$WT_NAME"; then
    git -C "$ROOT_DIR" worktree add "$WT_DIR" "$WT_NAME"
  else
    git -C "$ROOT_DIR" worktree add -b "$WT_NAME" "$WT_DIR"
  fi
  echo "✓ Worktree erstellt: $WT_DIR"
fi

"$SCRIPT_DIR/link-worktree-env.sh" "$WT_DIR"
