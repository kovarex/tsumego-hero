<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The coded columns of user explained only by a comment get lookup tables like date_format, so a
 * player can only hold a value that exists:
 *
 * - premium (0 none, 1 basic, 2 golden) → premium_id, NULL without premium
 * - mode (1 level, 2 rating, 3 time, 5 mistake training) → mode_id
 * - theme (0 light, 1 dark) → theme_id
 * - level_bar (1 level, 2 rating) → level_bar_id
 * - highscore_page (1 level, 2 rating, 3 daily, 4 time mode, 0 none yet) → highscore_page_id, NULL for none yet
 * - pref_avatar (0 go board, 1 gravatar, 2 google picture) → avatar_setting_id
 *
 * The two-valued ones become booleans: sound, and pref_player_color and pref_board_orientation,
 * renamed to player_color_setting and board_orientation_setting. A value no code wrote takes the
 * default.
 */
final class AddUserSettingLookups extends AbstractMigration
{
	/** lookup table => [id => name] */
	private const LOOKUPS = [
		'premium' => [1 => 'basic', 2 => 'golden'],
		'mode' => [1 => 'level', 2 => 'rating', 3 => 'time', 5 => 'mistake training'],
		'theme' => [1 => 'light', 2 => 'dark'],
		'level_bar' => [1 => 'level', 2 => 'rating'],
		'highscore_page' => [1 => 'level', 2 => 'rating', 3 => 'daily', 4 => 'time mode'],
		'avatar_setting' => [1 => 'go board', 2 => 'gravatar', 3 => 'google picture'],
	];

	public function up(): void
	{
		foreach (self::LOOKUPS as $table => $names)
		{
			$this->execute("CREATE TABLE `$table` (
				id int(10) unsigned NOT NULL,
				name varchar(30) NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY name (name)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
			$rows = [];
			foreach ($names as $id => $name)
				$rows[] = '(' . $id . ', ' . $this->getAdapter()->getConnection()->quote($name) . ')';
			$this->execute("INSERT INTO `$table` (id, name) VALUES " . implode(', ', $rows));
		}

		$this->execute('ALTER TABLE user
			ADD premium_id int(10) unsigned DEFAULT NULL AFTER premium,
			ADD mode_id int(10) unsigned NOT NULL DEFAULT 1 AFTER mode,
			ADD highscore_page_id int(10) unsigned DEFAULT NULL AFTER highscore_page,
			ADD theme_id int(10) unsigned NOT NULL DEFAULT 1 AFTER theme,
			ADD level_bar_id int(10) unsigned NOT NULL DEFAULT 1 AFTER level_bar,
			ADD avatar_setting_id int(10) unsigned NOT NULL DEFAULT 1 AFTER pref_avatar');
		$this->execute('UPDATE user SET
			premium_id = IF(premium IN (1, 2), premium, NULL),
			mode_id = IF(mode IN (1, 2, 3, 5), mode, 1),
			highscore_page_id = IF(highscore_page IN (1, 2, 3, 4), highscore_page, NULL),
			theme_id = IF(theme = 1, 2, 1),
			level_bar_id = IF(level_bar = 2, 2, 1),
			avatar_setting_id = CASE pref_avatar WHEN 1 THEN 2 WHEN 2 THEN 3 ELSE 1 END,
			sound = sound <> 0,
			pref_player_color = pref_player_color = 1,
			pref_board_orientation = pref_board_orientation = 1');

		$alter = ['DROP COLUMN premium', 'DROP COLUMN mode', 'DROP COLUMN highscore_page', 'DROP COLUMN theme', 'DROP COLUMN level_bar', 'DROP COLUMN pref_avatar',
			"MODIFY sound tinyint(1) NOT NULL DEFAULT 0",
			"CHANGE pref_player_color player_color_setting tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 random, 1 the color of the original'",
			"CHANGE pref_board_orientation board_orientation_setting tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 random, 1 the orientation of the original'"];
		foreach (array_keys(self::LOOKUPS) as $table)
		{
			$column = $table . '_id';
			$alter[] = "ADD KEY user_$column ($column)";
			$alter[] = "ADD CONSTRAINT user_$column FOREIGN KEY ($column) REFERENCES `$table` (id) ON UPDATE CASCADE";
		}
		$this->execute('ALTER TABLE user ' . implode(', ', $alter));
	}

	public function down(): void
	{
		$alter = [];
		foreach (array_keys(self::LOOKUPS) as $table)
			$alter[] = 'DROP FOREIGN KEY user_' . $table . '_id';
		$this->execute('ALTER TABLE user ' . implode(', ', $alter));

		$this->execute("ALTER TABLE user
			ADD premium smallint(2) unsigned NOT NULL DEFAULT 0 COMMENT '0=no premium, 1=basic premium, 2=golden premium' AFTER premium_id,
			ADD mode int(11) NOT NULL DEFAULT 1 COMMENT 'Current mode.' AFTER mode_id,
			ADD highscore_page int(11) NOT NULL DEFAULT 0 COMMENT 'The highscore page the menu links to: 1 level, 2 rating, 3 daily, 4 time mode, 0 none yet.' AFTER highscore_page_id,
			ADD theme tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=light, 1=dark' AFTER theme_id,
			ADD level_bar tinyint(4) NOT NULL DEFAULT 1 COMMENT 'What the bar in the header shows: 1=level, 2=rating' AFTER level_bar_id,
			ADD pref_avatar tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=go board, 1=gravatar, 2=google picture' AFTER avatar_setting_id,
			MODIFY sound tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=silent, 1=sound on',
			CHANGE player_color_setting pref_player_color tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=random, 1=from original',
			CHANGE board_orientation_setting pref_board_orientation tinyint(4) NOT NULL DEFAULT 0 COMMENT '0=random, 1=from original'");
		$this->execute('UPDATE user SET
			premium = COALESCE(premium_id, 0),
			mode = mode_id,
			highscore_page = COALESCE(highscore_page_id, 0),
			theme = theme_id - 1,
			level_bar = level_bar_id,
			pref_avatar = avatar_setting_id - 1');

		$alter = [];
		foreach (array_keys(self::LOOKUPS) as $table)
		{
			$alter[] = 'DROP KEY user_' . $table . '_id';
			$alter[] = 'DROP COLUMN ' . $table . '_id';
		}
		$this->execute('ALTER TABLE user ' . implode(', ', $alter));
		foreach (array_keys(self::LOOKUPS) as $table)
			$this->execute("DROP TABLE `$table`");
	}
}
