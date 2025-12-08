#!/bin/bash
# Setup test databases for parallel testing in DDEV local environment
# Creates multiple test databases (test_1 through test_4) and runs migrations on each
# This runs automatically on `ddev start` via post-start hook

echo "Creating test databases in DDEV MySQL..."

for i in 1 2 3 4; do
  echo "  - Creating test_$i database..."
  ddev exec mysql -e "CREATE DATABASE IF NOT EXISTS test_$i"
  ddev exec mysql -e "GRANT ALL PRIVILEGES ON test_$i.* TO 'db'@'%'"

  echo "  - Running migrations on test_$i..."
  # Use the test_N phinx environment (defined in phinx.php)
  ddev exec vendor/bin/phinx migrate -e test_$i
doneecho "  - Flushing privileges..."
ddev exec mysql -e "FLUSH PRIVILEGES"

echo ""
echo "✅ Parallel testing setup complete!"
echo ""
echo "You can now run parallel tests locally with:"
echo "  ddev xdebug off  # Disable Xdebug for speed"
echo "  ddev exec vendor/bin/paratest --processes=4 --runner=WrapperRunner"
