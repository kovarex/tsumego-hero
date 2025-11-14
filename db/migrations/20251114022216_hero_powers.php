<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class HeroPowers extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE user DROP COLUMN refinement");
		$this->execute("ALTER TABLE user CHANGE `usedRefinement` `used_refinement` SMALLINT(1) NOT NULL DEFAULT 0");
		$this->execute("ALTER TABLE user CHANGE `potion` `used_potion` SMALLINT(1) NOT NULL DEFAULT 0");
		$this->execute("ALTER TABLE user DROP COLUMN sprint");
		$this->execute("ALTER TABLE user CHANGE `usedSprint` `used_sprint` SMALLINT(1) NOT NULL DEFAULT 0");
		$this->execute("ALTER TABLE user CHANGE `intuition` `used_intuition` SMALLINT(1) NOT NULL DEFAULT 0");
		$this->execute("ALTER TABLE user CHANGE `usedRejuvenation` `used_rejuvenation` SMALLINT(1) NOT NULL DEFAULT 0");
		$this->execute("ALTER TABLE user DROP COLUMN rejuvenation");
		$this->execute("ALTER TABLE user CHANGE `revelation` `used_revelation` SMALLINT(1) NOT NULL DEFAULT 0");
	}
}
