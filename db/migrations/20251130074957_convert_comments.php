<?php

declare(strict_types=1);
use Phinx\Migration\AbstractMigration;

final class ConvertComments extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("DROP TABLE IF EXISTS `tsumego_comment`");
		$this->execute("DROP TABLE IF EXISTS `tsumego_issue`");
		$this->execute("DROP TABLE IF EXISTS `tsumego_issue_status`");
		$this->execute("
CREATE TABLE `tsumego_issue_status` (
	`id` INT UNSIGNED NOT NULL,
	`name` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	PRIMARY KEY (`id`))
ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci");

		// Status values: 1=opened, 2=closed (deleted is a boolean column on the issue)
		$this->execute("INSERT INTO tsumego_issue_status (id, name)
VALUES
(1, 'opened'),
(2, 'closed')");

		$this->execute("
CREATE TABLE `tsumego_issue` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`tsumego_issue_status_id` INT UNSIGNED NOT NULL,
	`tsumego_id` INT UNSIGNED NOT NULL,
	`user_id` INT UNSIGNED NOT NULL,
	`created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`deleted` BOOL NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	INDEX `tsumego_issue_status_id` (`tsumego_issue_status_id`),
	INDEX `tsumego_id` (`tsumego_id`),
	INDEX `user_id` (`user_id`),
	INDEX `created` (`created`),
	CONSTRAINT `tsumego_issue_tsumego_issue_status_id` FOREIGN KEY (`tsumego_issue_status_id`) REFERENCES `tsumego_issue_status` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
	CONSTRAINT `tsumego_issue_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `tsumego_issue_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT)
ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
");

		$this->execute("
CREATE TABLE `tsumego_comment` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`tsumego_id` INT UNSIGNED NOT NULL,
	`tsumego_issue_id` INT UNSIGNED NULL DEFAULT NULL,
	`message` VARCHAR(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	`created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`user_id` INT UNSIGNED NOT NULL,
	`position` VARCHAR(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
	`deleted` BOOL NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	INDEX `tsumego_id` (`tsumego_id`),
	INDEX `user_id` (`user_id`),
	INDEX `tsumego_issue_id` (`tsumego_issue_id`),
	INDEX `created` (`created`),
	CONSTRAINT `tsumego_comment_tsumego_id` FOREIGN KEY (`tsumego_id`) REFERENCES `tsumego` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `tsumego_comment_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
	CONSTRAINT `tsumego_comment_tsumego_issue_id` FOREIGN KEY (`tsumego_issue_id`) REFERENCES `tsumego_issue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE)
ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
");
		$comments = $this->query("SELECT * from comment");
		foreach ($comments as $comment)
		{
			$status = $comment['status'];
			$intStatus = is_numeric($status) ? intval($status) : 100;
			if ($intStatus >= 96 && $intStatus != 100)
				continue;
			$user = $this->query("SELECT * from user where id=".$comment['user_id']);
			if (!$user->rowCount())
				continue;
			$tsumego = $this->query("SELECT * from tsumego where id=".$comment['tsumego_id']);
			if (!$tsumego->rowCount())
				continue;

			if ($status == "Your moves have been added.")
				$intStatus = 1;

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

			$adminUser = $this->query("SELECT * from user where id=?", [$comment['admin_id']]);

			// creating mini issue from the comment
			if ($adminUser->rowCount() && $intStatus >= 1 && $intStatus <= 14)
			{
				// 2 = CLOSED_STATUS
			$this->execute("INSERT INTO tsumego_issue (`user_id`, `tsumego_id`, `tsumego_issue_status_id`, `created`) VALUES(?, ?, ?, ?)",
					[$comment['user_id'], $comment['tsumego_id'], '2', $comment['created']]);

				$tsumegoIssue = $this->query("SELECT * from tsumego_issue ORDER BY id DESC")->fetchAll()[0];


				$this->execute("INSERT INTO tsumego_comment (`user_id`, `tsumego_id`, `tsumego_issue_id`, `message`, `created`) VALUES(?, ?, ?, ?, ?)",
					[$comment['user_id'], $comment['tsumego_id'], $tsumegoIssue['id'], $comment['message'], $comment['created']]);

				$this->execute("INSERT INTO tsumego_comment (`user_id`, `tsumego_id`, `tsumego_issue_id`, `message`, `created`) VALUES(?, ?, ?, ?, ?)",
					[$comment['admin_id'], $comment['tsumego_id'], $tsumegoIssue['id'], $answerMessage, $comment['created']]);
				continue;
			}

			$this->execute("INSERT INTO tsumego_comment (`user_id`, `tsumego_id`, `message`, `created`) VALUES(?, ?, ?, ?)",
				[$comment['user_id'], $comment['tsumego_id'], $comment['message'], $comment['created']]);

			if ($answerMessage && $adminUser->rowCount())
				$this->execute("INSERT INTO tsumego_comment (`user_id`, `tsumego_id`, `message`, `created`) VALUES(?, ?, ?, ?)",
					[$comment['admin_id'], $comment['tsumego_id'], $answerMessage, $comment['created']]);
		}
    }
}
