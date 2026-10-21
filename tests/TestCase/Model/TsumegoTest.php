<?php

class TsumegoTest extends CakeTestCase
{
	public function testCountPublicProblems(): void
	{
		// a problem that only belongs to a private set is not counted
		$context = new ContextPreparator([
			'tsumegos' => [
				['sets' => [['name' => 'public set', 'num' => '1']]],
				['sets' => [['name' => 'sandbox set', 'public' => 0, 'num' => '1']]],
			],
		]);

		$this->assertSame(['total' => 1, 'solved' => 0], Tsumego::publicProblemProgress($context->user['id']));
	}
}
