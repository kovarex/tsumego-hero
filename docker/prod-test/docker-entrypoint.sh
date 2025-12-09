#!/bin/bash
set -e

# Auto-create database config if missing OR empty (Docker only)
if [ ! -s "/var/www/html/config/database.php" ]; then
    echo "Creating database.php from template..."
    cp /var/www/html/config/database.example.php /var/www/html/config/database.php
    
    # Use environment variables or defaults for Docker
    DB_HOST="${DB_HOST:-db}"
    DB_USER="${DB_USER:-db}"
    DB_PASS="${DB_PASS:-db}"
    
    sed -i "s/template_db_host/${DB_HOST}/g" /var/www/html/config/database.php
    sed -i "s/template_db_user/${DB_USER}/g" /var/www/html/config/database.php
    sed -i "s/template_db_password/${DB_PASS}/g" /var/www/html/config/database.php
    
    echo "Database config created with host=${DB_HOST}, user=${DB_USER}"
fi

# Start Apache
exec apache2-foreground
