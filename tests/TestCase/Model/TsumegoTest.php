<?php

App::uses('Tsumego', 'Model');

class TsumegoTest extends CakeTestCase
{
	public function testCountPublicProblems(): void
	{
		// a problem that only belongs to a private set is not counted
		new ContextPreparator([
			'tsumegos' => [
				['sets' => [['name' => 'public set', 'num' => '1']]],
				['sets' => [['name' => 'sandbox set', 'public' => 0, 'num' => '1']]],
			],
		]);

		$count = ClassRegistry::init('Tsumego')->countPublicProblems();
		$this->assertSame(1, $count);
	}
}
