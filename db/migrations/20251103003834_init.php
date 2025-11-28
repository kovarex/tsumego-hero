<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/* Original state of the database from tsumego-hero.com */
final class Init extends AbstractMigration
{
	public function up(): void
	{
		// the schema already exists lets skip this step (the skip is needed once we want to migrate the real db)
		if ($this->hasTable('users') || $this->hasTable('user')) {
			return;
		}

		$this->execute("
--
-- Table structure for table `users`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=25644 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

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

CREATE TABLE `achievement_conditions` (
	`id` int NOT NULL AUTO_INCREMENT,
	`value` double DEFAULT NULL,
	`user_id` int DEFAULT NULL,
	`set_id` int DEFAULT NULL,
	`category` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
	`created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=63628 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `achievement_statuses`
--

CREATE TABLE `achievement_statuses` (
	`id` int NOT NULL AUTO_INCREMENT,
	`user_id` int DEFAULT NULL,
	`achievement_id` int DEFAULT NULL,
	`value` int NOT NULL DEFAULT '0',
	`created` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=113281 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `achievements`
--

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

INSERT INTO `achievements` (`id`, `name`, `description`, `image`, `color`, `order`, `xp`, `additionalDescription`, `created`) VALUES
(1, '1000 Problems', 'Solve 1000 problems.', 'ac001', 'achievementColorBlack', 21, 1000, NULL, '2023-10-16 00:42:21'),
(2, '2000 Problems', 'Solve 2000 problems.', 'ac002', 'achievementColorBlack', 22, 2000, NULL, '2023-10-16 00:44:07'),
(3, '3000 Problems', 'Solve 3000 problems.', 'ac003', 'achievementColorBlack', 23, 3000, NULL, '2023-10-16 00:45:10'),
(4, '4000 Problems', 'Solve 4000 problems.', 'ac004', 'achievementColorBlack', 24, 4000, NULL, '2023-10-16 00:45:18'),
(5, '5000 Problems', 'Solve 5000 problems.', 'ac005', 'achievementColorBlack', 25, 5000, NULL, '2023-10-16 00:45:26'),
(6, '6000 Problems', 'Solve 6000 problems.', 'ac006', 'achievementColorBlack', 26, 6000, NULL, '2023-10-16 00:45:33'),
(7, '7000 Problems', 'Solve 7000 problems.', 'ac007', 'achievementColorBlack', 27, 7000, NULL, '2023-10-16 00:45:43'),
(8, '8000 Problems', 'Solve 8000 problems.', 'ac008', 'achievementColorBlack', 28, 8000, NULL, '2023-10-16 00:45:49'),
(9, '9000 Problems', 'Solve 9000 problems.', 'ac009', 'achievementColorBlack', 29, 9000, NULL, '2023-10-16 00:45:55'),
(10, '10000 Problems', 'Solve 10000 problems.', 'ac010', 'achievementColorBlack', 30, 10000, NULL, '2023-10-16 00:46:01'),
(11, 'User of the Day', 'Become User of the Day.', 'ac011', 'achievementColorPurple', 150, 15000, NULL, '2023-10-16 01:22:54'),
(12, 'Accuracy I', 'Finish a collection of 11k or lower   with at least 75% accuracy.', 'ac012', 'achievementColorOrange', 52, 3000, 'The collection must contain at least 100 problems.', '2023-10-16 02:18:09'),
(13, 'Accuracy II', 'Finish a collection of 11k or lower  with at least 85% accuracy.', 'ac013', 'achievementColorOrange', 53, 4000, 'The collection must contain at least 100 problems.', '2023-10-16 02:22:50'),
(14, 'Accuracy III', 'Finish a collection of 11k or lower  with at least 95% accuracy.', 'ac014', 'achievementColorOrange', 54, 6000, 'The collection must contain at least 100 problems.', '2023-10-16 02:23:05'),
(15, 'Accuracy IV', 'Finish a collection between 10k and 8k with at least 75% accuracy.', 'ac015', 'achievementColorOrange', 55, 4000, 'The collection must contain at least 100 problems.', '2023-10-16 02:23:40'),
(16, 'Accuracy V', 'Finish a collection between 10k and 8k with at least 85% accuracy.', 'ac016', 'achievementColorOrange', 56, 6000, 'The collection must contain at least 100 problems.', '2023-10-16 02:24:12'),
(17, 'Accuracy VI', 'Finish a collection between 10k and 8k with at least 95% accuracy.', 'ac017', 'achievementColorOrange', 57, 8000, 'The collection must contain at least 100 problems.', '2023-10-16 02:24:30'),
(18, 'Accuracy VII', 'Finish a collection between 7k and 5k with at least 75% accuracy.', 'ac018', 'achievementColorOrange', 58, 5000, 'The collection must contain at least 100 problems.', '2023-10-16 02:24:50'),
(19, 'Accuracy VIII', 'Finish a collection between 7k and 5k with at least 85% accuracy.', 'ac019', 'achievementColorOrange', 59, 7000, 'The collection must contain at least 100 problems.', '2023-10-16 02:25:19'),
(20, 'Accuracy IX', 'Finish a collection between 7k and 5k with at least 95% accuracy.', 'ac020', 'achievementColorOrange', 60, 10000, 'The collection must contain at least 100 problems.', '2023-10-16 02:25:38'),
(21, 'Accuracy X', 'Finish a collection of 4k or higher with at least 75% accuracy.', 'ac021', 'achievementColorOrange', 61, 6000, 'The collection must contain at least 100 problems.', '2023-10-16 02:25:52'),
(22, 'Accuracy XI', 'Finish a collection of 4k or higher with at least 85% accuracy.', 'ac022', 'achievementColorOrange', 62, 8000, 'The collection must contain at least 100 problems.', '2023-10-16 02:26:06'),
(23, 'Accuracy XII', 'Finish a collection of 4k or higher with at least 95% accuracy.', 'ac023', 'achievementColorOrange', 63, 12000, 'The collection must contain at least 100 problems.', '2023-10-16 02:26:56'),
(24, 'Speed I', 'Finish a collection of 11k or lower  with an avg. time of under 15 seconds.', 'ac024', 'achievementColorGreen', 71, 3000, 'The collection must contain at least 100 problems.', '2023-10-16 02:28:36'),
(25, 'Speed II', 'Finish a collection of 11k or lower  with an avg. time of under 10 seconds.', 'ac025', 'achievementColorGreen', 72, 4000, 'The collection must contain at least 100 problems.', '2023-10-16 02:29:11'),
(26, 'Speed III', 'Finish a collection of 11k or lower  with an avg. time of under 5 seconds.', 'ac026', 'achievementColorGreen', 73, 6000, 'The collection must contain at least 100 problems.', '2023-10-16 02:29:39'),
(27, 'Speed IV', 'Finish a collection between 10k and 8k with an avg. time of under 18 seconds.', 'ac027', 'achievementColorGreen', 74, 4000, 'The collection must contain at least 100 problems.', '2023-10-16 02:30:25'),
(28, 'Speed V', 'Finish a collection between 10k and 8k with an avg. time of under 13 seconds.', 'ac028', 'achievementColorGreen', 75, 6000, 'The collection must contain at least 100 problems.', '2023-10-16 02:30:57'),
(29, 'Speed VI', 'Finish a collection between 10k and 8k with an avg. time of under 8 seconds.', 'ac029', 'achievementColorGreen', 76, 8000, 'The collection must contain at least 100 problems.', '2023-10-16 02:31:18'),
(30, 'Speed VII', 'Finish a collection between 7k and 5k with an avg. time of under 30 seconds.', 'ac030', 'achievementColorGreen', 77, 5000, 'The collection must contain at least 100 problems.', '2023-10-16 02:33:20'),
(31, 'Speed VIII', 'Finish a collection between 7k and 5k with an avg. time of under 20 seconds.', 'ac031', 'achievementColorGreen', 78, 7000, 'The collection must contain at least 100 problems.', '2023-10-16 02:33:38'),
(32, 'Speed IX', 'Finish a collection between 7k and 5k with an avg. time of under 10 seconds.', 'ac032', 'achievementColorGreen', 79, 10000, 'The collection must contain at least 100 problems.', '2023-10-16 02:34:03'),
(33, 'Speed X', 'Finish a collection of 4k or higher with an avg. time of under 30 seconds.', 'ac033', 'achievementColorGreen', 80, 6000, 'The collection must contain at least 100 problems.', '2023-10-16 02:34:32'),
(34, 'Speed XI', 'Finish a collection of 4k or higher with an avg. time of under 20 seconds.', 'ac034', 'achievementColorGreen', 81, 8000, 'The collection must contain at least 100 problems.', '2023-10-16 02:34:43'),
(35, 'Speed XII', 'Finish a collection of 4k or higher with an avg. time of under 10 seconds.', 'ac035', 'achievementColorGreen', 82, 12000, 'The collection must contain at least 100 problems.', '2023-10-16 02:35:19'),
(36, 'Level up!', 'Reach level 10.', 'ac036', 'achievementColorBlue', 1, 100, NULL, '2023-10-16 02:36:46'),
(37, 'First Hero Power', 'Reach level 20.', 'ac037', 'achievementColorBlue', 2, 250, NULL, '2023-10-16 02:37:15'),
(38, 'Upgraded Intuition', 'Reach level 30.', 'ac038', 'achievementColorBlue', 3, 500, NULL, '2023-10-16 02:37:20'),
(39, 'More power!', 'Reach level 40.', 'ac039', 'achievementColorBlue', 4, 750, NULL, '2023-10-16 02:37:27'),
(40, 'Half way to the top.', 'Reach level 50.', 'ac040', 'achievementColorBlue', 5, 1000, NULL, '2023-10-16 02:37:33'),
(41, 'Congrats! Here, have more problems.', 'Reach level 60.', 'ac041', 'achievementColorBlue', 6, 1250, NULL, '2023-10-16 02:37:38'),
(42, 'Nice level, bruh.', 'Reach level 70.', 'ac042', 'achievementColorBlue', 7, 1500, NULL, '2023-10-16 02:37:44'),
(43, 'Did a lot of Tsumego.', 'Reach level 80.', 'ac043', 'achievementColorBlue', 8, 1750, NULL, '2023-10-16 02:37:51'),
(44, 'Still doing the Tsumego thing.', 'Reach level 90.', 'ac044', 'achievementColorBlue', 9, 2000, NULL, '2023-10-16 02:37:56'),
(45, 'The Top', 'Reach level 100.', 'ac045', 'achievementColorBlue', 10, 10000, NULL, '2023-10-16 02:38:02'),
(46, 'Superior Accuracy', 'Finish a collection with 100% accuracy.', 'ac046', 'achievementColorOrange', 65, 5000, 'The collection must contain at least 100 problems. Can be repeated.', '2023-10-16 02:26:56'),
(47, 'Complete Sets I', 'Complete 10 Collections.', 'ac047', 'achievementColorDarkblue', 31, 2000, NULL, '2023-11-04 17:26:35'),
(48, 'Complete Sets II', 'Complete 20 Collections.', 'ac048', 'achievementColorDarkblue', 32, 4000, NULL, '2023-11-04 17:26:42'),
(49, 'Complete Sets III', 'Complete 30 Collections.', 'ac049', 'achievementColorDarkblue', 33, 6000, NULL, '2023-11-04 17:26:50'),
(50, 'Complete Sets IV', 'Complete 40 Collections.', 'ac050', 'achievementColorDarkblue', 34, 8000, NULL, '2023-11-04 17:27:00'),
(51, 'Complete Sets V', 'Complete 50 Collections.', 'ac051', 'achievementColorDarkblue', 35, 10000, NULL, '2023-11-04 17:27:06'),
(52, 'Complete Sets VI', 'Complete 60 Collections.', 'ac052', 'achievementColorDarkblue', 36, 12000, NULL, '2023-11-04 17:27:15'),
(53, 'No Error Streak I', 'Make no error for 10 problems.', 'ac053', 'achievementColorRed', 83, 100, NULL, '2023-11-04 18:55:58'),
(54, 'No Error Streak II', 'Make no error for 20 problems.', 'ac054', 'achievementColorRed', 84, 250, NULL, '2023-11-04 18:55:58'),
(55, 'No Error Streak III', 'Make no error for 30 problems.', 'ac055', 'achievementColorRed', 85, 500, NULL, '2023-11-04 18:55:58'),
(56, 'No Error Streak IV', 'Make no error for 50 problems.', 'ac056', 'achievementColorRed', 86, 1000, NULL, '2023-11-04 18:55:58'),
(57, 'No Error Streak V', 'Make no error for 100 problems.', 'ac057', 'achievementColorRed', 87, 2000, NULL, '2023-11-04 18:55:58'),
(58, 'No Error Streak VI', 'Make no error for 200 problems.', 'ac058', 'achievementColorRed', 88, 5000, NULL, '2023-11-04 18:55:58'),
(59, '6 Kyu Rating', 'Reach a rating of 1500.', 'ac059', 'achievementColorRating', 90, 100, NULL, '2023-11-04 18:55:58'),
(60, '5 Kyu Rating', 'Reach a rating of 1600.', 'ac060', 'achievementColorRating', 91, 250, NULL, '2023-11-04 18:55:58'),
(61, '4 Kyu Rating', 'Reach a rating of 1700.', 'ac061', 'achievementColorRating', 92, 500, NULL, '2023-11-04 18:55:58'),
(62, '3 Kyu Rating', 'Reach a rating of 1800.', 'ac062', 'achievementColorRating', 93, 1000, NULL, '2023-11-04 18:55:58'),
(63, '2 Kyu Rating', 'Reach a rating of 1900.', 'ac063', 'achievementColorRating', 94, 2000, NULL, '2023-11-04 18:55:58'),
(64, '1 Kyu Rating', 'Reach a rating of 2000.', 'ac064', 'achievementColorRating', 95, 3000, NULL, '2023-11-04 18:55:58'),
(65, '1 Dan Rating', 'Reach a rating of 2100.', 'ac065', 'achievementColorRating', 96, 4000, NULL, '2023-11-04 18:55:58'),
(66, '2 Dan Rating', 'Reach a rating of 2200.', 'ac066', 'achievementColorRating', 97, 5000, NULL, '2023-11-04 18:55:58'),
(67, '3 Dan Rating', 'Reach a rating of 2300.', 'ac067', 'achievementColorRating', 98, 6000, NULL, '2023-11-04 18:55:58'),
(68, '4 Dan Rating', 'Reach a rating of 2400.', 'ac068', 'achievementColorRating', 99, 8000, NULL, '2023-11-04 18:55:58'),
(69, '5 Dan Rating', 'Reach a rating of 2500.', 'ac069', 'achievementColorRating', 100, 10000, NULL, '2023-11-04 18:55:58'),
(70, 'Time Mode Apprentice (Slow)', 'Pass the 5k rank in the slow time mode.', 'ac070', 'achievementColorTime', 110, 150, NULL, '2023-11-05 11:06:09'),
(71, 'Time Mode Scholar (Slow)', 'Pass the 4k rank in the slow time mode.', 'ac071', 'achievementColorTime', 111, 300, NULL, '2023-11-05 11:06:09'),
(72, 'Time Mode Labourer (Slow)', 'Pass the 3k rank in the slow time mode.', 'ac072', 'achievementColorTime', 112, 600, NULL, '2023-11-05 11:06:09'),
(73, 'Time Mode Adept (Slow)', 'Pass the 2k rank in the slow time mode.', 'ac073', 'achievementColorTime', 113, 1200, NULL, '2023-11-05 11:06:09'),
(74, 'Time Mode Expert (Slow)', 'Pass the 1k rank in the slow time mode.', 'ac074', 'achievementColorTime', 114, 2400, NULL, '2023-11-05 11:06:09'),
(75, 'Time Mode Master (Slow)', 'Pass the 1d rank in the slow time mode.', 'ac075', 'achievementColorTime', 115, 4800, NULL, '2023-11-05 11:06:09'),
(76, 'Time Mode Apprentice (Fast)', 'Pass the 5k rank in the fast time mode.', 'ac076', 'achievementColorTime', 116, 250, NULL, '2023-11-05 11:06:09'),
(77, 'Time Mode Scholar (Fast)', 'Pass the 4k rank in the fast time mode.', 'ac077', 'achievementColorTime', 117, 500, NULL, '2023-11-05 11:06:09'),
(78, 'Time Mode Labourer (Fast)', 'Pass the 3k rank in the fast time mode.', 'ac078', 'achievementColorTime', 118, 1000, NULL, '2023-11-05 11:06:09'),
(79, 'Time Mode Adept (Fast)', 'Pass the 2k rank in the fast time mode.', 'ac079', 'achievementColorTime', 119, 2000, NULL, '2023-11-05 11:06:09'),
(80, 'Time Mode Expert (Fast)', 'Pass the 1k rank in the fast time mode.', 'ac080', 'achievementColorTime', 120, 4000, NULL, '2023-11-05 11:06:09'),
(81, 'Time Mode Master (Fast)', 'Pass the 1d rank in the fast time mode.', 'ac081', 'achievementColorTime', 121, 8000, NULL, '2023-11-05 11:06:09'),
(82, 'Time Mode Apprentice (Blitz)', 'Pass the 5k rank in the blitz time mode.', 'ac082', 'achievementColorTime', 122, 350, NULL, '2023-11-05 11:06:09'),
(83, 'Time Mode Scholar (Blitz)', 'Pass the 4k rank in the blitz time mode.', 'ac083', 'achievementColorTime', 123, 700, NULL, '2023-11-05 11:06:09'),
(84, 'Time Mode Labourer (Blitz)', 'Pass the 3k rank in the blitz time mode.', 'ac084', 'achievementColorTime', 124, 1400, NULL, '2023-11-05 11:06:09'),
(85, 'Time Mode Adept (Blitz)', 'Pass the 2k rank in the blitz time mode.', 'ac085', 'achievementColorTime', 125, 2800, NULL, '2023-11-05 11:06:09'),
(86, 'Time Mode Expert (Blitz)', 'Pass the 1k rank in the blitz time mode.', 'ac086', 'achievementColorTime', 126, 5600, NULL, '2023-11-05 11:06:09'),
(87, 'Time Mode Master (Blitz)', 'Pass the 1d rank in the blitz time mode.', 'ac087', 'achievementColorTime', 127, 11200, NULL, '2023-11-05 11:06:09'),
(88, 'Time Mode Precision I', 'Reach at least 950 points at 10k or stronger.', 'ac088', 'achievementColorTime', 128, 2500, NULL, '2023-11-05 11:06:09'),
(89, 'Time Mode Precision II', 'Reach at least 900 points at 8k or stronger.', 'ac089', 'achievementColorTime', 129, 3500, NULL, '2023-11-05 11:06:09'),
(90, 'Time Mode Precision III', 'Reach at least 875 points at 6k or stronger.', 'ac090', 'achievementColorTime', 130, 5000, NULL, '2023-11-05 11:06:09'),
(91, 'Time Mode Precision IV', 'Reach at least 850 points at 4k or stronger.', 'ac091', 'achievementColorTime', 131, 7000, NULL, '2023-11-05 11:06:09'),
(92, 'Life & Death Elementary', 'Finish Life & Death Elementary.', 'ac092', 'achievementColorTeal', 37, 3000, NULL, '2023-12-21 11:06:09'),
(93, 'Life & Death Intermediate', 'Finish Life & Death Intermediate.', 'ac093', 'achievementColorTeal', 38, 4000, NULL, '2023-12-21 11:06:09'),
(94, 'Life & Death Advanced', 'Finish Life & Death Advanced.', 'ac094', 'achievementColorTeal', 39, 10000, NULL, '2023-12-21 11:06:09'),
(95, '1000 Weiqi problems 1st half', 'Finish the first half of 1000 Weiqi problems.', 'ac095', 'achievementColorTeal', 40, 5000, NULL, '2023-12-21 14:44:28'),
(96, 'That escalated quickly!', 'Solve 30 problems within a sprint.', 'ac096', 'achievementColorDarkblue', -8, 5000, NULL, '2023-12-21 14:44:28'),
(97, 'Gold Digger', 'Don\'t fail 10 times in a row on a golden tsumego.', 'ac097', 'achievementColorGold', -7, 5000, NULL, '2023-12-21 14:51:52'),
(98, 'Bad Potion', 'Have the potion not triggered 15 times on the same day.', 'ac098', 'achievementColorRed', -6, 5000, NULL, '2023-12-21 14:51:52'),
(99, 'Those are my favories', 'Create a collection with your very own favorite problems.', 'ac099', 'achievementColorBlack', -9, 500, NULL, '2023-12-21 14:51:52'),
(100, 'Premium Account', 'Appreciate the work that is done on this website by buying a premium account.', 'ac100', 'achievementColorGreen', -10, 8000, NULL, '2023-12-21 14:51:52'),
(101, 'Solve a 1d problem', 'Solve a 1d problem.', 'ac101', 'achievementColorBluex', 42, 250, NULL, '2023-12-21 14:51:52'),
(102, 'Solve a 2d problem', 'Solve a 2d problem.', 'ac102', 'achievementColorBluex', 43, 400, NULL, '2023-12-21 14:51:52'),
(103, 'Solve a 3d problem', 'Solve a 3d problem.', 'ac103', 'achievementColorBluex', 44, 550, NULL, '2023-12-21 14:51:52'),
(104, 'Solve a 4d problem', 'Solve a 4d problem.', 'ac104', 'achievementColorBluex', 45, 700, NULL, '2023-12-21 14:51:52'),
(105, 'Solve a 5d problem', 'Solve a 5d problem.', 'ac105', 'achievementColorBluex', 46, 850, NULL, '2023-12-21 14:51:52'),
(106, 'Solve 10 1d problems', 'Solve 10 1d problems.', 'ac106', 'achievementColorBluex', 47, 500, NULL, '2023-12-21 14:51:52'),
(107, 'Solve 10 2d problems', 'Solve 10 2d problems.', 'ac107', 'achievementColorBluex', 48, 800, NULL, '2023-12-21 14:51:52'),
(108, 'Solve 10 3d problems', 'Solve 10 3d problems.', 'ac108', 'achievementColorBluex', 49, 1100, NULL, '2023-12-21 14:51:52'),
(109, 'Solve 10 4d problems', 'Solve 10 4d problems.', 'ac109', 'achievementColorBluex', 50, 1400, NULL, '2023-12-21 14:51:52'),
(110, 'Solve 10 5d problems', 'Solve 10 5d problems.', 'ac110', 'achievementColorBluex', 51, 1700, NULL, '2023-12-21 14:51:52'),
(111, 'Emerald', 'Has a chance to trigger once a day on a regular ddk problem.', 'ac111', 'achievementColorTeal', 151, 15000, NULL, '2023-12-21 14:51:52'),
(112, 'Sapphire', 'Has a chance to trigger once a day on a difficult sdk problem.', 'ac112', 'achievementColorTeal', 152, 15000, NULL, '2023-12-21 14:51:52'),
(113, 'Ruby', 'Has a chance to trigger once a day on a difficult dan problem.', 'ac113', 'achievementColorTeal', 153, 15000, NULL, '2023-12-21 14:51:52'),
(114, 'Diamond', 'Conditions unknown.', 'ac114', 'achievementColorTeal', 154, 115000, NULL, '2023-12-21 14:51:52'),
(115, '1000 Weiqi problems 2nd half', 'Finish the second half of 1000 Weiqi problems.', 'ac115', 'achievementColorTeal', 41, 5000, NULL, '2023-12-21 14:44:28');

--
-- Table structure for table `activates`
--

CREATE TABLE `activates` (
	`id` int NOT NULL AUTO_INCREMENT,
	`user_id` int NOT NULL,
	`string` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
	`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `admin_activities`
--

CREATE TABLE `admin_activities` (
	`id` int NOT NULL AUTO_INCREMENT,
	`user_id` int NOT NULL,
	`tsumego_id` int NOT NULL,
	`file` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	`answer` varchar(900) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41357 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `answers`
--

CREATE TABLE `answers` (
	`id` int NOT NULL AUTO_INCREMENT,
	`user_id` int NOT NULL,
	`comment_id` int NOT NULL,
	`message` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	`dismissed` int NOT NULL DEFAULT '0',
	`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8389 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `cake_sessions`
--

CREATE TABLE `cake_sessions` (
	`id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
	`data` text COLLATE utf8mb4_unicode_ci,
	`expires` int DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `expires_idx` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `comments`
--

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

--
-- Table structure for table `day_records`
--

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

--
-- Table structure for table `duplicates`
--

CREATE TABLE `duplicates` (
	`id` int NOT NULL AUTO_INCREMENT,
	`tsumego_id` int NOT NULL,
	`dGroup` int NOT NULL,
	PRIMARY KEY (`id`),
	KEY `tsumego_id` (`tsumego_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2074 DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `favorites`
--

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

--
-- Table structure for table `josekis`
--

CREATE TABLE `josekis` (
	`id` int unsigned NOT NULL AUTO_INCREMENT,
	`tsumego_id` int NOT NULL,
	`type` int NOT NULL,
	`order` double NOT NULL,
	`thumbnail` varchar(100) NOT NULL,
	`hints` varchar(100) NOT NULL DEFAULT '1',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `progress_deletions`
--

CREATE TABLE `progress_deletions` (
	`id` int NOT NULL AUTO_INCREMENT,
	`user_id` int NOT NULL,
	`set_id` int NOT NULL,
	`created` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5239 DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `publish_dates`
--

CREATE TABLE `publish_dates` (
	`id` int NOT NULL AUTO_INCREMENT,
	`tsumego_id` int DEFAULT NULL,
	`date` datetime DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26605 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `purge_lists`
--

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

--
-- Table structure for table `purges`
--

CREATE TABLE `purges` (
	`id` int NOT NULL AUTO_INCREMENT,
	`user_id` int NOT NULL,
	`pre` int NOT NULL,
	`after` int NOT NULL,
	`duplicates` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2480950 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `rank_overviews`
--

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

--
-- Table structure for table `rank_settings`
--

CREATE TABLE `rank_settings` (
	`id` int NOT NULL AUTO_INCREMENT,
	`user_id` int NOT NULL,
	`set_id` int NOT NULL,
	`status` int NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=144600 DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `ranks`
--

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

--
-- Table structure for table `rejects`
--

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

--
-- Table structure for table `reputations`
--

CREATE TABLE `reputations` (
	`id` int NOT NULL AUTO_INCREMENT,
	`user_id` int DEFAULT NULL,
	`tsumego_id` int DEFAULT NULL,
	`set_id` int DEFAULT NULL,
	`value` int DEFAULT NULL,
	`created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7392 DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
	`id` int NOT NULL AUTO_INCREMENT,
	`date` date NOT NULL,
	`tsumego_id` int DEFAULT NULL,
	`set_id` int NOT NULL,
	`published` int NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6980 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `set_connections`
--

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

--
-- Table structure for table `sets`
--

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

--
-- Table structure for table `sgfs`
--

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

--
-- Table structure for table `signatures`
--

CREATE TABLE `signatures` (
	`id` int NOT NULL AUTO_INCREMENT,
	`tsumego_id` int NOT NULL,
	`signature` varchar(500) DEFAULT NULL,
	`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `tsumego_id` (`tsumego_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3862455 DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `sites`
--

CREATE TABLE `sites` (
	`id` int NOT NULL AUTO_INCREMENT,
	`title` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
	`body` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `tag_names`
--

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

--
-- Table structure for table `tags`
--

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
	KEY `tag_id` (`tag_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17769 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `tsumego_attempts`
--

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

--
-- Table structure for table `tsumego_rating_attempts`
--

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

--
-- Table structure for table `tsumego_statuses`
--

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

--
-- Table structure for table `tsumego_variants`
--

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

--
-- Table structure for table `tsumegos`
--

--
-- Table structure for table `user_boards`
--

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

--
-- Table structure for table `user_contributions`
--

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

--
-- Table structure for table `user_sa_maps`
--

CREATE TABLE `user_sa_maps` (
	`id` int NOT NULL AUTO_INCREMENT,
	`uid` int NOT NULL,
	`sid` int NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `user_texture_maps`
--

CREATE TABLE `user_texture_maps` (
	`id` int NOT NULL AUTO_INCREMENT,
	`uid` int NOT NULL,
	`tid` int NOT NULL,
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;");
	}
}
