#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

# Worktree name: use parameter or generate random name
if [[ $# -ge 1 ]]; then
  WORKTREE_NAME="$1"
  if [[ ! "$WORKTREE_NAME" =~ ^[a-zA-Z0-9/_.-]+$ ]]; then
    echo "Error: Invalid worktree name '$WORKTREE_NAME'. Use only letters, digits, '-', '_', '.', and '/'." >&2
    exit 1
  fi
else
  ADJECTIVES=(
    swift calm bold quiet bright sharp keen warm cool fast
    brave clear crisp dense eager fair glad grand harsh icy
    jolly kind lean light merry neat pale prime raw rich
    safe slim soft stark tame thin vast vivid wide wise
    agile brisk deep dry even flat free fresh full gold
  )
  NOUNS=(
    falcon river tiger spark cedar arrow flint maple ridge pine
    amber basin cliff delta ember forge grove haven ivory knoll
    larch marsh nexus oasis pearl quartz reef shore titan vale
    aspen birch crest dune fjord glade heron inlet jade kelp
    lotus mango north onyx prism rapid steel thorn umbra vortex
  )
  # Generate a random worktree name and ensure it does not collide
  # with an existing worktree directory or tmux session.
  while true; do
    ADJ=${ADJECTIVES[$RANDOM % ${#ADJECTIVES[@]}]}
    NOUN=${NOUNS[$RANDOM % ${#NOUNS[@]}]}
    CANDIDATE_NAME="${ADJ}-${NOUN}"
    CANDIDATE_DIR="$ROOT_DIR/.claude/worktrees/$CANDIDATE_NAME"
    CANDIDATE_SESSION="herold-wt-${CANDIDATE_NAME//[.:]/-}"

    if [[ ! -d "$CANDIDATE_DIR" ]] && ! tmux has-session -t "$CANDIDATE_SESSION" 2>/dev/null; then
      WORKTREE_NAME="$CANDIDATE_NAME"
      break
    fi
  done
fi

# Name derivation rules for worktrees:
#
#   claude --worktree uses the original name (e.g. "foo/baz").
#   The worktree directory replaces "/" with "+" (e.g. ".claude/worktrees/foo+baz")
#   to avoid nested directories under .claude/worktrees/.
#   The tmux session name uses the "herold-wt-" prefix and replaces "/", ".",
#   and ":" with "-" (e.g. "herold-wt-foo-baz"). The "-wt-" infix separates
#   worktree sessions from the base project session (which uses "herold-<dir>").
#   tmux does not allow "." or ":" in session names.
#
#   "+" is intentionally forbidden in input names to avoid ambiguity
#   with the "/" → "+" directory mapping.
WORKTREE_DIR_NAME="${WORKTREE_NAME//\//+}"
WORKTREE_DIR="$ROOT_DIR/.claude/worktrees/$WORKTREE_DIR_NAME"
SESSION="herold-wt-${WORKTREE_NAME//[\/.::]/-}"

# If session already exists, just attach and exit
if tmux has-session -t "$SESSION" 2>/dev/null; then
  exec tmux attach-session -t "$SESSION"
fi

# Ensure worktree directory exists before pane setup — claude --worktree
# will populate it later, but the panes need it as working directory now.
mkdir -p "$WORKTREE_DIR"
PANE_DIR="$WORKTREE_DIR"

# Create session (pane 0)
tmux new-session -d -s "$SESSION" -c "$PANE_DIR"

# Split: new pane on top (20%), original pane stays at bottom (80%)
# After split: 0=top, 1=bottom
tmux split-window -v -b -l '20%' -c "$PANE_DIR"

# Split top pane horizontally: 0=top-left, 1=top-right, 2=bottom
tmux select-pane -t "$SESSION:0.0"
tmux split-window -h -l '70%' -c "$PANE_DIR"

# Panes: 0=top-left, 1=top-right, 2=bottom
# Start claude with worktree in the bottom pane
tmux send-keys -t "$SESSION:0.2" "claude --worktree $WORKTREE_NAME" Enter

# Select the bottom pane (claude)
tmux select-pane -t "$SESSION:0.2"

# Attach
tmux attach-session -t "$SESSION"
