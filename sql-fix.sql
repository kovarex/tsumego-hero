-- set of fixes to do on the tsumego hero database once it is moved to our servers

/* fixing wrong zero dates in users.rewards first */
SET @@sql_mode='';
UPDATE `users` SET reward=null where reward='0000-00-00 00:00:00';
SET @@sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'

ALTER TABLE users MODIFY premium boolean;
UPDATE users set premium=1 WHERE premium > 1;
ALTER TABLE users MODIFY isAdmin boolean;

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
