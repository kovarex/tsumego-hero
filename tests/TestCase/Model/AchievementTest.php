<?php

App::uses('Achievement', 'Model');

class AchievementTest extends CakeTestCase
{
	/**
	 * Test that Achievement::COUNT equals the actual number of defined achievement IDs.
	 */
	public function testCountMatchesDefinedAchievements(): void
	{
		$reflection = new ReflectionClass(Achievement::class);

		$achievementIds = [];
		foreach ($reflection->getConstants() as $name => $value)
		{
			if (!is_int($value) || $name === 'COUNT' || str_ends_with($name, '_COUNT'))
				continue;
			$achievementIds[$name] = $value;
		}

		$uniqueIds = array_unique($achievementIds);
		$this->assertSame(count($uniqueIds), Achievement::COUNT,
			'Achievement::COUNT should equal the number of unique achievement IDs');
	}

	/**
	 * Test that no two achievement constants share the same ID.
	 */
	public function testNoDuplicateAchievementIds(): void
	{
		$reflection = new ReflectionClass(Achievement::class);

		$idToName = [];
		foreach ($reflection->getConstants() as $name => $value)
		{
			if (!is_int($value) || $name === 'COUNT' || str_ends_with($name, '_COUNT'))
				continue;

			$this->assertArrayNotHasKey($value, $idToName,
				"Duplicate achievement ID $value: '$name' conflicts with '" . ($idToName[$value] ?? '') . "'");
			$idToName[$value] = $name;
		}
	}
}
