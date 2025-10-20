-- set of fixes to do on the tsumego hero database once it is moved to our servers

/* fixing wrong zero dates in users.rewards and tsumego_rating_attempts.created first */
SET @@sql_mode='';
UPDATE `users` SET reward=null where reward='0000-00-00 00:00:00';
UPDATE `tsumego_rating_attempts` SET created='2022-01-31 01:02:04' where created='0000-00-00 00:00:00'; /* around 1200 out of 800k records with this error, around this time */
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

ALTER TABLE `achievements` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `achievement_conditions` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `achievement_statuses` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `activates` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `admin_activities` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `answers` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `comments` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `day_records` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `duplicates` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `favorites` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `josekis` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `progress_deletions` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `publish_dates` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `purges` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `purge_lists` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `ranks` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `rank_overviews` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `rank_settings` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `rejects` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `reputations` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `schedules` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `sets` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `set_connections` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `sgfs` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `signatures` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `sites` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `tags` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `tag_names` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `tsumegos` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `tsumego_attempts` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `tsumego_rating_attempts` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `tsumego_statuses` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `tsumego_variants` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `users` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `user_boards` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `user_contributions` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `user_sa_maps` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
ALTER TABLE `user_texture_maps` convert to character set utf8mb4 collate utf8mb4_unicode_ci;
