#!/usr/bin/env bash
set -euo pipefail

AGENT="${1:-claude}"
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DIR_NAME="$(basename "$ROOT_DIR")"
# Sanitize: tmux session names must not contain dots or colons
SESSION="herold-${DIR_NAME//[.:]/-}"

# If session already exists, just attach and exit
if tmux has-session -t "$SESSION" 2>/dev/null; then
  exec tmux attach-session -t "$SESSION"
fi

# Create session (pane 0)
tmux new-session -d -s "$SESSION" -c "$ROOT_DIR"

# Split: new pane on top (20%), original pane stays at bottom (80%)
# After split: 0=top, 1=bottom
tmux split-window -v -b -l '20%' -c "$ROOT_DIR"

# Split top pane horizontally: 0=top-left, 1=top-right, 2=bottom
tmux select-pane -t "$SESSION:0.0"
tmux split-window -h -l '70%' -c "$ROOT_DIR"

# Panes: 0=top-left, 1=top-right, 2=bottom
# Start agent in the bottom pane
tmux send-keys -t "$SESSION:0.2" "$AGENT" Enter

# Select the bottom pane (agent)
tmux select-pane -t "$SESSION:0.2"

# Attach
tmux attach-session -t "$SESSION"
