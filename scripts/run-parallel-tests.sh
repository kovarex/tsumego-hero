#!/bin/bash
# Configurable ParaTest wrapper - worker count from TEST_WORKERS env var
# Usage:
#   ./scripts/run-parallel-tests.sh          # Uses TEST_WORKERS env var (default 8)
#   ./scripts/run-parallel-tests.sh 2        # Override to 2 workers (e.g., CI)
#   TEST_WORKERS=16 ./scripts/run-parallel-tests.sh  # 16 workers via env var

# Get worker count - from first argument or TEST_WORKERS env var (default 8)
if [[ -n "$1" && ! "$1" =~ ^- ]]; then
    # First arg is a number (not a flag) - use as override
    WORKER_COUNT="$1"
    shift  # Remove worker count from args
else
    # Use TEST_WORKERS env var or default to 8
    WORKER_COUNT="${TEST_WORKERS:-8}"
fi

echo "Running ParaTest with $WORKER_COUNT workers..."
echo "Note: Requires test_1 through test_$WORKER_COUNT databases to exist"

# Check if all required databases exist
MISSING_DBS=""
for i in $(seq 1 "$WORKER_COUNT"); do
    DB_NAME="test_$i"
    
    # Use mysql directly (works inside DDEV container and on host)
    DB_EXISTS=$(mysql -e "SHOW DATABASES LIKE '$DB_NAME';" 2>/dev/null | grep -c "$DB_NAME" || echo "0")
    
    if [[ "$DB_EXISTS" -eq 0 ]]; then
        MISSING_DBS="$MISSING_DBS $DB_NAME"
    fi
done

# If databases missing, create them
if [[ -n "$MISSING_DBS" ]]; then
    echo ""
    echo "⚠️  WARNING: Missing test databases:$MISSING_DBS"
    echo ""
    echo "Setting up $WORKER_COUNT test databases..."
    
    # Set TEST_WORKERS for called scripts (in case it was passed as argument)
    export TEST_WORKERS="$WORKER_COUNT"
    bash "$(dirname "$0")/setup-test-databases.sh"
    echo ""
fi

# Always run migrations to ensure schema is up-to-date
echo "Running migrations on test databases..."
export TEST_WORKERS="$WORKER_COUNT"
bash "$(dirname "$0")/migrate-test-databases.sh"
echo ""
echo "✓ Test databases ready!"
echo ""

# Run ParaTest with specified worker count (ParaTest handles coverage if flags present)
vendor/bin/paratest --processes="$WORKER_COUNT" --runner=WrapperRunner "$@"
