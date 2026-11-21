<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//The bests of a collection that problems are still scheduled into were kept from runs through parts that were not
//complete yet. From now on no best is kept while a collection is being filled (App\Model\Table\ScheduleTable::isBeingFilled()),
//so the best accuracies and times kept of those collections are deleted, to be earned again once the last scheduled
//problem is published. The achievements they unlocked stay.
final class ForgetBestsOfSetsBeingFilled extends AbstractMigration
{
	public function up(): void
	{
		$sets = $this->fetchAll('SELECT DISTINCT set_id FROM schedule WHERE published = 0 AND set_id IS NOT NULL');
		if (!$sets)
			return;
		$setIDs = implode(',', array_map(fn($row) => (int) $row['set_id'], $sets));
		$deleted = $this->execute("DELETE FROM achievement_condition WHERE achievement_condition_category_id IN (2, 3) AND set_id IN ($setIDs)");
		$this->getOutput()?->writeln("Deleted $deleted best times and accuracies of the collections $setIDs, which problems are still scheduled into");
	}

	public function down(): void
	{
		// the deleted bests stay deleted
	}
}
