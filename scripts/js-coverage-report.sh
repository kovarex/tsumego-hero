#!/usr/bin/env bash
set -euo pipefail

# Generates a JS/TS coverage report from per-page Istanbul snapshots.
#
# Snapshots are written to /tmp/coverage/js-raw-*.json by Browser::captureJsCoverage()
# during the CI coverage run (TEST_ENVIRONMENT=github-ci + VITE_COVERAGE build).
# nyc report merges them into coverage/frontend/ (HTML + Clover + summary).

RAW_DIR=/tmp/coverage/js-raw
OUT_DIR=coverage/frontend

if [ ! -d /tmp/coverage ]; then
	echo "No /tmp/coverage: skipping JS coverage report."
	exit 0
fi

mkdir -p "$RAW_DIR" "$OUT_DIR"

# Move per-page snapshots into a directory nyc can scan.
shopt -s nullglob
cp /tmp/coverage/js-raw-*.json "$RAW_DIR/" 2>/dev/null || true

if [ -z "$(ls -A "$RAW_DIR")" ]; then
	echo "No JS coverage snapshots found; skipping."
	exit 0
fi

echo "Reporting on $(ls "$RAW_DIR" | wc -l) JS coverage snapshots..."

npx nyc report \
	--temp-dir="$RAW_DIR" \
	--reporter=html \
	--reporter=clover \
	--reporter=json-summary \
	--report-dir="$OUT_DIR" \
	--cwd="$PWD"

echo "JS coverage report written to $OUT_DIR"
