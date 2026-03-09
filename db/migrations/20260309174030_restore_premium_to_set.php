<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RestorePremiumToSet extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `set` ADD COLUMN `premium` INT(11) NOT NULL DEFAULT 0 AFTER `public`");

		$premiumSetIds = [
			38,  // In-seong's Lectures
			41,  // Life & Death - Intermediate
			42,  // The Rules of Capturing Races
			50,  // Life & Death - Elementary
			51,  // Life & Death - Advanced
			56,  // Life & Death - Advanced
			57,  // Life & Death - Advanced
			58,  // Counting Liberties & Winning Capturing Races
			62,  // Level Up Vol. 1
			63,  // Get Strong at the Endgame
			67,  // Korean Problem Academy 1
			69,  // Korean Problem Academy 2
			72,  // (untitled)
			73,  // (untitled)
			74,  // (untitled)
			75,  // (untitled)
			76,  // (untitled)
			77,  // (untitled)
			78,  // (untitled)
			79,  // (untitled)
			80,  // (untitled)
			81,  // Korean Problem Academy 3
			90,  // Tsumego Dictionary Volume I
			91,  // Level Up Vol. 1
			92,  // Korean Problem Academy 4
			93,  // Introduction to Life & Death
			101, // Tsumego Dictionary Volume II
			107, // Tsumego Dictionary Volume III
			109, // Problems from Professional Games
			113, // Tesuji Training
			114, // Endgame Tesujis
			118, // Life & Death - Intermediate
			119, // Life & Death - Advanced
			120, // Gokyo Shumyo I
			122, // Gokyo Shumyo I
			123, // Gokyo Shumyo II
			124, // Gokyo Shumyo II
			125, // Gokyo Shumyo III
			126, // Tsumego Masterx
			127, // Gokyo Shumyo III
			128, // Life & Death - Intermediate
			129, // Tsumego Masterx
			130, // Gokyo Shumyo IV
			134, // Tsumego Masterx
			139, // Gokyo Shumyo IV
			140, // The Art of Go Series I: Connecting Stones
			144, // Tesujis in Real Board Positions
			145, // Tesujis in Real Board Positions
			146, // Tsumego of Fortitude
			148, // 13x13 Endgame Problems
			149, // Kanzufu
			151, // Kanzufu - Tesuji
			156, // The Sabaki Collection
			157, // Kanzufu - Tesuji
			158, // Beautiful Tsumego
			159, // Tsumego Hero Study Group
			160, // Single-Digit-Kyu Problems
			161, // Joseki Hero
			162, // Xuanxuan Qijing
			163, // Xuanxuan Qijing
			164, // Secret by Kim Jiseok
			165, // test
			167, // 9x9 Endgame Problems
			168, // 5x5 Problems
			169, // 4x4 Problems
			170, // Sacrificial Tsumego
			173, // Secret by Kim Jiseok
			174, // 1000 Weiqi problems - 1st half
			175, // 1000 Weiqi problems - 1st half
			177, // Life & Death - Advanced
			178, // Life & Death - Advanced
			179, // Life & Death - Advanced
			180, // Life & Death - Advanced
			181, // 5x5 Problems
		];

		$ids = implode(', ', $premiumSetIds);
		$this->execute("UPDATE `set` SET `premium` = 1 WHERE `id` IN ({$ids})");
	}

	public function down(): void
	{
		$this->execute("ALTER TABLE `set` DROP COLUMN `premium`");
	}
}
