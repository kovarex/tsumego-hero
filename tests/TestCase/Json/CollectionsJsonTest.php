<?php

use App\Utility\Constants;
use App\Utility\Util;

//The JSON of the tags of a problem, the player's collections, the SGF versions and the admin pages.
class CollectionsJsonTest extends JsonGoldenTestCase
{
	public function testTagProblems(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				['set_order' => 2, 'tags' => [['name' => 'atari', 'approved' => 0]]],
				['set_order' => 1, 'tags' => [['name' => 'atari']]],
				['set_order' => 3, 'tags' => [['name' => 'ko']]]]]);
		$this->get('/tags/problems/' . $context->tsumegos[0]['tags'][0]->id);
		$this->assertJsonGolden('tag problems');
	}

	public function testTagAdd(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE],
			'tsumego' => ['set_order' => 1, 'status' => 'S'],
			'tags' => [['name' => 'snapback']],
		]);
		$this->post('/tagConnection/add/' . $context->tsumegos[0]['id'] . '/snapback');
		$this->assertJsonGolden('tag add');
	}

	public function testTagRemove(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'tsumego' => ['set_order' => 1, 'status' => 'S', 'tags' => [['name' => 'snapback', 'approved' => 0]]],
		]);
		$this->post('/tagConnection/remove/' . $context->tsumegos[0]['id'] . '/snapback');
		$this->assertJsonGolden('tag remove');
	}

	//A collection of the player with one problem in it, and a problem that is not
	private function prepareOwnCollection(): array
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [
				['sets' => [['name' => 'My Set', 'num' => 1, 'public' => 0]], 'sgf' => '(;GM[1]FF[4]SZ[19]AB[cc])'],
				['sets' => [['name' => 'Public Set', 'num' => 1]], 'sgf' => '(;GM[1]FF[4]SZ[19]AW[dd])'],
			],
		]);
		$set = $this->fetchTable('Set')->find()->where(['title' => 'My Set'])->firstOrFail();
		$set->user_id = $context->user['id'];
		$this->fetchTable('Set')->saveOrFail($set);
		$this->login('alice');
		//The page asks by fetch(), the JSON answers only that
		$this->configRequest(['headers' => ['X-Requested-With' => 'XMLHttpRequest']]);
		return [$context, $set->id];
	}

	public function testSetsAddTsumego(): void
	{
		[$context, $setId] = $this->prepareOwnCollection();
		$this->post('/sets/addTsumego/' . $setId, ['tsumego_id' => $context->tsumegos[1]['id']]);
		$this->assertJsonGolden('sets add tsumego');
	}

	public function testSetsAddTsumegoToFavorites(): void
	{
		[$context] = $this->prepareOwnCollection();
		$this->post('/sets/addTsumego/favorites', ['tsumego_id' => $context->tsumegos[1]['id']]);
		$this->assertJsonGolden('sets add tsumego to favorites');
	}

	public function testSetsRemoveTsumego(): void
	{
		[$context, $setId] = $this->prepareOwnCollection();
		$this->post('/sets/removeTsumego/' . $setId, ['tsumego_id' => $context->tsumegos[0]['id']]);
		$this->assertJsonGolden('sets remove tsumego');
	}

	public function testSgfInfo(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'proposer']],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])']]);
		$sgfID = $context->tsumegos[0]['sgfs'][0]['id'];
		Util::execute('UPDATE sgf SET user_id = ?, description = ? WHERE id = ?', [$context->otherUsers[0]['id'], "Added D16\nbecause", $sgfID]);
		$this->get('/sgf/info/' . $sgfID);
		$this->assertJsonGolden('sgf info');
	}

	public function testSchedulePreview(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 5, 'public' => 0]], 'sgf' => '(;GM[1]FF[4]SZ[19]AB[cc]AW[dd])'],
				['sets' => [['name' => 'sandbox set', 'num' => 6, 'public' => 0]], 'sgf' => '(;GM[1]FF[4]SZ[9]AB[ee])'],
				['sets' => [['name' => 'target set', 'num' => 5]]],
			],
		]);
		$this->login('admin');
		$this->get('/schedule/preview?' . http_build_query([
			'set_id_from' => $context->tsumegos[0]['set-connections'][0]['set_id'],
			'set_id_to' => $context->tsumegos[2]['set-connections'][0]['set_id'],
			'count' => 2, 'num' => 0, 'per_day' => 1, 'start_date' => '2030-01-01']));
		$this->assertJsonGolden('schedule preview');
	}

	public function testAdminReviewOfAnSgfChange(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'other-users' => [['name' => 'author', 'admin' => true]],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])']]);
		$sgfID = $context->tsumegos[0]['sgfs'][0]['id'];
		Util::execute('UPDATE sgf SET user_id = ?, accepted_by_user_id = ? WHERE id = ?', [$context->otherUsers[0]['id'], $context->otherUsers[0]['id'], $sgfID]);
		$this->post('/admin/sgf-changes/review/' . $sgfID, ['reviewed' => 1]);
		$this->assertJsonGolden('admin review of an sgf change');
		$this->post('/admin/sgf-changes/review/' . $sgfID, ['reviewed' => 0]);
		$this->assertJsonGolden('admin review taken back');
	}
}
