<?php

use App\Utility\Util;

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

	/**
	 * A result read the problem when its request started. An admin's edit and another result that
	 * came meanwhile stay: only the rating is written, moved from the value stored now.
	 */
	public function testAddToRatingKeepsWhatChangedMeanwhile(): void
	{
		$context = new ContextPreparator(['tsumego' => ['rating' => 1000, 'description' => 'old description']]);
		$tsumegoID = $context->tsumegos[0]['id'];
		Util::execute('UPDATE tsumego SET description = ?, rating = 1050 WHERE id = ?', ['new description', $tsumegoID]);

		Tsumego::addToRating($tsumegoID, -10.5);

		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID)['Tsumego'];
		$this->assertSame('new description', $tsumego['description']);
		$this->assertSame(1039.5, (float) $tsumego['rating']);
	}

	public function testAddToRatingStaysWithinTheLimits(): void
	{
		$context = new ContextPreparator([
			'tsumegos' => [
				['rating' => 1000, 'minimum_rating' => 995],
				['rating' => 1000, 'maximum_rating' => 1005]]]);
		[$lowest, $highest] = array_column($context->tsumegos, 'id');

		Tsumego::addToRating($lowest, -10);
		Tsumego::addToRating($highest, 10);

		$this->assertSame(995.0, (float) ClassRegistry::init('Tsumego')->findById($lowest)['Tsumego']['rating']);
		$this->assertSame(1005.0, (float) ClassRegistry::init('Tsumego')->findById($highest)['Tsumego']['rating']);
	}
}
