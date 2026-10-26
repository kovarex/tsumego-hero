<?php

class TsumegoTest extends CakeTestCase
{
	/**
	 * The problems the site holds are the problems that exist: the collection a problem sits in says
	 * nothing about it, so a collection nobody published yet still holds its problems.
	 */
	public function testProblemCount(): void
	{
		new ContextPreparator([
			'tsumegos' => [
				['sets' => [['name' => 'public set', 'num' => '1']]],
				['sets' => [['name' => 'unpublished set', 'public' => 0, 'num' => '1']]],
				['sets' => [['name' => 'player set', 'public' => 1, 'user_id' => 'self', 'num' => '1']]],
			],
		]);

		$this->assertSame(3, Tsumego::problemCount());
	}
}
