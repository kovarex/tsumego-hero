<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//What an achievement condition counts comes from achievement_condition_category (see the
//AchievementCondition::CATEGORY_ constants) instead of a varchar with short codes, and a player has
//each condition once: a unique key on the player, the category and the set. set_id is NULL for all
//but the two set records and a unique key lets NULLs repeat, so the key takes set_id_or_zero. That
//column is VIRTUAL, as MariaDB refuses a stored one over set_id, whose foreign key cascades.
//The code wrote the conditions by "find it, else create it", so a condition could be there twice;
//now it writes them with INSERT ... ON DUPLICATE KEY UPDATE.
//Duplicates are merged into the oldest row with the value the code read: the highest one, the lowest
//for the best time on a set. Conditions of a category no code knows, and set records without a set,
//go; nothing read them.
final class AddAchievementConditionCategory extends AbstractMigration
{
	//id => [the code in achievement_condition.category, the name]
	private const CATEGORIES = [
		1 => ['set', 'completed sets'],
		2 => ['s', 'set time'],
		3 => ['%', 'set accuracy'],
		4 => ['err', 'no error streak'],
		5 => ['potion', 'potion'],
		6 => ['sprint', 'sprint'],
		7 => ['golden', 'golden'],
		8 => ['uotd', 'user of the day'],
		9 => ['danSolve1d', 'dan solve 1d'],
		10 => ['danSolve2d', 'dan solve 2d'],
		11 => ['danSolve3d', 'dan solve 3d'],
		12 => ['danSolve4d', 'dan solve 4d'],
		13 => ['danSolve5d', 'dan solve 5d'],
		14 => ['emerald', 'emerald'],
		15 => ['sapphire', 'sapphire'],
		16 => ['ruby', 'ruby'],
	];

	private const SET_TIME = 2;
	private const SET_ACCURACY = 3;

	public function up(): void
	{
		$this->execute("CREATE TABLE achievement_condition_category (
			id int(10) unsigned NOT NULL,
			name varchar(30) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY name (name)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		$rows = [];
		$cases = [];
		foreach (self::CATEGORIES as $id => [$code, $name])
		{
			$rows[] = '(' . $id . ', ' . $this->getAdapter()->getConnection()->quote($name) . ')';
			$cases[] = 'WHEN ' . $this->getAdapter()->getConnection()->quote($code) . ' THEN ' . $id;
		}
		$this->execute('INSERT INTO achievement_condition_category (id, name) VALUES ' . implode(', ', $rows));

		$this->execute('ALTER TABLE achievement_condition ADD achievement_condition_category_id int(10) unsigned DEFAULT NULL AFTER user_id');
		$this->execute('UPDATE achievement_condition SET achievement_condition_category_id = CASE category ' . implode(' ', $cases) . ' END');
		$this->execute('DELETE FROM achievement_condition WHERE achievement_condition_category_id IS NULL');
		$setRecords = self::SET_TIME . ', ' . self::SET_ACCURACY;
		$this->execute('DELETE FROM achievement_condition WHERE achievement_condition_category_id IN (' . $setRecords . ') AND set_id IS NULL');
		$this->execute('UPDATE achievement_condition SET set_id = NULL WHERE achievement_condition_category_id NOT IN (' . $setRecords . ')');
		$this->execute('UPDATE achievement_condition kept
			JOIN (
				SELECT MIN(id) AS id, IF(achievement_condition_category_id = ' . self::SET_TIME . ', MIN(value), MAX(value)) AS value
				FROM achievement_condition
				GROUP BY user_id, achievement_condition_category_id, set_id
				HAVING COUNT(*) > 1
			) merged ON merged.id = kept.id
			SET kept.value = merged.value');
		$this->execute('DELETE later FROM achievement_condition later
			JOIN achievement_condition earlier
				ON earlier.user_id = later.user_id
				AND earlier.achievement_condition_category_id = later.achievement_condition_category_id
				AND earlier.set_id <=> later.set_id
				AND earlier.id < later.id');

		$this->execute("ALTER TABLE achievement_condition
			DROP COLUMN category,
			MODIFY id int(10) unsigned NOT NULL AUTO_INCREMENT,
			MODIFY achievement_condition_category_id int(10) unsigned NOT NULL,
			ADD set_id_or_zero int(10) unsigned AS (COALESCE(set_id, 0)) VIRTUAL COMMENT 'set_id for the unique key, which would let NULLs repeat' AFTER set_id,
			ADD UNIQUE KEY user_category_set (user_id, achievement_condition_category_id, set_id_or_zero),
			ADD KEY achievement_condition_achievement_condition_category_id (achievement_condition_category_id),
			ADD CONSTRAINT achievement_condition_achievement_condition_category_id FOREIGN KEY (achievement_condition_category_id) REFERENCES achievement_condition_category (id) ON UPDATE CASCADE");
		// the unique key starts with user_id, its foreign key goes with it
		$this->execute('ALTER TABLE achievement_condition DROP KEY user_id');
	}

	public function down(): void
	{
		$cases = [];
		foreach (self::CATEGORIES as $id => [$code])
			$cases[] = 'WHEN ' . $id . ' THEN ' . $this->getAdapter()->getConnection()->quote($code);
		$this->execute('ALTER TABLE achievement_condition ADD KEY user_id (user_id)');
		$this->execute('ALTER TABLE achievement_condition DROP FOREIGN KEY achievement_condition_achievement_condition_category_id');
		$this->execute('ALTER TABLE achievement_condition ADD category varchar(500) DEFAULT NULL AFTER set_id');
		$this->execute('UPDATE achievement_condition SET category = CASE achievement_condition_category_id ' . implode(' ', $cases) . ' END');
		$this->execute('ALTER TABLE achievement_condition
			DROP KEY user_category_set,
			DROP KEY achievement_condition_achievement_condition_category_id,
			DROP COLUMN set_id_or_zero,
			DROP COLUMN achievement_condition_category_id,
			MODIFY id int(11) NOT NULL AUTO_INCREMENT');
		$this->execute('DROP TABLE achievement_condition_category');
	}
}
