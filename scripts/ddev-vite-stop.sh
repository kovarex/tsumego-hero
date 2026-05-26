#!/usr/bin/env bash
# Stop the Vite dev watcher started by ddev-vite-start.sh.
cd "$(dirname "$0")/.."

if [ -f .ddev/.pnpm-dev.pid ]; then
	PID="$(cat .ddev/.pnpm-dev.pid)"
	if command -v powershell &>/dev/null; then
		powershell -NonInteractive -Command "Stop-Process -Id $PID -Force -ErrorAction SilentlyContinue"
	else
		kill "$PID" 2>/dev/null || true
	fi
	rm .ddev/.pnpm-dev.pid
fi
