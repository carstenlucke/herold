#!/usr/bin/env bash
# Generate PNG images from PlantUML diagrams.
# Usage: ./scripts/generate-diagrams.sh [file.plantuml ...]
# Without arguments, converts all .plantuml files in docs/.

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DOCS_DIR="$PROJECT_ROOT/docs"
PLANTUML_JAR="$PROJECT_ROOT/scripts/.plantuml.jar"
PLANTUML_VERSION="1.2025.2"
PLANTUML_URL="https://github.com/plantuml/plantuml/releases/download/v${PLANTUML_VERSION}/plantuml-${PLANTUML_VERSION}.jar"

# Download PlantUML JAR if missing
if [[ ! -f "$PLANTUML_JAR" ]]; then
    echo "Downloading PlantUML v${PLANTUML_VERSION}..."
    curl -fsSL -o "$PLANTUML_JAR" "$PLANTUML_URL"
    echo "Saved to $PLANTUML_JAR"
fi

# Collect input files
if [[ $# -gt 0 ]]; then
    files=("$@")
else
    files=("$DOCS_DIR"/spec/*.plantuml)
fi

for f in "${files[@]}"; do
    if [[ ! -f "$f" ]]; then
        echo "Skipping: $f (not found)"
        continue
    fi
    echo "Generating: ${f%.plantuml}.png"
    java -jar "$PLANTUML_JAR" -tpng "$f"
done

echo "Done."
