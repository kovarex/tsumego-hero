<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SetThemeIndex extends AbstractMigration
{
    public function up(): void
    {
		$this->execute("ALTER TABLE `set` ADD `board_theme_index` INT NULL AFTER `included_in_time_mode`;");
		$this->execute("UPDATE `set` SET board_theme_index = 23 WHERE id = 11969"); // pretty area
		$this->execute("UPDATE `set` SET board_theme_index = 24 WHERE id = 29156"); //Hunting
		$this->execute("UPDATE `set` SET board_theme_index = 25 WHERE id = 31813"); //The Ghost
		$this->execute("UPDATE `set` SET board_theme_index = 26 WHERE id = 33007"); //Carnage
		$this->execute("UPDATE `set` SET board_theme_index = 27 WHERE id = 71790"); //Blind Spot
		$this->execute("UPDATE `set` SET board_theme_index = 28 WHERE id = 74761"); //Giants
		$this->execute("UPDATE `set` SET board_theme_index = 29 WHERE id = 81578"); //Moves of Resistance
		$this->execute("UPDATE `set` SET board_theme_index = 29 WHERE id = 88156"); //Hand of God
    }
}
