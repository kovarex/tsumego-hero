#!/bin/bash

# Get list of modified and staged PHP files
MODIFIED_FILES=$(git diff --name-only --diff-filter=ACMRTUXB HEAD src/ tests/ config/ | grep -E '\.php$')

# Exit if no PHP files are modified
if [ -z "$MODIFIED_FILES" ]; then
echo "No modified PHP files found."
exit 0
fi

echo "Running Code Sniffer Fixer on modified PHP files..."
vendor/bin/phpcbf $MODIFIED_FILES

echo "Checking remaining issues..."
vendor/bin/phpcs $MODIFIED_FILES
