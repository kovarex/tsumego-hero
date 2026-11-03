<?php

use App\Utility\TsumegoFilters;
use App\Utility\Util;

//The collections and tags the filters of a player name.
class TsumegoFiltersTest extends AppTestCase
{
	public function testTheFilteredCollectionsAndTagsKeepTheirOrderAndLeaveOutWhatIsNotThere(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'filterer'],
			'tsumegos' => [
				['sets' => [['name' => 'Alpha', 'num' => 1]], 'tags' => [['name' => 'atari']]],
				['sets' => [['name' => 'Beta', 'num' => 1]], 'tags' => [['name' => 'snapback']]],
				['sets' => [['name' => 'Own', 'num' => 1, 'public' => 0, 'user_id' => 'self']]]]]);
		$alphaID = $context->tsumegos[0]['sets'][0]['id'];
		$betaID = $context->tsumegos[1]['sets'][0]['id'];
		$_COOKIE['filtered_sets'] = 'Beta@missing@Own@alpha';
		$_COOKIE['filtered_tags'] = 'snapback@nothing@atari';

		$filters = new TsumegoFilters('topics');

		// a name matches as the database compares titles, regardless of case
		$this->assertSame(['Beta', 'alpha'], $filters->sets);
		$this->assertSame([$betaID, $alphaID], $filters->setIDs);
		$this->assertSame(['snapback', 'atari'], $filters->tags);
		$tagIDs = array_column(Util::query("SELECT id, name FROM tag WHERE name IN ('snapback', 'atari')"), 'id', 'name');
		$this->assertSame([(int) $tagIDs['snapback'], (int) $tagIDs['atari']], $filters->tagIDs);
		$this->assertSame(['snapback', 'atari'], array_keys($filters->tagColors));
	}

	public function testNoFilterLooksNothingUp(): void
	{
		new ContextPreparator(['user' => ['name' => 'filterer']]);

		$filters = new TsumegoFilters('topics');

		$this->assertSame([], $filters->sets);
		$this->assertSame([], $filters->tags);
	}
}
