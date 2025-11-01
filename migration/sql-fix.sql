-- set of fixes to do on the tsumego hero database once it is moved to our servers

/* fixing wrong zero dates in users.rewards and tsumego_rating_attempts.created first */
SET @@sql_mode='';
UPDATE `users` SET reward=null where reward='0000-00-00 00:00:00';
UPDATE `tsumego_rating_attempts` SET created='2022-01-31 01:02:04' where created='0000-00-00 00:00:00'; /* around 1200 out of 800k records with this error, around this time */
SET @@sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
ALTER TABLE users MODIFY premium SMALLINT(2) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=no premium, 1=basic premium, 2=golden premium';
ALTER TABLE users MODIFY isAdmin boolean NOT NULL DEFAULT 0;
ALTER TABLE users DROP column completed;
ALTER TABLE users MODIFY passwordreset VARCHAR(50) NULL DEFAULT NULL;
UPDATE users set passwordreset=null where passwordreset='0';

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

ALTER TABLE `progress_deletions` MODIFY created DATETIME NOT NULL;

/* These are now probably redundant as we already modify the script (as it can't run on the weird collations anyway
* But I'm keeping it
*/
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
ALTER TABLE users DROP COLUMN reuse1;
ALTER TABLE users ADD COLUMN password_hash CHAR(60) NOT NULL;
ALTER TABLE users MODIFY pw VARCHAR(50) NULL DEFAULT NULL COMMENT 'old password - will be dropped after migration';

ALTER TABLE ranks RENAME time_mode_attempt;
ALTER TABLE rank_overviews RENAME time_mode_overview;
ALTER TABLE rank_settings RENAME time_mode_setting;

ALTER TABLE achievements RENAME achievement;
ALTER TABLE achievement_conditions RENAME achievement_condition;
ALTER TABLE achievement_statuses RENAME achievement_status;
ALTER TABLE activates RENAME activate;
ALTER TABLE admin_activities RENAME admin_activity;
ALTER TABLE answers RENAME answer;
ALTER TABLE comments RENAME comment;
ALTER TABLE day_records RENAME day_record;
ALTER TABLE duplicates RENAME duplicate;
ALTER TABLE favorites RENAME favorite;
ALTER TABLE josekis RENAME joseki;
ALTER TABLE progress_deletions RENAME progress_deletion;
ALTER TABLE publish_dates RENAME publish_date;
ALTER TABLE purges RENAME `purge`;
ALTER TABLE purge_lists RENAME purge_list;
ALTER TABLE rejects RENAME reject;
ALTER TABLE reputations RENAME reputation;
ALTER TABLE schedules RENAME schedule;
ALTER TABLE `sets` RENAME `set`;
ALTER TABLE set_connections RENAME set_connection;
ALTER TABLE sgfs RENAME sgf;
ALTER TABLE signatures RENAME signature;
ALTER TABLE sites RENAME site;
ALTER TABLE tags RENAME tag;
ALTER TABLE tag_names RENAME tag_name;
ALTER TABLE tsumegos RENAME tsumego;
ALTER TABLE tsumego_attempts RENAME tsumego_attempt;
ALTER TABLE tsumego_rating_attempts RENAME tsumego_rating_attempt;
ALTER TABLE tsumego_statuses RENAME tsumego_status;
ALTER TABLE tsumego_variants RENAME tsumego_variant;
ALTER TABLE users RENAME `user`;
ALTER TABLE user_contributions RENAME user_contribution;
ALTER TABLE user_boards RENAME user_board;

ALTER TABLE `sgf` CHANGE `id` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT;

/* was needed because of besogo superko bug, which is fixed now, so it is used everywhere. */
ALTER TABLE tsumego DROP COLUMN virtual_children;

/* test.tsumego version until this point */
ALTER TABLE tsumego RENAME COLUMN elo_rating_mode to `rating`;
ALTER TABLE user RENAME COLUMN elo_rating_mode to `rating`;

ALTER TABLE time_mode_attempt  ADD time_mode_overview_id INT UNSIGNED NULL;
ALTER TABLE time_mode_overview MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE time_mode_attempt ADD INDEX `session` (`session`);
ALTER TABLE time_mode_overview ADD INDEX `session` (`session`);

UPDATE time_mode_attempt JOIN time_mode_overview ON time_mode_attempt.session=time_mode_overview.session SET time_mode_overview_id = time_mode_overview.id;

DELETE FROM time_mode_attempt WHERE time_mode_overview_id is null;

ALTER TABLE time_mode_attempt MODIFY time_mode_overview_id INT UNSIGNED NOT NULL;
ALTER TABLE time_mode_attempt ADD INDEX `time_mode_overview_id` (`time_mode_overview_id`);
ALTER TABLE `time_mode_attempt` ADD CONSTRAINT `time_mode_attempt_time_mode_overview_id` FOREIGN KEY (`time_mode_overview_id`) REFERENCES `time_mode_overview`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE time_mode_attempt DROP COLUMN `session`;
ALTER TABLE time_mode_overview DROP COLUMN `session`;

ALTER TABLE time_mode_attempt MODIFY created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;

/* is doing nothing, but we clean it just to be sure */
DELETE time_mode_attempt.* FROM `time_mode_attempt` JOIN time_mode_overview ON time_mode_attempt.time_mode_overview_id=time_mode_overview.id WHERE time_mode_attempt.user_id != time_mode_overview.user_id;

ALTER TABLE time_mode_attempt DROP FOREIGN KEY FK_tsumego_timed_attempts_to_users;
ALTER TABLE time_mode_attempt DROP COLUMN user_id;

ALTER TABLE time_mode_overview ADD INDEX `user_id` (`user_id`);

DELETE time_mode_overview.* FROM `time_mode_overview` LEFT JOIN user ON time_mode_overview.user_id=user.id WHERE user.id is NULL;

ALTER TABLE time_mode_overview MODIFY user_id INT UNSIGNED NOT NULL;
ALTER TABLE `time_mode_overview` ADD CONSTRAINT `time_mode_overview_user_id` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE time_mode_attempt ADD INDEX `rank` (`rank`);
ALTER TABLE time_mode_overview ADD INDEX `rank` (`rank`);

/* is empty, but we clear it just to be sure */
DELETE time_mode_attempt.* FROM `time_mode_attempt` JOIN time_mode_overview ON time_mode_attempt.time_mode_overview_id=time_mode_overview.id WHERE time_mode_attempt.rank != time_mode_overview.rank;
ALTER TABLE time_mode_attempt DROP COLUMN `rank`;
ALTER TABLE `time_mode_attempt` CHANGE `num` `order` SMALLINT UNSIGNED NOT NULL;
ALTER TABLE time_mode_attempt DROP COLUMN currentNum;

CREATE TABLE `time_mode_attempt_status` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(10) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO time_mode_attempt_status (`name`) VALUES ('queued'), ('solved'), ('failed'), ('timeout'), ('skipped');
ALTER TABLE time_mode_attempt ADD COLUMN time_mode_attempt_status_id INT UNSIGNED NOT NULL;

UPDATE time_mode_attempt JOIN time_mode_attempt_status ON time_mode_attempt.result = time_mode_attempt_status.name SET time_mode_attempt_status_id = time_mode_attempt_status.id;
ALTER TABLE time_mode_attempt ADD CONSTRAINT `time_mode_attempt_time_mode_attempt_status_id` FOREIGN KEY (`time_mode_attempt_status_id`) REFERENCES `time_mode_attempt_status`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
ALTER TABLE time_mode_attempt DROP COLUMN `result`;
ALTER TABLE `time_mode_attempt` CHANGE `time_mode_attempt_status_id` `time_mode_attempt_status_id` INT UNSIGNED NOT NULL;
UPDATE `time_mode_attempt` SET seconds = 0 WHERE seconds < 0;
UPDATE `time_mode_attempt` SET seconds = 240 WHERE seconds > 240;
ALTER TABLE `time_mode_attempt` CHANGE `seconds` `seconds` DECIMAL(5,2) UNSIGNED NULL;

UPDATE `time_mode_attempt` SET points = 0 WHERE points < 0;
ALTER TABLE `time_mode_attempt` CHANGE `points` `points` DECIMAL(5,2) UNSIGNED NULL;

ALTER TABLE `time_mode_attempt` CHANGE `id` `id` INT UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE time_mode_overview RENAME time_mode_session;
ALTER TABLE time_mode_attempt  CHANGE `time_mode_overview_id` `time_mode_session_id` INT UNSIGNED NOT NULL;
ALTER TABLE time_mode_attempt RENAME INDEX time_mode_overview_id TO time_mode_session_id;

CREATE TABLE `time_mode_session_status` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(12) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO time_mode_session_status(`name`) VALUES ('in progress'),('failed'), ('solved');

ALTER TABLE `time_mode_attempt_status` ADD UNIQUE `name` (`name`);
ALTER TABLE `time_mode_session_status` ADD UNIQUE `name` (`name`);

ALTER TABLE time_mode_session ADD COLUMN time_mode_session_status_id INT UNSIGNED NOT NULL;

UPDATE time_mode_session SET time_mode_session_status_id = (SELECT id FROM time_mode_session_status WHERE name= 'solved') WHERE status = 's';
UPDATE time_mode_session SET time_mode_session_status_id = (SELECT id FROM time_mode_session_status WHERE name= 'failed') WHERE status = 'f';

ALTER TABLE time_mode_session ADD CONSTRAINT `time_mode_session_time_mode_session_status_id` FOREIGN KEY (`time_mode_session_status_id`) REFERENCES `time_mode_session_status`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE time_mode_session DROP COLUMN `status`;

CREATE TABLE `time_mode_category` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(12) NOT NULL UNIQUE,
    `seconds` SMALLINT NOT NULL UNIQUE,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO time_mode_category(`name`, `seconds`) VALUES ('Blitz', 30),('Fast', 60), ('Slow', 240);

ALTER TABLE time_mode_session ADD COLUMN time_mode_category_id INT UNSIGNED NOT NULL;

UPDATE time_mode_session SET time_mode_category_id = (SELECT id FROM time_mode_category WHERE name = 'Blitz') WHERE mode = 0;
UPDATE time_mode_session SET time_mode_category_id = (SELECT id FROM time_mode_category WHERE name = 'Fast') WHERE mode = 1;
UPDATE time_mode_session SET time_mode_category_id = (SELECT id FROM time_mode_category WHERE name = 'Slow') WHERE mode = 2;
ALTER TABLE time_mode_session ADD CONSTRAINT `time_mode_session_time_mode_category_id` FOREIGN KEY (`time_mode_category_id`) REFERENCES `time_mode_category`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE time_mode_session DROP COLUMN mode;

UPDATE `time_mode_session` SET points = 0 WHERE points < 0;
ALTER TABLE `time_mode_session` CHANGE `points` `points` DECIMAL(6,2) UNSIGNED NOT NULL;

CREATE TABLE `time_mode_rank` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(12) NOT NULL UNIQUE,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO time_mode_rank(`name`) VALUES ('15k'),('14k'), ('13k'), ('12k'), ('11k'), ('10k'), ('9k'), ('8k'), ('7k'), ('6k'), ('5k'), ('4k'), ('3k'), ('2k'), ('1k'), ('1d'), ('2d'), ('3d'), ('4d'), ('5d');


ALTER TABLE time_mode_session ADD COLUMN time_mode_rank_id INT UNSIGNED NULL;

UPDATE time_mode_session JOIN time_mode_rank ON time_mode_session.rank = time_mode_rank.name SET time_mode_rank_id = time_mode_rank.id;

DELETE FROM time_mode_session WHERE time_mode_rank_id = 0;

ALTER TABLE time_mode_session ADD CONSTRAINT `time_mode_session_time_mode_rank_id` FOREIGN KEY (`time_mode_rank_id`) REFERENCES `time_mode_rank`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE time_mode_session DROP COLUMN `rank`;

ALTER TABLE user DROP COLUMN activeRank;
ALTER TABLE `tsumego` DROP COLUMN num;

ALTER TABLE `set` ADD COLUMN included_in_time_mode BOOLEAN NOT NULL DEFAULT TRUE;
UPDATE `set` SET included_in_time_mode = FALSE WHERE id in (42, 109, 114, 143, 172, 29156, 33007, 74761);

ALTER TABLE `user` CHANGE `lastMode` `last_time_mode_category_id` INT UNSIGNED NULL;
ALTER TABLE `user` ADD CONSTRAINT `user_last_time_mode_category_id` FOREIGN KEY (`last_time_mode_category_id`) REFERENCES `time_mode_category`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;