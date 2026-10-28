<?php

use App\Utility\Util;
use App\Model\Table\TsumegoTable;
use Cake\ORM\TableRegistry;

class TsumegoTest extends CakeTestCase
{
	//The problems the site holds are the problems that exist: the collection a problem sits in says
	//nothing about it, so a collection nobody published yet still holds its problems.
	public function testProblemCount(): void
	{
		new ContextPreparator([
			'tsumegos' => [
				['sets' => [['name' => 'public set', 'num' => '1']]],
				['sets' => [['name' => 'unpublished set', 'public' => 0, 'num' => '1']]],
				['sets' => [['name' => 'player set', 'public' => 1, 'user_id' => 'self', 'num' => '1']]],
			],
		]);

		$this->assertSame(3, TsumegoTable::problemCount());
	}

	//A result read the problem when its request started. An admin's edit and another result that
	//came meanwhile stay: only the rating is written, moved from the value stored now.
	public function testAddToRatingKeepsWhatChangedMeanwhile(): void
	{
		$context = new ContextPreparator(['tsumego' => ['rating' => 1000, 'description' => 'old description']]);
		$tsumegoID = $context->tsumegos[0]['id'];
		Util::execute('UPDATE tsumego SET description = ?, rating = 1050 WHERE id = ?', ['new description', $tsumegoID]);

		TsumegoTable::addToRating($tsumegoID, -10.5);

		$tsumego = TableRegistry::getTableLocator()->get('Tsumego')->get($tsumegoID);
		$this->assertSame('new description', $tsumego->description);
		$this->assertSame(1039.5, $tsumego->rating);
	}

	public function testAddToRatingStaysWithinTheLimits(): void
	{
		$context = new ContextPreparator([
			'tsumegos' => [
				['rating' => 1000, 'minimum_rating' => 995],
				['rating' => 1000, 'maximum_rating' => 1005]]]);
		[$lowest, $highest] = array_column($context->tsumegos, 'id');

		TsumegoTable::addToRating($lowest, -10);
		TsumegoTable::addToRating($highest, 10);

		$this->assertSame(995.0, (float) TableRegistry::getTableLocator()->get('Tsumego')->get($lowest)->rating);
		$this->assertSame(1005.0, (float) TableRegistry::getTableLocator()->get('Tsumego')->get($highest)->rating);
	}
}
