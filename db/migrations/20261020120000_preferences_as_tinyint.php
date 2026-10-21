<?php

use Phinx\Migration\AbstractMigration;

/**
 * The three preferences a player picks once each hold two values, so they are kept
 * in tinyint columns with one meaning per value. Until now each of them mixed the
 * spelling of an older version with the current one:
 *
 * - sound was a copy of the cookie ('on'/'off') next to older 1 and 0,
 * - lastLight was 0 for light and 2 for dark, with an old 1 that also meant light,
 * - levelBar was 0 for the level bar and anything else for the rating.
 *
 * The values are rewritten so that what each account sees now stays the same.
 */
class PreferencesAsTinyint extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("UPDATE `user` SET `sound` = IF(`sound` IN ('on', '1'), 1, 0);");
		$this->execute("ALTER TABLE `user` MODIFY `sound` TINYINT NOT NULL DEFAULT 0 COMMENT '0=silent, 1=sound on';");

		$this->execute('UPDATE `user` SET `lastLight` = IF(`lastLight` = 2, 1, 0);');
		$this->execute("ALTER TABLE `user` MODIFY `lastLight` TINYINT NOT NULL DEFAULT 0 COMMENT '0=light, 1=dark';");

		// 1 and 0 are both what clicking Level leaves behind, so they read as level
		$this->execute('UPDATE `user` SET `levelBar` = IF(`levelBar` IN (0, 1), 1, 2);');
		$this->execute("ALTER TABLE `user` MODIFY `levelBar` TINYINT NOT NULL DEFAULT 1 COMMENT '1=level, 2=rating';");
	}

	/**
	 * The values go back into the wider columns. Two spellings that meant the same
	 * choice can not be recovered separately, so each keeps the one the interface uses.
	 */
	public function down(): void
	{
		$this->execute("ALTER TABLE `user` MODIFY `sound` VARCHAR(11) NOT NULL DEFAULT '0';");
		$this->execute("UPDATE `user` SET `sound` = IF(`sound` = 1, 'on', 'off');");

		$this->execute('ALTER TABLE `user` MODIFY `lastLight` INT(11) NOT NULL DEFAULT 0;');
		$this->execute('UPDATE `user` SET `lastLight` = IF(`lastLight` = 1, 2, 0);');

		$this->execute('ALTER TABLE `user` MODIFY `levelBar` INT(11) NOT NULL DEFAULT 0;');
		$this->execute('UPDATE `user` SET `levelBar` = IF(`levelBar` = 2, 2, 0);');
	}
}
