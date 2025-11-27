<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ConvertComments extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("
CREATE TABLE `tsumego_issue_status` (
	`id` INT UNSIGNED NOT NULL,
	`name` INT UNSIGNED NOT NULL)
ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
");
		$this->execute("INSERT INTO tsumego_issue_status (id, name)
VALUES (".TsumegoIssue::$OPENED_STATUS.", 'opened'),
VALUES (".TsumegoIssue::$CLOSED_STATUS.", 'closed'),
VALUES (".TsumegoIssue::$REVIEW_STATUS.", 'reviewed'),
VALUES (".TsumegoIssue::$DELETED_STATUS.", 'deleted')");

		$this->execute("
CREATE TABLE `tsumego_issue` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`tsumego_issue_status_id` INT UNSIGNED NOT NULL,
	`tsumego_id` INT UNSIGNED NOT NULL,
	`user_id` INT UNSIGNED NOT NULL,
	`datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	INDEX (`tsumego_issue_status_id`),
	INDEX `tsumego_id` (`tsumego_id`),
	INDEX `user_id` (`user_id`),
	INDEX `datetime` (`datetime`),
	CONSTRAINT `tsumego_comment_tsumego_issue_status_id` FOREIGN KEY (`tsumego_issue_status_id`) REFERENCES `tsumego_issue_status` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
	CONSTRAINT `tsumego_comment_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `tsumego_comment_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT)
ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
");

		$this->execute("
CREATE TABLE `tsumego_comment` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`tsumego_id` INT UNSIGNED NOT NULL,
	`tsumego_issue_id` INT UNSIGNED NULL,
	`message` VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	`datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`user_id` INT UNSIGNED NOT NULL,
	`position` VARCHAR(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	PRIMARY KEY (`id`),
	INDEX `tsumego_id` (`tsumego_id`),
	INDEX `user_id` (`user_id`),
	INDEX `tsumego_issue_id` (`tsumego_issue_id`),
	INDEX `datetime` (`datetime`),
	CONSTRAINT `tsumego_comment_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `tsumego_comment_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
	CONSTRAINT `tsumego_comment_tsumego_issue_id` FOREIGN KEY (`tsumego_issue`) REFERENCES `tsumego_issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE)
ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
");
		$comments = ClassRegistry::init('Comment')->find('all');
		foreach ($comments as $comment)
		{
			$status = $comment['status'];
			$intStatus = is_int($status) ? intval($status) : 100;
			if ($intStatus >= 96)
				continue;
			$comment = $comment['Comment'];
			if (!ClassRegistry::init('User')->findById($comment['user_id']))
				continue;
			if (!ClassRegistry::init('Tsumego')->findById($comment['tsumego_id']))
				continue;

			$answerMessage = null;
			switch ($intStatus)
			{
				case 1: $answerMessage ='Your move(s) have been added.'; break;
				case 2: $answerMessage ='Your file has been added.'; break;
				case 3: $answerMessage ='Your solution has been added.'; break;
				case 4: $answerMessage ='I disagree with your comment.'; break;
				case 5: $answerMessage = 'Provide sequence.'; break;
				case 6: $answerMessage = 'Resolved.'; break;
				case 7: $answerMessage = 'I couldn\'t follow your comment.'; break;
				case 8: $answerMessage = 'You seem to try sending non-SGF-files.'; break;
				case 9: $answerMessage = 'You answer is inferior to the correct solution.'; break;
				case 10: $answerMessage = 'I disagree with your comment. I added sequences.'; break;
				case 11: $answerMessage = 'I don\'t know.'; break;
				case 12: $answerMessage = 'I added sequences.'; break;
				case 13: $answerMessage = 'You are right, but the presented sequence is more interesting.'; break;
				case 14: $answerMessage = 'I didn\'t add your file.'; break;
				case 100: $answerMessage = $status; break;
			}

			// creating mini issue from the comment
			if ($intStatus >= 1 && $intStatus <= 14 && ClassRegistry::init('User')->findById($comment['admin_id']))
			{
				$tsumegoIssue = [];
				$tsumegoIssue['user_id'] = $comment['user_id'];
				$tsumegoIssue['tsumego_id'] = $comment['tsumego_id'];
				$tsumegoIssue['tsumego_issue_status_id'] = TsumegoIssue::$OPENED_STATUS;
				$tsumegoIssue['datetime'] = $comment['created'];
				ClassRegistry::init('TsumegoIssue')->create();
				ClassRegistry::init('TsumegoIssue')->save($tsumegoIssue);
				$tsumegoIssue = ClassRegistry::init('TsumegoIssue')->find('first', ['order'=> 'id DESC'])['TsumegoIssue'];

				$tsumegoComment = [];
				$tsumegoComment['user_id'] = $comment['user_id'];
				$tsumegoComment['tsumego_id'] = $comment['tsumego_id'];
				$tsumegoComment['message'] = $comment['message'];
				$tsumegoComment['tsumego_issue_id'] = $tsumegoIssue['id'];
				ClassRegistry::init('TsumegoComment')->create();
				ClassRegistry::init('TsumegoComment')->save($tsumegoComment);

				$answerComment = [];
				$answerComment['user_id'] = $comment['user_id'];
				$answerComment['tsumego_id'] = $comment['tsumego_id'];
				$answerComment['message'] = $answerMessage;
				$answerComment['tsumego_issue_id'] = $tsumegoIssue['id'];
				ClassRegistry::init('TsumegoComment')->create();
				ClassRegistry::init('TsumegoComment')->save($answerComment);
				continue;
			}

			// converting it to regular comment
			$tsumegoComment = [];
			$tsumegoComment['user_id'] = $comment['user_id'];
			$tsumegoComment['tsumego_id'] = $comment['tsumego_id'];
			$tsumegoComment['message'] = $comment['message'];
			ClassRegistry::init('Comment')->create();
			ClassRegistry::init('Comment')->save($tsumegoComment);

			if ($answerMessage)
			{
				$answerComment = [];
				$answerComment['user_id'] = $comment['user_id'];
				$answerComment['tsumego_id'] = $comment['tsumego_id'];
				$answerComment['message'] = $comment['message'];
				ClassRegistry::init('Comment')->create();
				ClassRegistry::init('Comment')->save($answerComment);
			}
		}
    }
}
