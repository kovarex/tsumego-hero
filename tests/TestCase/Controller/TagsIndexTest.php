<?php

use App\Model\Table\AdminActivityTable;
use App\Model\Table\AdminActivityTypeTable;

//The tag overview (/tags): every tag with its problem count, the problems of a tag, and deleting a tag.
class TagsIndexTest extends IntegrationTestCase
{
	public function testIndexListsTagsWithProblemCounts()
	{
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				['set_order' => 1, 'tags' => [['name' => 'atari']]],
				['set_order' => 2, 'tags' => [['name' => 'atari'], ['name' => 'ko', 'approved' => 0]]],
				['set_order' => 3, 'tags' => [['name' => 'atari', 'approved' => 0]]],
				['set_order' => 4, 'deleted' => '2026-10-01 00:00:00', 'tags' => [['name' => 'atari']]]],
			'tags' => [['name' => 'snapback']]]);

		$this->get('/tags');

		$this->assertMatchesRegularExpression('/>atari<.*?2 problems \+ 1 proposed/s', $this->view);
		$this->assertMatchesRegularExpression('/>ko<.*?0 problems \+ 1 proposed/s', $this->view);
		$this->assertMatchesRegularExpression('/>snapback<.*?0 problems/s', $this->view);
	}

	public function testIndexSortsByProblemCount()
	{
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				['set_order' => 1, 'tags' => [['name' => 'ko']]],
				['set_order' => 2, 'tags' => [['name' => 'ko']]],
				['set_order' => 3, 'tags' => [['name' => 'atari']]]],
			'tags' => [['name' => 'snapback']]]);

		$this->get('/tags');
		$this->assertMatchesRegularExpression('/>atari<.*>ko<.*>snapback</s', $this->view);

		$this->get('/tags?sort=problems');
		$this->assertMatchesRegularExpression('/>ko<.*>atari<.*>snapback</s', $this->view);
	}

	public function testProblemsOfTag()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				['set_order' => 2, 'tags' => [['name' => 'atari', 'approved' => 0]]],
				['set_order' => 1, 'tags' => [['name' => 'atari']]],
				['set_order' => 3, 'tags' => [['name' => 'ko']]]]]);

		$this->get('/tags/problems/' . $context->tsumegos[0]['tags'][0]->id);
		$problems = json_decode($this->view, true);

		$this->assertSame([
			['url' => '/' . $context->tsumegos[1]['set-connections'][0]['id'], 'name' => 'test set 1', 'approved' => true],
			['url' => '/' . $context->tsumegos[0]['set-connections'][0]['id'], 'name' => 'test set 2', 'approved' => false]], $problems);
	}

	public function testCountOpensTheProblemList()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [['set_order' => 7, 'tags' => [['name' => 'atari']]]]]);
		$tagId = $context->tsumegos[0]['tags'][0]->id;
		$browser = Browser::instance();
		$browser->get('tags');

		$problems = $browser->find('#tag-problems-' . $tagId);
		$this->assertFalse($problems->isDisplayed());
		$browser->clickCssSelect('.tag-overview__toggle[data-tag-id="' . $tagId . '"]');
		$link = $browser->find('#tag-problems-' . $tagId . ' li a');
		$this->assertSame('test set 7', $link->getText());
		$this->assertStringEndsWith('/' . $context->tsumegos[0]['set-connections'][0]['id'], $link->getAttribute('href'));

		$browser->clickCssSelect('.tag-overview__toggle[data-tag-id="' . $tagId . '"]');
		$this->assertFalse($problems->isDisplayed());
	}

	public function testDeleteFromTheOverview()
	{
		$context = new ContextPreparator([
			'user' => ['owner' => true],
			'tsumegos' => [['set_order' => 1, 'tags' => [['name' => 'atari'], ['name' => 'ko']]]]]);
		$atariId = $context->tsumegos[0]['tags'][0]->id;
		$browser = Browser::instance();
		$browser->get('tags');

		$koId = $context->tsumegos[0]['tags'][1]->id;
		$browser->clickIdAndAcceptAlert('tag-delete-' . $atariId);
		// The delete reloads the page, and atari is also missing while the new page is still blank.
		$browser->waitUntilJs('!document.getElementById("tag-' . $atariId . '") && !!document.getElementById("tag-' . $koId . '")');
		$this->assertCount(1, $browser->getElements('#tag-' . $koId), 'ko is still listed');
		$this->assertFalse($this->fetchTable('Tag')->exists(['id' => $atariId]));
	}

	public function testOnlyOwnersAreOfferedTheDelete()
	{
		new ContextPreparator(['user' => ['admin' => true], 'tags' => [['name' => 'atari']]]);
		$this->get('/tags');
		$this->assertStringContainsString('>atari<', $this->view);
		$this->assertStringNotContainsString('tag-delete-', $this->view);
	}

	public function testIndexRequiresAdmin()
	{
		new ContextPreparator(['user' => ['admin' => false]]);
		$this->get('/tags');

		$this->assertResponseCode(403);
	}

	public function testProblemsRequireAdmin()
	{
		$context = new ContextPreparator(['user' => ['admin' => false], 'tags' => [['name' => 'atari']]]);
		$this->get('/tags/problems/' . $context->tags[0]->id);

		$this->assertResponseCode(403);
	}

	public function testDeleteRemovesTagFromProblemsAndLogsWhatItWas()
	{
		$context = new ContextPreparator([
			'user' => ['owner' => true],
			'tsumegos' => [
				['set_order' => 1, 'tags' => [['name' => 'atari'], ['name' => 'ko']]],
				['set_order' => 2, 'tags' => [['name' => 'atari', 'approved' => 0]]]]]);
		$atari = $context->tsumegos[0]['tags'][0];

		$this->post('/tags/delete/' . $atari->id);

		$this->assertStringEndsWith('/tags', $this->headerValue('Location'));
		$this->assertFalse($this->fetchTable('Tag')->exists(['id' => $atari->id]));
		$this->assertSame(0, $this->fetchTable('TagConnection')->find()->where(['tag_id' => $atari->id])->count());
		$this->assertSame(1, $this->fetchTable('TagConnection')->find()->count(), 'ko stays on its problem');

		$activity = $this->fetchTable('AdminActivity')->find()->where(['type' => AdminActivityTypeTable::DELETE_TAG])->firstOrFail();
		$deleted = json_decode($activity->old_value, true);
		$this->assertSame('atari', $deleted['tag']['name']);
		$this->assertSame(
			[$context->tsumegos[0]['id'], $context->tsumegos[1]['id']],
			array_map(fn($connection) => (int) $connection['tsumego_id'], $deleted['connections']));
		$this->assertSame('Deleted tag atari (2 problems)', AdminActivityTable::renderChange($activity->toArray()));
	}
}
