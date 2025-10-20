-- set of fixes to do on the tsumego hero database once it is moved to our servers

ALTER TABLE `user_boards` DROP COLUMN `id`;
ALTER TABLE ranks ALTER COLUMN points SET DEFAULT 0;

-- Create sessions table for database-based session storage
-- This helps with race conditions and session persistence issues
CREATE TABLE IF NOT EXISTS `cake_sessions` (
  `id` VARCHAR(255) NOT NULL PRIMARY KEY,
  `data` TEXT,
  `expires` INT(11) DEFAULT NULL,
  INDEX `expires_idx` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users MODIFY premium boolean;
UPDATE users set premium=1 WHERE premium > 1;

ALTER TABLE users MODIFY isAdmin boolean;
