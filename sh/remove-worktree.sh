#!/usr/bin/env bash
# remove-worktree.sh — Entfernt einen Git-Worktree aus .claude/worktrees/.
# Aufruf: ./sh/remove-worktree.sh <WT_NAME>
# Muss aus dem Repo-Root ausgeführt werden.

set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Aufruf: $0 <WT_NAME>" >&2
  exit 1
fi

WT_NAME="$1"
REPO_ROOT="$(git rev-parse --show-toplevel)"

if [[ "$(pwd)" != "$REPO_ROOT" ]]; then
  echo "Fehler: Muss aus dem Repo-Root ausgeführt werden ($REPO_ROOT)." >&2
  exit 1
fi

git worktree remove ".claude/worktrees/$WT_NAME"
