<?php

/**
 * The tag overview (/tags): every tag with its problem count, the problems of a tag,
 * and deleting a tag.
 */
class TagsIndexTest extends TestCaseWithAuth
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

		$this->testAction('/tags', ['return' => 'view']);

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

		$this->testAction('/tags', ['return' => 'view']);
		$this->assertMatchesRegularExpression('/>atari<.*>ko<.*>snapback</s', $this->view);

		$this->testAction('/tags?sort=problems', ['return' => 'view']);
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

		$problems = json_decode($this->testAction('/tags/problems/' . $context->tsumegos[0]['tags'][0]['id'], ['return' => 'contents']), true);

		$this->assertSame([
			['url' => '/' . $context->tsumegos[1]['set-connections'][0]['id'], 'name' => 'test set 1', 'approved' => true],
			['url' => '/' . $context->tsumegos[0]['set-connections'][0]['id'], 'name' => 'test set 2', 'approved' => false]], $problems);
	}

	public function testCountOpensTheProblemList()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [['set_order' => 7, 'tags' => [['name' => 'atari']]]]]);
		$tagId = $context->tsumegos[0]['tags'][0]['id'];
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
			'user' => ['admin' => true, 'id' => 72],
			'tsumegos' => [['set_order' => 1, 'tags' => [['name' => 'atari'], ['name' => 'ko']]]]]);
		$atariId = $context->tsumegos[0]['tags'][0]['id'];
		$browser = Browser::instance();
		$browser->get('tags');

		$browser->clickIdAndAcceptAlert('tag-delete-' . $atariId);
		$browser->waitUntilJs('!document.getElementById("tag-' . $atariId . '")');
		$this->assertCount(1, $browser->getElements('#tag-' . $context->tsumegos[0]['tags'][1]['id']), 'ko is still listed');
		$this->assertEmpty(ClassRegistry::init('Tag')->findById($atariId));
	}

	public function testIndexRequiresAdmin()
	{
		new ContextPreparator(['user' => ['admin' => false]]);
		$this->expectException(ForbiddenException::class);
		$this->testAction('/tags');
	}

	public function testProblemsRequireAdmin()
	{
		$context = new ContextPreparator(['user' => ['admin' => false], 'tags' => [['name' => 'atari']]]);
		$this->expectException(ForbiddenException::class);
		$this->testAction('/tags/problems/' . $context->tags[0]['id']);
	}

	public function testDeleteRemovesTagFromProblemsAndLogsWhatItWas()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true, 'id' => 72],
			'tsumegos' => [
				['set_order' => 1, 'tags' => [['name' => 'atari'], ['name' => 'ko']]],
				['set_order' => 2, 'tags' => [['name' => 'atari', 'approved' => 0]]]]]);
		$atari = $context->tsumegos[0]['tags'][0];

		$this->testAction('/tags/delete/' . $atari['id'], ['method' => 'post']);

		$this->assertStringEndsWith('/tags', $this->headers['Location']);
		$this->assertEmpty(ClassRegistry::init('Tag')->findById($atari['id']));
		$this->assertSame(0, ClassRegistry::init('TagConnection')->find('count', ['conditions' => ['tag_id' => $atari['id']]]));
		$this->assertSame(1, ClassRegistry::init('TagConnection')->find('count'), 'ko stays on its problem');

		$activity = ClassRegistry::init('AdminActivity')->find('first', ['conditions' => ['type' => AdminActivityType::DELETE_TAG]])['AdminActivity'];
		$deleted = json_decode($activity['old_value'], true);
		$this->assertSame('atari', $deleted['tag']['name']);
		$this->assertSame(
			[$context->tsumegos[0]['id'], $context->tsumegos[1]['id']],
			array_map(fn($connection) => (int) $connection['tsumego_id'], $deleted['connections']));
		$this->assertSame('Deleted tag atari (2 problems)', AdminActivity::renderChange($activity));
	}
}
