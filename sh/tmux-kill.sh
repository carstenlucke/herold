#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
WORKTREES_DIR="$ROOT_DIR/.claude/worktrees"
CWD="$(pwd)"

# Detect session type based on current working directory:
# Inside a worktree → kill the worktree session
# Otherwise         → kill the base project session
if [[ "$CWD" == "$WORKTREES_DIR"/* ]]; then
  # Extract worktree directory name (first path component after worktrees/)
  WT_REL="${CWD#$WORKTREES_DIR/}"
  WT_DIR_NAME="${WT_REL%%/*}"
  # Worktree dir uses "+" for "/", tmux session replaces "+", ".", ":" with "-"
  SESSION="herold-wt-${WT_DIR_NAME//[+.:]/-}"
else
  DIR_NAME="$(basename "$ROOT_DIR")"
  # Sanitize: tmux session names must not contain dots or colons
  SESSION="herold-${DIR_NAME//[.:]/-}"
fi

if tmux has-session -t "$SESSION" 2>/dev/null; then
  read -p "Session '$SESSION' wirklich beenden? [j/N] " answer
  if [[ "$answer" != "j" && "$answer" != "J" ]]; then
    echo "Abgebrochen."
    exit 0
  fi
  tmux kill-session -t "$SESSION"
  echo "Session '$SESSION' beendet."
else
  echo "Keine aktive Session '$SESSION' gefunden."
fi
