#!/usr/bin/env php
<?php

/**
 * Install git hooks script
 * Copies pre-commit hook to .git/hooks/ and ensures proper line endings and permissions
 */

// Ensure .git/hooks directory exists
if (!file_exists('.git/hooks'))
	mkdir('.git/hooks', 0755, true);

// Read the pre-commit hook
$sourceFile = '.githooks/pre-commit';
$targetFile = '.git/hooks/pre-commit';

if (!file_exists($sourceFile))
{
	echo "Error: Source file $sourceFile not found!\n";
	exit(1);
}

$content = file_get_contents($sourceFile);

// Normalize line endings to LF (Unix style)
$content = str_replace("\r\n", "\n", $content);

// Write to target
file_put_contents($targetFile, $content);

// Make executable
chmod($targetFile, 0755);

echo "Git hooks installed successfully!\n";
