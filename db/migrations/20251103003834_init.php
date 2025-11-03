<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/* Original state of the database from tsumego-hero.com */
final class Init extends AbstractMigration
{
    public function up(): void
    {
		$this->query("CREATE TABLE `achievement_conditions` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `value` double DEFAULT NULL,
                        `user_id` int DEFAULT NULL,
                        `set_id` int DEFAULT NULL,
                        `category` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=63628 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `achievement_statuses`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `achievement_statuses` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `user_id` int DEFAULT NULL,
                        `achievement_id` int DEFAULT NULL,
                        `value` int NOT NULL DEFAULT '0',
                        `created` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=113281 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `achievements`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `achievements` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `image` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `color` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `order` int DEFAULT NULL,
                        `xp` int DEFAULT NULL,
                        `additionalDescription` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `created` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `activates`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `activates` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `user_id` int NOT NULL,
                        `string` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `admin_activities`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `admin_activities` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `user_id` int NOT NULL,
                        `tsumego_id` int NOT NULL,
                        `file` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                        `answer` varchar(900) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=41357 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `answers`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `answers` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `user_id` int NOT NULL,
                        `comment_id` int NOT NULL,
                        `message` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                        `dismissed` int NOT NULL DEFAULT '0',
                        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=8389 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `cake_sessions`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `cake_sessions` (
                        `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                        `data` text COLLATE utf8mb4_unicode_ci,
                        `expires` int DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `expires_idx` (`expires`)
                      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `comments`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `comments` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `user_id` int NOT NULL DEFAULT '0',
                        `tsumego_id` int NOT NULL DEFAULT '0',
                        `set_id` int DEFAULT NULL,
                        `admin_id` int DEFAULT NULL,
                        `message` varchar(3000) NOT NULL,
                        `status` varchar(3000) NOT NULL DEFAULT '0',
                        `position` varchar(300) DEFAULT NULL,
                        `created` datetime DEFAULT NULL,
                        `created2` varchar(100) DEFAULT NULL,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=20918 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `day_records`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `day_records` (
                        `id` int unsigned NOT NULL AUTO_INCREMENT,
                        `user_id` int NOT NULL,
                        `date` date DEFAULT NULL,
                        `solved` int DEFAULT NULL,
                        `quote` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `userbg` int NOT NULL,
                        `tsumego` int DEFAULT NULL,
                        `newTsumego` int DEFAULT NULL,
                        `usercount` int DEFAULT NULL,
                        `visitedproblems` int NOT NULL,
                        `gems` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0-0-0',
                        `gemCounter1` int NOT NULL DEFAULT '0',
                        `gemCounter2` int NOT NULL DEFAULT '0',
                        `gemCounter3` int NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=2498 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `duplicates`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `duplicates` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `tsumego_id` int NOT NULL,
                        `dGroup` int NOT NULL,
                        PRIMARY KEY (`id`),
                        KEY `tsumego_id` (`tsumego_id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=2074 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `favorites`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `favorites` (
                        `id` int unsigned NOT NULL AUTO_INCREMENT,
                        `user_id` int unsigned NOT NULL,
                        `tsumego_id` int unsigned NOT NULL,
                        `created` datetime DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `user_id_index` (`user_id`),
                        KEY `tsumego_id_index` (`tsumego_id`),
                        KEY `user_id_and_tsumego_id_index` (`user_id`,`tsumego_id`),
                        CONSTRAINT `FK_favorites_to_tsumegos` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumegos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                        CONSTRAINT `FK_favorites_to_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                      ) ENGINE=InnoDB AUTO_INCREMENT=101495 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `josekis`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `josekis` (
                        `id` int unsigned NOT NULL AUTO_INCREMENT,
                        `tsumego_id` int NOT NULL,
                        `type` int NOT NULL,
                        `order` double NOT NULL,
                        `thumbnail` varchar(100) NOT NULL,
                        `hints` varchar(100) NOT NULL DEFAULT '1',
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `progress_deletions`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `progress_deletions` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `user_id` int NOT NULL,
                        `set_id` int NOT NULL,
                        `created` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=5239 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `publish_dates`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `publish_dates` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `tsumego_id` int DEFAULT NULL,
                        `date` datetime DEFAULT NULL,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=26605 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `purge_lists`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `purge_lists` (
                        `id` int unsigned NOT NULL AUTO_INCREMENT,
                        `start` varchar(100) NOT NULL,
                        `empty_uts` varchar(100) NOT NULL,
                        `purge` varchar(100) NOT NULL,
                        `count` varchar(100) NOT NULL,
                        `archive` varchar(100) NOT NULL,
                        `tsumego_scores` varchar(100) NOT NULL,
                        `set_scores` varchar(100) NOT NULL,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `purges`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `purges` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `user_id` int NOT NULL,
                        `pre` int NOT NULL,
                        `after` int NOT NULL,
                        `duplicates` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=2480950 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `rank_overviews`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `rank_overviews` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `user_id` int NOT NULL,
                        `session` varchar(100) NOT NULL,
                        `rank` varchar(100) NOT NULL,
                        `status` varchar(100) NOT NULL,
                        `mode` int NOT NULL,
                        `points` int NOT NULL,
                        `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=52724 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `rank_settings`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `rank_settings` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `user_id` int NOT NULL,
                        `set_id` int NOT NULL,
                        `status` int NOT NULL,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=144600 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `ranks`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `ranks` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `session` varchar(100) DEFAULT NULL,
                        `user_id` int unsigned NOT NULL,
                        `tsumego_id` int unsigned NOT NULL,
                        `rank` varchar(100) DEFAULT NULL,
                        `num` int DEFAULT NULL,
                        `currentNum` int DEFAULT NULL,
                        `result` varchar(10) NOT NULL DEFAULT 'skipped',
                        `seconds` double NOT NULL DEFAULT '60',
                        `points` int NOT NULL DEFAULT '0',
                        `created` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `user_id` (`user_id`),
                        KEY `tsumego_id` (`tsumego_id`),
                        KEY `user_id_and_tsumego_id` (`user_id`,`tsumego_id`),
                        CONSTRAINT `FK_tsumego_timed_attempts_to_tsumegos` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumegos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                        CONSTRAINT `FK_tsumego_timed_attempts_to_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                      ) ENGINE=InnoDB AUTO_INCREMENT=1004682 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `rejects`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `rejects` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `type` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `text` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `tsumego_id` int NOT NULL,
                        `user_id` int NOT NULL,
                        `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `tsumego_id` (`tsumego_id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=946 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `reputations`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `reputations` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `user_id` int DEFAULT NULL,
                        `tsumego_id` int DEFAULT NULL,
                        `set_id` int DEFAULT NULL,
                        `value` int DEFAULT NULL,
                        `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=7392 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `schedules`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `schedules` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `date` date NOT NULL,
                        `tsumego_id` int DEFAULT NULL,
                        `set_id` int NOT NULL,
                        `published` int NOT NULL,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=6980 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `set_connections`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `set_connections` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `set_id` int NOT NULL,
                        `tsumego_id` int NOT NULL,
                        `num` int NOT NULL,
                        `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `set_id` (`set_id`),
                        KEY `tsumego_id` (`tsumego_id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=17082 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `sets`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `sets` (
                        `id` int unsigned NOT NULL AUTO_INCREMENT,
                        `title` varchar(400) DEFAULT NULL,
                        `title2` varchar(50) DEFAULT NULL,
                        `author` varchar(400) DEFAULT NULL,
                        `description` varchar(4000) DEFAULT NULL,
                        `folder` varchar(400) DEFAULT NULL,
                        `difficulty` int NOT NULL DEFAULT '1',
                        `image` varchar(50) DEFAULT NULL,
                        `order` int DEFAULT NULL,
                        `public` int DEFAULT '1',
                        `premium` int NOT NULL DEFAULT '0',
                        `multiplier` float NOT NULL DEFAULT '1',
                        `color` varchar(10) DEFAULT NULL,
                        `created` datetime DEFAULT NULL,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=88172 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `sgfs`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `sgfs` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `sgf` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                        `user_id` int DEFAULT NULL,
                        `tsumego_id` int DEFAULT NULL,
                        `version` double DEFAULT NULL,
                        `created` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `user_id` (`user_id`),
                        KEY `tsumego_id` (`tsumego_id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=27020 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `signatures`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `signatures` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `tsumego_id` int NOT NULL,
                        `signature` varchar(500) DEFAULT NULL,
                        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `tsumego_id` (`tsumego_id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=3862455 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `sites`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `sites` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `title` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                        `body` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `tag_names`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `tag_names` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `description` varchar(5000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `link` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `user_id` int DEFAULT NULL,
                        `approved` int DEFAULT '1',
                        `color` int NOT NULL DEFAULT '0',
                        `hint` int DEFAULT '0',
                        `created` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `user_id` (`user_id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=191 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `tags`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `tags` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `tag_name_id` int DEFAULT NULL,
                        `user_id` int DEFAULT NULL,
                        `tsumego_id` int DEFAULT NULL,
                        `approved` int DEFAULT '1',
                        `created` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `tsumego_id` (`tsumego_id`),
                        KEY `user_id` (`user_id`),
                        KEY `tag_name_id` (`tag_name_id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=17769 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `tsumego_attempts`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `tsumego_attempts` (
                        `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'id',
                        `user_id` int unsigned NOT NULL COMMENT 'The user it belongs to.',
                        `tsumego_id` int unsigned NOT NULL COMMENT 'The tsumego it belongs to.',
                        `gain` int NOT NULL COMMENT 'The gain (XP) that the user got out of it.',
                        `solved` tinyint(1) NOT NULL COMMENT 'Solved or failed.',
                        `seconds` int NOT NULL COMMENT 'Time spent in seconds.',
                        `misplays` int NOT NULL DEFAULT '0' COMMENT 'Number of misplays. Didn''t exist a month ago, so before the maximum misplay was 1 per day.',
                        `mode` int NOT NULL DEFAULT '1',
                        `elo` int DEFAULT NULL,
                        `tsumego_elo` int NOT NULL DEFAULT '0',
                        `created` datetime NOT NULL,
                        PRIMARY KEY (`id`),
                        KEY `user_id_index` (`user_id`),
                        KEY `tsumego_id_index` (`tsumego_id`),
                        KEY `user_id_and_tsumego_id_index` (`user_id`,`tsumego_id`),
                        CONSTRAINT `FK_user_records_to_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumegos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                        CONSTRAINT `FK_user_records_to_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                      ) ENGINE=InnoDB AUTO_INCREMENT=24958683 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `tsumego_rating_attempts`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `tsumego_rating_attempts` (
                        `id` int unsigned NOT NULL AUTO_INCREMENT,
                        `user_id` int unsigned NOT NULL,
                        `user_elo` int DEFAULT NULL,
                        `user_deviation` int DEFAULT NULL,
                        `tsumego_id` int unsigned NOT NULL,
                        `tsumego_elo` int DEFAULT NULL,
                        `tsumego_deviation` int DEFAULT NULL,
                        `status` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                        `seconds` int DEFAULT NULL,
                        `sequence` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `recent` int NOT NULL DEFAULT '1',
                        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `user_id_index` (`user_id`),
                        KEY `tsumego_id_index` (`tsumego_id`),
                        KEY `user_id_and_tsumego_id_index` (`user_id`,`tsumego_id`),
                        CONSTRAINT `FK_tsumego_records_to_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumegos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                        CONSTRAINT `FK_tsumego_recors_to_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                      ) ENGINE=InnoDB AUTO_INCREMENT=1235742 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `tsumego_statuses`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `tsumego_statuses` (
                        `id` int unsigned NOT NULL AUTO_INCREMENT,
                        `user_id` int unsigned NOT NULL COMMENT 'The user it belongs to.',
                        `tsumego_id` int unsigned NOT NULL COMMENT 'The tsumego it belongs to.',
                        `status` char(1) NOT NULL COMMENT 'Status: Visited ''V'', solved ''S'', solved twice ''C'', half xp ''W'', failed ''F'', failed twice ''X'', golden ''G''.',
                        `created` datetime DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        KEY `user_id_index` (`user_id`),
                        KEY `tsumego_id_index` (`tsumego_id`),
                        KEY `user_id_and_tsumego_id_index` (`user_id`,`tsumego_id`),
                        CONSTRAINT `FK_user_tsumegos_to_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumegos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                        CONSTRAINT `FK_user_tsumegos_to_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                      ) ENGINE=InnoDB AUTO_INCREMENT=61333026 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `tsumego_variants`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `tsumego_variants` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `tsumego_id` int NOT NULL DEFAULT '0',
                        `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
                        `numAnswer` double NOT NULL DEFAULT '0',
                        `answer1` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
                        `answer2` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
                        `answer3` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
                        `answer4` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
                        `winner` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
                        `explanation` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `tsumegos`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `tsumegos` (
                        `id` int unsigned NOT NULL AUTO_INCREMENT,
                        `part` varchar(50) DEFAULT NULL COMMENT 'Experimental field. Currently one use for Study Group in the sandbox.',
                        `part_increment` int DEFAULT NULL COMMENT 'Experimental field. No use currently.',
                        `num` int DEFAULT NULL COMMENT 'Number in the collection.',
                        `difficulty` int DEFAULT '1' COMMENT 'The XP value.',
                        `file` varchar(50) DEFAULT NULL COMMENT 'File name.',
                        `description` varchar(400) DEFAULT NULL COMMENT 'Example: Black to kill.',
                        `hint` varchar(400) DEFAULT NULL COMMENT 'Example: No ko.',
                        `author` varchar(100) NOT NULL DEFAULT 'Joschka Zimdars',
                        `set_id` int DEFAULT NULL,
                        `public` int NOT NULL DEFAULT '0',
                        `solved` int DEFAULT NULL COMMENT 'Number of solves by registered users. In between step to calculate the success percentage.',
                        `failed` int DEFAULT NULL COMMENT 'Number of fails by registered users. In between step to calculate the success percentage.',
                        `elo_rating_mode` float NOT NULL DEFAULT '900' COMMENT 'Tsumego elo for the rating mode.',
                        `rd` float NOT NULL DEFAULT '7' COMMENT 'Rating deviation - not in use. Was supposed to help find problems where the difficulty is off.',
                        `userWin` float NOT NULL DEFAULT '0' COMMENT 'Success percentage. (Registered users)',
                        `userLoss` int NOT NULL DEFAULT '0' COMMENT 'How often the problem has been tried. (Registered users)',
                        `created` datetime DEFAULT NULL COMMENT 'Datetime of creation.',
                        `minLib` int DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
                        `maxLib` int DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
                        `variance` int DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races. Is also used for new problems in the sandbox that don''t have a file.',
                        `libertyCount` int DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
                        `insideLiberties` int DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
                        `eyeLiberties1` int DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races',
                        `eyeLiberties2` int DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races',
                        `semeaiType` int DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races',
                        `alternative_response` tinyint(1) NOT NULL DEFAULT '1',
                        `virtual_children` tinyint(1) NOT NULL DEFAULT '1',
                        `duplicate` int NOT NULL DEFAULT '0',
                        `pass` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'is pass enabled',
                        `activity_value` int NOT NULL DEFAULT '0',
                        PRIMARY KEY (`id`),
                        KEY `elo_index` (`elo_rating_mode`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=38962 DEFAULT CHARSET=utf8mb4;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `user_boards`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `user_boards` (
                        `id` int NOT NULL,
                        `user_id` int NOT NULL,
                        `b1` int NOT NULL DEFAULT '1',
                        `b2` int NOT NULL DEFAULT '1',
                        `b3` int NOT NULL DEFAULT '1',
                        `b4` int NOT NULL DEFAULT '1',
                        `b5` int NOT NULL DEFAULT '1',
                        `b6` int NOT NULL DEFAULT '1',
                        `b7` int NOT NULL DEFAULT '1',
                        `b8` int NOT NULL DEFAULT '1',
                        `b9` int NOT NULL DEFAULT '1',
                        `b10` int NOT NULL DEFAULT '1',
                        `b11` int NOT NULL DEFAULT '1',
                        `b12` int NOT NULL DEFAULT '1',
                        `b13` int NOT NULL DEFAULT '1',
                        `b14` int NOT NULL DEFAULT '1',
                        `b15` int NOT NULL DEFAULT '1',
                        `b16` int NOT NULL DEFAULT '1',
                        `b17` int NOT NULL DEFAULT '1',
                        `b18` int NOT NULL DEFAULT '1',
                        `b19` int NOT NULL DEFAULT '1',
                        `b20` int NOT NULL DEFAULT '1',
                        `b21` int NOT NULL DEFAULT '1',
                        `b22` int NOT NULL DEFAULT '1',
                        `b23` int NOT NULL DEFAULT '0',
                        `b24` int NOT NULL DEFAULT '0',
                        `b25` int NOT NULL DEFAULT '0',
                        `b26` int NOT NULL DEFAULT '0',
                        `b27` int NOT NULL DEFAULT '0',
                        `b28` int NOT NULL DEFAULT '0',
                        `b29` int NOT NULL DEFAULT '0',
                        `b30` int NOT NULL DEFAULT '0',
                        `b31` int NOT NULL DEFAULT '0',
                        `b32` int NOT NULL DEFAULT '0',
                        `b33` int NOT NULL DEFAULT '1',
                        `34` int NOT NULL DEFAULT '0',
                        `b35` int NOT NULL DEFAULT '0',
                        `b36` int NOT NULL DEFAULT '0',
                        `b37` int NOT NULL DEFAULT '0',
                        `b38` int NOT NULL DEFAULT '0',
                        `b39` int NOT NULL DEFAULT '0',
                        `b40` int NOT NULL DEFAULT '0',
                        `b41` int NOT NULL DEFAULT '0',
                        `b42` int NOT NULL DEFAULT '0',
                        `b43` int NOT NULL DEFAULT '0',
                        `b44` int NOT NULL DEFAULT '0',
                        `b45` int NOT NULL DEFAULT '0',
                        `b46` int NOT NULL DEFAULT '0',
                        `b47` int NOT NULL DEFAULT '0',
                        `b48` int NOT NULL DEFAULT '0',
                        `b49` int NOT NULL DEFAULT '0',
                        `b50` int NOT NULL DEFAULT '0',
                        `b51` int NOT NULL DEFAULT '0',
                        `b52` int NOT NULL DEFAULT '0',
                        `b53` int NOT NULL DEFAULT '0',
                        `b54` int NOT NULL DEFAULT '0'
                      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `user_contributions`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `user_contributions` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `user_id` int DEFAULT NULL,
                        `query` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'topics',
                        `collectionSize` int NOT NULL DEFAULT '200',
                        `search1` varchar(5000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `search2` varchar(5000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `search3` varchar(5000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                        `added_tag` int DEFAULT '0',
                        `created_tag` int DEFAULT '0',
                        `made_proposal` int DEFAULT '0',
                        `reviewed` int DEFAULT '0',
                        `score` int DEFAULT '0',
                        `reward1` int DEFAULT '0',
                        `reward2` int DEFAULT '0',
                        `reward3` int DEFAULT '0',
                        `created` datetime DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        KEY `user_id` (`user_id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=8613 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `user_sa_maps`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `user_sa_maps` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `uid` int NOT NULL,
                        `sid` int NOT NULL,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `user_texture_maps`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `user_texture_maps` (
                        `id` int NOT NULL AUTO_INCREMENT,
                        `uid` int NOT NULL,
                        `tid` int NOT NULL,
                        PRIMARY KEY (`id`)
                      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
                      /*!40101 SET character_set_client = @saved_cs_client */;
                      
                      --
                      -- Table structure for table `users`
                      --
                      
                      /*!40101 SET @saved_cs_client     = @@character_set_client */;
                      /*!50503 SET character_set_client = utf8mb4 */;
                      CREATE TABLE `users` (
                        `id` int unsigned NOT NULL AUTO_INCREMENT,
                        `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'name',
                        `email` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'email',
                        `pw` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'password',
                        `external_id` varchar(200) DEFAULT NULL COMMENT 'google id',
                        `picture` varchar(500) DEFAULT NULL COMMENT 'google image',
                        `premium` int NOT NULL DEFAULT '0' COMMENT 'Premium user or not.',
                        `completed` int NOT NULL DEFAULT '0' COMMENT 'Not in use. Was used for unlocking the sandbox and other things.',
                        `level` int NOT NULL DEFAULT '1' COMMENT 'User level.',
                        `mode` int NOT NULL DEFAULT '1' COMMENT 'Current mode.',
                        `elo_rating_mode` float NOT NULL DEFAULT '900' COMMENT 'User elo for the rating mode.',
                        `rd` float NOT NULL DEFAULT '300' COMMENT 'Rating deviation for the rating mode. The changing value for the rating mode depends on the user activity and it higher when the user was not so active recently.',
                        `_sessid` varchar(500) DEFAULT NULL,
                        `t_glicko` int NOT NULL DEFAULT '4' COMMENT 'Rating mode formula for calculating the changing value.',
                        `dbstorage` int NOT NULL DEFAULT '0' COMMENT 'Has no use now. Was value for the OldUserTsumego archice. ',
                        `created` datetime DEFAULT NULL,
                        `lastRefresh` date DEFAULT NULL,
                        `xp` int NOT NULL DEFAULT '0' COMMENT 'Overall xp the user has.',
                        `nextlvl` int NOT NULL DEFAULT '50' COMMENT 'Xp needed for the next level.',
                        `health` int NOT NULL DEFAULT '10' COMMENT 'Current maximum hearts.',
                        `damage` int NOT NULL DEFAULT '0' COMMENT 'The hearts that the user has lost on this day.',
                        `potion` int NOT NULL COMMENT 'Hero Power: Potion',
                        `promoted` int NOT NULL DEFAULT '0' COMMENT 'How active is the user in general.',
                        `reuse1` int NOT NULL COMMENT 'Not is use(?)',
                        `sprint` int DEFAULT '0' COMMENT 'Hero Power: Sprint',
                        `intuition` int DEFAULT '0' COMMENT 'Hero Power: Intuition',
                        `rejuvenation` int DEFAULT '0' COMMENT 'Hero Power: Rejuvenation',
                        `refinement` int NOT NULL DEFAULT '0' COMMENT 'Hero Power: Refinement',
                        `revelation` int NOT NULL DEFAULT '0',
                        `readingTrial` int NOT NULL DEFAULT '30' COMMENT 'Use of the skip button in the rating mode.',
                        `isAdmin` int NOT NULL DEFAULT '0' COMMENT 'Is the user admin.',
                        `lastHighscore` int NOT NULL DEFAULT '0' COMMENT 'Last used highscore page.',
                        `lastLight` int NOT NULL DEFAULT '0',
                        `levelBar` int NOT NULL DEFAULT '0',
                        `lastProfileLeft` int NOT NULL DEFAULT '0',
                        `lastProfileRight` int NOT NULL DEFAULT '0',
                        `lastMode` int NOT NULL DEFAULT '3' COMMENT 'Last used mode.',
                        `reuse2` int NOT NULL DEFAULT '0' COMMENT 'Counts suspicious behavior that could be interpreted as cheating.',
                        `reuse3` int NOT NULL DEFAULT '0' COMMENT 'Checksum to identify cheaters.',
                        `reuse4` int NOT NULL DEFAULT '0' COMMENT 'Daily maximum for non-premium users. Also helps to identify cheaters.',
                        `reuse5` int NOT NULL DEFAULT '0' COMMENT 'Locks accounts. For example cheaters or toxic people.',
                        `penalty` int NOT NULL COMMENT 'Counts suspicious behavior. Is probably redundant with reuse2.',
                        `reward` datetime DEFAULT NULL COMMENT 'Shows date and time of people who donated.',
                        `usedSprint` int NOT NULL DEFAULT '0' COMMENT 'Anti cheating value for Sprint.',
                        `usedRejuvenation` int NOT NULL DEFAULT '0' COMMENT 'Anti cheating value for Rejuvenation.',
                        `usedRefinement` int NOT NULL DEFAULT '0' COMMENT 'Anti cheating value for Refinement.',
                        `passwordreset` varchar(50) NOT NULL DEFAULT '0' COMMENT 'Hash for password reset.',
                        `solved` int DEFAULT '0' COMMENT 'Overall solves.',
                        `solved2` int NOT NULL DEFAULT '0' COMMENT 'Daily solves.',
                        `activeRank` varchar(100) DEFAULT '0' COMMENT 'Hash for an actice time mode run.',
                        `sortOrder` int NOT NULL DEFAULT '0' COMMENT 'Last used sorting for collections.',
                        `sortColor` int NOT NULL DEFAULT '0' COMMENT 'Last used color sorting for collections.',
                        `sound` varchar(11) NOT NULL DEFAULT '0' COMMENT 'Sound on/off',
                        `texture` varchar(100) NOT NULL DEFAULT '222222221111111111111111111111111111111111111111111',
                        `ip` varchar(40) DEFAULT NULL COMMENT 'IP address. ',
                        `location` varchar(10) DEFAULT NULL COMMENT 'Estimated location.',
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `name_index` (`name`) USING BTREE,
                        KEY `email_index` (`email`)
                      ) ENGINE=InnoDB AUTO_INCREMENT=25644 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;");
    }
}
