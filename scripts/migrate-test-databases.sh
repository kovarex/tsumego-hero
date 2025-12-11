#!/bin/bash
# Run migrations on all test databases
# Usage:
#   ./scripts/migrate-test-databases.sh      # Uses TEST_WORKERS env var (default 8)
#   TEST_WORKERS=16 ./scripts/migrate-test-databases.sh  # Migrates 16 databases

WORKER_COUNT="${TEST_WORKERS:-8}"

echo "Running migrations on test_1 through test_$WORKER_COUNT databases..."

for i in $(seq 1 "$WORKER_COUNT"); do
    echo "Migrating test_$i..."
    vendor/bin/phinx migrate -e "test_$i"
done

echo "✓ All test databases migrated!"
