#!/bin/bash
# Seed test accounts for local development.
# Safe to run multiple times (idempotent via ON DUPLICATE KEY UPDATE).
# Only runs if the 'db' database is accessible.

set -e

mysql -udb -pdb db 2>/dev/null <<'SQL'
INSERT INTO user (name, password_hash, email, isAdmin, rating, created)
VALUES ('admin', '$2y$12$iQW0o02pqdFE71P2dxJxT.WXS7K3EVZIMfVMwQi7Icu8QQnVOLMZ.', 'admin@test.local', 1, 1000, NOW())
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), isAdmin = 1;

INSERT INTO user (name, password_hash, email, isAdmin, rating, created)
VALUES ('testuser', '$2y$12$iQW0o02pqdFE71P2dxJxT.WXS7K3EVZIMfVMwQi7Icu8QQnVOLMZ.', 'testuser@test.local', 0, 1000, NOW())
ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), isAdmin = 0;
SQL
