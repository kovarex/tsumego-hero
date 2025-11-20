<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DailyXp extends AbstractMigration {
    public function up(): void {
		$this->execute("ALTER TABLE `user` CHANGE `reuse3` `daily_xp` INT UNSIGNED NOT NULL DEFAULT '0'");
		$this->execute("ALTER TABLE `user` CHANGE `reuse2` `daily_solved` INT UNSIGNED NOT NULL DEFAULT '0'");
    }
}
