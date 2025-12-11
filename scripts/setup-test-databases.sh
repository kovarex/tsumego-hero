#!/bin/bash
# Setup test databases for parallel testing
# Usage:
#   ./scripts/setup-test-databases.sh        # Uses TEST_WORKERS env var (default 8)
#   TEST_WORKERS=16 ./scripts/setup-test-databases.sh  # Creates 16 databases

WORKER_COUNT="${TEST_WORKERS:-8}"

echo "Setting up $WORKER_COUNT test databases for ParaTest..."

# Build MySQL command with credentials from environment
MYSQL_CMD="mysql"
if [[ -n "$DB_USER" ]]; then
    MYSQL_CMD="$MYSQL_CMD -u$DB_USER"
fi
if [[ -n "$DB_PASS" ]]; then
    MYSQL_CMD="$MYSQL_CMD -p$DB_PASS"
fi
if [[ -n "$DB_HOST" ]]; then
    MYSQL_CMD="$MYSQL_CMD -h$DB_HOST"
fi

# Create each database and grant permissions
for i in $(seq 1 "$WORKER_COUNT"); do
    DB_NAME="test_$i"
    echo "Creating database: $DB_NAME"
    
    $MYSQL_CMD -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
    $MYSQL_CMD -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO 'db'@'%';" 2>/dev/null
done

# Flush privileges once at the end
$MYSQL_CMD -e "FLUSH PRIVILEGES;" 2>/dev/null

echo "✓ All $WORKER_COUNT test databases created!"
echo ""
echo "Now run migrations on each database:"
echo "  bash scripts/migrate-test-databases.sh"
