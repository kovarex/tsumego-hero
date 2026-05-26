#!/usr/bin/env bash
# Start the Vite dev watcher as a background process.
# Works on Windows (Git Bash), macOS, and Linux.
cd "$(dirname "$0")/.."

if command -v powershell &>/dev/null; then
	# On Windows: launch as a hidden detached Windows process via PowerShell.
	powershell -NonInteractive -Command "Start-Process (Get-Command pnpm).Source -ArgumentList 'dev' -WorkingDirectory '$(pwd -W)' -WindowStyle Hidden -PassThru | Select-Object -ExpandProperty Id | Set-Content -Path '.ddev/.pnpm-dev.pid' -Encoding ascii"
else
	# On Linux/macOS: standard background job; process survives when bash exits.
	pnpm dev > /dev/null 2>&1 &
	echo $! > .ddev/.pnpm-dev.pid
fi
