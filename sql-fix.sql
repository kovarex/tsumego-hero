-- set of fixes to do on the tsumego hero database once it is moved to our servers

/* fixing wrong zero dates in users.rewards and tsumego_rating_attempts.created first */
SET @@sql_mode='';
UPDATE `users` SET reward=null where reward='0000-00-00 00:00:00';
UPDATE `tsumego_rating_attempts` SET created='2022-01-31 01:02:04' where created='0000-00-00 00:00:00'; /* around 1200 out of 800k records with this error, around this time */
SET @@sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
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

ALTER TABLE `sets` DROP COLUMN `folder`;

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

ALTER TABLE `set_connections` MODIFY `set_id` INT UNSIGNED NOT NULL;
ALTER TABLE `set_connections` ADD CONSTRAINT `set_connections_set_id` FOREIGN KEY (`set_id`) REFERENCES `sets`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE `set_connections` MODIFY `tsumego_id` INT UNSIGNED NOT NULL;

/* Invalid set_connections as the tsumego was removed (1 entry in current import) */
DELETE set_connections.* from set_connections LEFT JOIN tsumegos on set_connections.tsumego_id=tsumegos.id WHERE tsumegos.id is null;
ALTER TABLE `set_connections` ADD CONSTRAINT `set_connections_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumegos` (`id`) ON UPDATE CASCADE ON DELETE CASCADE;


ALTER TABLE `sgfs` MODIFY `id` INT UNSIGNED;
UPDATE `sgfs` SET user_id = null where user_id=33; /* the mysterious noUser :) */
ALTER TABLE `sgfs` MODIFY `user_id` INT UNSIGNED;
ALTER TABLE `sgfs` ADD CONSTRAINT `sgfs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT; /* We dont delete sgfs because of potential user removal. */

DELETE FROM sgfs WHERE sgfs.tsumego_id is null; /* nonsensual record, sgf without a tsumego has no meaning, 1 record in database */
ALTER TABLE `sgfs` MODIFY `tsumego_id` INT UNSIGNED NOT NULL;
DELETE sgfs.* FROM sgfs LEFT JOIN tsumegos on sgfs.tsumego_id=tsumegos.id WHERE tsumegos.id is null; /* 3800 entries in the database 22k, nonsensual to have these */
ALTER TABLE `sgfs` ADD CONSTRAINT `sgfs_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumegos` (`id`) ON UPDATE CASCADE ON DELETE CASCADE; /* When tsumego is deleted it is ok to remove all of its sgf versions*/

ALTER TABLE tsumegos DROP COLUMN `file`;

/* I'm not 100% sure about this, but set_id should be deprecated on tsumegos, as they are now conncted through set_connections.
 Some tsumegos, even new ones still have set_id set, but I'm suspecting it is some artifact of how the set id is filled in the controller, and then it is saved sometimes.
 Fast testing shows that the sites works fine with this column gone, and if something breaks, it probably means the logic should be fixed, not the set_id restored.
 */

ALTER TABLE tsumegos DROP COLUMN `set_id`;
ALTER TABLE tsumegos MODIFY public boolean;

ALTER TABLE tsumegos MODIFY `alternative_response` boolean NOT NULL DEFAULT true COMMENT 'If the user has to counter all possible countrplays. Should be eventually removed and used for all tsumegos, more than 90% have it on.';
ALTER TABLE tsumegos MODIFY `virtual_children` boolean NOT NULL DEFAULT true COMMENT 'Specifies if the variations in the sgf trees are merged, is used on all but few tsumegos, probably because of past or current bugs, should be checked and this field removed once not needed.';

DROP TABLE user_sa_maps; /* unknown has one weird entry */
DROP TABLE user_texture_maps; /* unknown - empty */

ALTER TABLE `day_records` MODIFY `user_id` INT UNSIGNED NOT NULL;
DELETE day_records.* FROM day_records LEFT JOIN users on day_records.user_id=users.id WHERE users.id is null; /* 9 deleted out of many */
ALTER TABLE `day_records` ADD CONSTRAINT `day_records_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE; /* If user would be deleted, we delete his day record I guess */

DROP PROCEDURE IF EXISTS remove_duplicate_tsumego_statuses;
DELIMITER //
CREATE PROCEDURE remove_duplicate_tsumego_statuses()
BEGIN
  DECLARE count_to_delete int unsigned;
  (SELECT MAX(tmp.count) FROM (SELECT COUNT(*) as count, user_id, tsumego_id FROM `tsumego_statuses` GROUP BY user_id, tsumego_id HAVING COUNT(*) > 1) as tmp) INTO count_to_delete;
  WHILE (count_to_delete > 0) DO
    DELETE to_remove FROM `tsumego_statuses` as to_remove JOIN(SELECT MIN(id) as id, user_id, tsumego_id FROM `tsumego_statuses` GROUP BY user_id, tsumego_id HAVING COUNT(*) > 1) as tmp ON tmp.id=to_remove.id;
    SET count_to_delete = count_to_delete - 1;
  END WHILE;
END //
DELIMITER ;
CALL remove_duplicate_tsumego_statuses();
SELECT COUNT(*) as count, user_id, tsumego_id FROM `tsumego_statuses` GROUP BY user_id, tsumego_id HAVING COUNT(*) > 1 ORDER BY 1 DESC;
DROP PROCEDURE IF EXISTS remove_duplicate_tsumego_statuses;

ALTER TABLE `tsumego_statuses` DROP INDEX `user_id_and_tsumego_id_index`, ADD UNIQUE `user_id_and_tsumego_id_index` (`user_id`, `tsumego_id`) USING BTREE;

ALTER TABLE users ALTER COLUMN potion SET DEFAULT 0;
ALTER TABLE users ALTER COLUMN penalty SET DEFAULT 0;
