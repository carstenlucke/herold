#!/usr/bin/env bash
# Generate PNG images from PlantUML diagrams.
# Usage: ./scripts/generate-diagrams.sh [file.plantuml ...]
# Without arguments, converts all .plantuml files in:
#   - docs/spec/diagrams/  → docs/spec/diagrams-png/
#   - docs/arch/diagrams/  → docs/arch/diagrams-png/
# Each source file is rendered to the diagrams-png/ sibling of its
# source directory.

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIAGRAM_ROOTS=(
    "$PROJECT_ROOT/docs/spec/diagrams"
    "$PROJECT_ROOT/docs/arch/diagrams"
)
PLANTUML_JAR="$PROJECT_ROOT/scripts/.plantuml.jar"
PLANTUML_VERSION="1.2025.2"
PLANTUML_URL="https://github.com/plantuml/plantuml/releases/download/v${PLANTUML_VERSION}/plantuml-${PLANTUML_VERSION}.jar"

# Download PlantUML JAR if missing
if [[ ! -f "$PLANTUML_JAR" ]]; then
    echo "Downloading PlantUML v${PLANTUML_VERSION}..."
    curl -fsSL -o "$PLANTUML_JAR" "$PLANTUML_URL"
    echo "Saved to $PLANTUML_JAR"
fi

# Map a source .plantuml file path to its target PNG output directory
# (sibling diagrams-png/ next to the source's diagrams/ directory).
out_dir_for() {
    local src_file="$1"
    echo "$(dirname "$src_file")-png"
}

# Collect input files
if [[ $# -gt 0 ]]; then
    files=("$@")
else
    files=()
    for root in "${DIAGRAM_ROOTS[@]}"; do
        if [[ -d "$root" ]]; then
            for f in "$root"/*.plantuml; do
                [[ -f "$f" ]] && files+=("$f")
            done
        fi
    done
fi

for f in "${files[@]}"; do
    if [[ ! -f "$f" ]]; then
        echo "Skipping: $f (not found)"
        continue
    fi
    out_dir="$(out_dir_for "$f")"
    mkdir -p "$out_dir"
    echo "Generating: $out_dir/$(basename "${f%.plantuml}").png"
    java -jar "$PLANTUML_JAR" -tpng -o "$out_dir" "$f"
done

echo "Done."
