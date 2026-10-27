<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//A version of a problem remembers which admin made it part of the problem and when: the admin who
//saved their own change, or the admin who accepted a proposal. The SGF changes page shows it next
//to the original author of an accepted proposal, and lists the changes in the order they became
//part of the problem, so a proposal accepted today is on top, not among the days it was proposed.
//The versions from before are filled from what is known:
//- An accepted proposal of the admin activity log (Accept Proposal, its new value is the name of
//  the proposer) is the newest accepted version of the problem by that proposer, the newest log
//  rows taking the newest versions. It was accepted by the admin of the log row, at its time.
//- Any other accepted version by someone who is an admin now is their own change, made when it
//  was saved.
//- The rest are accepted proposals nobody logged the decision of. They keep no admin, the page
//  says so, and they take the time they were saved. Until 2026-10-24 sgf.created changed on every
//  update of the row, so that is usually the time of the acceptance anyway.
final class AddSgfAcceptedBy extends AbstractMigration
{
	public function up(): void
	{
		$this->execute("ALTER TABLE `sgf`
  ADD `accepted_by_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'The admin who made the version part of the problem: the author of an admin''s own change, the admin who accepted a proposal. NULL while proposed or rejected, and for old accepted proposals nobody logged the decision of' AFTER `user_id`,
  ADD `accepted` datetime DEFAULT NULL COMMENT 'When the version became part of the problem, NULL while proposed or rejected' AFTER `created`,
  ADD KEY `sgf_status_id_accepted` (`sgf_status_id`, `accepted`),
  ADD CONSTRAINT `sgf_accepted_by_user_id` FOREIGN KEY (`accepted_by_user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;");

		$this->fillLoggedAcceptances();
		$this->execute("UPDATE `sgf`
JOIN `user` ON `user`.`id` = `sgf`.`user_id`
SET `sgf`.`accepted_by_user_id` = `sgf`.`user_id`
WHERE `sgf`.`sgf_status_id` = 2 AND `sgf`.`accepted` IS NULL AND `user`.`user_role_id` IN (2, 3);");
		$this->execute('UPDATE `sgf` SET `accepted` = `created` WHERE `sgf_status_id` = 2 AND `accepted` IS NULL;');
	}

	//Pairs each Accept Proposal of the admin activity log with the version it accepted.
	private function fillLoggedAcceptances(): void
	{
		$candidates = [];
		foreach ($this->fetchAll('SELECT `sgf`.`id`, `sgf`.`tsumego_id`, `sgf`.`user_id`, `user`.`display_name`
FROM `sgf`
JOIN `user` ON `user`.`id` = `sgf`.`user_id`
WHERE `sgf`.`sgf_status_id` = 2
ORDER BY `sgf`.`id` DESC') as $sgf)
			$candidates[$sgf['tsumego_id'] . "\n" . $sgf['display_name']][] = $sgf;

		$acceptances = $this->fetchAll('SELECT `user_id`, `tsumego_id`, `new_value`, `created`
FROM `admin_activity`
WHERE `type` = 29 AND `tsumego_id` IS NOT NULL AND `new_value` IS NOT NULL
ORDER BY `id` DESC');
		foreach ($acceptances as $acceptance)
		{
			$key = $acceptance['tsumego_id'] . "\n" . $acceptance['new_value'];
			foreach ($candidates[$key] ?? [] as $index => $sgf)
			{
				// an admin does not accept their own proposal, a version of theirs is their own change
				if ((int) $sgf['user_id'] === (int) $acceptance['user_id'])
					continue;
				$this->execute('UPDATE `sgf` SET `accepted_by_user_id` = ' . (int) $acceptance['user_id']
					. ', `accepted` = ' . $this->getAdapter()->getConnection()->quote($acceptance['created'])
					. ' WHERE `id` = ' . (int) $sgf['id']);
				unset($candidates[$key][$index]);
				break;
			}
		}
	}

	public function down(): void
	{
		$this->execute('ALTER TABLE `sgf` DROP FOREIGN KEY `sgf_accepted_by_user_id`;');
		// the new key took the place of the one the foreign key of sgf_status_id had
		$this->execute('ALTER TABLE `sgf` ADD KEY `sgf_sgf_status_id` (`sgf_status_id`);');
		$this->execute('ALTER TABLE `sgf` DROP KEY `sgf_status_id_accepted`, DROP KEY `sgf_accepted_by_user_id`, DROP COLUMN `accepted_by_user_id`, DROP COLUMN `accepted`;');
	}
}
