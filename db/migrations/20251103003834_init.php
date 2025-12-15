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

		$this->execute(<<<'SQL'

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;




CREATE TABLE `achievement` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(500) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `image` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `order` int(11) DEFAULT NULL,
  `xp` int(11) DEFAULT NULL,
  `additionalDescription` varchar(500) DEFAULT NULL,
  `created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `achievement` (`id`, `name`, `description`, `image`, `color`, `order`, `xp`, `additionalDescription`, `created`) VALUES
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
(111, 'Emerald', 'Has a chance to trigger once a day on a difficult ddk problem.', 'ac111', 'achievementColorTeal', 151, 15000, NULL, '2023-12-21 14:51:52'),
(112, 'Sapphire', 'Has a chance to trigger once a day on a difficult sdk problem.', 'ac112', 'achievementColorTeal', 152, 15000, NULL, '2023-12-21 14:51:52'),
(113, 'Ruby', 'Has a chance to trigger once a day on an easy dan problem.', 'ac113', 'achievementColorTeal', 153, 15000, NULL, '2023-12-21 14:51:52'),
(114, 'Diamond', 'Conditions unknown.', 'ac114', 'achievementColorTeal', 154, 115000, NULL, '2023-12-21 14:51:52'),
(115, '1000 Weiqi problems 2nd half', 'Finish the second half of 1000 Weiqi problems.', 'ac115', 'achievementColorTeal', 41, 5000, NULL, '2023-12-21 14:44:28');



CREATE TABLE `achievement_condition` (
  `id` int(11) NOT NULL,
  `value` double DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `set_id` int(10) UNSIGNED DEFAULT NULL,
  `category` varchar(500) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `achievement_status` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `achievement_id` int(10) UNSIGNED NOT NULL,
  `value` int(11) NOT NULL DEFAULT 0,
  `created` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `activate` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `string` varchar(100) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;



CREATE TABLE `admin_activity` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `tsumego_id` int(10) UNSIGNED DEFAULT NULL,
  `set_id` int(10) UNSIGNED DEFAULT NULL,
  `type` int(10) UNSIGNED NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `admin_activity_type` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `admin_activity_type` (`id`, `name`) VALUES
(23, 'Accept Tag'),
(4, 'Alternative Response'),
(19, 'Author Edit'),
(1, 'Description Edit'),
(2, 'Hint Edit'),
(22, 'Maximum Rating Edit'),
(21, 'Minimum Rating Edit'),
(6, 'Multiple Choice'),
(5, 'Pass Mode'),
(14, 'Problem Add'),
(3, 'Problem Delete'),
(20, 'Rating Edit'),
(24, 'Reject Tag'),
(7, 'Score Estimating'),
(15, 'Set Alternative Response'),
(11, 'Set Color Edit'),
(10, 'Set Description Edit'),
(12, 'Set Order Edit'),
(16, 'Set Pass Mode'),
(13, 'Set Rating Edit'),
(9, 'Set Title Edit'),
(8, 'Solution Request'),
(25, 'Tsumego Merge');



CREATE TABLE `answer` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `message` varchar(1000) NOT NULL,
  `dismissed` int(11) NOT NULL DEFAULT 0,
  `created` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;



CREATE TABLE `cake_sessions` (
  `id` varchar(255) NOT NULL,
  `data` text DEFAULT NULL,
  `expires` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `day_record` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `date` date DEFAULT NULL,
  `solved` int(11) DEFAULT NULL,
  `quote` varchar(10) DEFAULT NULL,
  `usercount` int(11) DEFAULT NULL,
  `visitedproblems` int(11) NOT NULL DEFAULT 0,
  `gems` varchar(50) NOT NULL DEFAULT '0-0-0',
  `gemCounter1` int(11) NOT NULL DEFAULT 0,
  `gemCounter2` int(11) NOT NULL DEFAULT 0,
  `gemCounter3` int(11) NOT NULL DEFAULT 0,
  `tsumego_count` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;



CREATE TABLE `favorite` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `tsumego_id` int(10) UNSIGNED NOT NULL,
  `created` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `phpbb_acl_groups` (
  `group_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `forum_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `auth_option_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `auth_role_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `auth_setting` tinyint(2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;


CREATE TABLE `phpbb_acl_options` (
  `auth_option_id` mediumint(8) UNSIGNED NOT NULL,
  `auth_option` varchar(50) NOT NULL DEFAULT '',
  `is_global` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `is_local` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `founder_only` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_acl_options` (`auth_option_id`, `auth_option`, `is_global`, `is_local`, `founder_only`) VALUES
(1, 'f_', 0, 1, 0),
(2, 'f_announce', 0, 1, 0),
(3, 'f_announce_global', 0, 1, 0),
(4, 'f_attach', 0, 1, 0),
(5, 'f_bbcode', 0, 1, 0),
(6, 'f_bump', 0, 1, 0),
(7, 'f_delete', 0, 1, 0),
(8, 'f_download', 0, 1, 0),
(9, 'f_edit', 0, 1, 0),
(10, 'f_email', 0, 1, 0),
(11, 'f_flash', 0, 1, 0),
(12, 'f_icons', 0, 1, 0),
(13, 'f_ignoreflood', 0, 1, 0),
(14, 'f_img', 0, 1, 0),
(15, 'f_list', 0, 1, 0),
(16, 'f_list_topics', 0, 1, 0),
(17, 'f_noapprove', 0, 1, 0),
(18, 'f_poll', 0, 1, 0),
(19, 'f_post', 0, 1, 0),
(20, 'f_postcount', 0, 1, 0),
(21, 'f_print', 0, 1, 0),
(22, 'f_read', 0, 1, 0),
(23, 'f_reply', 0, 1, 0),
(24, 'f_report', 0, 1, 0),
(25, 'f_search', 0, 1, 0),
(26, 'f_sigs', 0, 1, 0),
(27, 'f_smilies', 0, 1, 0),
(28, 'f_sticky', 0, 1, 0),
(29, 'f_subscribe', 0, 1, 0),
(30, 'f_user_lock', 0, 1, 0),
(31, 'f_vote', 0, 1, 0),
(32, 'f_votechg', 0, 1, 0),
(33, 'f_softdelete', 0, 1, 0),
(34, 'm_', 1, 1, 0),
(35, 'm_approve', 1, 1, 0),
(36, 'm_chgposter', 1, 1, 0),
(37, 'm_delete', 1, 1, 0),
(38, 'm_edit', 1, 1, 0),
(39, 'm_info', 1, 1, 0),
(40, 'm_lock', 1, 1, 0),
(41, 'm_merge', 1, 1, 0),
(42, 'm_move', 1, 1, 0),
(43, 'm_report', 1, 1, 0),
(44, 'm_split', 1, 1, 0),
(45, 'm_softdelete', 1, 1, 0),
(46, 'm_ban', 1, 0, 0),
(47, 'm_pm_report', 1, 0, 0),
(48, 'm_warn', 1, 0, 0),
(49, 'a_', 1, 0, 0),
(50, 'a_aauth', 1, 0, 0),
(51, 'a_attach', 1, 0, 0),
(52, 'a_authgroups', 1, 0, 0),
(53, 'a_authusers', 1, 0, 0),
(54, 'a_backup', 1, 0, 0),
(55, 'a_ban', 1, 0, 0),
(56, 'a_bbcode', 1, 0, 0),
(57, 'a_board', 1, 0, 0),
(58, 'a_bots', 1, 0, 0),
(59, 'a_clearlogs', 1, 0, 0),
(60, 'a_email', 1, 0, 0),
(61, 'a_extensions', 1, 0, 0),
(62, 'a_fauth', 1, 0, 0),
(63, 'a_forum', 1, 0, 0),
(64, 'a_forumadd', 1, 0, 0),
(65, 'a_forumdel', 1, 0, 0),
(66, 'a_group', 1, 0, 0),
(67, 'a_groupadd', 1, 0, 0),
(68, 'a_groupdel', 1, 0, 0),
(69, 'a_icons', 1, 0, 0),
(70, 'a_jabber', 1, 0, 0),
(71, 'a_language', 1, 0, 0),
(72, 'a_mauth', 1, 0, 0),
(73, 'a_modules', 1, 0, 0),
(74, 'a_names', 1, 0, 0),
(75, 'a_phpinfo', 1, 0, 0),
(76, 'a_profile', 1, 0, 0),
(77, 'a_prune', 1, 0, 0),
(78, 'a_ranks', 1, 0, 0),
(79, 'a_reasons', 1, 0, 0),
(80, 'a_roles', 1, 0, 0),
(81, 'a_search', 1, 0, 0),
(82, 'a_server', 1, 0, 0),
(83, 'a_styles', 1, 0, 0),
(84, 'a_switchperm', 1, 0, 0),
(85, 'a_uauth', 1, 0, 0),
(86, 'a_user', 1, 0, 0),
(87, 'a_userdel', 1, 0, 0),
(88, 'a_viewauth', 1, 0, 0),
(89, 'a_viewlogs', 1, 0, 0),
(90, 'a_words', 1, 0, 0),
(91, 'u_', 1, 0, 0),
(92, 'u_attach', 1, 0, 0),
(93, 'u_chgavatar', 1, 0, 0),
(94, 'u_chgcensors', 1, 0, 0),
(95, 'u_chgemail', 1, 0, 0),
(96, 'u_chggrp', 1, 0, 0),
(97, 'u_chgname', 1, 0, 0),
(98, 'u_chgpasswd', 1, 0, 0),
(99, 'u_chgprofileinfo', 1, 0, 0),
(100, 'u_download', 1, 0, 0),
(101, 'u_emoji', 1, 0, 0),
(102, 'u_hideonline', 1, 0, 0),
(103, 'u_ignoreflood', 1, 0, 0),
(104, 'u_masspm', 1, 0, 0),
(105, 'u_masspm_group', 1, 0, 0),
(106, 'u_pm_attach', 1, 0, 0),
(107, 'u_pm_bbcode', 1, 0, 0),
(108, 'u_pm_delete', 1, 0, 0),
(109, 'u_pm_download', 1, 0, 0),
(110, 'u_pm_edit', 1, 0, 0),
(111, 'u_pm_emailpm', 1, 0, 0),
(112, 'u_pm_flash', 1, 0, 0),
(113, 'u_pm_forward', 1, 0, 0),
(114, 'u_pm_img', 1, 0, 0),
(115, 'u_pm_printpm', 1, 0, 0),
(116, 'u_pm_smilies', 1, 0, 0),
(117, 'u_readpm', 1, 0, 0),
(118, 'u_savedrafts', 1, 0, 0),
(119, 'u_search', 1, 0, 0),
(120, 'u_sendemail', 1, 0, 0),
(121, 'u_sendim', 1, 0, 0),
(122, 'u_sendpm', 1, 0, 0),
(123, 'u_sig', 1, 0, 0),
(124, 'u_viewonline', 1, 0, 0),
(125, 'u_viewprofile', 1, 0, 0);

CREATE TABLE `phpbb_acl_roles` (
  `role_id` mediumint(8) UNSIGNED NOT NULL,
  `role_name` varchar(255) NOT NULL DEFAULT '',
  `role_description` text NOT NULL,
  `role_type` varchar(10) NOT NULL DEFAULT '',
  `role_order` smallint(4) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_acl_roles` (`role_id`, `role_name`, `role_description`, `role_type`, `role_order`) VALUES
(1, 'ROLE_ADMIN_STANDARD', 'ROLE_DESCRIPTION_ADMIN_STANDARD', 'a_', 1),
(2, 'ROLE_ADMIN_FORUM', 'ROLE_DESCRIPTION_ADMIN_FORUM', 'a_', 3),
(3, 'ROLE_ADMIN_USERGROUP', 'ROLE_DESCRIPTION_ADMIN_USERGROUP', 'a_', 4),
(4, 'ROLE_ADMIN_FULL', 'ROLE_DESCRIPTION_ADMIN_FULL', 'a_', 2),
(5, 'ROLE_USER_FULL', 'ROLE_DESCRIPTION_USER_FULL', 'u_', 3),
(6, 'ROLE_USER_STANDARD', 'ROLE_DESCRIPTION_USER_STANDARD', 'u_', 1),
(7, 'ROLE_USER_LIMITED', 'ROLE_DESCRIPTION_USER_LIMITED', 'u_', 2),
(8, 'ROLE_USER_NOPM', 'ROLE_DESCRIPTION_USER_NOPM', 'u_', 4),
(9, 'ROLE_USER_NOAVATAR', 'ROLE_DESCRIPTION_USER_NOAVATAR', 'u_', 5),
(10, 'ROLE_MOD_FULL', 'ROLE_DESCRIPTION_MOD_FULL', 'm_', 3),
(11, 'ROLE_MOD_STANDARD', 'ROLE_DESCRIPTION_MOD_STANDARD', 'm_', 1),
(12, 'ROLE_MOD_SIMPLE', 'ROLE_DESCRIPTION_MOD_SIMPLE', 'm_', 2),
(13, 'ROLE_MOD_QUEUE', 'ROLE_DESCRIPTION_MOD_QUEUE', 'm_', 4),
(14, 'ROLE_FORUM_FULL', 'ROLE_DESCRIPTION_FORUM_FULL', 'f_', 7),
(15, 'ROLE_FORUM_STANDARD', 'ROLE_DESCRIPTION_FORUM_STANDARD', 'f_', 5),
(16, 'ROLE_FORUM_NOACCESS', 'ROLE_DESCRIPTION_FORUM_NOACCESS', 'f_', 1),
(17, 'ROLE_FORUM_READONLY', 'ROLE_DESCRIPTION_FORUM_READONLY', 'f_', 2),
(18, 'ROLE_FORUM_LIMITED', 'ROLE_DESCRIPTION_FORUM_LIMITED', 'f_', 3),
(19, 'ROLE_FORUM_BOT', 'ROLE_DESCRIPTION_FORUM_BOT', 'f_', 9),
(20, 'ROLE_FORUM_ONQUEUE', 'ROLE_DESCRIPTION_FORUM_ONQUEUE', 'f_', 8),
(21, 'ROLE_FORUM_POLLS', 'ROLE_DESCRIPTION_FORUM_POLLS', 'f_', 6),
(22, 'ROLE_FORUM_LIMITED_POLLS', 'ROLE_DESCRIPTION_FORUM_LIMITED_POLLS', 'f_', 4),
(23, 'ROLE_USER_NEW_MEMBER', 'ROLE_DESCRIPTION_USER_NEW_MEMBER', 'u_', 6),
(24, 'ROLE_FORUM_NEW_MEMBER', 'ROLE_DESCRIPTION_FORUM_NEW_MEMBER', 'f_', 10);

CREATE TABLE `phpbb_acl_roles_data` (
  `role_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `auth_option_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `auth_setting` tinyint(2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_acl_roles_data` (`role_id`, `auth_option_id`, `auth_setting`) VALUES
(1, 49, 1),
(1, 51, 1),
(1, 52, 1),
(1, 53, 1),
(1, 55, 1),
(1, 56, 1),
(1, 57, 1),
(1, 61, 1),
(1, 62, 1),
(1, 63, 1),
(1, 64, 1),
(1, 65, 1),
(1, 66, 1),
(1, 67, 1),
(1, 68, 1),
(1, 69, 1),
(1, 72, 1),
(1, 74, 1),
(1, 76, 1),
(1, 77, 1),
(1, 78, 1),
(1, 79, 1),
(1, 85, 1),
(1, 86, 1),
(1, 87, 1),
(1, 88, 1),
(1, 89, 1),
(1, 90, 1),
(2, 49, 1),
(2, 52, 1),
(2, 53, 1),
(2, 62, 1),
(2, 63, 1),
(2, 64, 1),
(2, 65, 1),
(2, 72, 1),
(2, 77, 1),
(2, 85, 1),
(2, 88, 1),
(2, 89, 1),
(3, 49, 1),
(3, 52, 1),
(3, 53, 1),
(3, 55, 1),
(3, 66, 1),
(3, 67, 1),
(3, 68, 1),
(3, 78, 1),
(3, 85, 1),
(3, 86, 1),
(3, 88, 1),
(3, 89, 1),
(4, 49, 1),
(4, 50, 1),
(4, 51, 1),
(4, 52, 1),
(4, 53, 1),
(4, 54, 1),
(4, 55, 1),
(4, 56, 1),
(4, 57, 1),
(4, 58, 1),
(4, 59, 1),
(4, 60, 1),
(4, 61, 1),
(4, 62, 1),
(4, 63, 1),
(4, 64, 1),
(4, 65, 1),
(4, 66, 1),
(4, 67, 1),
(4, 68, 1),
(4, 69, 1),
(4, 70, 1),
(4, 71, 1),
(4, 72, 1),
(4, 73, 1),
(4, 74, 1),
(4, 75, 1),
(4, 76, 1),
(4, 77, 1),
(4, 78, 1),
(4, 79, 1),
(4, 80, 1),
(4, 81, 1),
(4, 82, 1),
(4, 83, 1),
(4, 84, 1),
(4, 85, 1),
(4, 86, 1),
(4, 87, 1),
(4, 88, 1),
(4, 89, 1),
(4, 90, 1),
(5, 91, 1),
(5, 92, 1),
(5, 93, 1),
(5, 94, 1),
(5, 95, 1),
(5, 96, 1),
(5, 97, 1),
(5, 98, 1),
(5, 99, 1),
(5, 100, 1),
(5, 101, 1),
(5, 102, 1),
(5, 103, 1),
(5, 104, 1),
(5, 105, 1),
(5, 106, 1),
(5, 107, 1),
(5, 108, 1),
(5, 109, 1),
(5, 110, 1),
(5, 111, 1),
(5, 112, 1),
(5, 113, 1),
(5, 114, 1),
(5, 115, 1),
(5, 116, 1),
(5, 117, 1),
(5, 118, 1),
(5, 119, 1),
(5, 120, 1),
(5, 121, 1),
(5, 122, 1),
(5, 123, 1),
(5, 124, 1),
(5, 125, 1),
(6, 91, 1),
(6, 92, 1),
(6, 93, 1),
(6, 94, 1),
(6, 95, 1),
(6, 98, 1),
(6, 99, 1),
(6, 100, 1),
(6, 101, 1),
(6, 102, 1),
(6, 104, 1),
(6, 105, 1),
(6, 106, 1),
(6, 107, 1),
(6, 108, 1),
(6, 109, 1),
(6, 110, 1),
(6, 111, 1),
(6, 114, 1),
(6, 115, 1),
(6, 116, 1),
(6, 117, 1),
(6, 118, 1),
(6, 119, 1),
(6, 120, 1),
(6, 121, 1),
(6, 122, 1),
(6, 123, 1),
(6, 125, 1),
(7, 91, 1),
(7, 93, 1),
(7, 94, 1),
(7, 95, 1),
(7, 98, 1),
(7, 99, 1),
(7, 100, 1),
(7, 101, 1),
(7, 102, 1),
(7, 107, 1),
(7, 108, 1),
(7, 109, 1),
(7, 110, 1),
(7, 113, 1),
(7, 114, 1),
(7, 115, 1),
(7, 116, 1),
(7, 117, 1),
(7, 122, 1),
(7, 123, 1),
(7, 125, 1),
(8, 91, 1),
(8, 93, 1),
(8, 94, 1),
(8, 95, 1),
(8, 98, 1),
(8, 100, 1),
(8, 102, 1),
(8, 104, 0),
(8, 105, 0),
(8, 117, 0),
(8, 122, 0),
(8, 123, 1),
(8, 125, 1),
(9, 91, 1),
(9, 93, 0),
(9, 94, 1),
(9, 95, 1),
(9, 98, 1),
(9, 99, 1),
(9, 100, 1),
(9, 101, 1),
(9, 102, 1),
(9, 107, 1),
(9, 108, 1),
(9, 109, 1),
(9, 110, 1),
(9, 113, 1),
(9, 114, 1),
(9, 115, 1),
(9, 116, 1),
(9, 117, 1),
(9, 122, 1),
(9, 123, 1),
(9, 125, 1),
(10, 34, 1),
(10, 35, 1),
(10, 36, 1),
(10, 37, 1),
(10, 38, 1),
(10, 39, 1),
(10, 40, 1),
(10, 41, 1),
(10, 42, 1),
(10, 43, 1),
(10, 44, 1),
(10, 45, 1),
(10, 46, 1),
(10, 47, 1),
(10, 48, 1),
(11, 34, 1),
(11, 35, 1),
(11, 37, 1),
(11, 38, 1),
(11, 39, 1),
(11, 40, 1),
(11, 41, 1),
(11, 42, 1),
(11, 43, 1),
(11, 44, 1),
(11, 45, 1),
(11, 47, 1),
(11, 48, 1),
(12, 34, 1),
(12, 37, 1),
(12, 38, 1),
(12, 39, 1),
(12, 43, 1),
(12, 45, 1),
(12, 47, 1),
(13, 34, 1),
(13, 35, 1),
(13, 38, 1),
(14, 1, 1),
(14, 2, 1),
(14, 3, 1),
(14, 4, 1),
(14, 5, 1),
(14, 6, 1),
(14, 7, 1),
(14, 8, 1),
(14, 9, 1),
(14, 10, 1),
(14, 11, 1),
(14, 12, 1),
(14, 13, 1),
(14, 14, 1),
(14, 15, 1),
(14, 16, 1),
(14, 17, 1),
(14, 18, 1),
(14, 19, 1),
(14, 20, 1),
(14, 21, 1),
(14, 22, 1),
(14, 23, 1),
(14, 24, 1),
(14, 25, 1),
(14, 26, 1),
(14, 27, 1),
(14, 28, 1),
(14, 29, 1),
(14, 30, 1),
(14, 31, 1),
(14, 32, 1),
(14, 33, 1),
(15, 1, 1),
(15, 4, 1),
(15, 5, 1),
(15, 6, 1),
(15, 7, 1),
(15, 8, 1),
(15, 9, 1),
(15, 10, 1),
(15, 12, 1),
(15, 14, 1),
(15, 15, 1),
(15, 16, 1),
(15, 17, 1),
(15, 19, 1),
(15, 20, 1),
(15, 21, 1),
(15, 22, 1),
(15, 23, 1),
(15, 24, 1),
(15, 25, 1),
(15, 26, 1),
(15, 27, 1),
(15, 29, 1),
(15, 31, 1),
(15, 32, 1),
(15, 33, 1),
(16, 1, 0),
(17, 1, 1),
(17, 8, 1),
(17, 15, 1),
(17, 16, 1),
(17, 21, 1),
(17, 22, 1),
(17, 25, 1),
(17, 29, 1),
(18, 1, 1),
(18, 5, 1),
(18, 8, 1),
(18, 9, 1),
(18, 10, 1),
(18, 14, 1),
(18, 15, 1),
(18, 16, 1),
(18, 17, 1),
(18, 19, 1),
(18, 20, 1),
(18, 21, 1),
(18, 22, 1),
(18, 23, 1),
(18, 24, 1),
(18, 25, 1),
(18, 26, 1),
(18, 27, 1),
(18, 29, 1),
(18, 31, 1),
(18, 33, 1),
(19, 1, 1),
(19, 8, 1),
(19, 15, 1),
(19, 16, 1),
(19, 21, 1),
(19, 22, 1),
(20, 1, 1),
(20, 4, 1),
(20, 5, 1),
(20, 8, 1),
(20, 9, 1),
(20, 10, 1),
(20, 14, 1),
(20, 15, 1),
(20, 16, 1),
(20, 17, 0),
(20, 19, 1),
(20, 20, 1),
(20, 21, 1),
(20, 22, 1),
(20, 23, 1),
(20, 24, 1),
(20, 25, 1),
(20, 26, 1),
(20, 27, 1),
(20, 29, 1),
(20, 31, 1),
(20, 33, 1),
(21, 1, 1),
(21, 4, 1),
(21, 5, 1),
(21, 6, 1),
(21, 7, 1),
(21, 8, 1),
(21, 9, 1),
(21, 10, 1),
(21, 12, 1),
(21, 14, 1),
(21, 15, 1),
(21, 16, 1),
(21, 17, 1),
(21, 18, 1),
(21, 19, 1),
(21, 20, 1),
(21, 21, 1),
(21, 22, 1),
(21, 23, 1),
(21, 24, 1),
(21, 25, 1),
(21, 26, 1),
(21, 27, 1),
(21, 29, 1),
(21, 31, 1),
(21, 32, 1),
(21, 33, 1),
(22, 1, 1),
(22, 5, 1),
(22, 8, 1),
(22, 9, 1),
(22, 10, 1),
(22, 14, 1),
(22, 15, 1),
(22, 16, 1),
(22, 17, 1),
(22, 18, 1),
(22, 19, 1),
(22, 20, 1),
(22, 21, 1),
(22, 22, 1),
(22, 23, 1),
(22, 24, 1),
(22, 25, 1),
(22, 26, 1),
(22, 27, 1),
(22, 29, 1),
(22, 31, 1),
(22, 33, 1),
(23, 99, 0),
(23, 104, 0),
(23, 105, 0),
(23, 122, 0),
(24, 17, 0);

CREATE TABLE `phpbb_acl_users` (
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `forum_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `auth_option_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `auth_role_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `auth_setting` tinyint(2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_acl_users` (`user_id`, `forum_id`, `auth_option_id`, `auth_role_id`, `auth_setting`) VALUES
(2, 0, 0, 5, 0);

CREATE TABLE `phpbb_attachments` (
  `attach_id` int(10) UNSIGNED NOT NULL,
  `post_msg_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `topic_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `in_message` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `poster_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_orphan` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `physical_filename` varchar(255) NOT NULL DEFAULT '',
  `real_filename` varchar(255) NOT NULL DEFAULT '',
  `download_count` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `attach_comment` text NOT NULL,
  `extension` varchar(100) NOT NULL DEFAULT '',
  `mimetype` varchar(100) NOT NULL DEFAULT '',
  `filesize` int(20) UNSIGNED NOT NULL DEFAULT 0,
  `filetime` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `thumbnail` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_banlist` (
  `ban_id` int(10) UNSIGNED NOT NULL,
  `ban_userid` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ban_ip` varchar(40) NOT NULL DEFAULT '',
  `ban_email` varchar(100) NOT NULL DEFAULT '',
  `ban_start` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `ban_end` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `ban_exclude` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `ban_reason` varchar(255) NOT NULL DEFAULT '',
  `ban_give_reason` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_bbcodes` (
  `bbcode_id` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `bbcode_tag` varchar(16) NOT NULL DEFAULT '',
  `bbcode_helpline` text NOT NULL,
  `display_on_posting` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `bbcode_match` text NOT NULL,
  `bbcode_tpl` mediumtext NOT NULL,
  `first_pass_match` mediumtext NOT NULL,
  `first_pass_replace` mediumtext NOT NULL,
  `second_pass_match` mediumtext NOT NULL,
  `second_pass_replace` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_bookmarks` (
  `topic_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_bots` (
  `bot_id` int(10) UNSIGNED NOT NULL,
  `bot_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `bot_name` varchar(255) NOT NULL DEFAULT '',
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `bot_agent` varchar(255) NOT NULL DEFAULT '',
  `bot_ip` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_bots` (`bot_id`, `bot_active`, `bot_name`, `user_id`, `bot_agent`, `bot_ip`) VALUES
(1, 1, 'AdsBot [Google]', 3, 'AdsBot-Google', ''),
(2, 1, 'Ahrefs [Bot]', 4, 'AhrefsBot/', ''),
(3, 1, 'Alexa [Bot]', 5, 'ia_archiver', ''),
(4, 1, 'Alta Vista [Bot]', 6, 'Scooter/', ''),
(5, 1, 'Amazon [Bot]', 7, 'Amazonbot/', ''),
(6, 1, 'Ask Jeeves [Bot]', 8, 'Ask Jeeves', ''),
(7, 1, 'Baidu [Spider]', 9, 'Baiduspider', ''),
(8, 1, 'Bing [Bot]', 10, 'bingbot/', ''),
(9, 1, 'DuckDuckGo [Bot]', 11, 'DuckDuckBot/', ''),
(10, 1, 'Exabot [Bot]', 12, 'Exabot/', ''),
(11, 1, 'FAST Enterprise [Crawler]', 13, 'FAST Enterprise Crawler', ''),
(12, 1, 'FAST WebCrawler [Crawler]', 14, 'FAST-WebCrawler/', ''),
(13, 1, 'Francis [Bot]', 15, 'http://www.neomo.de/', ''),
(14, 1, 'Gigabot [Bot]', 16, 'Gigabot/', ''),
(15, 1, 'Google Adsense [Bot]', 17, 'Mediapartners-Google', ''),
(16, 1, 'Google Desktop', 18, 'Google Desktop', ''),
(17, 1, 'Google Feedfetcher', 19, 'Feedfetcher-Google', ''),
(18, 1, 'Google [Bot]', 20, 'Googlebot', ''),
(19, 1, 'Heise IT-Markt [Crawler]', 21, 'heise-IT-Markt-Crawler', ''),
(20, 1, 'Heritrix [Crawler]', 22, 'heritrix/1.', ''),
(21, 1, 'IBM Research [Bot]', 23, 'ibm.com/cs/crawler', ''),
(22, 1, 'ICCrawler - ICjobs', 24, 'ICCrawler - ICjobs', ''),
(23, 1, 'ichiro [Crawler]', 25, 'ichiro/', ''),
(24, 1, 'Majestic-12 [Bot]', 26, 'MJ12bot/', ''),
(25, 1, 'Metager [Bot]', 27, 'MetagerBot/', ''),
(26, 1, 'MSN NewsBlogs', 28, 'msnbot-NewsBlogs/', ''),
(27, 1, 'MSN [Bot]', 29, 'msnbot/', ''),
(28, 1, 'MSNbot Media', 30, 'msnbot-media/', ''),
(29, 1, 'NG-Search [Bot]', 31, 'NG-Search/', ''),
(30, 1, 'Nutch [Bot]', 32, 'http://lucene.apache.org/nutch/', ''),
(31, 1, 'Nutch/CVS [Bot]', 33, 'NutchCVS/', ''),
(32, 1, 'OmniExplorer [Bot]', 34, 'OmniExplorer_Bot/', ''),
(33, 1, 'Online link [Validator]', 35, 'online link validator', ''),
(34, 1, 'psbot [Picsearch]', 36, 'psbot/0', ''),
(35, 1, 'Seekport [Bot]', 37, 'Seekbot/', ''),
(36, 1, 'Semrush [Bot]', 38, 'SemrushBot/', ''),
(37, 1, 'Sensis [Crawler]', 39, 'Sensis Web Crawler', ''),
(38, 1, 'SEO Crawler', 40, 'SEO search Crawler/', ''),
(39, 1, 'Seoma [Crawler]', 41, 'Seoma [SEO Crawler]', ''),
(40, 1, 'SEOSearch [Crawler]', 42, 'SEOsearch/', ''),
(41, 1, 'Snappy [Bot]', 43, 'Snappy/1.1 ( http://www.urltrends.com/ )', ''),
(42, 1, 'Steeler [Crawler]', 44, 'http://www.tkl.iis.u-tokyo.ac.jp/~crawler/', ''),
(43, 1, 'Synoo [Bot]', 45, 'SynooBot/', ''),
(44, 1, 'Telekom [Bot]', 46, 'crawleradmin.t-info@telekom.de', ''),
(45, 1, 'TurnitinBot [Bot]', 47, 'TurnitinBot/', ''),
(46, 1, 'Voyager [Bot]', 48, 'voyager/', ''),
(47, 1, 'W3 [Sitesearch]', 49, 'W3 SiteSearch Crawler', ''),
(48, 1, 'W3C [Linkcheck]', 50, 'W3C-checklink/', ''),
(49, 1, 'W3C [Validator]', 51, 'W3C_*Validator', ''),
(50, 1, 'WiseNut [Bot]', 52, 'http://www.WISEnutbot.com', ''),
(51, 1, 'YaCy [Bot]', 53, 'yacybot', ''),
(52, 1, 'Yahoo MMCrawler [Bot]', 54, 'Yahoo-MMCrawler/', ''),
(53, 1, 'Yahoo Slurp [Bot]', 55, 'Yahoo! DE Slurp', ''),
(54, 1, 'Yahoo [Bot]', 56, 'Yahoo! Slurp', ''),
(55, 1, 'YahooSeeker [Bot]', 57, 'YahooSeeker/', '');

CREATE TABLE `phpbb_config` (
  `config_name` varchar(255) NOT NULL DEFAULT '',
  `config_value` varchar(255) NOT NULL DEFAULT '',
  `is_dynamic` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_config` (`config_name`, `config_value`, `is_dynamic`) VALUES
('active_sessions', '0', 0),
('allow_attachments', '1', 0),
('allow_autologin', '1', 0),
('allow_avatar', '1', 0),
('allow_avatar_gravatar', '0', 0),
('allow_avatar_local', '0', 0),
('allow_avatar_remote', '0', 0),
('allow_avatar_remote_upload', '0', 0),
('allow_avatar_upload', '1', 0),
('allow_bbcode', '1', 0),
('allow_birthdays', '1', 0),
('allow_board_notifications', '1', 0),
('allow_bookmarks', '1', 0),
('allow_cdn', '0', 0),
('allow_emailreuse', '0', 0),
('allow_forum_notify', '1', 0),
('allow_live_searches', '1', 0),
('allow_mass_pm', '1', 0),
('allow_name_chars', 'USERNAME_CHARS_ANY', 0),
('allow_namechange', '0', 0),
('allow_nocensors', '0', 0),
('allow_password_reset', '1', 0),
('allow_pm_attach', '0', 0),
('allow_pm_report', '1', 0),
('allow_post_flash', '1', 0),
('allow_post_links', '1', 0),
('allow_privmsg', '1', 0),
('allow_quick_reply', '1', 0),
('allow_sig', '1', 0),
('allow_sig_bbcode', '1', 0),
('allow_sig_flash', '0', 0),
('allow_sig_img', '1', 0),
('allow_sig_links', '1', 0),
('allow_sig_pm', '1', 0),
('allow_sig_smilies', '1', 0),
('allow_smilies', '1', 0),
('allow_topic_notify', '1', 0),
('allow_viglink_phpbb', '1', 0),
('allowed_schemes_links', 'http,https,ftp', 0),
('assets_version', '3', 0),
('attachment_quota', '52428800', 0),
('auth_bbcode_pm', '1', 0),
('auth_flash_pm', '0', 0),
('auth_img_pm', '1', 0),
('auth_method', 'jwtauth', 0),
('auth_oauth_bitly_key', '', 0),
('auth_oauth_bitly_secret', '', 0),
('auth_oauth_facebook_key', '', 0),
('auth_oauth_facebook_secret', '', 0),
('auth_oauth_google_key', '', 0),
('auth_oauth_google_secret', '', 0),
('auth_oauth_twitter_key', '', 0),
('auth_oauth_twitter_secret', '', 0),
('auth_smilies_pm', '1', 0),
('avatar_filesize', '6144', 0),
('avatar_gallery_path', 'images/avatars/gallery', 0),
('avatar_max_height', '90', 0),
('avatar_max_width', '90', 0),
('avatar_min_height', '20', 0),
('avatar_min_width', '20', 0),
('avatar_path', 'images/avatars/upload', 0),
('avatar_salt', '74df6955a58b445cf79d3daf842847a0', 0),
('board_contact', 'kovarex@gmail.com', 0),
('board_contact_name', '', 0),
('board_disable', '0', 0),
('board_disable_msg', '', 0),
('board_email', 'kovarex@gmail.com', 0),
('board_email_form', '0', 0),
('board_email_sig', 'Thanks, The Management', 0),
('board_hide_emails', '1', 0),
('board_index_text', '', 0),
('board_startdate', '1763867216', 0),
('board_timezone', 'UTC', 0),
('browser_check', '1', 0),
('bump_interval', '10', 0),
('bump_type', 'd', 0),
('cache_gc', '7200', 0),
('cache_last_gc', '1764884353', 1),
('captcha_gd', '1', 0),
('captcha_gd_3d_noise', '1', 0),
('captcha_gd_fonts', '1', 0),
('captcha_gd_foreground_noise', '0', 0),
('captcha_gd_wave', '0', 0),
('captcha_gd_x_grid', '25', 0),
('captcha_gd_y_grid', '25', 0),
('captcha_plugin', 'core.captcha.plugins.gd', 0),
('check_attachment_content', '1', 0),
('check_dnsbl', '0', 0),
('chg_passforce', '0', 0),
('confirm_refresh', '1', 0),
('contact_admin_form_enable', '1', 0),
('cookie_domain', 'tsumego.com', 0),
('cookie_name', 'phpbb3_ei5ak', 0),
('cookie_notice', '0', 0),
('cookie_path', '/', 0),
('cookie_secure', '1', 0),
('coppa_enable', '0', 0),
('coppa_fax', '', 0),
('coppa_mail', '', 0),
('cron_lock', '0', 1),
('database_gc', '604800', 0),
('database_last_gc', '1764477860', 1),
('dbms_version', '11.7.2-MariaDB-ubu2404', 0),
('default_dateformat', 'D M d, Y g:i a', 0),
('default_lang', 'en', 0),
('default_search_return_chars', '300', 0),
('default_style', '1', 0),
('delete_time', '0', 0),
('display_last_edited', '1', 0),
('display_last_subject', '1', 0),
('display_order', '0', 0),
('display_unapproved_posts', '1', 0),
('edit_time', '0', 0),
('email_check_mx', '1', 0),
('email_enable', '1', 0),
('email_force_sender', '0', 0),
('email_max_chunk_size', '50', 0),
('email_package_size', '20', 0),
('enable_accurate_pm_button', '1', 0),
('enable_confirm', '1', 0),
('enable_mod_rewrite', '0', 0),
('enable_pm_icons', '1', 0),
('enable_post_confirm', '1', 0),
('enable_queue_trigger', '0', 0),
('enable_update_hashes', '0', 0),
('extension_force_unstable', '0', 0),
('feed_enable', '1', 0),
('feed_forum', '1', 0),
('feed_http_auth', '0', 0),
('feed_item_statistics', '1', 0),
('feed_limit', '10', 0),
('feed_limit_post', '15', 0),
('feed_limit_topic', '10', 0),
('feed_overall', '1', 0),
('feed_overall_forums', '0', 0),
('feed_overall_forums_limit', '15', 0),
('feed_overall_topics', '0', 0),
('feed_overall_topics_limit', '15', 0),
('feed_topic', '1', 0),
('feed_topics_active', '0', 0),
('feed_topics_new', '1', 0),
('flood_interval', '15', 0),
('force_server_vars', '0', 0),
('form_token_lifetime', '7200', 0),
('form_token_mintime', '0', 0),
('form_token_sid_guests', '1', 0),
('forward_pm', '1', 0),
('forwarded_for_check', '0', 0),
('full_folder_action', '2', 0),
('fulltext_mysql_max_word_len', '254', 0),
('fulltext_mysql_min_word_len', '4', 0),
('fulltext_native_common_thres', '5', 0),
('fulltext_native_load_upd', '1', 0),
('fulltext_native_max_chars', '14', 0),
('fulltext_native_min_chars', '3', 0),
('fulltext_postgres_max_word_len', '254', 0),
('fulltext_postgres_min_word_len', '4', 0),
('fulltext_postgres_ts_name', 'simple', 0),
('fulltext_sphinx_indexer_mem_limit', '512', 0),
('fulltext_sphinx_stopwords', '0', 0),
('gzip_compress', '0', 0),
('help_send_statistics', '1', 0),
('help_send_statistics_time', '0', 0),
('hot_threshold', '25', 0),
('icons_path', 'images/icons', 0),
('img_create_thumbnail', '0', 0),
('img_display_inlined', '1', 0),
('img_link_height', '0', 0),
('img_link_width', '0', 0),
('img_max_height', '0', 0),
('img_max_thumb_width', '400', 0),
('img_max_width', '0', 0),
('img_min_thumb_filesize', '12000', 0),
('img_quality', '85', 0),
('img_strip_metadata', '0', 0),
('ip_check', '3', 0),
('ip_login_limit_max', '50', 0),
('ip_login_limit_time', '21600', 0),
('ip_login_limit_use_forwarded', '0', 0),
('jab_allow_self_signed', '0', 0),
('jab_enable', '0', 0),
('jab_host', '', 0),
('jab_package_size', '20', 0),
('jab_password', '', 0),
('jab_port', '5222', 0),
('jab_use_ssl', '0', 0),
('jab_username', '', 0),
('jab_verify_peer', '1', 0),
('jab_verify_peer_name', '1', 0),
('last_queue_run', '1764777448', 1),
('ldap_base_dn', '', 0),
('ldap_email', '', 0),
('ldap_password', '', 0),
('ldap_port', '', 0),
('ldap_server', '', 0),
('ldap_uid', '', 0),
('ldap_user', '', 0),
('ldap_user_filter', '', 0),
('legend_sort_groupname', '0', 0),
('limit_load', '0', 0),
('limit_search_load', '0', 0),
('load_anon_lastread', '0', 0),
('load_birthdays', '1', 0),
('load_cpf_memberlist', '1', 0),
('load_cpf_pm', '1', 0),
('load_cpf_viewprofile', '1', 0),
('load_cpf_viewtopic', '1', 0),
('load_db_lastread', '1', 0),
('load_db_track', '1', 0),
('load_font_awesome_url', 'https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css', 0),
('load_jquery_url', '//ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js', 0),
('load_jumpbox', '1', 0),
('load_moderators', '1', 0),
('load_notifications', '1', 0),
('load_online', '1', 0),
('load_online_guests', '1', 0),
('load_online_time', '5', 0),
('load_onlinetrack', '1', 0),
('load_search', '1', 0),
('load_tplcompile', '0', 0),
('load_unreads_search', '1', 0),
('load_user_activity', '1', 0),
('load_user_activity_limit', '5000', 0),
('max_attachments', '3', 0),
('max_attachments_pm', '1', 0),
('max_autologin_time', '0', 0),
('max_filesize', '262144', 0),
('max_filesize_pm', '262144', 0),
('max_login_attempts', '3', 0),
('max_name_chars', '20', 0),
('max_num_search_keywords', '10', 0),
('max_poll_options', '10', 0),
('max_post_chars', '60000', 0),
('max_post_font_size', '200', 0),
('max_post_img_height', '0', 0),
('max_post_img_width', '0', 0),
('max_post_smilies', '0', 0),
('max_post_urls', '0', 0),
('max_quote_depth', '3', 0),
('max_reg_attempts', '5', 0),
('max_sig_chars', '255', 0),
('max_sig_font_size', '200', 0),
('max_sig_img_height', '0', 0),
('max_sig_img_width', '0', 0),
('max_sig_smilies', '0', 0),
('max_sig_urls', '5', 0),
('mime_triggers', 'body|head|html|img|plaintext|a href|pre|script|table|title', 0),
('min_name_chars', '3', 0),
('min_pass_chars', '6', 0),
('min_post_chars', '1', 0),
('min_search_author_chars', '3', 0),
('new_member_group_default', '0', 0),
('new_member_post_limit', '3', 0),
('newest_user_colour', '', 1),
('newest_user_id', '143', 1),
('newest_username', 'g__Nikolai Pridachin', 1),
('num_files', '0', 1),
('num_posts', '77', 1),
('num_topics', '25', 1),
('num_users', '86', 1),
('override_user_style', '0', 0),
('pass_complex', 'PASS_TYPE_ANY', 0),
('phpbb_viglink_api_key', 'e4fd14f5d7f2bb6d80b8f8da1354718c', 0),
('plupload_last_gc', '0', 1),
('plupload_salt', '90f85f442d4ca10bedb765b4b3bc0d61', 0),
('pm_edit_time', '0', 0),
('pm_max_boxes', '4', 0),
('pm_max_msgs', '50', 0),
('pm_max_recipients', '0', 0),
('posts_per_page', '10', 0),
('print_pm', '1', 0),
('questionnaire_unique_id', '83j2sruh6o6pzohc', 0),
('queue_interval', '60', 0),
('queue_trigger_posts', '3', 0),
('rand_seed', '0', 1),
('rand_seed_last_update', '0', 1),
('ranks_path', 'images/ranks', 0),
('read_notification_expire_days', '30', 0),
('read_notification_gc', '86400', 0),
('read_notification_last_gc', '1764853999', 1),
('recaptcha_v3_domain', 'google.com', 0),
('recaptcha_v3_key', '', 0),
('recaptcha_v3_method', 'post', 0),
('recaptcha_v3_secret', '', 0),
('recaptcha_v3_threshold_default', '0.5', 0),
('recaptcha_v3_threshold_login', '0.5', 0),
('recaptcha_v3_threshold_post', '0.5', 0),
('recaptcha_v3_threshold_register', '0.5', 0),
('recaptcha_v3_threshold_report', '0.5', 0),
('record_online_date', '1764014028', 1),
('record_online_users', '6', 1),
('referer_validation', '0', 0),
('remote_upload_verify', '0', 0),
('reparse_lock', '0', 1),
('require_activation', '0', 0),
('script_path', '/forums', 0),
('search_anonymous_interval', '0', 0),
('search_block_size', '250', 0),
('search_gc', '7200', 0),
('search_indexing_state', '', 1),
('search_interval', '0', 0),
('search_last_gc', '1764882274', 1),
('search_store_results', '1800', 0),
('search_type', '\\phpbb\\search\\fulltext_native', 0),
('secure_allow_deny', '1', 0),
('secure_allow_empty_referer', '1', 0),
('secure_downloads', '0', 0),
('server_name', 'tsumego.com', 0),
('server_port', '80', 0),
('server_protocol', 'https://', 0),
('session_gc', '3600', 0),
('session_last_gc', '1764877532', 1),
('session_length', '3600', 0),
('site_desc', 'Forums related to tsumego.com', 0),
('site_home_text', '', 0),
('site_home_url', '', 0),
('sitename', 'tsumego.com', 0),
('smilies_path', 'images/smilies', 0),
('smilies_per_page', '50', 0),
('smtp_allow_self_signed', '0', 0),
('smtp_auth_method', 'PLAIN', 0),
('smtp_delivery', '0', 0),
('smtp_host', '', 0),
('smtp_password', '', 1),
('smtp_port', '', 0),
('smtp_username', '', 1),
('smtp_verify_peer', '1', 0),
('smtp_verify_peer_name', '1', 0),
('teampage_forums', '1', 0),
('teampage_memberships', '1', 0),
('text_reparser.pm_text_cron_interval', '10', 0),
('text_reparser.pm_text_last_cron', '1764698568', 0),
('text_reparser.poll_option_cron_interval', '10', 0),
('text_reparser.poll_option_last_cron', '1764879103', 0),
('text_reparser.poll_title_cron_interval', '10', 0),
('text_reparser.poll_title_last_cron', '1763867881', 0),
('text_reparser.post_text_cron_interval', '10', 0),
('text_reparser.post_text_last_cron', '1763868064', 0),
('text_reparser.user_signature_cron_interval', '10', 0),
('text_reparser.user_signature_last_cron', '1763867709', 0),
('topics_per_page', '25', 0),
('tpl_allow_php', '0', 0),
('update_hashes_last_cron', '1763868063', 0),
('update_hashes_lock', '0', 0),
('upload_dir_size', '0', 1),
('upload_icons_path', 'images/upload_icons', 0),
('upload_path', 'files', 0),
('use_system_cron', '0', 0),
('version', '3.3.15', 0),
('viglink_api_siteid', 'd41d8cd98f00b204e9800998ecf8427e', 0),
('viglink_ask_admin', '', 0),
('viglink_ask_admin_last', '1764737038', 0),
('viglink_convert_account_url', '', 0),
('viglink_enabled', '0', 0),
('viglink_last_gc', '1764737038', 1),
('warnings_expire_days', '90', 0),
('warnings_gc', '14400', 0),
('warnings_last_gc', '1764867442', 1);

CREATE TABLE `phpbb_config_text` (
  `config_name` varchar(255) NOT NULL DEFAULT '',
  `config_value` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_config_text` (`config_name`, `config_value`) VALUES
('contact_admin_info', ''),
('contact_admin_info_bitfield', ''),
('contact_admin_info_flags', '7'),
('contact_admin_info_uid', ''),
('reparser_resume', 'a:4:{s:28:\"text_reparser.user_signature\";a:3:{s:9:\"range-min\";i:1;s:9:\"range-max\";i:0;s:10:\"range-size\";i:100;}s:24:\"text_reparser.poll_title\";a:3:{s:9:\"range-min\";i:1;s:9:\"range-max\";i:0;s:10:\"range-size\";i:100;}s:23:\"text_reparser.post_text\";a:3:{s:9:\"range-min\";i:1;s:9:\"range-max\";i:0;s:10:\"range-size\";i:100;}s:21:\"text_reparser.pm_text\";a:3:{s:9:\"range-min\";i:1;s:9:\"range-max\";i:0;s:10:\"range-size\";i:100;}}');

CREATE TABLE `phpbb_confirm` (
  `confirm_id` char(32) NOT NULL DEFAULT '',
  `session_id` char(32) NOT NULL DEFAULT '',
  `confirm_type` tinyint(3) NOT NULL DEFAULT 0,
  `code` varchar(8) NOT NULL DEFAULT '',
  `seed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `attempts` mediumint(8) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_disallow` (
  `disallow_id` mediumint(8) UNSIGNED NOT NULL,
  `disallow_username` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_drafts` (
  `draft_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `topic_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `forum_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `save_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `draft_subject` varchar(255) NOT NULL DEFAULT '',
  `draft_message` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_ext` (
  `ext_name` varchar(255) NOT NULL DEFAULT '',
  `ext_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `ext_state` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_ext` (`ext_name`, `ext_active`, `ext_state`) VALUES
('phpbb/viglink', 1, 'b:0;'),
('tsumego/customsso', 1, 'b:0;');

CREATE TABLE `phpbb_extensions` (
  `extension_id` mediumint(8) UNSIGNED NOT NULL,
  `group_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `extension` varchar(100) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_extensions` (`extension_id`, `group_id`, `extension`) VALUES
(1, 1, 'gif'),
(2, 1, 'png'),
(3, 1, 'jpeg'),
(4, 1, 'jpg'),
(5, 1, 'tif'),
(6, 1, 'tiff'),
(7, 1, 'tga'),
(8, 2, 'gtar'),
(9, 2, 'gz'),
(10, 2, 'tar'),
(11, 2, 'zip'),
(12, 2, 'rar'),
(13, 2, 'ace'),
(14, 2, 'torrent'),
(15, 2, 'tgz'),
(16, 2, 'bz2'),
(17, 2, '7z'),
(18, 3, 'txt'),
(19, 3, 'c'),
(20, 3, 'h'),
(21, 3, 'cpp'),
(22, 3, 'hpp'),
(23, 3, 'diz'),
(24, 3, 'csv'),
(25, 3, 'ini'),
(26, 3, 'log'),
(27, 3, 'js'),
(28, 3, 'xml'),
(29, 4, 'xls'),
(30, 4, 'xlsx'),
(31, 4, 'xlsm'),
(32, 4, 'xlsb'),
(33, 4, 'doc'),
(34, 4, 'docx'),
(35, 4, 'docm'),
(36, 4, 'dot'),
(37, 4, 'dotx'),
(38, 4, 'dotm'),
(39, 4, 'pdf'),
(40, 4, 'ai'),
(41, 4, 'ps'),
(42, 4, 'ppt'),
(43, 4, 'pptx'),
(44, 4, 'pptm'),
(45, 4, 'odg'),
(46, 4, 'odp'),
(47, 4, 'ods'),
(48, 4, 'odt'),
(49, 4, 'rtf'),
(50, 5, 'mp3'),
(51, 5, 'mpeg'),
(52, 5, 'mpg'),
(53, 5, 'ogg'),
(54, 5, 'ogm');

CREATE TABLE `phpbb_extension_groups` (
  `group_id` mediumint(8) UNSIGNED NOT NULL,
  `group_name` varchar(255) NOT NULL DEFAULT '',
  `cat_id` tinyint(2) NOT NULL DEFAULT 0,
  `allow_group` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `download_mode` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `upload_icon` varchar(255) NOT NULL DEFAULT '',
  `max_filesize` int(20) UNSIGNED NOT NULL DEFAULT 0,
  `allowed_forums` text NOT NULL,
  `allow_in_pm` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_extension_groups` (`group_id`, `group_name`, `cat_id`, `allow_group`, `download_mode`, `upload_icon`, `max_filesize`, `allowed_forums`, `allow_in_pm`) VALUES
(1, 'IMAGES', 1, 1, 1, '', 0, '', 0),
(2, 'ARCHIVES', 0, 1, 1, '', 0, '', 0),
(3, 'PLAIN_TEXT', 0, 0, 1, '', 0, '', 0),
(4, 'DOCUMENTS', 0, 0, 1, '', 0, '', 0),
(5, 'DOWNLOADABLE_FILES', 0, 0, 1, '', 0, '', 0);

CREATE TABLE `phpbb_forums` (
  `forum_id` mediumint(8) UNSIGNED NOT NULL,
  `parent_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `left_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `right_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `forum_parents` mediumtext NOT NULL,
  `forum_name` varchar(255) NOT NULL DEFAULT '',
  `forum_desc` text NOT NULL,
  `forum_desc_bitfield` varchar(255) NOT NULL DEFAULT '',
  `forum_desc_options` int(11) UNSIGNED NOT NULL DEFAULT 7,
  `forum_desc_uid` varchar(8) NOT NULL DEFAULT '',
  `forum_link` varchar(255) NOT NULL DEFAULT '',
  `forum_password` varchar(255) NOT NULL DEFAULT '',
  `forum_style` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `forum_image` varchar(255) NOT NULL DEFAULT '',
  `forum_rules` text NOT NULL,
  `forum_rules_link` varchar(255) NOT NULL DEFAULT '',
  `forum_rules_bitfield` varchar(255) NOT NULL DEFAULT '',
  `forum_rules_options` int(11) UNSIGNED NOT NULL DEFAULT 7,
  `forum_rules_uid` varchar(8) NOT NULL DEFAULT '',
  `forum_topics_per_page` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `forum_type` tinyint(4) NOT NULL DEFAULT 0,
  `forum_status` tinyint(4) NOT NULL DEFAULT 0,
  `forum_last_post_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `forum_last_poster_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `forum_last_post_subject` varchar(255) NOT NULL DEFAULT '',
  `forum_last_post_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `forum_last_poster_name` varchar(255) NOT NULL DEFAULT '',
  `forum_last_poster_colour` varchar(6) NOT NULL DEFAULT '',
  `forum_flags` tinyint(4) NOT NULL DEFAULT 32,
  `display_on_index` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `enable_indexing` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `enable_icons` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `enable_prune` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `prune_next` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `prune_days` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `prune_viewed` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `prune_freq` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `display_subforum_list` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `display_subforum_limit` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `forum_options` int(20) UNSIGNED NOT NULL DEFAULT 0,
  `enable_shadow_prune` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `prune_shadow_days` mediumint(8) UNSIGNED NOT NULL DEFAULT 7,
  `prune_shadow_freq` mediumint(8) UNSIGNED NOT NULL DEFAULT 1,
  `prune_shadow_next` int(11) NOT NULL DEFAULT 0,
  `forum_posts_approved` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `forum_posts_unapproved` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `forum_posts_softdeleted` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `forum_topics_approved` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `forum_topics_unapproved` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `forum_topics_softdeleted` mediumint(8) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_forums_access` (
  `forum_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `session_id` char(32) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_forums_track` (
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `forum_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `mark_time` int(11) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_forums_watch` (
  `forum_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `notify_status` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_groups` (
  `group_id` mediumint(8) UNSIGNED NOT NULL,
  `group_type` tinyint(4) NOT NULL DEFAULT 1,
  `group_founder_manage` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `group_skip_auth` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `group_name` varchar(255) NOT NULL DEFAULT '',
  `group_desc` text NOT NULL,
  `group_desc_bitfield` varchar(255) NOT NULL DEFAULT '',
  `group_desc_options` int(11) UNSIGNED NOT NULL DEFAULT 7,
  `group_desc_uid` varchar(8) NOT NULL DEFAULT '',
  `group_display` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `group_avatar` varchar(255) NOT NULL DEFAULT '',
  `group_avatar_type` varchar(255) NOT NULL DEFAULT '',
  `group_avatar_width` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `group_avatar_height` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `group_rank` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `group_colour` varchar(6) NOT NULL DEFAULT '',
  `group_sig_chars` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `group_receive_pm` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `group_message_limit` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `group_legend` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `group_max_recipients` mediumint(8) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_groups` (`group_id`, `group_type`, `group_founder_manage`, `group_skip_auth`, `group_name`, `group_desc`, `group_desc_bitfield`, `group_desc_options`, `group_desc_uid`, `group_display`, `group_avatar`, `group_avatar_type`, `group_avatar_width`, `group_avatar_height`, `group_rank`, `group_colour`, `group_sig_chars`, `group_receive_pm`, `group_message_limit`, `group_legend`, `group_max_recipients`) VALUES
(1, 3, 0, 0, 'GUESTS', '', '', 7, '', 0, '', '', 0, 0, 0, '', 0, 0, 0, 0, 5),
(2, 3, 0, 0, 'REGISTERED', '', '', 7, '', 0, '', '', 0, 0, 0, '', 0, 0, 0, 0, 5),
(3, 3, 0, 0, 'REGISTERED_COPPA', '', '', 7, '', 0, '', '', 0, 0, 0, '', 0, 0, 0, 0, 5),
(4, 3, 0, 0, 'GLOBAL_MODERATORS', '', '', 7, '', 0, '', '', 0, 0, 0, '00AA00', 0, 0, 0, 2, 0),
(5, 3, 1, 0, 'ADMINISTRATORS', '', '', 7, '', 0, '', '', 0, 0, 0, 'AA0000', 0, 0, 0, 1, 0),
(6, 3, 0, 0, 'BOTS', '', '', 7, '', 0, '', '', 0, 0, 0, '9E8DA7', 0, 0, 0, 0, 5),
(7, 3, 0, 0, 'NEWLY_REGISTERED', '', '', 7, '', 0, '', '', 0, 0, 0, '', 0, 0, 0, 0, 5);

CREATE TABLE `phpbb_icons` (
  `icons_id` mediumint(8) UNSIGNED NOT NULL,
  `icons_url` varchar(255) NOT NULL DEFAULT '',
  `icons_width` tinyint(4) NOT NULL DEFAULT 0,
  `icons_height` tinyint(4) NOT NULL DEFAULT 0,
  `icons_alt` varchar(255) NOT NULL DEFAULT '',
  `icons_order` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `display_on_posting` tinyint(1) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_icons` (`icons_id`, `icons_url`, `icons_width`, `icons_height`, `icons_alt`, `icons_order`, `display_on_posting`) VALUES
(1, 'misc/fire.gif', 16, 16, '', 1, 1),
(2, 'smile/redface.gif', 16, 16, '', 9, 1),
(3, 'smile/mrgreen.gif', 16, 16, '', 10, 1),
(4, 'misc/heart.gif', 16, 16, '', 4, 1),
(5, 'misc/star.gif', 16, 16, '', 2, 1),
(6, 'misc/radioactive.gif', 16, 16, '', 3, 1),
(7, 'misc/thinking.gif', 16, 16, '', 5, 1),
(8, 'smile/info.gif', 16, 16, '', 8, 1),
(9, 'smile/question.gif', 16, 16, '', 6, 1),
(10, 'smile/alert.gif', 16, 16, '', 7, 1);

CREATE TABLE `phpbb_lang` (
  `lang_id` tinyint(4) NOT NULL,
  `lang_iso` varchar(30) NOT NULL DEFAULT '',
  `lang_dir` varchar(30) NOT NULL DEFAULT '',
  `lang_english_name` varchar(100) NOT NULL DEFAULT '',
  `lang_local_name` varchar(255) NOT NULL DEFAULT '',
  `lang_author` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_lang` (`lang_id`, `lang_iso`, `lang_dir`, `lang_english_name`, `lang_local_name`, `lang_author`) VALUES
(1, 'en', 'en', 'British English', 'British English', 'phpBB Limited');

CREATE TABLE `phpbb_log` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `log_type` tinyint(4) NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `forum_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `topic_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `post_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `reportee_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `log_ip` varchar(40) NOT NULL DEFAULT '',
  `log_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `log_operation` text NOT NULL,
  `log_data` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_login_attempts` (
  `attempt_ip` varchar(40) NOT NULL DEFAULT '',
  `attempt_browser` varchar(150) NOT NULL DEFAULT '',
  `attempt_forwarded_for` varchar(255) NOT NULL DEFAULT '',
  `attempt_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `username` varchar(255) NOT NULL DEFAULT '0',
  `username_clean` varchar(255) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_migrations` (
  `migration_name` varchar(255) NOT NULL DEFAULT '',
  `migration_depends_on` text NOT NULL,
  `migration_schema_done` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `migration_data_done` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `migration_data_state` text NOT NULL,
  `migration_start_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `migration_end_time` int(11) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_migrations` (`migration_name`, `migration_depends_on`, `migration_schema_done`, `migration_data_done`, `migration_data_state`, `migration_start_time`, `migration_end_time`) VALUES
('\\phpbb\\db\\migration\\data\\v30x\\local_url_bbcode', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_12_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_0', 'a:0:{}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_1', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_1_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_10', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_10_rc3\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_10_rc1', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_9\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_10_rc2', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_10_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_10_rc3', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_10_rc2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11_rc2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11_rc1', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_10\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11_rc2', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_12', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_12_rc3\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_12_rc1', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_12_rc2', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_12_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_12_rc3', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_12_rc2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_13', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_13_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_13_pl1', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_13\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_13_rc1', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_12\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_14', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_14_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_14_rc1', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_13\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_1_rc1', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_0\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_2', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_2_rc2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_2_rc1', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_2_rc2', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_2_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_3', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_3_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_3_rc1', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_4', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_4_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_4_rc1', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_3\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_5', 'a:1:{i:0;s:52:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_5_rc1part2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_5_rc1', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_4\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_5_rc1part2', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_5_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_6', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_6_rc4\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_6_rc1', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_5\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_6_rc2', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_6_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_6_rc3', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_6_rc2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_6_rc4', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_6_rc3\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_7', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_7_rc2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_7_pl1', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_7\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_7_rc1', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_6\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_7_rc2', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_7_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_8', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_8_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_8_rc1', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_7_pl1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_9', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_9_rc4\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_9_rc1', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_8\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_9_rc2', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_9_rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_9_rc3', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_9_rc2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v30x\\release_3_0_9_rc4', 'a:1:{i:0;s:47:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_9_rc3\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v310\\acp_prune_users_module', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v310\\beta1\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\acp_style_components_module', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\allow_cdn', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v310\\jquery_update\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\alpha1', 'a:18:{i:0;s:46:\"\\phpbb\\db\\migration\\data\\v30x\\local_url_bbcode\";i:1;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_12\";i:2;s:57:\"\\phpbb\\db\\migration\\data\\v310\\acp_style_components_module\";i:3;s:39:\"\\phpbb\\db\\migration\\data\\v310\\allow_cdn\";i:4;s:49:\"\\phpbb\\db\\migration\\data\\v310\\auth_provider_oauth\";i:5;s:37:\"\\phpbb\\db\\migration\\data\\v310\\avatars\";i:6;s:40:\"\\phpbb\\db\\migration\\data\\v310\\boardindex\";i:7;s:44:\"\\phpbb\\db\\migration\\data\\v310\\config_db_text\";i:8;s:45:\"\\phpbb\\db\\migration\\data\\v310\\forgot_password\";i:9;s:41:\"\\phpbb\\db\\migration\\data\\v310\\mod_rewrite\";i:10;s:49:\"\\phpbb\\db\\migration\\data\\v310\\mysql_fulltext_drop\";i:11;s:40:\"\\phpbb\\db\\migration\\data\\v310\\namespaces\";i:12;s:48:\"\\phpbb\\db\\migration\\data\\v310\\notifications_cron\";i:13;s:60:\"\\phpbb\\db\\migration\\data\\v310\\notification_options_reconvert\";i:14;s:38:\"\\phpbb\\db\\migration\\data\\v310\\plupload\";i:15;s:51:\"\\phpbb\\db\\migration\\data\\v310\\signature_module_auth\";i:16;s:52:\"\\phpbb\\db\\migration\\data\\v310\\softdelete_mcp_modules\";i:17;s:38:\"\\phpbb\\db\\migration\\data\\v310\\teampage\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\alpha2', 'a:2:{i:0;s:36:\"\\phpbb\\db\\migration\\data\\v310\\alpha1\";i:1;s:51:\"\\phpbb\\db\\migration\\data\\v310\\notifications_cron_p2\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\alpha3', 'a:4:{i:0;s:36:\"\\phpbb\\db\\migration\\data\\v310\\alpha2\";i:1;s:42:\"\\phpbb\\db\\migration\\data\\v310\\avatar_types\";i:2;s:39:\"\\phpbb\\db\\migration\\data\\v310\\passwords\";i:3;s:48:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_types\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\auth_provider_oauth', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_0\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\auth_provider_oauth2', 'a:1:{i:0;s:49:\"\\phpbb\\db\\migration\\data\\v310\\auth_provider_oauth\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\avatar_types', 'a:2:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";i:1;s:37:\"\\phpbb\\db\\migration\\data\\v310\\avatars\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\avatars', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\beta1', 'a:7:{i:0;s:36:\"\\phpbb\\db\\migration\\data\\v310\\alpha3\";i:1;s:42:\"\\phpbb\\db\\migration\\data\\v310\\passwords_p2\";i:2;s:52:\"\\phpbb\\db\\migration\\data\\v310\\postgres_fulltext_drop\";i:3;s:63:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_change_load_settings\";i:4;s:51:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_location\";i:5;s:54:\"\\phpbb\\db\\migration\\data\\v310\\soft_delete_mod_convert2\";i:6;s:48:\"\\phpbb\\db\\migration\\data\\v310\\ucp_popuppm_module\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\beta2', 'a:3:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v310\\beta1\";i:1;s:52:\"\\phpbb\\db\\migration\\data\\v310\\acp_prune_users_module\";i:2;s:59:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_location_cleanup\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\beta3', 'a:6:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v310\\beta2\";i:1;s:50:\"\\phpbb\\db\\migration\\data\\v310\\auth_provider_oauth2\";i:2;s:48:\"\\phpbb\\db\\migration\\data\\v310\\board_contact_name\";i:3;s:44:\"\\phpbb\\db\\migration\\data\\v310\\jquery_update2\";i:4;s:50:\"\\phpbb\\db\\migration\\data\\v310\\live_searches_config\";i:5;s:49:\"\\phpbb\\db\\migration\\data\\v310\\prune_shadow_topics\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\beta4', 'a:3:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v310\\beta3\";i:1;s:69:\"\\phpbb\\db\\migration\\data\\v310\\extensions_version_check_force_unstable\";i:2;s:58:\"\\phpbb\\db\\migration\\data\\v310\\reset_missing_captcha_plugin\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\board_contact_name', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v310\\beta2\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\boardindex', 'a:0:{}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\bot_update', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\rc6\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\captcha_plugins', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\rc2\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\config_db_text', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\contact_admin_acp_module', 'a:0:{}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\contact_admin_form', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v310\\config_db_text\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\dev', 'a:5:{i:0;s:40:\"\\phpbb\\db\\migration\\data\\v310\\extensions\";i:1;s:45:\"\\phpbb\\db\\migration\\data\\v310\\style_update_p2\";i:2;s:41:\"\\phpbb\\db\\migration\\data\\v310\\timezone_p2\";i:3;s:52:\"\\phpbb\\db\\migration\\data\\v310\\reported_posts_display\";i:4;s:46:\"\\phpbb\\db\\migration\\data\\v310\\migrations_table\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\extensions', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\extensions_version_check_force_unstable', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\forgot_password', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\gold', 'a:2:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\rc6\";i:1;s:40:\"\\phpbb\\db\\migration\\data\\v310\\bot_update\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\jquery_update', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\jquery_update2', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v310\\jquery_update\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\live_searches_config', 'a:0:{}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\migrations_table', 'a:0:{}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\mod_rewrite', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\mysql_fulltext_drop', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\namespaces', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\notification_options_reconvert', 'a:1:{i:0;s:54:\"\\phpbb\\db\\migration\\data\\v310\\notifications_schema_fix\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\notifications', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\notifications_cron', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v310\\notifications\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\notifications_cron_p2', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v310\\notifications_cron\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\notifications_schema_fix', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v310\\notifications\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\notifications_use_full_name', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\rc3\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\passwords', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\passwords_convert_p1', 'a:1:{i:0;s:42:\"\\phpbb\\db\\migration\\data\\v310\\passwords_p2\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\passwords_convert_p2', 'a:1:{i:0;s:50:\"\\phpbb\\db\\migration\\data\\v310\\passwords_convert_p1\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\passwords_p2', 'a:1:{i:0;s:39:\"\\phpbb\\db\\migration\\data\\v310\\passwords\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\plupload', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\postgres_fulltext_drop', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_aol', 'a:1:{i:0;s:56:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_yahoo_cleanup\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_aol_cleanup', 'a:1:{i:0;s:46:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_aol\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_change_load_settings', 'a:1:{i:0;s:54:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_aol_cleanup\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_cleanup', 'a:2:{i:0;s:52:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_interests\";i:1;s:53:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_occupation\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_contact_field', 'a:1:{i:0;s:56:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_on_memberlist\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_facebook', 'a:3:{i:0;s:56:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_contact_field\";i:1;s:55:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_show_novalue\";i:2;s:48:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_types\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_field_validation_length', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\rc3\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_googleplus', 'a:3:{i:0;s:56:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_contact_field\";i:1;s:55:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_show_novalue\";i:2;s:48:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_types\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_icq', 'a:1:{i:0;s:56:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_contact_field\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_icq_cleanup', 'a:1:{i:0;s:46:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_icq\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_interests', 'a:2:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_types\";i:1;s:55:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_show_novalue\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_location', 'a:2:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_types\";i:1;s:56:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_on_memberlist\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_location_cleanup', 'a:1:{i:0;s:51:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_location\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_occupation', 'a:1:{i:0;s:52:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_interests\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_on_memberlist', 'a:1:{i:0;s:50:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_cleanup\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_show_novalue', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_types\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_skype', 'a:3:{i:0;s:56:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_contact_field\";i:1;s:55:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_show_novalue\";i:2;s:48:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_types\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_twitter', 'a:3:{i:0;s:56:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_contact_field\";i:1;s:55:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_show_novalue\";i:2;s:48:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_types\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_types', 'a:1:{i:0;s:36:\"\\phpbb\\db\\migration\\data\\v310\\alpha2\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_website', 'a:2:{i:0;s:56:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_on_memberlist\";i:1;s:54:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_icq_cleanup\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_website_cleanup', 'a:1:{i:0;s:50:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_website\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_wlm', 'a:1:{i:0;s:58:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_website_cleanup\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_wlm_cleanup', 'a:1:{i:0;s:46:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_wlm\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_yahoo', 'a:1:{i:0;s:54:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_wlm_cleanup\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_yahoo_cleanup', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_yahoo\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\profilefield_youtube', 'a:3:{i:0;s:56:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_contact_field\";i:1;s:55:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_show_novalue\";i:2;s:48:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_types\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\prune_shadow_topics', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\rc1', 'a:9:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v310\\beta4\";i:1;s:54:\"\\phpbb\\db\\migration\\data\\v310\\contact_admin_acp_module\";i:2;s:48:\"\\phpbb\\db\\migration\\data\\v310\\contact_admin_form\";i:3;s:50:\"\\phpbb\\db\\migration\\data\\v310\\passwords_convert_p2\";i:4;s:51:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_facebook\";i:5;s:53:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_googleplus\";i:6;s:48:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_skype\";i:7;s:50:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_twitter\";i:8;s:50:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_youtube\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\rc2', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\rc3', 'a:5:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\rc2\";i:1;s:45:\"\\phpbb\\db\\migration\\data\\v310\\captcha_plugins\";i:2;s:53:\"\\phpbb\\db\\migration\\data\\v310\\rename_too_long_indexes\";i:3;s:41:\"\\phpbb\\db\\migration\\data\\v310\\search_type\";i:4;s:49:\"\\phpbb\\db\\migration\\data\\v310\\topic_sort_username\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\rc4', 'a:2:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\rc3\";i:1;s:57:\"\\phpbb\\db\\migration\\data\\v310\\notifications_use_full_name\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\rc5', 'a:3:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\rc4\";i:1;s:66:\"\\phpbb\\db\\migration\\data\\v310\\profilefield_field_validation_length\";i:2;s:53:\"\\phpbb\\db\\migration\\data\\v310\\remove_acp_styles_cache\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\rc6', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\rc5\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\remove_acp_styles_cache', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\rc4\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\rename_too_long_indexes', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_0\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\reported_posts_display', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\reset_missing_captcha_plugin', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\search_type', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\signature_module_auth', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\soft_delete_mod_convert', 'a:1:{i:0;s:36:\"\\phpbb\\db\\migration\\data\\v310\\alpha3\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\soft_delete_mod_convert2', 'a:1:{i:0;s:53:\"\\phpbb\\db\\migration\\data\\v310\\soft_delete_mod_convert\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\softdelete_mcp_modules', 'a:2:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";i:1;s:43:\"\\phpbb\\db\\migration\\data\\v310\\softdelete_p2\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\softdelete_p1', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\softdelete_p2', 'a:2:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";i:1;s:43:\"\\phpbb\\db\\migration\\data\\v310\\softdelete_p1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\style_update_p1', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\style_update_p2', 'a:1:{i:0;s:45:\"\\phpbb\\db\\migration\\data\\v310\\style_update_p1\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\teampage', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\timezone', 'a:1:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_11\";}', 1, 1, '', 1763867220, 1763867220),
('\\phpbb\\db\\migration\\data\\v310\\timezone_p2', 'a:1:{i:0;s:38:\"\\phpbb\\db\\migration\\data\\v310\\timezone\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\topic_sort_username', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v310\\ucp_popuppm_module', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v310\\dev\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\add_jabber_ssl_context_config_options', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v31x\\v3110\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\add_latest_topics_index', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v31x\\v3110\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\add_log_time_index', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v319\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\add_smtp_ssl_context_config_options', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v31x\\v3110\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\increase_size_of_dateformat', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v317\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\increase_size_of_emotion', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v31x\\v3110\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\m_pm_report', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v316rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\m_softdelete_global', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v311\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\plupload_last_gc_dynamic', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v312\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\profilefield_remove_underscore_from_alpha', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v311\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\profilefield_yahoo_update_url', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v312\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\remove_duplicate_migrations', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v31x\\v3110\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\style_update', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v310\\gold\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\update_custom_bbcodes_with_idn', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v312\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\update_hashes', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v31x\\v3110\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v311', 'a:2:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v310\\gold\";i:1;s:42:\"\\phpbb\\db\\migration\\data\\v31x\\style_update\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v3110', 'a:1:{i:0;s:38:\"\\phpbb\\db\\migration\\data\\v31x\\v3110rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v3110rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v319\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v3111', 'a:1:{i:0;s:38:\"\\phpbb\\db\\migration\\data\\v31x\\v3111rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v3111rc1', 'a:8:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v31x\\v3110\";i:1;s:48:\"\\phpbb\\db\\migration\\data\\v31x\\add_log_time_index\";i:2;s:54:\"\\phpbb\\db\\migration\\data\\v31x\\increase_size_of_emotion\";i:3;s:67:\"\\phpbb\\db\\migration\\data\\v31x\\add_jabber_ssl_context_config_options\";i:4;s:65:\"\\phpbb\\db\\migration\\data\\v31x\\add_smtp_ssl_context_config_options\";i:5;s:43:\"\\phpbb\\db\\migration\\data\\v31x\\update_hashes\";i:6;s:57:\"\\phpbb\\db\\migration\\data\\v31x\\remove_duplicate_migrations\";i:7;s:53:\"\\phpbb\\db\\migration\\data\\v31x\\add_latest_topics_index\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v3112', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v31x\\v3111\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v312', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v312rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v312rc1', 'a:2:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v311\";i:1;s:49:\"\\phpbb\\db\\migration\\data\\v31x\\m_softdelete_global\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v313', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v313rc2\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v313rc1', 'a:5:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_13_rc1\";i:1;s:54:\"\\phpbb\\db\\migration\\data\\v31x\\plupload_last_gc_dynamic\";i:2;s:71:\"\\phpbb\\db\\migration\\data\\v31x\\profilefield_remove_underscore_from_alpha\";i:3;s:59:\"\\phpbb\\db\\migration\\data\\v31x\\profilefield_yahoo_update_url\";i:4;s:60:\"\\phpbb\\db\\migration\\data\\v31x\\update_custom_bbcodes_with_idn\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v313rc2', 'a:2:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_13_pl1\";i:1;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v313rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v314', 'a:2:{i:0;s:44:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_14\";i:1;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v314rc2\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v314rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v313\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v314rc2', 'a:2:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_14_rc1\";i:1;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v314rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v315', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v315rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v315rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v314\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v316', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v316rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v316rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v315\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v317', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v317rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v317pl1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v317\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v317rc1', 'a:2:{i:0;s:41:\"\\phpbb\\db\\migration\\data\\v31x\\m_pm_report\";i:1;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v316\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v318', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v318rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v318rc1', 'a:2:{i:0;s:57:\"\\phpbb\\db\\migration\\data\\v31x\\increase_size_of_dateformat\";i:1;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v317pl1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v319', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v319rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v31x\\v319rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v318\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v320\\add_help_phpbb', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v320\\v320rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\allowed_schemes_links', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v320\\dev\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\announce_global_permission', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v320\\dev\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\cookie_notice', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v320\\v320rc2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\default_data_type_ids', 'a:2:{i:0;s:36:\"\\phpbb\\db\\migration\\data\\v320\\v320a2\";i:1;s:42:\"\\phpbb\\db\\migration\\data\\v320\\oauth_states\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\dev', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v316\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\font_awesome_update', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v320\\dev\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\icons_alt', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v320\\dev\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\log_post_id', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v320\\dev\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\notifications_board', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v320\\dev\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\oauth_states', 'a:1:{i:0;s:49:\"\\phpbb\\db\\migration\\data\\v310\\auth_provider_oauth\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\remote_upload_validation', 'a:1:{i:0;s:36:\"\\phpbb\\db\\migration\\data\\v320\\v320a2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\remove_outdated_media', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v320\\dev\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\remove_profilefield_wlm', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v320\\dev\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\report_id_auto_increment', 'a:1:{i:0;s:51:\"\\phpbb\\db\\migration\\data\\v320\\default_data_type_ids\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\text_reparser', 'a:2:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v310\\contact_admin_form\";i:1;s:51:\"\\phpbb\\db\\migration\\data\\v320\\allowed_schemes_links\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\v320', 'a:2:{i:0;s:54:\"\\phpbb\\db\\migration\\data\\v31x\\increase_size_of_emotion\";i:1;s:43:\"\\phpbb\\db\\migration\\data\\v320\\cookie_notice\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\v320a1', 'a:9:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v320\\dev\";i:1;s:51:\"\\phpbb\\db\\migration\\data\\v320\\allowed_schemes_links\";i:2;s:56:\"\\phpbb\\db\\migration\\data\\v320\\announce_global_permission\";i:3;s:53:\"\\phpbb\\db\\migration\\data\\v320\\remove_profilefield_wlm\";i:4;s:49:\"\\phpbb\\db\\migration\\data\\v320\\font_awesome_update\";i:5;s:39:\"\\phpbb\\db\\migration\\data\\v320\\icons_alt\";i:6;s:41:\"\\phpbb\\db\\migration\\data\\v320\\log_post_id\";i:7;s:51:\"\\phpbb\\db\\migration\\data\\v320\\remove_outdated_media\";i:8;s:49:\"\\phpbb\\db\\migration\\data\\v320\\notifications_board\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\v320a2', 'a:3:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v317rc1\";i:1;s:43:\"\\phpbb\\db\\migration\\data\\v320\\text_reparser\";i:2;s:36:\"\\phpbb\\db\\migration\\data\\v320\\v320a1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\v320b1', 'a:4:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v31x\\v317pl1\";i:1;s:36:\"\\phpbb\\db\\migration\\data\\v320\\v320a2\";i:2;s:57:\"\\phpbb\\db\\migration\\data\\v31x\\increase_size_of_dateformat\";i:3;s:51:\"\\phpbb\\db\\migration\\data\\v320\\default_data_type_ids\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\v320b2', 'a:3:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v318\";i:1;s:36:\"\\phpbb\\db\\migration\\data\\v320\\v320b1\";i:2;s:54:\"\\phpbb\\db\\migration\\data\\v320\\remote_upload_validation\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\v320rc1', 'a:3:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v319\";i:1;s:54:\"\\phpbb\\db\\migration\\data\\v320\\report_id_auto_increment\";i:2;s:36:\"\\phpbb\\db\\migration\\data\\v320\\v320b2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v320\\v320rc2', 'a:3:{i:0;s:57:\"\\phpbb\\db\\migration\\data\\v31x\\remove_duplicate_migrations\";i:1;s:48:\"\\phpbb\\db\\migration\\data\\v31x\\add_log_time_index\";i:2;s:44:\"\\phpbb\\db\\migration\\data\\v320\\add_help_phpbb\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v32x\\add_missing_config', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v329\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\add_plupload_config', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v329\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\cookie_notice_p2', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v320\\v320\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\disable_remote_avatar', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v325\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\email_force_sender', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v321\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\enable_accurate_pm_button', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v322\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\f_list_topics_permission_add', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v321\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\fix_user_styles', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v320\\v320\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\font_awesome_update_cdn', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v32x\\add_missing_config\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\font_awesome_update_cdn_fix_depends_on', 'a:2:{i:0;s:53:\"\\phpbb\\db\\migration\\data\\v32x\\font_awesome_update_cdn\";i:1;s:48:\"\\phpbb\\db\\migration\\data\\v32x\\add_missing_config\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\forum_topics_per_page_type', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v323\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\jquery_update', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v325rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\load_user_activity_limit', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v320\\v320\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\merge_duplicate_bbcodes', 'a:0:{}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\remove_imagick', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v324rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\smtp_dynamic_data', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v326rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\timezone_p3', 'a:1:{i:0;s:38:\"\\phpbb\\db\\migration\\data\\v310\\timezone\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\update_prosilver_bitfield', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v321\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\user_emoji_permission', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v329rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_index_p1', 'a:1:{i:0;s:46:\"\\phpbb\\db\\migration\\data\\v32x\\cookie_notice_p2\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_index_p2', 'a:1:{i:0;s:63:\"\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_index_p1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_index_p3', 'a:1:{i:0;s:63:\"\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_index_p2\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_reduce_column_sizes', 'a:1:{i:0;s:63:\"\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_index_p3\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_remove_duplicates', 'a:1:{i:0;s:65:\"\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_temp_index\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_temp_index', 'a:1:{i:0;s:74:\"\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_reduce_column_sizes\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_unique_index', 'a:1:{i:0;s:72:\"\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_remove_duplicates\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v321', 'a:2:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v31x\\v3111\";i:1;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v321rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v3210', 'a:1:{i:0;s:38:\"\\phpbb\\db\\migration\\data\\v32x\\v3210rc2\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v3210rc1', 'a:3:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v329\";i:1;s:49:\"\\phpbb\\db\\migration\\data\\v32x\\add_plupload_config\";i:2;s:53:\"\\phpbb\\db\\migration\\data\\v32x\\font_awesome_update_cdn\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v3210rc2', 'a:2:{i:0;s:68:\"\\phpbb\\db\\migration\\data\\v32x\\font_awesome_update_cdn_fix_depends_on\";i:1;s:38:\"\\phpbb\\db\\migration\\data\\v32x\\v3210rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v3211', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v32x\\v3210\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v321rc1', 'a:4:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v320\\v320\";i:1;s:38:\"\\phpbb\\db\\migration\\data\\v31x\\v3111rc1\";i:2;s:54:\"\\phpbb\\db\\migration\\data\\v32x\\load_user_activity_limit\";i:3;s:67:\"\\phpbb\\db\\migration\\data\\v32x\\user_notifications_table_unique_index\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v322', 'a:2:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v31x\\v3112\";i:1;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v322rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v322rc1', 'a:6:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v321\";i:1;s:45:\"\\phpbb\\db\\migration\\data\\v32x\\fix_user_styles\";i:2;s:55:\"\\phpbb\\db\\migration\\data\\v32x\\update_prosilver_bitfield\";i:3;s:48:\"\\phpbb\\db\\migration\\data\\v32x\\email_force_sender\";i:4;s:58:\"\\phpbb\\db\\migration\\data\\v32x\\f_list_topics_permission_add\";i:5;s:53:\"\\phpbb\\db\\migration\\data\\v32x\\merge_duplicate_bbcodes\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v323', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v323rc2\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v323rc1', 'a:2:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v322\";i:1;s:55:\"\\phpbb\\db\\migration\\data\\v32x\\enable_accurate_pm_button\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v323rc2', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v323rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v324', 'a:2:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v324rc1\";i:1;s:44:\"\\phpbb\\db\\migration\\data\\v32x\\remove_imagick\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v324rc1', 'a:2:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v323\";i:1;s:56:\"\\phpbb\\db\\migration\\data\\v32x\\forum_topics_per_page_type\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v325', 'a:2:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v325rc1\";i:1;s:43:\"\\phpbb\\db\\migration\\data\\v32x\\jquery_update\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v325rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v324\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v326', 'a:3:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v326rc1\";i:1;s:51:\"\\phpbb\\db\\migration\\data\\v32x\\disable_remote_avatar\";i:2;s:47:\"\\phpbb\\db\\migration\\data\\v32x\\smtp_dynamic_data\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v326rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v325\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v327', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v327rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v327rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v326\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v328', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v328rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v328rc1', 'a:2:{i:0;s:41:\"\\phpbb\\db\\migration\\data\\v32x\\timezone_p3\";i:1;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v327\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v329', 'a:2:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v32x\\v329rc1\";i:1;s:51:\"\\phpbb\\db\\migration\\data\\v32x\\user_emoji_permission\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v32x\\v329rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v328\";}', 1, 1, '', 1763867221, 1763867221);
INSERT INTO `phpbb_migrations` (`migration_name`, `migration_depends_on`, `migration_schema_done`, `migration_data_done`, `migration_data_state`, `migration_start_time`, `migration_end_time`) VALUES
('\\phpbb\\db\\migration\\data\\v330\\add_display_unapproved_posts_config', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v330\\dev\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v330\\dev', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v327\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v330\\forums_legend_limit', 'a:1:{i:0;s:36:\"\\phpbb\\db\\migration\\data\\v330\\v330b1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v330\\jquery_update', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v330\\dev\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v330\\remove_attachment_flash', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v330\\dev\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v330\\remove_email_hash', 'a:1:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v30x\\release_3_0_0\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v330\\remove_max_pass_chars', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v330\\dev\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v330\\reset_password', 'a:1:{i:0;s:33:\"\\phpbb\\db\\migration\\data\\v330\\dev\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v330\\v330', 'a:2:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v329\";i:1;s:37:\"\\phpbb\\db\\migration\\data\\v330\\v330rc1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v330\\v330b1', 'a:6:{i:0;s:43:\"\\phpbb\\db\\migration\\data\\v330\\jquery_update\";i:1;s:44:\"\\phpbb\\db\\migration\\data\\v330\\reset_password\";i:2;s:53:\"\\phpbb\\db\\migration\\data\\v330\\remove_attachment_flash\";i:3;s:51:\"\\phpbb\\db\\migration\\data\\v330\\remove_max_pass_chars\";i:4;s:34:\"\\phpbb\\db\\migration\\data\\v32x\\v328\";i:5;s:33:\"\\phpbb\\db\\migration\\data\\v330\\dev\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v330\\v330b2', 'a:4:{i:0;s:65:\"\\phpbb\\db\\migration\\data\\v330\\add_display_unapproved_posts_config\";i:1;s:49:\"\\phpbb\\db\\migration\\data\\v330\\forums_legend_limit\";i:2;s:47:\"\\phpbb\\db\\migration\\data\\v330\\remove_email_hash\";i:3;s:36:\"\\phpbb\\db\\migration\\data\\v330\\v330b1\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v330\\v330rc1', 'a:1:{i:0;s:36:\"\\phpbb\\db\\migration\\data\\v330\\v330b2\";}', 1, 1, '', 1763867222, 1763867222),
('\\phpbb\\db\\migration\\data\\v33x\\add_notification_emails_table', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v330\\v330\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\add_resend_activation_expiration', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v33x\\v3311\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\add_user_last_active', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v33x\\v3311\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\bot_update', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v330\\v330\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\bot_update_v2', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v33x\\v334\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\default_search_return_chars', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v330\\v330\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\extend_bbcode_tooltip', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v33x\\v334\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\fix_display_unapproved_posts_config', 'a:1:{i:0;s:65:\"\\phpbb\\db\\migration\\data\\v330\\add_display_unapproved_posts_config\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\font_awesome_5_rollback', 'a:1:{i:0;s:51:\"\\phpbb\\db\\migration\\data\\v33x\\font_awesome_5_update\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\font_awesome_5_update', 'a:2:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v330\\v330\";i:1;s:53:\"\\phpbb\\db\\migration\\data\\v32x\\font_awesome_update_cdn\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\google_recaptcha_v3', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v330\\v330\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\jquery_update', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v33x\\v331rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\jquery_update_v2', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v33x\\v334\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\jquery_update_v3', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v33x\\v3311\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\profilefield_cleanup', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v330\\v330\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\profilefield_youtube_update', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v33x\\v337\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\profilefields_update', 'a:2:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v33x\\v3310\";i:1;s:57:\"\\phpbb\\db\\migration\\data\\v33x\\profilefield_youtube_update\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\remove_orphaned_roles', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v33x\\v335\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\remove_profilefield_aol', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v33x\\v331\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\topic_views_update', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v33x\\v3314\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v331', 'a:4:{i:0;s:53:\"\\phpbb\\db\\migration\\data\\v33x\\font_awesome_5_rollback\";i:1;s:43:\"\\phpbb\\db\\migration\\data\\v33x\\jquery_update\";i:2;s:35:\"\\phpbb\\db\\migration\\data\\v32x\\v3210\";i:3;s:37:\"\\phpbb\\db\\migration\\data\\v33x\\v331rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v3310', 'a:1:{i:0;s:38:\"\\phpbb\\db\\migration\\data\\v33x\\v3310rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v3310rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v33x\\v339\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v3311', 'a:1:{i:0;s:50:\"\\phpbb\\db\\migration\\data\\v33x\\profilefields_update\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v3312', 'a:3:{i:0;s:62:\"\\phpbb\\db\\migration\\data\\v33x\\add_resend_activation_expiration\";i:1;s:50:\"\\phpbb\\db\\migration\\data\\v33x\\add_user_last_active\";i:2;s:38:\"\\phpbb\\db\\migration\\data\\v33x\\v3312rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v3312rc1', 'a:1:{i:0;s:46:\"\\phpbb\\db\\migration\\data\\v33x\\jquery_update_v3\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v3313', 'a:1:{i:0;s:38:\"\\phpbb\\db\\migration\\data\\v33x\\v3313rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v3313rc1', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v33x\\v3312\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v3314', 'a:1:{i:0;s:38:\"\\phpbb\\db\\migration\\data\\v33x\\v3314rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v3314rc1', 'a:1:{i:0;s:35:\"\\phpbb\\db\\migration\\data\\v33x\\v3313\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v3315', 'a:1:{i:0;s:38:\"\\phpbb\\db\\migration\\data\\v33x\\v3315rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v3315rc1', 'a:1:{i:0;s:48:\"\\phpbb\\db\\migration\\data\\v33x\\topic_views_update\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v331rc1', 'a:8:{i:0;s:59:\"\\phpbb\\db\\migration\\data\\v33x\\add_notification_emails_table\";i:1;s:65:\"\\phpbb\\db\\migration\\data\\v33x\\fix_display_unapproved_posts_config\";i:2;s:40:\"\\phpbb\\db\\migration\\data\\v33x\\bot_update\";i:3;s:51:\"\\phpbb\\db\\migration\\data\\v33x\\font_awesome_5_update\";i:4;s:50:\"\\phpbb\\db\\migration\\data\\v33x\\profilefield_cleanup\";i:5;s:49:\"\\phpbb\\db\\migration\\data\\v33x\\google_recaptcha_v3\";i:6;s:57:\"\\phpbb\\db\\migration\\data\\v33x\\default_search_return_chars\";i:7;s:38:\"\\phpbb\\db\\migration\\data\\v32x\\v3210rc2\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v332', 'a:2:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v33x\\v332rc1\";i:1;s:35:\"\\phpbb\\db\\migration\\data\\v32x\\v3211\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v332rc1', 'a:1:{i:0;s:53:\"\\phpbb\\db\\migration\\data\\v33x\\remove_profilefield_aol\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v333', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v33x\\v333rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v333rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v33x\\v332\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v334', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v33x\\v334rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v334rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v33x\\v333\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v335', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v33x\\v335rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v335rc1', 'a:3:{i:0;s:51:\"\\phpbb\\db\\migration\\data\\v33x\\extend_bbcode_tooltip\";i:1;s:43:\"\\phpbb\\db\\migration\\data\\v33x\\bot_update_v2\";i:2;s:46:\"\\phpbb\\db\\migration\\data\\v33x\\jquery_update_v2\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v336', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v33x\\v336rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v336rc1', 'a:1:{i:0;s:51:\"\\phpbb\\db\\migration\\data\\v33x\\remove_orphaned_roles\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v337', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v33x\\v336\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v338', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v33x\\v338rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v338rc1', 'a:1:{i:0;s:57:\"\\phpbb\\db\\migration\\data\\v33x\\profilefield_youtube_update\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v339', 'a:1:{i:0;s:37:\"\\phpbb\\db\\migration\\data\\v33x\\v339rc1\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\db\\migration\\data\\v33x\\v339rc1', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v33x\\v338\";}', 1, 1, '', 1763867221, 1763867221),
('\\phpbb\\viglink\\migrations\\viglink_ask_admin', 'a:1:{i:0;s:41:\"\\phpbb\\viglink\\migrations\\viglink_data_v2\";}', 1, 1, '', 1763867223, 1763867223),
('\\phpbb\\viglink\\migrations\\viglink_ask_admin_wait', 'a:1:{i:0;s:43:\"\\phpbb\\viglink\\migrations\\viglink_ask_admin\";}', 1, 1, '', 1763867223, 1763867223),
('\\phpbb\\viglink\\migrations\\viglink_cron', 'a:1:{i:0;s:38:\"\\phpbb\\viglink\\migrations\\viglink_data\";}', 1, 1, '', 0, 0),
('\\phpbb\\viglink\\migrations\\viglink_data', 'a:1:{i:0;s:34:\"\\phpbb\\db\\migration\\data\\v31x\\v312\";}', 1, 1, '', 1763867222, 1763867223),
('\\phpbb\\viglink\\migrations\\viglink_data_v2', 'a:1:{i:0;s:38:\"\\phpbb\\viglink\\migrations\\viglink_data\";}', 1, 1, '', 1763867223, 1763867223);

CREATE TABLE `phpbb_moderator_cache` (
  `forum_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `username` varchar(255) NOT NULL DEFAULT '',
  `group_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `group_name` varchar(255) NOT NULL DEFAULT '',
  `display_on_index` tinyint(1) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_modules` (
  `module_id` mediumint(8) UNSIGNED NOT NULL,
  `module_enabled` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `module_display` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `module_basename` varchar(255) NOT NULL DEFAULT '',
  `module_class` varchar(10) NOT NULL DEFAULT '',
  `parent_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `left_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `right_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `module_langname` varchar(255) NOT NULL DEFAULT '',
  `module_mode` varchar(255) NOT NULL DEFAULT '',
  `module_auth` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_modules` (`module_id`, `module_enabled`, `module_display`, `module_basename`, `module_class`, `parent_id`, `left_id`, `right_id`, `module_langname`, `module_mode`, `module_auth`) VALUES
(1, 1, 1, '', 'acp', 0, 1, 68, 'ACP_CAT_GENERAL', '', ''),
(2, 1, 1, '', 'acp', 1, 4, 17, 'ACP_QUICK_ACCESS', '', ''),
(3, 1, 1, '', 'acp', 1, 18, 45, 'ACP_BOARD_CONFIGURATION', '', ''),
(4, 1, 1, '', 'acp', 1, 46, 53, 'ACP_CLIENT_COMMUNICATION', '', ''),
(5, 1, 1, '', 'acp', 1, 54, 67, 'ACP_SERVER_CONFIGURATION', '', ''),
(6, 1, 1, '', 'acp', 0, 69, 88, 'ACP_CAT_FORUMS', '', ''),
(7, 1, 1, '', 'acp', 6, 70, 75, 'ACP_MANAGE_FORUMS', '', ''),
(8, 1, 1, '', 'acp', 6, 76, 87, 'ACP_FORUM_BASED_PERMISSIONS', '', ''),
(9, 1, 1, '', 'acp', 0, 89, 116, 'ACP_CAT_POSTING', '', ''),
(10, 1, 1, '', 'acp', 9, 90, 103, 'ACP_MESSAGES', '', ''),
(11, 1, 1, '', 'acp', 9, 104, 115, 'ACP_ATTACHMENTS', '', ''),
(12, 1, 1, '', 'acp', 0, 117, 174, 'ACP_CAT_USERGROUP', '', ''),
(13, 1, 1, '', 'acp', 12, 118, 153, 'ACP_CAT_USERS', '', ''),
(14, 1, 1, '', 'acp', 12, 154, 163, 'ACP_GROUPS', '', ''),
(15, 1, 1, '', 'acp', 12, 164, 173, 'ACP_USER_SECURITY', '', ''),
(16, 1, 1, '', 'acp', 0, 175, 224, 'ACP_CAT_PERMISSIONS', '', ''),
(17, 1, 1, '', 'acp', 16, 178, 187, 'ACP_GLOBAL_PERMISSIONS', '', ''),
(18, 1, 1, '', 'acp', 16, 188, 199, 'ACP_FORUM_BASED_PERMISSIONS', '', ''),
(19, 1, 1, '', 'acp', 16, 200, 209, 'ACP_PERMISSION_ROLES', '', ''),
(20, 1, 1, '', 'acp', 16, 210, 223, 'ACP_PERMISSION_MASKS', '', ''),
(21, 1, 1, '', 'acp', 0, 225, 240, 'ACP_CAT_CUSTOMISE', '', ''),
(22, 1, 1, '', 'acp', 21, 230, 235, 'ACP_STYLE_MANAGEMENT', '', ''),
(23, 1, 1, '', 'acp', 21, 226, 229, 'ACP_EXTENSION_MANAGEMENT', '', ''),
(24, 1, 1, '', 'acp', 21, 236, 239, 'ACP_LANGUAGE', '', ''),
(25, 1, 1, '', 'acp', 0, 241, 260, 'ACP_CAT_MAINTENANCE', '', ''),
(26, 1, 1, '', 'acp', 25, 242, 251, 'ACP_FORUM_LOGS', '', ''),
(27, 1, 1, '', 'acp', 25, 252, 259, 'ACP_CAT_DATABASE', '', ''),
(28, 1, 1, '', 'acp', 0, 261, 284, 'ACP_CAT_SYSTEM', '', ''),
(29, 1, 1, '', 'acp', 28, 262, 265, 'ACP_AUTOMATION', '', ''),
(30, 1, 1, '', 'acp', 28, 266, 275, 'ACP_GENERAL_TASKS', '', ''),
(31, 1, 1, '', 'acp', 28, 276, 283, 'ACP_MODULE_MANAGEMENT', '', ''),
(32, 1, 1, '', 'acp', 0, 285, 286, 'ACP_CAT_DOT_MODS', '', ''),
(33, 1, 1, 'acp_attachments', 'acp', 3, 19, 20, 'ACP_ATTACHMENT_SETTINGS', 'attach', 'acl_a_attach'),
(34, 1, 1, 'acp_attachments', 'acp', 11, 105, 106, 'ACP_ATTACHMENT_SETTINGS', 'attach', 'acl_a_attach'),
(35, 1, 1, 'acp_attachments', 'acp', 11, 107, 108, 'ACP_MANAGE_EXTENSIONS', 'extensions', 'acl_a_attach'),
(36, 1, 1, 'acp_attachments', 'acp', 11, 109, 110, 'ACP_EXTENSION_GROUPS', 'ext_groups', 'acl_a_attach'),
(37, 1, 1, 'acp_attachments', 'acp', 11, 111, 112, 'ACP_ORPHAN_ATTACHMENTS', 'orphan', 'acl_a_attach'),
(38, 1, 1, 'acp_attachments', 'acp', 11, 113, 114, 'ACP_MANAGE_ATTACHMENTS', 'manage', 'acl_a_attach'),
(39, 1, 1, 'acp_ban', 'acp', 15, 165, 166, 'ACP_BAN_EMAILS', 'email', 'acl_a_ban'),
(40, 1, 1, 'acp_ban', 'acp', 15, 167, 168, 'ACP_BAN_IPS', 'ip', 'acl_a_ban'),
(41, 1, 1, 'acp_ban', 'acp', 15, 169, 170, 'ACP_BAN_USERNAMES', 'user', 'acl_a_ban'),
(42, 1, 1, 'acp_bbcodes', 'acp', 10, 91, 92, 'ACP_BBCODES', 'bbcodes', 'acl_a_bbcode'),
(43, 1, 1, 'acp_board', 'acp', 3, 21, 22, 'ACP_BOARD_SETTINGS', 'settings', 'acl_a_board'),
(44, 1, 1, 'acp_board', 'acp', 3, 23, 24, 'ACP_BOARD_FEATURES', 'features', 'acl_a_board'),
(45, 1, 1, 'acp_board', 'acp', 3, 25, 26, 'ACP_AVATAR_SETTINGS', 'avatar', 'acl_a_board'),
(46, 1, 1, 'acp_board', 'acp', 3, 27, 28, 'ACP_MESSAGE_SETTINGS', 'message', 'acl_a_board'),
(47, 1, 1, 'acp_board', 'acp', 10, 93, 94, 'ACP_MESSAGE_SETTINGS', 'message', 'acl_a_board'),
(48, 1, 1, 'acp_board', 'acp', 3, 29, 30, 'ACP_POST_SETTINGS', 'post', 'acl_a_board'),
(49, 1, 1, 'acp_board', 'acp', 10, 95, 96, 'ACP_POST_SETTINGS', 'post', 'acl_a_board'),
(50, 1, 1, 'acp_board', 'acp', 3, 31, 32, 'ACP_SIGNATURE_SETTINGS', 'signature', 'acl_a_board'),
(51, 1, 1, 'acp_board', 'acp', 3, 33, 34, 'ACP_FEED_SETTINGS', 'feed', 'acl_a_board'),
(52, 1, 1, 'acp_board', 'acp', 3, 35, 36, 'ACP_REGISTER_SETTINGS', 'registration', 'acl_a_board'),
(53, 1, 1, 'acp_board', 'acp', 4, 47, 48, 'ACP_AUTH_SETTINGS', 'auth', 'acl_a_server'),
(54, 1, 1, 'acp_board', 'acp', 4, 49, 50, 'ACP_EMAIL_SETTINGS', 'email', 'acl_a_server'),
(55, 1, 1, 'acp_board', 'acp', 5, 55, 56, 'ACP_COOKIE_SETTINGS', 'cookie', 'acl_a_server'),
(56, 1, 1, 'acp_board', 'acp', 5, 57, 58, 'ACP_SERVER_SETTINGS', 'server', 'acl_a_server'),
(57, 1, 1, 'acp_board', 'acp', 5, 59, 60, 'ACP_SECURITY_SETTINGS', 'security', 'acl_a_server'),
(58, 1, 1, 'acp_board', 'acp', 5, 61, 62, 'ACP_LOAD_SETTINGS', 'load', 'acl_a_server'),
(59, 1, 1, 'acp_bots', 'acp', 30, 267, 268, 'ACP_BOTS', 'bots', 'acl_a_bots'),
(60, 1, 1, 'acp_captcha', 'acp', 3, 37, 38, 'ACP_VC_SETTINGS', 'visual', 'acl_a_board'),
(61, 1, 0, 'acp_captcha', 'acp', 3, 39, 40, 'ACP_VC_CAPTCHA_DISPLAY', 'img', 'acl_a_board'),
(62, 1, 1, 'acp_contact', 'acp', 3, 41, 42, 'ACP_CONTACT_SETTINGS', 'contact', 'acl_a_board'),
(63, 1, 1, 'acp_database', 'acp', 27, 253, 254, 'ACP_BACKUP', 'backup', 'acl_a_backup'),
(64, 1, 1, 'acp_database', 'acp', 27, 255, 256, 'ACP_RESTORE', 'restore', 'acl_a_backup'),
(65, 1, 1, 'acp_disallow', 'acp', 15, 171, 172, 'ACP_DISALLOW_USERNAMES', 'usernames', 'acl_a_names'),
(66, 1, 1, 'acp_email', 'acp', 30, 269, 270, 'ACP_MASS_EMAIL', 'email', 'acl_a_email && cfg_email_enable'),
(67, 1, 1, 'acp_extensions', 'acp', 23, 227, 228, 'ACP_EXTENSIONS', 'main', 'acl_a_extensions'),
(68, 1, 1, 'acp_forums', 'acp', 7, 71, 72, 'ACP_MANAGE_FORUMS', 'manage', 'acl_a_forum'),
(69, 1, 1, 'acp_groups', 'acp', 14, 155, 156, 'ACP_GROUPS_MANAGE', 'manage', 'acl_a_group'),
(70, 1, 1, 'acp_groups', 'acp', 14, 157, 158, 'ACP_GROUPS_POSITION', 'position', 'acl_a_group'),
(71, 1, 1, 'acp_help_phpbb', 'acp', 5, 63, 64, 'ACP_HELP_PHPBB', 'help_phpbb', 'acl_a_server'),
(72, 1, 1, 'acp_icons', 'acp', 10, 97, 98, 'ACP_ICONS', 'icons', 'acl_a_icons'),
(73, 1, 1, 'acp_icons', 'acp', 10, 99, 100, 'ACP_SMILIES', 'smilies', 'acl_a_icons'),
(74, 1, 1, 'acp_inactive', 'acp', 13, 119, 120, 'ACP_INACTIVE_USERS', 'list', 'acl_a_user'),
(75, 1, 1, 'acp_jabber', 'acp', 4, 51, 52, 'ACP_JABBER_SETTINGS', 'settings', 'acl_a_jabber'),
(76, 1, 1, 'acp_language', 'acp', 24, 237, 238, 'ACP_LANGUAGE_PACKS', 'lang_packs', 'acl_a_language'),
(77, 1, 1, 'acp_logs', 'acp', 26, 243, 244, 'ACP_ADMIN_LOGS', 'admin', 'acl_a_viewlogs'),
(78, 1, 1, 'acp_logs', 'acp', 26, 245, 246, 'ACP_MOD_LOGS', 'mod', 'acl_a_viewlogs'),
(79, 1, 1, 'acp_logs', 'acp', 26, 247, 248, 'ACP_USERS_LOGS', 'users', 'acl_a_viewlogs'),
(80, 1, 1, 'acp_logs', 'acp', 26, 249, 250, 'ACP_CRITICAL_LOGS', 'critical', 'acl_a_viewlogs'),
(81, 1, 1, 'acp_main', 'acp', 1, 2, 3, 'ACP_INDEX', 'main', ''),
(82, 1, 1, 'acp_modules', 'acp', 31, 277, 278, 'ACP', 'acp', 'acl_a_modules'),
(83, 1, 1, 'acp_modules', 'acp', 31, 279, 280, 'UCP', 'ucp', 'acl_a_modules'),
(84, 1, 1, 'acp_modules', 'acp', 31, 281, 282, 'MCP', 'mcp', 'acl_a_modules'),
(85, 1, 1, 'acp_permission_roles', 'acp', 19, 201, 202, 'ACP_ADMIN_ROLES', 'admin_roles', 'acl_a_roles && acl_a_aauth'),
(86, 1, 1, 'acp_permission_roles', 'acp', 19, 203, 204, 'ACP_USER_ROLES', 'user_roles', 'acl_a_roles && acl_a_uauth'),
(87, 1, 1, 'acp_permission_roles', 'acp', 19, 205, 206, 'ACP_MOD_ROLES', 'mod_roles', 'acl_a_roles && acl_a_mauth'),
(88, 1, 1, 'acp_permission_roles', 'acp', 19, 207, 208, 'ACP_FORUM_ROLES', 'forum_roles', 'acl_a_roles && acl_a_fauth'),
(89, 1, 1, 'acp_permissions', 'acp', 16, 176, 177, 'ACP_PERMISSIONS', 'intro', 'acl_a_authusers || acl_a_authgroups || acl_a_viewauth'),
(90, 1, 0, 'acp_permissions', 'acp', 20, 211, 212, 'ACP_PERMISSION_TRACE', 'trace', 'acl_a_viewauth'),
(91, 1, 1, 'acp_permissions', 'acp', 18, 189, 190, 'ACP_FORUM_PERMISSIONS', 'setting_forum_local', 'acl_a_fauth && (acl_a_authusers || acl_a_authgroups)'),
(92, 1, 1, 'acp_permissions', 'acp', 18, 191, 192, 'ACP_FORUM_PERMISSIONS_COPY', 'setting_forum_copy', 'acl_a_fauth && acl_a_authusers && acl_a_authgroups && acl_a_mauth'),
(93, 1, 1, 'acp_permissions', 'acp', 18, 193, 194, 'ACP_FORUM_MODERATORS', 'setting_mod_local', 'acl_a_mauth && (acl_a_authusers || acl_a_authgroups)'),
(94, 1, 1, 'acp_permissions', 'acp', 17, 179, 180, 'ACP_USERS_PERMISSIONS', 'setting_user_global', 'acl_a_authusers && (acl_a_aauth || acl_a_mauth || acl_a_uauth)'),
(95, 1, 1, 'acp_permissions', 'acp', 13, 123, 124, 'ACP_USERS_PERMISSIONS', 'setting_user_global', 'acl_a_authusers && (acl_a_aauth || acl_a_mauth || acl_a_uauth)'),
(96, 1, 1, 'acp_permissions', 'acp', 18, 195, 196, 'ACP_USERS_FORUM_PERMISSIONS', 'setting_user_local', 'acl_a_authusers && (acl_a_mauth || acl_a_fauth)'),
(97, 1, 1, 'acp_permissions', 'acp', 13, 125, 126, 'ACP_USERS_FORUM_PERMISSIONS', 'setting_user_local', 'acl_a_authusers && (acl_a_mauth || acl_a_fauth)'),
(98, 1, 1, 'acp_permissions', 'acp', 17, 181, 182, 'ACP_GROUPS_PERMISSIONS', 'setting_group_global', 'acl_a_authgroups && (acl_a_aauth || acl_a_mauth || acl_a_uauth)'),
(99, 1, 1, 'acp_permissions', 'acp', 14, 159, 160, 'ACP_GROUPS_PERMISSIONS', 'setting_group_global', 'acl_a_authgroups && (acl_a_aauth || acl_a_mauth || acl_a_uauth)'),
(100, 1, 1, 'acp_permissions', 'acp', 18, 197, 198, 'ACP_GROUPS_FORUM_PERMISSIONS', 'setting_group_local', 'acl_a_authgroups && (acl_a_mauth || acl_a_fauth)'),
(101, 1, 1, 'acp_permissions', 'acp', 14, 161, 162, 'ACP_GROUPS_FORUM_PERMISSIONS', 'setting_group_local', 'acl_a_authgroups && (acl_a_mauth || acl_a_fauth)'),
(102, 1, 1, 'acp_permissions', 'acp', 17, 183, 184, 'ACP_ADMINISTRATORS', 'setting_admin_global', 'acl_a_aauth && (acl_a_authusers || acl_a_authgroups)'),
(103, 1, 1, 'acp_permissions', 'acp', 17, 185, 186, 'ACP_GLOBAL_MODERATORS', 'setting_mod_global', 'acl_a_mauth && (acl_a_authusers || acl_a_authgroups)'),
(104, 1, 1, 'acp_permissions', 'acp', 20, 213, 214, 'ACP_VIEW_ADMIN_PERMISSIONS', 'view_admin_global', 'acl_a_viewauth'),
(105, 1, 1, 'acp_permissions', 'acp', 20, 215, 216, 'ACP_VIEW_USER_PERMISSIONS', 'view_user_global', 'acl_a_viewauth'),
(106, 1, 1, 'acp_permissions', 'acp', 20, 217, 218, 'ACP_VIEW_GLOBAL_MOD_PERMISSIONS', 'view_mod_global', 'acl_a_viewauth'),
(107, 1, 1, 'acp_permissions', 'acp', 20, 219, 220, 'ACP_VIEW_FORUM_MOD_PERMISSIONS', 'view_mod_local', 'acl_a_viewauth'),
(108, 1, 1, 'acp_permissions', 'acp', 20, 221, 222, 'ACP_VIEW_FORUM_PERMISSIONS', 'view_forum_local', 'acl_a_viewauth'),
(109, 1, 1, 'acp_php_info', 'acp', 30, 271, 272, 'ACP_PHP_INFO', 'info', 'acl_a_phpinfo'),
(110, 1, 1, 'acp_profile', 'acp', 13, 127, 128, 'ACP_CUSTOM_PROFILE_FIELDS', 'profile', 'acl_a_profile'),
(111, 1, 1, 'acp_prune', 'acp', 7, 73, 74, 'ACP_PRUNE_FORUMS', 'forums', 'acl_a_prune'),
(112, 1, 1, 'acp_prune', 'acp', 13, 129, 130, 'ACP_PRUNE_USERS', 'users', 'acl_a_userdel'),
(113, 1, 1, 'acp_ranks', 'acp', 13, 131, 132, 'ACP_MANAGE_RANKS', 'ranks', 'acl_a_ranks'),
(114, 1, 1, 'acp_reasons', 'acp', 30, 273, 274, 'ACP_MANAGE_REASONS', 'main', 'acl_a_reasons'),
(115, 1, 1, 'acp_search', 'acp', 5, 65, 66, 'ACP_SEARCH_SETTINGS', 'settings', 'acl_a_search'),
(116, 1, 1, 'acp_search', 'acp', 27, 257, 258, 'ACP_SEARCH_INDEX', 'index', 'acl_a_search'),
(117, 1, 1, 'acp_styles', 'acp', 22, 231, 232, 'ACP_STYLES', 'style', 'acl_a_styles'),
(118, 1, 1, 'acp_styles', 'acp', 22, 233, 234, 'ACP_STYLES_INSTALL', 'install', 'acl_a_styles'),
(119, 1, 1, 'acp_update', 'acp', 29, 263, 264, 'ACP_VERSION_CHECK', 'version_check', 'acl_a_board'),
(120, 1, 1, 'acp_users', 'acp', 13, 121, 122, 'ACP_MANAGE_USERS', 'overview', 'acl_a_user'),
(121, 1, 0, 'acp_users', 'acp', 13, 133, 134, 'ACP_USER_FEEDBACK', 'feedback', 'acl_a_user'),
(122, 1, 0, 'acp_users', 'acp', 13, 135, 136, 'ACP_USER_WARNINGS', 'warnings', 'acl_a_user'),
(123, 1, 0, 'acp_users', 'acp', 13, 137, 138, 'ACP_USER_PROFILE', 'profile', 'acl_a_user'),
(124, 1, 0, 'acp_users', 'acp', 13, 139, 140, 'ACP_USER_PREFS', 'prefs', 'acl_a_user'),
(125, 1, 0, 'acp_users', 'acp', 13, 141, 142, 'ACP_USER_AVATAR', 'avatar', 'acl_a_user'),
(126, 1, 0, 'acp_users', 'acp', 13, 143, 144, 'ACP_USER_RANK', 'rank', 'acl_a_user'),
(127, 1, 0, 'acp_users', 'acp', 13, 145, 146, 'ACP_USER_SIG', 'sig', 'acl_a_user'),
(128, 1, 0, 'acp_users', 'acp', 13, 147, 148, 'ACP_USER_GROUPS', 'groups', 'acl_a_user && acl_a_group'),
(129, 1, 0, 'acp_users', 'acp', 13, 149, 150, 'ACP_USER_PERM', 'perm', 'acl_a_user && acl_a_viewauth'),
(130, 1, 0, 'acp_users', 'acp', 13, 151, 152, 'ACP_USER_ATTACH', 'attach', 'acl_a_user'),
(131, 1, 1, 'acp_words', 'acp', 10, 101, 102, 'ACP_WORDS', 'words', 'acl_a_words'),
(132, 1, 1, 'acp_users', 'acp', 2, 5, 6, 'ACP_MANAGE_USERS', 'overview', 'acl_a_user'),
(133, 1, 1, 'acp_groups', 'acp', 2, 7, 8, 'ACP_GROUPS_MANAGE', 'manage', 'acl_a_group'),
(134, 1, 1, 'acp_forums', 'acp', 2, 9, 10, 'ACP_MANAGE_FORUMS', 'manage', 'acl_a_forum'),
(135, 1, 1, 'acp_logs', 'acp', 2, 11, 12, 'ACP_MOD_LOGS', 'mod', 'acl_a_viewlogs'),
(136, 1, 1, 'acp_bots', 'acp', 2, 13, 14, 'ACP_BOTS', 'bots', 'acl_a_bots'),
(137, 1, 1, 'acp_php_info', 'acp', 2, 15, 16, 'ACP_PHP_INFO', 'info', 'acl_a_phpinfo'),
(138, 1, 1, 'acp_permissions', 'acp', 8, 77, 78, 'ACP_FORUM_PERMISSIONS', 'setting_forum_local', 'acl_a_fauth && (acl_a_authusers || acl_a_authgroups)'),
(139, 1, 1, 'acp_permissions', 'acp', 8, 79, 80, 'ACP_FORUM_PERMISSIONS_COPY', 'setting_forum_copy', 'acl_a_fauth && acl_a_authusers && acl_a_authgroups && acl_a_mauth'),
(140, 1, 1, 'acp_permissions', 'acp', 8, 81, 82, 'ACP_FORUM_MODERATORS', 'setting_mod_local', 'acl_a_mauth && (acl_a_authusers || acl_a_authgroups)'),
(141, 1, 1, 'acp_permissions', 'acp', 8, 83, 84, 'ACP_USERS_FORUM_PERMISSIONS', 'setting_user_local', 'acl_a_authusers && (acl_a_mauth || acl_a_fauth)'),
(142, 1, 1, 'acp_permissions', 'acp', 8, 85, 86, 'ACP_GROUPS_FORUM_PERMISSIONS', 'setting_group_local', 'acl_a_authgroups && (acl_a_mauth || acl_a_fauth)'),
(143, 1, 1, '', 'mcp', 0, 1, 10, 'MCP_MAIN', '', ''),
(144, 1, 1, '', 'mcp', 0, 11, 22, 'MCP_QUEUE', '', ''),
(145, 1, 1, '', 'mcp', 0, 23, 36, 'MCP_REPORTS', '', ''),
(146, 1, 1, '', 'mcp', 0, 37, 42, 'MCP_NOTES', '', ''),
(147, 1, 1, '', 'mcp', 0, 43, 52, 'MCP_WARN', '', ''),
(148, 1, 1, '', 'mcp', 0, 53, 60, 'MCP_LOGS', '', ''),
(149, 1, 1, '', 'mcp', 0, 61, 68, 'MCP_BAN', '', ''),
(150, 1, 1, 'mcp_ban', 'mcp', 149, 62, 63, 'MCP_BAN_USERNAMES', 'user', 'acl_m_ban'),
(151, 1, 1, 'mcp_ban', 'mcp', 149, 64, 65, 'MCP_BAN_IPS', 'ip', 'acl_m_ban'),
(152, 1, 1, 'mcp_ban', 'mcp', 149, 66, 67, 'MCP_BAN_EMAILS', 'email', 'acl_m_ban'),
(153, 1, 1, 'mcp_logs', 'mcp', 148, 54, 55, 'MCP_LOGS_FRONT', 'front', 'acl_m_ || aclf_m_'),
(154, 1, 1, 'mcp_logs', 'mcp', 148, 56, 57, 'MCP_LOGS_FORUM_VIEW', 'forum_logs', 'acl_m_,$id'),
(155, 1, 1, 'mcp_logs', 'mcp', 148, 58, 59, 'MCP_LOGS_TOPIC_VIEW', 'topic_logs', 'acl_m_,$id'),
(156, 1, 1, 'mcp_main', 'mcp', 143, 2, 3, 'MCP_MAIN_FRONT', 'front', ''),
(157, 1, 1, 'mcp_main', 'mcp', 143, 4, 5, 'MCP_MAIN_FORUM_VIEW', 'forum_view', 'acl_m_,$id'),
(158, 1, 1, 'mcp_main', 'mcp', 143, 6, 7, 'MCP_MAIN_TOPIC_VIEW', 'topic_view', 'acl_m_,$id'),
(159, 1, 1, 'mcp_main', 'mcp', 143, 8, 9, 'MCP_MAIN_POST_DETAILS', 'post_details', 'acl_m_,$id || (!$id && aclf_m_)'),
(160, 1, 1, 'mcp_notes', 'mcp', 146, 38, 39, 'MCP_NOTES_FRONT', 'front', ''),
(161, 1, 1, 'mcp_notes', 'mcp', 146, 40, 41, 'MCP_NOTES_USER', 'user_notes', ''),
(162, 1, 1, 'mcp_pm_reports', 'mcp', 145, 30, 31, 'MCP_PM_REPORTS_OPEN', 'pm_reports', 'acl_m_pm_report'),
(163, 1, 1, 'mcp_pm_reports', 'mcp', 145, 32, 33, 'MCP_PM_REPORTS_CLOSED', 'pm_reports_closed', 'acl_m_pm_report'),
(164, 1, 1, 'mcp_pm_reports', 'mcp', 145, 34, 35, 'MCP_PM_REPORT_DETAILS', 'pm_report_details', 'acl_m_pm_report'),
(165, 1, 1, 'mcp_queue', 'mcp', 144, 12, 13, 'MCP_QUEUE_UNAPPROVED_TOPICS', 'unapproved_topics', 'aclf_m_approve'),
(166, 1, 1, 'mcp_queue', 'mcp', 144, 14, 15, 'MCP_QUEUE_UNAPPROVED_POSTS', 'unapproved_posts', 'aclf_m_approve'),
(167, 1, 1, 'mcp_queue', 'mcp', 144, 16, 17, 'MCP_QUEUE_DELETED_TOPICS', 'deleted_topics', 'aclf_m_approve'),
(168, 1, 1, 'mcp_queue', 'mcp', 144, 18, 19, 'MCP_QUEUE_DELETED_POSTS', 'deleted_posts', 'aclf_m_approve'),
(169, 1, 1, 'mcp_queue', 'mcp', 144, 20, 21, 'MCP_QUEUE_APPROVE_DETAILS', 'approve_details', 'acl_m_approve,$id || (!$id && aclf_m_approve)'),
(170, 1, 1, 'mcp_reports', 'mcp', 145, 24, 25, 'MCP_REPORTS_OPEN', 'reports', 'aclf_m_report'),
(171, 1, 1, 'mcp_reports', 'mcp', 145, 26, 27, 'MCP_REPORTS_CLOSED', 'reports_closed', 'aclf_m_report'),
(172, 1, 1, 'mcp_reports', 'mcp', 145, 28, 29, 'MCP_REPORT_DETAILS', 'report_details', 'acl_m_report,$id || (!$id && aclf_m_report)'),
(173, 1, 1, 'mcp_warn', 'mcp', 147, 44, 45, 'MCP_WARN_FRONT', 'front', 'aclf_m_warn'),
(174, 1, 1, 'mcp_warn', 'mcp', 147, 46, 47, 'MCP_WARN_LIST', 'list', 'aclf_m_warn'),
(175, 1, 1, 'mcp_warn', 'mcp', 147, 48, 49, 'MCP_WARN_USER', 'warn_user', 'aclf_m_warn'),
(176, 1, 1, 'mcp_warn', 'mcp', 147, 50, 51, 'MCP_WARN_POST', 'warn_post', 'acl_m_warn && acl_f_read,$id'),
(177, 1, 1, '', 'ucp', 0, 1, 14, 'UCP_MAIN', '', ''),
(178, 1, 1, '', 'ucp', 0, 15, 28, 'UCP_PROFILE', '', ''),
(179, 1, 1, '', 'ucp', 0, 29, 38, 'UCP_PREFS', '', ''),
(180, 1, 1, 'ucp_pm', 'ucp', 0, 39, 48, 'UCP_PM', '', ''),
(181, 1, 1, '', 'ucp', 0, 49, 54, 'UCP_USERGROUPS', '', ''),
(182, 1, 1, '', 'ucp', 0, 55, 60, 'UCP_ZEBRA', '', ''),
(183, 1, 1, 'ucp_attachments', 'ucp', 177, 10, 11, 'UCP_MAIN_ATTACHMENTS', 'attachments', 'acl_u_attach'),
(184, 1, 1, 'ucp_auth_link', 'ucp', 178, 26, 27, 'UCP_AUTH_LINK_MANAGE', 'auth_link', 'authmethod_oauth'),
(185, 1, 1, 'ucp_groups', 'ucp', 181, 50, 51, 'UCP_USERGROUPS_MEMBER', 'membership', ''),
(186, 1, 1, 'ucp_groups', 'ucp', 181, 52, 53, 'UCP_USERGROUPS_MANAGE', 'manage', ''),
(187, 1, 1, 'ucp_main', 'ucp', 177, 2, 3, 'UCP_MAIN_FRONT', 'front', ''),
(188, 1, 1, 'ucp_main', 'ucp', 177, 4, 5, 'UCP_MAIN_SUBSCRIBED', 'subscribed', ''),
(189, 1, 1, 'ucp_main', 'ucp', 177, 6, 7, 'UCP_MAIN_BOOKMARKS', 'bookmarks', 'cfg_allow_bookmarks'),
(190, 1, 1, 'ucp_main', 'ucp', 177, 8, 9, 'UCP_MAIN_DRAFTS', 'drafts', ''),
(191, 1, 1, 'ucp_notifications', 'ucp', 179, 36, 37, 'UCP_NOTIFICATION_OPTIONS', 'notification_options', ''),
(192, 1, 1, 'ucp_notifications', 'ucp', 177, 12, 13, 'UCP_NOTIFICATION_LIST', 'notification_list', 'cfg_allow_board_notifications'),
(193, 1, 0, 'ucp_pm', 'ucp', 180, 40, 41, 'UCP_PM_VIEW', 'view', 'cfg_allow_privmsg'),
(194, 1, 1, 'ucp_pm', 'ucp', 180, 42, 43, 'UCP_PM_COMPOSE', 'compose', 'cfg_allow_privmsg'),
(195, 1, 1, 'ucp_pm', 'ucp', 180, 44, 45, 'UCP_PM_DRAFTS', 'drafts', 'cfg_allow_privmsg'),
(196, 1, 1, 'ucp_pm', 'ucp', 180, 46, 47, 'UCP_PM_OPTIONS', 'options', 'cfg_allow_privmsg'),
(197, 1, 1, 'ucp_prefs', 'ucp', 179, 30, 31, 'UCP_PREFS_PERSONAL', 'personal', ''),
(198, 1, 1, 'ucp_prefs', 'ucp', 179, 32, 33, 'UCP_PREFS_POST', 'post', ''),
(199, 1, 1, 'ucp_prefs', 'ucp', 179, 34, 35, 'UCP_PREFS_VIEW', 'view', ''),
(200, 1, 1, 'ucp_profile', 'ucp', 178, 16, 17, 'UCP_PROFILE_PROFILE_INFO', 'profile_info', 'acl_u_chgprofileinfo'),
(201, 1, 1, 'ucp_profile', 'ucp', 178, 18, 19, 'UCP_PROFILE_SIGNATURE', 'signature', 'acl_u_sig'),
(202, 1, 1, 'ucp_profile', 'ucp', 178, 20, 21, 'UCP_PROFILE_AVATAR', 'avatar', 'cfg_allow_avatar'),
(203, 1, 1, 'ucp_profile', 'ucp', 178, 22, 23, 'UCP_PROFILE_REG_DETAILS', 'reg_details', ''),
(204, 1, 1, 'ucp_profile', 'ucp', 178, 24, 25, 'UCP_PROFILE_AUTOLOGIN_KEYS', 'autologin_keys', ''),
(205, 1, 1, 'ucp_zebra', 'ucp', 182, 56, 57, 'UCP_ZEBRA_FRIENDS', 'friends', ''),
(206, 1, 1, 'ucp_zebra', 'ucp', 182, 58, 59, 'UCP_ZEBRA_FOES', 'foes', ''),
(207, 1, 1, '\\phpbb\\viglink\\acp\\viglink_module', 'acp', 3, 43, 44, 'ACP_VIGLINK_SETTINGS', 'settings', 'ext_phpbb/viglink && acl_a_board');

CREATE TABLE `phpbb_notifications` (
  `notification_id` int(10) UNSIGNED NOT NULL,
  `notification_type_id` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `item_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `item_parent_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `notification_read` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `notification_time` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `notification_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_notification_emails` (
  `notification_type_id` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `item_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `item_parent_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_notification_types` (
  `notification_type_id` smallint(4) UNSIGNED NOT NULL,
  `notification_type_name` varchar(255) NOT NULL DEFAULT '',
  `notification_type_enabled` tinyint(1) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_notification_types` (`notification_type_id`, `notification_type_name`, `notification_type_enabled`) VALUES
(1, 'notification.type.topic', 1),
(2, 'notification.type.approve_topic', 1),
(3, 'notification.type.quote', 1),
(4, 'notification.type.bookmark', 1),
(5, 'notification.type.post', 1),
(6, 'notification.type.approve_post', 1),
(7, 'notification.type.forum', 1),
(8, 'notification.type.post_in_queue', 1),
(9, 'notification.type.report_post', 1),
(10, 'notification.type.group_request', 1),
(11, 'notification.type.pm', 1);

CREATE TABLE `phpbb_oauth_accounts` (
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `provider` varchar(255) NOT NULL DEFAULT '',
  `oauth_provider_id` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_oauth_states` (
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `session_id` char(32) NOT NULL DEFAULT '',
  `provider` varchar(255) NOT NULL DEFAULT '',
  `oauth_state` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_oauth_tokens` (
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `session_id` char(32) NOT NULL DEFAULT '',
  `provider` varchar(255) NOT NULL DEFAULT '',
  `oauth_token` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_poll_options` (
  `poll_option_id` tinyint(4) NOT NULL DEFAULT 0,
  `topic_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `poll_option_text` text NOT NULL,
  `poll_option_total` mediumint(8) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_poll_votes` (
  `topic_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `poll_option_id` tinyint(4) NOT NULL DEFAULT 0,
  `vote_user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `vote_user_ip` varchar(40) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_posts` (
  `post_id` int(10) UNSIGNED NOT NULL,
  `topic_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `forum_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `poster_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `icon_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `poster_ip` varchar(40) NOT NULL DEFAULT '',
  `post_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `post_reported` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `enable_bbcode` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `enable_smilies` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `enable_magic_url` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `enable_sig` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `post_username` varchar(255) NOT NULL DEFAULT '',
  `post_subject` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `post_text` mediumtext NOT NULL,
  `post_checksum` varchar(32) NOT NULL DEFAULT '',
  `post_attachment` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `bbcode_bitfield` varchar(255) NOT NULL DEFAULT '',
  `bbcode_uid` varchar(8) NOT NULL DEFAULT '',
  `post_postcount` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `post_edit_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `post_edit_reason` varchar(255) NOT NULL DEFAULT '',
  `post_edit_user` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `post_edit_count` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `post_edit_locked` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `post_visibility` tinyint(3) NOT NULL DEFAULT 0,
  `post_delete_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `post_delete_reason` varchar(255) NOT NULL DEFAULT '',
  `post_delete_user` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_privmsgs` (
  `msg_id` int(10) UNSIGNED NOT NULL,
  `root_level` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `author_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `icon_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `author_ip` varchar(40) NOT NULL DEFAULT '',
  `message_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `enable_bbcode` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `enable_smilies` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `enable_magic_url` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `enable_sig` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `message_subject` varchar(255) NOT NULL DEFAULT '',
  `message_text` mediumtext NOT NULL,
  `message_edit_reason` varchar(255) NOT NULL DEFAULT '',
  `message_edit_user` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `message_attachment` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `bbcode_bitfield` varchar(255) NOT NULL DEFAULT '',
  `bbcode_uid` varchar(8) NOT NULL DEFAULT '',
  `message_edit_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `message_edit_count` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `to_address` text NOT NULL,
  `bcc_address` text NOT NULL,
  `message_reported` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_privmsgs_folder` (
  `folder_id` mediumint(8) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `folder_name` varchar(255) NOT NULL DEFAULT '',
  `pm_count` mediumint(8) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_privmsgs_rules` (
  `rule_id` mediumint(8) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rule_check` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `rule_connection` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `rule_string` varchar(255) NOT NULL DEFAULT '',
  `rule_user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rule_group_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `rule_action` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `rule_folder_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_privmsgs_to` (
  `msg_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `author_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pm_deleted` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `pm_new` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `pm_unread` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `pm_replied` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `pm_marked` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `pm_forwarded` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `folder_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_profile_fields` (
  `field_id` mediumint(8) UNSIGNED NOT NULL,
  `field_name` varchar(255) NOT NULL DEFAULT '',
  `field_type` varchar(100) NOT NULL DEFAULT '',
  `field_ident` varchar(20) NOT NULL DEFAULT '',
  `field_length` varchar(20) NOT NULL DEFAULT '',
  `field_minlen` varchar(255) NOT NULL DEFAULT '',
  `field_maxlen` varchar(255) NOT NULL DEFAULT '',
  `field_novalue` varchar(255) NOT NULL DEFAULT '',
  `field_default_value` varchar(255) NOT NULL DEFAULT '',
  `field_validation` varchar(128) NOT NULL DEFAULT '',
  `field_required` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `field_show_on_reg` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `field_hide` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `field_no_view` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `field_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `field_order` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `field_show_profile` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `field_show_on_vt` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `field_show_novalue` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `field_show_on_pm` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `field_show_on_ml` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `field_is_contact` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `field_contact_desc` varchar(255) NOT NULL DEFAULT '',
  `field_contact_url` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_profile_fields` (`field_id`, `field_name`, `field_type`, `field_ident`, `field_length`, `field_minlen`, `field_maxlen`, `field_novalue`, `field_default_value`, `field_validation`, `field_required`, `field_show_on_reg`, `field_hide`, `field_no_view`, `field_active`, `field_order`, `field_show_profile`, `field_show_on_vt`, `field_show_novalue`, `field_show_on_pm`, `field_show_on_ml`, `field_is_contact`, `field_contact_desc`, `field_contact_url`) VALUES
(1, 'phpbb_location', 'profilefields.type.string', 'phpbb_location', '20', '2', '100', '', '', '.*', 0, 0, 0, 0, 1, 1, 1, 1, 0, 1, 1, 0, '', ''),
(2, 'phpbb_website', 'profilefields.type.url', 'phpbb_website', '40', '12', '255', '', '', '', 0, 0, 0, 0, 1, 2, 1, 1, 0, 1, 1, 1, 'VISIT_WEBSITE', '%s'),
(3, 'phpbb_interests', 'profilefields.type.text', 'phpbb_interests', '3|30', '2', '500', '', '', '.*', 0, 0, 0, 0, 0, 3, 1, 0, 0, 0, 0, 0, '', ''),
(4, 'phpbb_occupation', 'profilefields.type.text', 'phpbb_occupation', '3|30', '2', '500', '', '', '.*', 0, 0, 0, 0, 0, 4, 1, 0, 0, 0, 0, 0, '', ''),
(5, 'phpbb_icq', 'profilefields.type.string', 'phpbb_icq', '20', '3', '15', '', '', '[0-9]+', 0, 0, 0, 0, 0, 6, 1, 1, 0, 1, 1, 1, 'SEND_ICQ_MESSAGE', 'https://www.icq.com/people/%s/'),
(6, 'phpbb_yahoo', 'profilefields.type.string', 'phpbb_yahoo', '40', '5', '255', '', '', '.*', 0, 0, 0, 0, 0, 8, 1, 1, 0, 1, 1, 1, 'SEND_YIM_MESSAGE', 'ymsgr:sendim?%s'),
(7, 'phpbb_facebook', 'profilefields.type.string', 'phpbb_facebook', '20', '5', '50', '', '', '[\\w.]+', 0, 0, 0, 0, 1, 9, 1, 1, 0, 1, 1, 1, 'VIEW_FACEBOOK_PROFILE', 'https://facebook.com/%s/'),
(8, 'phpbb_twitter', 'profilefields.type.string', 'phpbb_twitter', '20', '1', '15', '', '', '[\\w_]+', 0, 0, 0, 0, 1, 10, 1, 1, 0, 1, 1, 1, 'VIEW_TWITTER_PROFILE', 'https://twitter.com/%s'),
(9, 'phpbb_skype', 'profilefields.type.string', 'phpbb_skype', '20', '6', '32', '', '', '[a-zA-Z][\\w\\.,\\-_]+', 0, 0, 0, 0, 1, 11, 1, 1, 0, 1, 1, 1, 'VIEW_SKYPE_PROFILE', 'skype:%s?userinfo'),
(10, 'phpbb_youtube', 'profilefields.type.string', 'phpbb_youtube', '20', '3', '60', '', '', '(@[a-zA-Z0-9_.-]{3,30}|c/[a-zA-Z][\\w\\.,\\-_]+|(channel|user)/[a-zA-Z][\\w\\.,\\-_]+)', 0, 0, 0, 0, 1, 12, 1, 1, 0, 1, 1, 1, 'VIEW_YOUTUBE_PROFILE', 'https://youtube.com/%s');

CREATE TABLE `phpbb_profile_fields_data` (
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `pf_phpbb_interests` mediumtext NOT NULL,
  `pf_phpbb_occupation` mediumtext NOT NULL,
  `pf_phpbb_location` varchar(255) NOT NULL DEFAULT '',
  `pf_phpbb_facebook` varchar(255) NOT NULL DEFAULT '',
  `pf_phpbb_icq` varchar(255) NOT NULL DEFAULT '',
  `pf_phpbb_skype` varchar(255) NOT NULL DEFAULT '',
  `pf_phpbb_twitter` varchar(255) NOT NULL DEFAULT '',
  `pf_phpbb_youtube` varchar(255) NOT NULL DEFAULT '',
  `pf_phpbb_website` varchar(255) NOT NULL DEFAULT '',
  `pf_phpbb_yahoo` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_profile_fields_lang` (
  `field_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `lang_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `option_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `field_type` varchar(100) NOT NULL DEFAULT '',
  `lang_value` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_profile_lang` (
  `field_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `lang_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `lang_name` varchar(255) NOT NULL DEFAULT '',
  `lang_explain` text NOT NULL,
  `lang_default_value` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_profile_lang` (`field_id`, `lang_id`, `lang_name`, `lang_explain`, `lang_default_value`) VALUES
(1, 1, 'LOCATION', '', ''),
(2, 1, 'WEBSITE', '', ''),
(3, 1, 'INTERESTS', '', ''),
(4, 1, 'OCCUPATION', '', ''),
(5, 1, 'ICQ', '', ''),
(6, 1, 'YAHOO', '', ''),
(7, 1, 'FACEBOOK', '', ''),
(8, 1, 'TWITTER', '', ''),
(9, 1, 'SKYPE', '', ''),
(10, 1, 'YOUTUBE', '', '');

CREATE TABLE `phpbb_ranks` (
  `rank_id` mediumint(8) UNSIGNED NOT NULL,
  `rank_title` varchar(255) NOT NULL DEFAULT '',
  `rank_min` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `rank_special` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `rank_image` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_reports` (
  `report_id` int(10) UNSIGNED NOT NULL,
  `reason_id` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `post_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `user_notify` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `report_closed` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `report_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `report_text` mediumtext NOT NULL,
  `pm_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `reported_post_enable_bbcode` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `reported_post_enable_smilies` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `reported_post_enable_magic_url` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `reported_post_text` mediumtext NOT NULL,
  `reported_post_uid` varchar(8) NOT NULL DEFAULT '',
  `reported_post_bitfield` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_reports_reasons` (
  `reason_id` smallint(4) UNSIGNED NOT NULL,
  `reason_title` varchar(255) NOT NULL DEFAULT '',
  `reason_description` mediumtext NOT NULL,
  `reason_order` smallint(4) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_reports_reasons` (`reason_id`, `reason_title`, `reason_description`, `reason_order`) VALUES
(1, 'warez', 'The post contains links to illegal or pirated software.', 1),
(2, 'spam', 'The reported post has the only purpose to advertise for a website or another product.', 2),
(3, 'off_topic', 'The reported post is off topic.', 3),
(4, 'other', 'The reported post does not fit into any other category, please use the further information field.', 4);

CREATE TABLE `phpbb_search_results` (
  `search_key` varchar(32) NOT NULL DEFAULT '',
  `search_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `search_keywords` mediumtext NOT NULL,
  `search_authors` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_search_wordlist` (
  `word_id` int(10) UNSIGNED NOT NULL,
  `word_text` varchar(255) NOT NULL DEFAULT '',
  `word_common` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `word_count` mediumint(8) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_search_wordmatch` (
  `post_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `word_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `title_match` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_sessions` (
  `session_id` char(32) NOT NULL DEFAULT '',
  `session_user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `session_last_visit` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `session_start` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `session_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `session_ip` varchar(40) NOT NULL DEFAULT '',
  `session_browser` varchar(150) NOT NULL DEFAULT '',
  `session_forwarded_for` varchar(255) NOT NULL DEFAULT '',
  `session_page` varchar(255) NOT NULL DEFAULT '',
  `session_viewonline` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `session_autologin` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `session_admin` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `session_forum_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_sessions_keys` (
  `key_id` char(32) NOT NULL DEFAULT '',
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_ip` varchar(40) NOT NULL DEFAULT '',
  `last_login` int(11) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_sitelist` (
  `site_id` mediumint(8) UNSIGNED NOT NULL,
  `site_ip` varchar(40) NOT NULL DEFAULT '',
  `site_hostname` varchar(255) NOT NULL DEFAULT '',
  `ip_exclude` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_smilies` (
  `smiley_id` mediumint(8) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL DEFAULT '',
  `emotion` varchar(255) NOT NULL DEFAULT '',
  `smiley_url` varchar(50) NOT NULL DEFAULT '',
  `smiley_width` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `smiley_height` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `smiley_order` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `display_on_posting` tinyint(1) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_smilies` (`smiley_id`, `code`, `emotion`, `smiley_url`, `smiley_width`, `smiley_height`, `smiley_order`, `display_on_posting`) VALUES
(1, ':D', 'Very Happy', 'icon_e_biggrin.gif', 15, 17, 1, 1),
(2, ':-D', 'Very Happy', 'icon_e_biggrin.gif', 15, 17, 2, 1),
(3, ':grin:', 'Very Happy', 'icon_e_biggrin.gif', 15, 17, 3, 1),
(4, ':)', 'Smile', 'icon_e_smile.gif', 15, 17, 4, 1),
(5, ':-)', 'Smile', 'icon_e_smile.gif', 15, 17, 5, 1),
(6, ':smile:', 'Smile', 'icon_e_smile.gif', 15, 17, 6, 1),
(7, ';)', 'Wink', 'icon_e_wink.gif', 15, 17, 7, 1),
(8, ';-)', 'Wink', 'icon_e_wink.gif', 15, 17, 8, 1),
(9, ':wink:', 'Wink', 'icon_e_wink.gif', 15, 17, 9, 1),
(10, ':(', 'Sad', 'icon_e_sad.gif', 15, 17, 10, 1),
(11, ':-(', 'Sad', 'icon_e_sad.gif', 15, 17, 11, 1),
(12, ':sad:', 'Sad', 'icon_e_sad.gif', 15, 17, 12, 1),
(13, ':o', 'Surprised', 'icon_e_surprised.gif', 15, 17, 13, 1),
(14, ':-o', 'Surprised', 'icon_e_surprised.gif', 15, 17, 14, 1),
(15, ':eek:', 'Surprised', 'icon_e_surprised.gif', 15, 17, 15, 1),
(16, ':shock:', 'Shocked', 'icon_eek.gif', 15, 17, 16, 1),
(17, ':?', 'Confused', 'icon_e_confused.gif', 15, 17, 17, 1),
(18, ':-?', 'Confused', 'icon_e_confused.gif', 15, 17, 18, 1),
(19, ':???:', 'Confused', 'icon_e_confused.gif', 15, 17, 19, 1),
(20, '8-)', 'Cool', 'icon_cool.gif', 15, 17, 20, 1),
(21, ':cool:', 'Cool', 'icon_cool.gif', 15, 17, 21, 1),
(22, ':lol:', 'Laughing', 'icon_lol.gif', 15, 17, 22, 1),
(23, ':x', 'Mad', 'icon_mad.gif', 15, 17, 23, 1),
(24, ':-x', 'Mad', 'icon_mad.gif', 15, 17, 24, 1),
(25, ':mad:', 'Mad', 'icon_mad.gif', 15, 17, 25, 1),
(26, ':P', 'Razz', 'icon_razz.gif', 15, 17, 26, 1),
(27, ':-P', 'Razz', 'icon_razz.gif', 15, 17, 27, 1),
(28, ':razz:', 'Razz', 'icon_razz.gif', 15, 17, 28, 1),
(29, ':oops:', 'Embarrassed', 'icon_redface.gif', 15, 17, 29, 1),
(30, ':cry:', 'Crying or Very Sad', 'icon_cry.gif', 15, 17, 30, 1),
(31, ':evil:', 'Evil or Very Mad', 'icon_evil.gif', 15, 17, 31, 1),
(32, ':twisted:', 'Twisted Evil', 'icon_twisted.gif', 15, 17, 32, 1),
(33, ':roll:', 'Rolling Eyes', 'icon_rolleyes.gif', 15, 17, 33, 1),
(34, ':!:', 'Exclamation', 'icon_exclaim.gif', 15, 17, 34, 1),
(35, ':?:', 'Question', 'icon_question.gif', 15, 17, 35, 1),
(36, ':idea:', 'Idea', 'icon_idea.gif', 15, 17, 36, 1),
(37, ':arrow:', 'Arrow', 'icon_arrow.gif', 15, 17, 37, 1),
(38, ':|', 'Neutral', 'icon_neutral.gif', 15, 17, 38, 1),
(39, ':-|', 'Neutral', 'icon_neutral.gif', 15, 17, 39, 1),
(40, ':mrgreen:', 'Mr. Green', 'icon_mrgreen.gif', 15, 17, 40, 1),
(41, ':geek:', 'Geek', 'icon_e_geek.gif', 17, 17, 41, 1),
(42, ':ugeek:', 'Uber Geek', 'icon_e_ugeek.gif', 17, 18, 42, 1);

CREATE TABLE `phpbb_styles` (
  `style_id` mediumint(8) UNSIGNED NOT NULL,
  `style_name` varchar(255) NOT NULL DEFAULT '',
  `style_copyright` varchar(255) NOT NULL DEFAULT '',
  `style_active` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `style_path` varchar(100) NOT NULL DEFAULT '',
  `bbcode_bitfield` varchar(255) NOT NULL DEFAULT 'kNg=',
  `style_parent_id` int(4) UNSIGNED NOT NULL DEFAULT 0,
  `style_parent_tree` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

INSERT INTO `phpbb_styles` (`style_id`, `style_name`, `style_copyright`, `style_active`, `style_path`, `bbcode_bitfield`, `style_parent_id`, `style_parent_tree`) VALUES
(1, 'prosilver', '&copy; phpBB Limited', 1, 'prosilver', '//g=', 0, '');

CREATE TABLE `phpbb_teampage` (
  `teampage_id` mediumint(8) UNSIGNED NOT NULL,
  `group_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `teampage_name` varchar(255) NOT NULL DEFAULT '',
  `teampage_position` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `teampage_parent` mediumint(8) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_topics` (
  `topic_id` int(10) UNSIGNED NOT NULL,
  `forum_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `icon_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `topic_attachment` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `topic_reported` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `topic_title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `topic_poster` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `topic_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `topic_time_limit` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `topic_views` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `topic_status` tinyint(3) NOT NULL DEFAULT 0,
  `topic_type` tinyint(3) NOT NULL DEFAULT 0,
  `topic_first_post_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `topic_first_poster_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '',
  `topic_first_poster_colour` varchar(6) NOT NULL DEFAULT '',
  `topic_last_post_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `topic_last_poster_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `topic_last_poster_name` varchar(255) NOT NULL DEFAULT '',
  `topic_last_poster_colour` varchar(6) NOT NULL DEFAULT '',
  `topic_last_post_subject` varchar(255) NOT NULL DEFAULT '',
  `topic_last_post_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `topic_last_view_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `topic_moved_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `topic_bumped` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `topic_bumper` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `poll_title` varchar(255) NOT NULL DEFAULT '',
  `poll_start` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `poll_length` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `poll_max_options` tinyint(4) NOT NULL DEFAULT 1,
  `poll_last_vote` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `poll_vote_change` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `topic_visibility` tinyint(3) NOT NULL DEFAULT 0,
  `topic_delete_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `topic_delete_reason` varchar(255) NOT NULL DEFAULT '',
  `topic_delete_user` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `topic_posts_approved` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `topic_posts_unapproved` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `topic_posts_softdeleted` mediumint(8) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_topics_posted` (
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `topic_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `topic_posted` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_topics_track` (
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `topic_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `forum_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `mark_time` int(11) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_topics_watch` (
  `topic_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `notify_status` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `user_type` tinyint(2) NOT NULL DEFAULT 0,
  `group_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 3,
  `user_permissions` mediumtext NOT NULL,
  `user_perm_from` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `user_ip` varchar(40) NOT NULL DEFAULT '',
  `user_regdate` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `username` varchar(255) NOT NULL DEFAULT '',
  `username_clean` varchar(255) NOT NULL DEFAULT '',
  `user_password` varchar(255) NOT NULL DEFAULT '',
  `user_passchg` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `user_email` varchar(100) NOT NULL DEFAULT '',
  `user_birthday` varchar(10) NOT NULL DEFAULT '',
  `user_lastvisit` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `user_last_active` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `user_lastmark` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `user_lastpost_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `user_lastpage` varchar(200) NOT NULL DEFAULT '',
  `user_last_confirm_key` varchar(10) NOT NULL DEFAULT '',
  `user_last_search` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `user_warnings` tinyint(4) NOT NULL DEFAULT 0,
  `user_last_warning` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `user_login_attempts` tinyint(4) NOT NULL DEFAULT 0,
  `user_inactive_reason` tinyint(2) NOT NULL DEFAULT 0,
  `user_inactive_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `user_posts` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `user_lang` varchar(30) NOT NULL DEFAULT '',
  `user_timezone` varchar(100) NOT NULL DEFAULT '',
  `user_dateformat` varchar(64) NOT NULL DEFAULT 'd M Y H:i',
  `user_style` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `user_rank` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `user_colour` varchar(6) NOT NULL DEFAULT '',
  `user_new_privmsg` int(4) NOT NULL DEFAULT 0,
  `user_unread_privmsg` int(4) NOT NULL DEFAULT 0,
  `user_last_privmsg` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `user_message_rules` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `user_full_folder` int(11) NOT NULL DEFAULT -3,
  `user_emailtime` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `user_topic_show_days` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `user_topic_sortby_type` varchar(1) NOT NULL DEFAULT 't',
  `user_topic_sortby_dir` varchar(1) NOT NULL DEFAULT 'd',
  `user_post_show_days` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `user_post_sortby_type` varchar(1) NOT NULL DEFAULT 't',
  `user_post_sortby_dir` varchar(1) NOT NULL DEFAULT 'a',
  `user_notify` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `user_notify_pm` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `user_notify_type` tinyint(4) NOT NULL DEFAULT 0,
  `user_allow_pm` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `user_allow_viewonline` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `user_allow_viewemail` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `user_allow_massemail` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `user_options` int(11) UNSIGNED NOT NULL DEFAULT 230271,
  `user_avatar` varchar(255) NOT NULL DEFAULT '',
  `user_avatar_type` varchar(255) NOT NULL DEFAULT '',
  `user_avatar_width` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `user_avatar_height` smallint(4) UNSIGNED NOT NULL DEFAULT 0,
  `user_sig` mediumtext NOT NULL,
  `user_sig_bbcode_uid` varchar(8) NOT NULL DEFAULT '',
  `user_sig_bbcode_bitfield` varchar(255) NOT NULL DEFAULT '',
  `user_jabber` varchar(255) NOT NULL DEFAULT '',
  `user_actkey` varchar(32) NOT NULL DEFAULT '',
  `user_actkey_expiration` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `reset_token` varchar(64) NOT NULL DEFAULT '',
  `reset_token_expiration` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `user_newpasswd` varchar(255) NOT NULL DEFAULT '',
  `user_form_salt` varchar(32) NOT NULL DEFAULT '',
  `user_new` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `user_reminded` tinyint(4) NOT NULL DEFAULT 0,
  `user_reminded_time` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `external_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'tsumego.com user id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;


CREATE TABLE `phpbb_user_group` (
  `group_id` mediumint(8) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `group_leader` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `user_pending` tinyint(1) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;


CREATE TABLE `phpbb_user_notifications` (
  `item_type` varchar(165) NOT NULL DEFAULT '',
  `item_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `method` varchar(165) NOT NULL DEFAULT '',
  `notify` tinyint(1) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;


CREATE TABLE `phpbb_warnings` (
  `warning_id` mediumint(8) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `post_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `log_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `warning_time` int(11) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_words` (
  `word_id` int(10) UNSIGNED NOT NULL,
  `word` varchar(255) NOT NULL DEFAULT '',
  `replacement` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;

CREATE TABLE `phpbb_zebra` (
  `user_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `zebra_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `friend` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `foe` tinyint(1) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;


ALTER TABLE `phpbb_acl_options`
  ADD PRIMARY KEY (`auth_option_id`),
  ADD UNIQUE KEY `auth_option` (`auth_option`);

ALTER TABLE `phpbb_acl_roles`
  ADD PRIMARY KEY (`role_id`),
  ADD KEY `role_type` (`role_type`),
  ADD KEY `role_order` (`role_order`);

ALTER TABLE `phpbb_acl_roles_data`
  ADD PRIMARY KEY (`role_id`,`auth_option_id`),
  ADD KEY `ath_op_id` (`auth_option_id`);

ALTER TABLE `phpbb_acl_users`
  ADD KEY `user_id` (`user_id`),
  ADD KEY `auth_option_id` (`auth_option_id`),
  ADD KEY `auth_role_id` (`auth_role_id`);

ALTER TABLE `phpbb_attachments`
  ADD PRIMARY KEY (`attach_id`),
  ADD KEY `filetime` (`filetime`),
  ADD KEY `post_msg_id` (`post_msg_id`),
  ADD KEY `topic_id` (`topic_id`),
  ADD KEY `poster_id` (`poster_id`),
  ADD KEY `is_orphan` (`is_orphan`);

ALTER TABLE `phpbb_banlist`
  ADD PRIMARY KEY (`ban_id`),
  ADD KEY `ban_end` (`ban_end`),
  ADD KEY `ban_user` (`ban_userid`,`ban_exclude`),
  ADD KEY `ban_email` (`ban_email`,`ban_exclude`),
  ADD KEY `ban_ip` (`ban_ip`,`ban_exclude`);

ALTER TABLE `phpbb_bbcodes`
  ADD PRIMARY KEY (`bbcode_id`),
  ADD KEY `display_on_post` (`display_on_posting`);

ALTER TABLE `phpbb_bookmarks`
  ADD PRIMARY KEY (`topic_id`,`user_id`);

ALTER TABLE `phpbb_bots`
  ADD PRIMARY KEY (`bot_id`),
  ADD KEY `bot_active` (`bot_active`);

ALTER TABLE `phpbb_config`
  ADD PRIMARY KEY (`config_name`),
  ADD KEY `is_dynamic` (`is_dynamic`);

ALTER TABLE `phpbb_config_text`
  ADD PRIMARY KEY (`config_name`);

ALTER TABLE `phpbb_confirm`
  ADD PRIMARY KEY (`session_id`,`confirm_id`),
  ADD KEY `confirm_type` (`confirm_type`);

ALTER TABLE `phpbb_disallow`
  ADD PRIMARY KEY (`disallow_id`);

ALTER TABLE `phpbb_drafts`
  ADD PRIMARY KEY (`draft_id`),
  ADD KEY `save_time` (`save_time`);

ALTER TABLE `phpbb_ext`
  ADD UNIQUE KEY `ext_name` (`ext_name`);

ALTER TABLE `phpbb_extensions`
  ADD PRIMARY KEY (`extension_id`);

ALTER TABLE `phpbb_extension_groups`
  ADD PRIMARY KEY (`group_id`);

ALTER TABLE `phpbb_forums`
  ADD PRIMARY KEY (`forum_id`),
  ADD KEY `left_right_id` (`left_id`,`right_id`),
  ADD KEY `forum_lastpost_id` (`forum_last_post_id`);

ALTER TABLE `phpbb_forums_access`
  ADD PRIMARY KEY (`forum_id`,`user_id`,`session_id`);

ALTER TABLE `phpbb_forums_track`
  ADD PRIMARY KEY (`user_id`,`forum_id`);

ALTER TABLE `phpbb_forums_watch`
  ADD KEY `forum_id` (`forum_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `notify_stat` (`notify_status`);

ALTER TABLE `phpbb_groups`
  ADD PRIMARY KEY (`group_id`),
  ADD KEY `group_legend_name` (`group_legend`,`group_name`);

ALTER TABLE `phpbb_icons`
  ADD PRIMARY KEY (`icons_id`),
  ADD KEY `display_on_posting` (`display_on_posting`);

ALTER TABLE `phpbb_lang`
  ADD PRIMARY KEY (`lang_id`),
  ADD KEY `lang_iso` (`lang_iso`);

ALTER TABLE `phpbb_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `log_type` (`log_type`),
  ADD KEY `forum_id` (`forum_id`),
  ADD KEY `topic_id` (`topic_id`),
  ADD KEY `reportee_id` (`reportee_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `log_time` (`log_time`);

ALTER TABLE `phpbb_login_attempts`
  ADD KEY `att_ip` (`attempt_ip`,`attempt_time`),
  ADD KEY `att_for` (`attempt_forwarded_for`,`attempt_time`),
  ADD KEY `att_time` (`attempt_time`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `phpbb_migrations`
  ADD PRIMARY KEY (`migration_name`);

ALTER TABLE `phpbb_moderator_cache`
  ADD KEY `disp_idx` (`display_on_index`),
  ADD KEY `forum_id` (`forum_id`);

ALTER TABLE `phpbb_modules`
  ADD PRIMARY KEY (`module_id`),
  ADD KEY `left_right_id` (`left_id`,`right_id`),
  ADD KEY `module_enabled` (`module_enabled`),
  ADD KEY `class_left_id` (`module_class`,`left_id`);

ALTER TABLE `phpbb_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `item_ident` (`notification_type_id`,`item_id`),
  ADD KEY `user` (`user_id`,`notification_read`);

ALTER TABLE `phpbb_notification_emails`
  ADD PRIMARY KEY (`notification_type_id`,`item_id`,`item_parent_id`,`user_id`);

ALTER TABLE `phpbb_notification_types`
  ADD PRIMARY KEY (`notification_type_id`),
  ADD UNIQUE KEY `type` (`notification_type_name`);

ALTER TABLE `phpbb_oauth_accounts`
  ADD PRIMARY KEY (`user_id`,`provider`);

ALTER TABLE `phpbb_oauth_states`
  ADD KEY `user_id` (`user_id`),
  ADD KEY `provider` (`provider`);

ALTER TABLE `phpbb_oauth_tokens`
  ADD KEY `user_id` (`user_id`),
  ADD KEY `provider` (`provider`);

ALTER TABLE `phpbb_poll_options`
  ADD KEY `poll_opt_id` (`poll_option_id`),
  ADD KEY `topic_id` (`topic_id`);

ALTER TABLE `phpbb_poll_votes`
  ADD KEY `topic_id` (`topic_id`),
  ADD KEY `vote_user_id` (`vote_user_id`),
  ADD KEY `vote_user_ip` (`vote_user_ip`);

ALTER TABLE `phpbb_posts`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `forum_id` (`forum_id`),
  ADD KEY `topic_id` (`topic_id`),
  ADD KEY `poster_ip` (`poster_ip`),
  ADD KEY `poster_id` (`poster_id`),
  ADD KEY `tid_post_time` (`topic_id`,`post_time`),
  ADD KEY `post_username` (`post_username`),
  ADD KEY `post_visibility` (`post_visibility`);

ALTER TABLE `phpbb_privmsgs`
  ADD PRIMARY KEY (`msg_id`),
  ADD KEY `author_ip` (`author_ip`),
  ADD KEY `message_time` (`message_time`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `root_level` (`root_level`);

ALTER TABLE `phpbb_privmsgs_folder`
  ADD PRIMARY KEY (`folder_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `phpbb_privmsgs_rules`
  ADD PRIMARY KEY (`rule_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `phpbb_privmsgs_to`
  ADD KEY `msg_id` (`msg_id`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `usr_flder_id` (`user_id`,`folder_id`);

ALTER TABLE `phpbb_profile_fields`
  ADD PRIMARY KEY (`field_id`),
  ADD KEY `fld_type` (`field_type`),
  ADD KEY `fld_ordr` (`field_order`);

ALTER TABLE `phpbb_profile_fields_data`
  ADD PRIMARY KEY (`user_id`);

ALTER TABLE `phpbb_profile_fields_lang`
  ADD PRIMARY KEY (`field_id`,`lang_id`,`option_id`);

ALTER TABLE `phpbb_profile_lang`
  ADD PRIMARY KEY (`field_id`,`lang_id`);

ALTER TABLE `phpbb_ranks`
  ADD PRIMARY KEY (`rank_id`);

ALTER TABLE `phpbb_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `pm_id` (`pm_id`);

ALTER TABLE `phpbb_reports_reasons`
  ADD PRIMARY KEY (`reason_id`);

ALTER TABLE `phpbb_search_results`
  ADD PRIMARY KEY (`search_key`);

ALTER TABLE `phpbb_search_wordlist`
  ADD PRIMARY KEY (`word_id`),
  ADD UNIQUE KEY `wrd_txt` (`word_text`),
  ADD KEY `wrd_cnt` (`word_count`);

ALTER TABLE `phpbb_search_wordmatch`
  ADD UNIQUE KEY `un_mtch` (`word_id`,`post_id`,`title_match`),
  ADD KEY `word_id` (`word_id`),
  ADD KEY `post_id` (`post_id`);

ALTER TABLE `phpbb_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `session_time` (`session_time`),
  ADD KEY `session_user_id` (`session_user_id`),
  ADD KEY `session_fid` (`session_forum_id`);

ALTER TABLE `phpbb_sessions_keys`
  ADD PRIMARY KEY (`key_id`,`user_id`),
  ADD KEY `last_login` (`last_login`);

ALTER TABLE `phpbb_sitelist`
  ADD PRIMARY KEY (`site_id`);

ALTER TABLE `phpbb_smilies`
  ADD PRIMARY KEY (`smiley_id`),
  ADD KEY `display_on_post` (`display_on_posting`);

ALTER TABLE `phpbb_styles`
  ADD PRIMARY KEY (`style_id`),
  ADD UNIQUE KEY `style_name` (`style_name`);

ALTER TABLE `phpbb_teampage`
  ADD PRIMARY KEY (`teampage_id`);

ALTER TABLE `phpbb_topics`
  ADD PRIMARY KEY (`topic_id`),
  ADD KEY `forum_id` (`forum_id`),
  ADD KEY `forum_id_type` (`forum_id`,`topic_type`),
  ADD KEY `last_post_time` (`topic_last_post_time`),
  ADD KEY `fid_time_moved` (`forum_id`,`topic_last_post_time`,`topic_moved_id`),
  ADD KEY `topic_visibility` (`topic_visibility`),
  ADD KEY `forum_vis_last` (`forum_id`,`topic_visibility`,`topic_last_post_id`),
  ADD KEY `latest_topics` (`forum_id`,`topic_last_post_time`,`topic_last_post_id`,`topic_moved_id`);

ALTER TABLE `phpbb_topics_posted`
  ADD PRIMARY KEY (`user_id`,`topic_id`);

ALTER TABLE `phpbb_topics_track`
  ADD PRIMARY KEY (`user_id`,`topic_id`),
  ADD KEY `forum_id` (`forum_id`),
  ADD KEY `topic_id` (`topic_id`);

ALTER TABLE `phpbb_topics_watch`
  ADD KEY `topic_id` (`topic_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `notify_stat` (`notify_status`);

ALTER TABLE `phpbb_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username_clean` (`username_clean`),
  ADD KEY `user_birthday` (`user_birthday`),
  ADD KEY `user_type` (`user_type`),
  ADD KEY `user_email` (`user_email`),
  ADD KEY `external_id` (`external_id`);

ALTER TABLE `phpbb_user_group`
  ADD KEY `group_id` (`group_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `group_leader` (`group_leader`);

ALTER TABLE `phpbb_user_notifications`
  ADD UNIQUE KEY `itm_usr_mthd` (`item_type`,`item_id`,`user_id`,`method`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `uid_itm_id` (`user_id`,`item_id`),
  ADD KEY `usr_itm_tpe` (`user_id`,`item_type`,`item_id`);

ALTER TABLE `phpbb_warnings`
  ADD PRIMARY KEY (`warning_id`);

ALTER TABLE `phpbb_words`
  ADD PRIMARY KEY (`word_id`);

ALTER TABLE `phpbb_zebra`
  ADD PRIMARY KEY (`user_id`,`zebra_id`);


ALTER TABLE `phpbb_acl_options`
  MODIFY `auth_option_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

ALTER TABLE `phpbb_acl_roles`
  MODIFY `role_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

ALTER TABLE `phpbb_attachments`
  MODIFY `attach_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `phpbb_banlist`
  MODIFY `ban_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `phpbb_bots`
  MODIFY `bot_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

ALTER TABLE `phpbb_disallow`
  MODIFY `disallow_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `phpbb_drafts`
  MODIFY `draft_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `phpbb_extensions`
  MODIFY `extension_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

ALTER TABLE `phpbb_extension_groups`
  MODIFY `group_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `phpbb_forums`
  MODIFY `forum_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

ALTER TABLE `phpbb_groups`
  MODIFY `group_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

ALTER TABLE `phpbb_icons`
  MODIFY `icons_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `phpbb_lang`
  MODIFY `lang_id` tinyint(4) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `phpbb_log`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

ALTER TABLE `phpbb_modules`
  MODIFY `module_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=208;

ALTER TABLE `phpbb_notifications`
  MODIFY `notification_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

ALTER TABLE `phpbb_notification_types`
  MODIFY `notification_type_id` smallint(4) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

ALTER TABLE `phpbb_posts`
  MODIFY `post_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

ALTER TABLE `phpbb_privmsgs`
  MODIFY `msg_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `phpbb_privmsgs_folder`
  MODIFY `folder_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `phpbb_privmsgs_rules`
  MODIFY `rule_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `phpbb_profile_fields`
  MODIFY `field_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `phpbb_ranks`
  MODIFY `rank_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `phpbb_reports`
  MODIFY `report_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `phpbb_reports_reasons`
  MODIFY `reason_id` smallint(4) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `phpbb_search_wordlist`
  MODIFY `word_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1205;

ALTER TABLE `phpbb_sitelist`
  MODIFY `site_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `phpbb_smilies`
  MODIFY `smiley_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

ALTER TABLE `phpbb_styles`
  MODIFY `style_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `phpbb_teampage`
  MODIFY `teampage_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `phpbb_topics`
  MODIFY `topic_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

ALTER TABLE `phpbb_users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=144;

ALTER TABLE `phpbb_warnings`
  MODIFY `warning_id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `phpbb_words`
  MODIFY `word_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;



CREATE TABLE `progress_deletion` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `set_id` int(10) UNSIGNED NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `publish_date` (
  `id` int(11) NOT NULL,
  `tsumego_id` int(11) DEFAULT NULL,
  `date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `reject` (
  `id` int(11) NOT NULL,
  `type` varchar(500) DEFAULT NULL,
  `text` varchar(500) DEFAULT NULL,
  `tsumego_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `schedule` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `tsumego_id` int(10) UNSIGNED DEFAULT NULL,
  `set_id` int(10) UNSIGNED DEFAULT NULL,
  `published` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;



CREATE TABLE `set` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(400) DEFAULT NULL,
  `title2` varchar(50) DEFAULT NULL,
  `author` varchar(400) DEFAULT NULL,
  `description` varchar(4000) DEFAULT NULL,
  `difficulty` int(11) NOT NULL DEFAULT 1,
  `image` varchar(50) DEFAULT NULL,
  `order` int(11) DEFAULT NULL,
  `public` int(11) DEFAULT 1,
  `premium` int(11) NOT NULL DEFAULT 0,
  `multiplier` float NOT NULL DEFAULT 1,
  `color` varchar(10) DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `included_in_time_mode` tinyint(1) NOT NULL DEFAULT 1,
  `board_theme_index` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;



CREATE TABLE `set_connection` (
  `id` int(11) NOT NULL,
  `set_id` int(10) UNSIGNED NOT NULL,
  `tsumego_id` int(10) UNSIGNED NOT NULL,
  `num` int(11) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `sgf` (
  `id` int(10) UNSIGNED NOT NULL,
  `sgf` mediumtext DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `tsumego_id` int(10) UNSIGNED NOT NULL,
  `created` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `accepted` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `signature` (
  `id` int(11) NOT NULL,
  `tsumego_id` int(11) NOT NULL,
  `signature` varchar(500) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `site` (
  `id` int(11) NOT NULL,
  `title` mediumtext DEFAULT NULL,
  `body` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;



CREATE TABLE `tag` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `description` varchar(5000) DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `approved` int(11) DEFAULT 1,
  `color` int(11) NOT NULL DEFAULT 0,
  `hint` int(11) DEFAULT 0,
  `created` datetime DEFAULT current_timestamp(),
  `popular` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'First 10 most used tags has this setup to 1 in the cron'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `tag_connection` (
  `id` int(11) NOT NULL,
  `tag_id` int(11) DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `tsumego_id` int(10) UNSIGNED NOT NULL,
  `approved` int(11) DEFAULT 1,
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `time_mode_attempt` (
  `id` int(10) UNSIGNED NOT NULL,
  `tsumego_id` int(10) UNSIGNED NOT NULL,
  `order` smallint(5) UNSIGNED NOT NULL,
  `seconds` decimal(5,2) UNSIGNED DEFAULT NULL,
  `points` decimal(5,2) UNSIGNED DEFAULT NULL,
  `started` datetime(2) DEFAULT NULL,
  `time_mode_session_id` int(10) UNSIGNED NOT NULL,
  `time_mode_attempt_status_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `time_mode_attempt_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `time_mode_category` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(12) NOT NULL,
  `seconds` smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `time_mode_category` (`id`, `name`, `seconds`) VALUES
(1, 'Blitz', 30),
(2, 'Fast', 60),
(3, 'Slow', 240);



CREATE TABLE `time_mode_rank` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(12) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `time_mode_session` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `points` decimal(6,2) UNSIGNED DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `time_mode_session_status_id` int(10) UNSIGNED NOT NULL,
  `time_mode_category_id` int(10) UNSIGNED NOT NULL,
  `time_mode_rank_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `time_mode_session_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(12) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `tsumego` (
  `id` int(10) UNSIGNED NOT NULL,
  `part` varchar(50) DEFAULT NULL COMMENT 'Experimental field. Currently one use for Study Group in the sandbox.',
  `part_increment` int(11) DEFAULT NULL COMMENT 'Experimental field. No use currently.',
  `description` varchar(400) DEFAULT NULL COMMENT 'Example: Black to kill.',
  `hint` varchar(400) DEFAULT NULL COMMENT 'Example: No ko.',
  `author` varchar(100) NOT NULL DEFAULT 'Joschka Zimdars',
  `solved` int(11) DEFAULT NULL COMMENT 'Number of solves by registered users. In between step to calculate the success percentage.',
  `failed` int(11) DEFAULT NULL COMMENT 'Number of fails by registered users. In between step to calculate the success percentage.',
  `rating` float NOT NULL DEFAULT 900 COMMENT 'Tsumego elo for the rating mode.',
  `minimum_rating` float DEFAULT NULL COMMENT 'Lower bound of what the rating is allowed to be for this tsumego.',
  `maximum_rating` float DEFAULT NULL COMMENT 'Upper bound of what the rating is allowed to be for this tsumego.',
  `userWin` float NOT NULL DEFAULT 0 COMMENT 'Success percentage. (Registered users)',
  `userLoss` int(11) NOT NULL DEFAULT 0 COMMENT 'How often the problem has been tried. (Registered users)',
  `created` datetime DEFAULT NULL COMMENT 'Datetime of creation.',
  `minLib` int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
  `maxLib` int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
  `variance` int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races. Is also used for new problems in the sandbox that don''t have a file.',
  `libertyCount` int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
  `insideLiberties` int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races.',
  `eyeLiberties1` int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races',
  `eyeLiberties2` int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races',
  `semeaiType` int(11) DEFAULT NULL COMMENT 'Value for The Rules of Capturing Races',
  `alternative_response` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'If the user has to counter all possible countrplays. Should be eventually removed and used for all tsumegos, more than 90% have it on.',
  `pass` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'is pass enabled',
  `activity_value` int(11) NOT NULL DEFAULT 0,
  `deleted` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `tsumego_attempt` (
  `id` int(10) UNSIGNED NOT NULL COMMENT 'id',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'The user it belongs to.',
  `tsumego_id` int(10) UNSIGNED NOT NULL COMMENT 'The tsumego it belongs to.',
  `gain` int(11) NOT NULL COMMENT 'The gain (XP) that the user got out of it.',
  `solved` tinyint(1) NOT NULL COMMENT 'Solved or failed.',
  `seconds` int(11) NOT NULL COMMENT 'Time spent in seconds.',
  `misplays` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of misplays. Didn''t exist a month ago, so before the maximum misplay was 1 per day.',
  `user_rating` int(11) DEFAULT NULL,
  `tsumego_rating` int(11) NOT NULL DEFAULT 0,
  `created` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;



CREATE TABLE `tsumego_comment` (
  `id` int(10) UNSIGNED NOT NULL,
  `tsumego_id` int(10) UNSIGNED NOT NULL,
  `tsumego_issue_id` int(10) UNSIGNED DEFAULT NULL,
  `message` varchar(2048) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(10) UNSIGNED NOT NULL,
  `position` varchar(300) DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `tsumego_issue` (
  `id` int(10) UNSIGNED NOT NULL,
  `tsumego_issue_status_id` int(10) UNSIGNED NOT NULL,
  `tsumego_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tsumego_issue_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tsumego_issue_status` (`id`, `name`) VALUES
(1, 'opened'),
(2, 'closed');

CREATE TABLE `tsumego_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'The user it belongs to.',
  `tsumego_id` int(10) UNSIGNED NOT NULL COMMENT 'The tsumego it belongs to.',
  `status` char(1) NOT NULL COMMENT 'Status: Visited ''V'', solved ''S'', solved twice ''C'', half xp ''W'', failed ''F'', failed twice ''X'', golden ''G''.',
  `updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `tsumego_variant` (
  `id` int(11) NOT NULL,
  `tsumego_id` int(11) NOT NULL DEFAULT 0,
  `type` varchar(50) NOT NULL DEFAULT '0',
  `numAnswer` double NOT NULL DEFAULT 0,
  `answer1` varchar(50) NOT NULL DEFAULT '0',
  `answer2` varchar(50) NOT NULL DEFAULT '0',
  `answer3` varchar(50) NOT NULL DEFAULT '0',
  `answer4` varchar(50) NOT NULL DEFAULT '0',
  `winner` varchar(500) DEFAULT '0',
  `explanation` varchar(500) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `user` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL COMMENT 'name',
  `email` varchar(50) NOT NULL COMMENT 'email',
  `external_id` varchar(200) DEFAULT NULL COMMENT 'google id',
  `picture` varchar(500) DEFAULT NULL COMMENT 'google image',
  `premium` smallint(2) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=no premium, 1=basic premium, 2=golden premium',
  `level` int(11) NOT NULL DEFAULT 1 COMMENT 'User level.',
  `mode` int(11) NOT NULL DEFAULT 1 COMMENT 'Current mode.',
  `rating` float NOT NULL DEFAULT 900 COMMENT 'User elo for the rating mode.',
  `rd` float NOT NULL DEFAULT 300 COMMENT 'Rating deviation for the rating mode. The changing value for the rating mode depends on the user activity and it higher when the user was not so active recently.',
  `_sessid` varchar(500) DEFAULT NULL,
  `t_glicko` int(11) NOT NULL DEFAULT 4 COMMENT 'Rating mode formula for calculating the changing value.',
  `dbstorage` int(11) NOT NULL DEFAULT 0 COMMENT 'Has no use now. Was value for the OldUserTsumego archice. ',
  `created` datetime DEFAULT NULL,
  `lastRefresh` date DEFAULT NULL,
  `xp` int(11) NOT NULL DEFAULT 0 COMMENT 'Overall xp the user has.',
  `nextlvl` int(11) NOT NULL DEFAULT 50 COMMENT 'Xp needed for the next level.',
  `damage` int(11) NOT NULL DEFAULT 0 COMMENT 'The hearts that the user has lost on this day.',
  `used_potion` smallint(1) NOT NULL DEFAULT 0,
  `promoted` int(11) NOT NULL DEFAULT 0 COMMENT 'How active is the user in general.',
  `used_intuition` smallint(1) NOT NULL DEFAULT 0,
  `used_revelation` smallint(1) NOT NULL DEFAULT 0,
  `readingTrial` int(11) NOT NULL DEFAULT 30 COMMENT 'Use of the skip button in the rating mode.',
  `isAdmin` tinyint(1) NOT NULL DEFAULT 0,
  `lastHighscore` int(11) NOT NULL DEFAULT 0 COMMENT 'Last used highscore page.',
  `lastLight` int(11) NOT NULL DEFAULT 0,
  `levelBar` int(11) NOT NULL DEFAULT 0,
  `lastProfileLeft` int(11) NOT NULL DEFAULT 0,
  `lastProfileRight` int(11) NOT NULL DEFAULT 0,
  `last_time_mode_category_id` int(10) UNSIGNED DEFAULT NULL,
  `daily_solved` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `daily_xp` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `reuse4` int(11) NOT NULL DEFAULT 0 COMMENT 'Daily maximum for non-premium users. Also helps to identify cheaters.',
  `reuse5` int(11) NOT NULL DEFAULT 0 COMMENT 'Locks accounts. For example cheaters or toxic people.',
  `penalty` int(11) NOT NULL DEFAULT 0 COMMENT 'Counts suspicious behavior. Is probably redundant with reuse2.',
  `reward` datetime DEFAULT NULL COMMENT 'Shows date and time of people who donated.',
  `used_sprint` smallint(1) NOT NULL DEFAULT 0,
  `used_rejuvenation` smallint(1) NOT NULL DEFAULT 0,
  `used_refinement` smallint(1) NOT NULL DEFAULT 0,
  `passwordreset` varchar(50) DEFAULT NULL,
  `solved` int(11) DEFAULT 0 COMMENT 'Overall solves.',
  `solved2` int(11) NOT NULL DEFAULT 0 COMMENT 'Daily solves.',
  `sortOrder` int(11) NOT NULL DEFAULT 0 COMMENT 'Last used sorting for collections.',
  `sortColor` int(11) NOT NULL DEFAULT 0 COMMENT 'Last used color sorting for collections.',
  `sound` varchar(11) NOT NULL DEFAULT '0' COMMENT 'Sound on/off',
  `ip` varchar(40) DEFAULT NULL COMMENT 'IP address. ',
  `location` varchar(10) DEFAULT NULL COMMENT 'Estimated location.',
  `password_hash` char(60) NOT NULL,
  `sprint_start` datetime DEFAULT NULL,
  `login_token` varchar(64) DEFAULT NULL COMMENT 'Used to identify the login in the forums',
  `boards_bitmask` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Bitmask representing enabled boards. Bit N corresponds to Board N+1.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT;



CREATE TABLE `user_contribution` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `query` varchar(50) NOT NULL DEFAULT 'topics',
  `collection_size` int(11) NOT NULL DEFAULT 200,
  `filtered_sets` varchar(5000) DEFAULT NULL,
  `filtered_ranks` varchar(5000) DEFAULT NULL,
  `filtered_tags` varchar(5000) DEFAULT NULL,
  `created_tag` int(11) DEFAULT 0,
  `made_proposal` int(11) DEFAULT 0,
  `reviewed` int(11) DEFAULT 0,
  `score` int(11) DEFAULT 0,
  `reward1` int(11) DEFAULT 0,
  `reward2` int(11) DEFAULT 0,
  `reward3` int(11) DEFAULT 0,
  `created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `achievement`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `achievement_condition`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `set_id` (`set_id`);

ALTER TABLE `achievement_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id_achievement_id` (`user_id`,`achievement_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `achievement_id` (`achievement_id`);

ALTER TABLE `activate`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `admin_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_tsumego_id` (`tsumego_id`),
  ADD KEY `idx_set_id` (`set_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created` (`created`);

ALTER TABLE `admin_activity_type`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `answer`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `cake_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `expires_idx` (`expires`);

ALTER TABLE `day_record`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`),
  ADD KEY `day_records_user_id` (`user_id`);

ALTER TABLE `favorite`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id_index` (`user_id`),
  ADD KEY `tsumego_id_index` (`tsumego_id`),
  ADD KEY `user_id_and_tsumego_id_index` (`user_id`,`tsumego_id`);

ALTER TABLE `progress_deletion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `progress_deletion_user_id` (`user_id`),
  ADD KEY `progress_deletion_set_id` (`set_id`);

ALTER TABLE `publish_date`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `reject`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tsumego_id` (`tsumego_id`);

ALTER TABLE `schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_tsumego_id` (`tsumego_id`),
  ADD KEY `schedule_set_id` (`set_id`),
  ADD KEY `date` (`date`);

ALTER TABLE `set`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `set_connection`
  ADD PRIMARY KEY (`id`),
  ADD KEY `set_id` (`set_id`),
  ADD KEY `tsumego_id` (`tsumego_id`);

ALTER TABLE `sgf`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tsumego_id` (`tsumego_id`);

ALTER TABLE `signature`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tsumego_id` (`tsumego_id`);

ALTER TABLE `site`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `tag`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `popular` (`popular`);

ALTER TABLE `tag_connection`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tsumego_id_tag_id` (`tsumego_id`,`tag_id`),
  ADD KEY `tsumego_id` (`tsumego_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tag_name_id` (`tag_id`);

ALTER TABLE `time_mode_attempt`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tsumego_id` (`tsumego_id`),
  ADD KEY `user_id_and_tsumego_id` (`tsumego_id`),
  ADD KEY `time_mode_session_id` (`time_mode_session_id`),
  ADD KEY `time_mode_attempt_time_mode_attempt_status_id` (`time_mode_attempt_status_id`);

ALTER TABLE `time_mode_attempt_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

 INSERT INTO `time_mode_attempt_status` (`id`, `name`) VALUES
(1, 'queued'),
(2, 'solved'),
(3, 'failed'),
(4, 'timeout'),
(5, 'skipped');

ALTER TABLE `time_mode_category`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `seconds` (`seconds`);

ALTER TABLE `time_mode_rank`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `time_mode_session`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `time_mode_session_time_mode_session_status_id` (`time_mode_session_status_id`),
  ADD KEY `time_mode_session_time_mode_category_id` (`time_mode_category_id`),
  ADD KEY `time_mode_session_time_mode_rank_id` (`time_mode_rank_id`);

ALTER TABLE `time_mode_session_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

INSERT INTO `time_mode_session_status` (`id`, `name`) VALUES
(1, 'in progress'),
(2, 'failed'),
(3, 'solved');

ALTER TABLE `tsumego`
  ADD PRIMARY KEY (`id`),
  ADD KEY `elo_index` (`rating`);

ALTER TABLE `tsumego_attempt`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id_index` (`user_id`),
  ADD KEY `tsumego_id_index` (`tsumego_id`),
  ADD KEY `user_id_and_tsumego_id_index` (`user_id`,`tsumego_id`),
  ADD KEY `created` (`created`),
  ADD KEY `user_rating` (`user_rating`),
  ADD KEY `user_id_and_user_rating` (`user_id`,`user_rating`);

ALTER TABLE `tsumego_comment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tsumego_id` (`tsumego_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tsumego_issue_id` (`tsumego_issue_id`),
  ADD KEY `created` (`created`);

ALTER TABLE `tsumego_issue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tsumego_issue_status_id` (`tsumego_issue_status_id`),
  ADD KEY `tsumego_id` (`tsumego_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created` (`created`);

ALTER TABLE `tsumego_issue_status`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `tsumego_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id_and_tsumego_id_index` (`user_id`,`tsumego_id`) USING BTREE,
  ADD KEY `user_id_index` (`user_id`),
  ADD KEY `tsumego_id_index` (`tsumego_id`);

ALTER TABLE `tsumego_variant`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name_index` (`name`) USING BTREE,
  ADD KEY `email_index` (`email`),
  ADD KEY `user_last_time_mode_category_id` (`last_time_mode_category_id`),
  ADD KEY `login_token` (`login_token`),
  ADD KEY `rating` (`rating`);

ALTER TABLE `user_contribution`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);


ALTER TABLE `achievement_condition`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `achievement_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `activate`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `admin_activity`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `answer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `day_record`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `favorite`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `progress_deletion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `publish_date`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `reject`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `set`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `set_connection`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `sgf`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `signature`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `site`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `tag`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `tag_connection`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `time_mode_attempt`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `time_mode_attempt_status`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `time_mode_category`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `time_mode_rank`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `time_mode_session`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `time_mode_session_status`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `tsumego`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `tsumego_attempt`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'id';

ALTER TABLE `tsumego_comment`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `tsumego_issue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `tsumego_status`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `tsumego_variant`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `user`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_contribution`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `achievement_condition`
  ADD CONSTRAINT `achievement_condition_set_id` FOREIGN KEY (`set_id`) REFERENCES `set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `achievement_condition_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `achievement_status`
  ADD CONSTRAINT `achievement_status_achievement_id` FOREIGN KEY (`achievement_id`) REFERENCES `achievement` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `achievement_status_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `admin_activity`
  ADD CONSTRAINT `fk_admin_activity_set` FOREIGN KEY (`set_id`) REFERENCES `set` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_admin_activity_tsumego` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_admin_activity_type` FOREIGN KEY (`type`) REFERENCES `admin_activity_type` (`id`),
  ADD CONSTRAINT `fk_admin_activity_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;

ALTER TABLE `day_record`
  ADD CONSTRAINT `day_records_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `favorite`
  ADD CONSTRAINT `FK_favorites_to_tsumegos` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_favorites_to_users` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `progress_deletion`
  ADD CONSTRAINT `progress_deletion_set_id` FOREIGN KEY (`set_id`) REFERENCES `set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `progress_deletion_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_set_id` FOREIGN KEY (`set_id`) REFERENCES `set` (`id`),
  ADD CONSTRAINT `schedule_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`);

ALTER TABLE `set_connection`
  ADD CONSTRAINT `set_connections_set_id` FOREIGN KEY (`set_id`) REFERENCES `set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `set_connections_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `sgf`
  ADD CONSTRAINT `sgfs_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `sgfs_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON UPDATE CASCADE;

ALTER TABLE `tag_connection`
  ADD CONSTRAINT `tag_connection_tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tag_connection_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tag_connection_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `time_mode_attempt`
  ADD CONSTRAINT `FK_tsumego_timed_attempts_to_tsumegos` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `time_mode_attempt_time_mode_attempt_status_id` FOREIGN KEY (`time_mode_attempt_status_id`) REFERENCES `time_mode_attempt_status` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `time_mode_attempt_time_mode_overview_id` FOREIGN KEY (`time_mode_session_id`) REFERENCES `time_mode_session` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `time_mode_session`
  ADD CONSTRAINT `time_mode_overview_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `time_mode_session_time_mode_category_id` FOREIGN KEY (`time_mode_category_id`) REFERENCES `time_mode_category` (`id`),
  ADD CONSTRAINT `time_mode_session_time_mode_rank_id` FOREIGN KEY (`time_mode_rank_id`) REFERENCES `time_mode_rank` (`id`),
  ADD CONSTRAINT `time_mode_session_time_mode_session_status_id` FOREIGN KEY (`time_mode_session_status_id`) REFERENCES `time_mode_session_status` (`id`);

ALTER TABLE `tsumego_attempt`
  ADD CONSTRAINT `FK_user_records_to_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_user_records_to_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `tsumego_comment`
  ADD CONSTRAINT `tsumego_comment_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tsumego_comment_tsumego_issue_id` FOREIGN KEY (`tsumego_issue_id`) REFERENCES `tsumego_issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tsumego_comment_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

ALTER TABLE `tsumego_issue`
  ADD CONSTRAINT `tsumego_issue_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tsumego_issue_tsumego_issue_status_id` FOREIGN KEY (`tsumego_issue_status_id`) REFERENCES `tsumego_issue_status` (`id`),
  ADD CONSTRAINT `tsumego_issue_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`);

ALTER TABLE `tsumego_status`
  ADD CONSTRAINT `FK_user_tsumegos_to_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_user_tsumegos_to_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `user`
  ADD CONSTRAINT `user_last_time_mode_category_id` FOREIGN KEY (`last_time_mode_category_id`) REFERENCES `time_mode_category` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
SQL);
	}
}
