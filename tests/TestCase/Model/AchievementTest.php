<?php

use App\Utility\AchievementId;

class AchievementTest extends CakeTestCase
{
	/**
	 * Every case of the enum is a row of the achievement table, which is what the
	 * migrations create, so a case added without the migration that creates the
	 * achievement fails here.
	 *
	 * The table is not the production data and can hold an achievement the site does not
	 * use (id 100 is gone), so the other direction is not asserted.
	 */
	public function testEveryCaseIsAnAchievementOfTheTable(): void
	{
		$rows = ClassRegistry::init('Achievement')->find('list', ['fields' => ['id']]);

		foreach (AchievementId::cases() as $case)
			$this->assertArrayHasKey($case->value, $rows, 'No achievement row for ' . $case->name);
	}
}
