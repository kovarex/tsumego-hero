<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//user_contribution was a 1:1 extension of user: the collection filters of a player and the
//contribution counters. Parallel first requests of a player could insert two rows (forum topic 95,
//PR #153), the site then read and wrote only the oldest one.
//The filters move to user, which is loaded on every request anyway. Of the contribution data only
//reward3 was read (one more revelation per day), it becomes contribution_revelation_bonus.
//The counters (created_tag, made_proposal, reviewed, score) were shown nowhere and reward1/reward2
//were never read, they are dropped.
//IRREVERSIBLE in part: a rollback brings the table back with the filters and reward3, the counters stay at 0.
final class MoveUserContributionIntoUser extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("
			ALTER TABLE user
				ADD COLUMN collection_grouping VARCHAR(50) NOT NULL DEFAULT 'topics',
				ADD COLUMN collection_size INT NOT NULL DEFAULT 200,
				ADD COLUMN filtered_sets TEXT NULL DEFAULT NULL,
				ADD COLUMN filtered_ranks TEXT NULL DEFAULT NULL,
				ADD COLUMN filtered_tags TEXT NULL DEFAULT NULL,
				ADD COLUMN contribution_revelation_bonus TINYINT(1) NOT NULL DEFAULT 0");

		// the oldest row of a player is the one the site read, a reward on any row counts
		$this->execute("
			UPDATE user
			JOIN (
				SELECT user_id, MIN(id) AS id, MAX(COALESCE(reward3, 0) <> 0) AS revelation_bonus
				FROM user_contribution
				WHERE user_id IS NOT NULL
				GROUP BY user_id
			) player ON player.user_id = user.id
			JOIN user_contribution oldest ON oldest.id = player.id
			SET
				user.collection_grouping = oldest.query,
				user.collection_size = oldest.collection_size,
				user.filtered_sets = oldest.filtered_sets,
				user.filtered_ranks = oldest.filtered_ranks,
				user.filtered_tags = oldest.filtered_tags,
				user.contribution_revelation_bonus = player.revelation_bonus");

		$this->execute('DROP TABLE user_contribution');
	}

	public function down(): void
	{
		$this->execute("
			CREATE TABLE user_contribution (
				id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
				user_id INT DEFAULT NULL,
				query VARCHAR(50) NOT NULL DEFAULT 'topics',
				collection_size INT NOT NULL DEFAULT 200,
				filtered_sets VARCHAR(5000) DEFAULT NULL,
				filtered_ranks VARCHAR(5000) DEFAULT NULL,
				filtered_tags VARCHAR(5000) DEFAULT NULL,
				created_tag INT DEFAULT 0,
				made_proposal INT DEFAULT 0,
				reviewed INT DEFAULT 0,
				score INT DEFAULT 0,
				reward1 INT DEFAULT 0,
				reward2 INT DEFAULT 0,
				reward3 INT DEFAULT 0,
				created DATETIME DEFAULT CURRENT_TIMESTAMP,
				KEY user_id (user_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$this->execute("
			INSERT INTO user_contribution (user_id, query, collection_size, filtered_sets, filtered_ranks, filtered_tags, reward3)
			SELECT id, collection_grouping, collection_size, filtered_sets, filtered_ranks, filtered_tags, contribution_revelation_bonus
			FROM user");
		$this->execute('
			ALTER TABLE user
				DROP COLUMN collection_grouping,
				DROP COLUMN collection_size,
				DROP COLUMN filtered_sets,
				DROP COLUMN filtered_ranks,
				DROP COLUMN filtered_tags,
				DROP COLUMN contribution_revelation_bonus');
	}
}
