<?php

use App\Utility\Constants;

//The filter menus of the collections index (webroot/js/SetsIndexPage.js): the topic and tag tiles,
//the chips of the active filters and the menus themselves.
class SetsIndexFiltersTest extends IntegrationTestCase
{
	private function prepareTwoSets(): ContextPreparator
	{
		return new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'topics'],
			'tsumegos' => [
				['sets' => [['name' => 'Alpha', 'num' => 1]], 'tags' => [['name' => 'atari']]],
				['sets' => [['name' => 'Beta', 'num' => 1]], 'tags' => [['name' => 'snapback']]]]]);
	}

	private function topicTile(Browser $browser, string $name)
	{
		foreach ($browser->getElements('#dropdown-topics .dropdown__tile[id^="tile-topics"]') as $tile)
			if ($tile->getText() === $name)
				return $tile;
		$this->fail('No topic tile ' . $name);
	}

	private function chips(Browser $browser): array
	{
		return array_map(fn($chip) => $chip->getText(), $browser->getElements('.active-tiles-container .dropdown__tile:not(#unselect-active-tiles)'));
	}

	public function testPickingATopicShowsOnlyItsCardsAndAChip(): void
	{
		$context = $this->prepareTwoSets();
		$alphaID = $context->tsumegos[0]['sets'][0]['id'];
		$betaID = $context->tsumegos[1]['sets'][0]['id'];

		$browser = Browser::instance();
		$browser->get('sets');
		$browser->findById('topics-button')->click();
		$browser->waitUntilCssSelectorDisplayed('#dropdown-topics');
		$this->topicTile($browser, 'Alpha')->click();

		$browser->waitUntilCssSelectorNotDisplayed('.topic-box' . $betaID);
		$this->assertTrue($browser->find('.topic-box' . $alphaID)->isDisplayed());
		$this->assertSame(['Alpha'], $this->chips($browser));
		$this->assertSame('Alpha', $browser->driver->manage()->getCookieNamed('filtered_sets')->getValue());
	}

	public function testUnpickingATopicShowsEveryCardAgain(): void
	{
		$context = $this->prepareTwoSets();
		$betaID = $context->tsumegos[1]['sets'][0]['id'];

		$browser = Browser::instance();
		$browser->get('sets');
		$browser->findById('topics-button')->click();
		$browser->waitUntilCssSelectorDisplayed('#dropdown-topics');
		$this->topicTile($browser, 'Alpha')->click();
		$browser->waitUntilCssSelectorNotDisplayed('.topic-box' . $betaID);
		$this->topicTile($browser, 'Alpha')->click();

		$browser->waitUntilCssSelectorDisplayed('.topic-box' . $betaID);
		$this->assertSame([], $this->chips($browser));
		$this->assertSame('clear', $browser->driver->manage()->getCookieNamed('filtered_sets')->getValue());
	}

	public function testRemovingTheChipOfATopicUnpicksIt(): void
	{
		$context = $this->prepareTwoSets();
		$betaID = $context->tsumegos[1]['sets'][0]['id'];

		$browser = Browser::instance();
		$browser->get('sets');
		$browser->findById('topics-button')->click();
		$browser->waitUntilCssSelectorDisplayed('#dropdown-topics');
		$this->topicTile($browser, 'Alpha')->click();
		$browser->waitUntilCssSelectorNotDisplayed('.topic-box' . $betaID);
		$browser->findById('topics-button')->click();
		$browser->waitUntilCssSelectorNotDisplayed('#dropdown-topics');
		$browser->find('#active-tiles-element0')->click();

		$browser->waitUntilCssSelectorDisplayed('.topic-box' . $betaID);
		$this->assertSame([], $this->chips($browser));
		$this->assertSame('clear', $browser->driver->manage()->getCookieNamed('filtered_sets')->getValue());
	}

	public function testSelectAllTopicsPicksAndUnpicksEveryTopic(): void
	{
		$this->prepareTwoSets();

		$browser = Browser::instance();
		$browser->get('sets');
		$browser->findById('topics-button')->click();
		$browser->waitUntilCssSelectorDisplayed('#dropdown-topics');
		$selectAll = $browser->findById('tile-topics-select-all');
		$this->assertSame('Select all', $selectAll->getText());

		$selectAll->click();
		$this->assertSame('Unselect all', $selectAll->getText());
		$this->assertSame(['Alpha', 'Beta'], $this->chips($browser));
		$this->assertSame('Alpha@Beta', urldecode($browser->driver->manage()->getCookieNamed('filtered_sets')->getValue()));

		$selectAll->click();
		$this->assertSame('Select all', $selectAll->getText());
		$this->assertSame([], $this->chips($browser));
	}

	public function testATopicPickedOnAnotherPageIsAChipAndATileAlreadyPicked(): void
	{
		$this->prepareTwoSets();

		$browser = Browser::instance();
		$browser->get('sets');
		$browser->driver->manage()->addCookie(['name' => 'filtered_sets', 'value' => 'Beta']);
		$browser->get('sets');

		$this->assertSame(['Beta'], $this->chips($browser));
		$browser->findById('topics-button')->click();
		$browser->waitUntilCssSelectorDisplayed('#dropdown-topics');
		$this->assertNotSame(
			$this->topicTile($browser, 'Alpha')->getCSSValue('background-color'),
			$this->topicTile($browser, 'Beta')->getCSSValue('background-color'),
			'the picked tile is colored');
	}

	public function testPickingATagOfAnotherQuerySavesItWithoutAChip(): void
	{
		$this->prepareTwoSets();

		$browser = Browser::instance();
		$browser->get('sets');
		$browser->findById('tags-button')->click();
		$browser->waitUntilCssSelectorDisplayed('#dropdown-tags');
		$this->tagTile($browser, 'snapback')->click();

		// the chips are of the cards' own kind, a tag shows its chip once the page reloads with it
		$this->assertSame([], $this->chips($browser));
		$this->assertSame('snapback', $browser->driver->manage()->getCookieNamed('filtered_tags')->getValue());
	}

	public function testPickingAndRemovingATagOnTheTagsQuery(): void
	{
		new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'query' => 'tags'],
			'tsumegos' => [
				['sets' => [['name' => 'Alpha', 'num' => 1]], 'tags' => [['name' => 'atari']]],
				['sets' => [['name' => 'Beta', 'num' => 1]], 'tags' => [['name' => 'snapback']]]]]);

		$browser = Browser::instance();
		$browser->get('sets');
		$browser->findById('tags-button')->click();
		$browser->waitUntilCssSelectorDisplayed('#dropdown-tags');
		$this->tagTile($browser, 'snapback')->click();
		$this->assertSame(['snapback'], $this->chips($browser));
		$this->assertSame('snapback', $browser->driver->manage()->getCookieNamed('filtered_tags')->getValue());

		$browser->findById('tags-button')->click();
		$browser->waitUntilCssSelectorNotDisplayed('#dropdown-tags');
		$browser->find('.active-tiles-container .dropdown__tile--brown')->click();
		$this->assertSame([], $this->chips($browser));
		$this->assertSame('clear', $browser->driver->manage()->getCookieNamed('filtered_tags')->getValue());
	}

	private function tagTile(Browser $browser, string $name)
	{
		foreach ($browser->getElements('#dropdown-tags .dropdown__tile[id^="tile-tags"]') as $tile)
			if ($tile->getText() === $name)
				return $tile;
		$this->fail('No tag tile ' . $name);
	}

	public function testOpeningOneFilterMenuClosesTheOthers(): void
	{
		$this->prepareTwoSets();

		$browser = Browser::instance();
		$browser->get('sets');
		$browser->findById('topics-button')->click();
		$browser->waitUntilCssSelectorDisplayed('#dropdown-topics');
		$this->assertStringContainsString('rotateArrow', $browser->find('#set-button-icon-topics .set-filter-chevron')->getAttribute('class'));

		$browser->findById('tags-button')->click();
		$browser->waitUntilCssSelectorDisplayed('#dropdown-tags');
		$browser->waitUntilCssSelectorNotDisplayed('#dropdown-topics');
		$this->assertStringNotContainsString('rotateArrow', $browser->find('#set-button-icon-topics .set-filter-chevron')->getAttribute('class'));
		$this->assertStringContainsString('rotateArrow', $browser->find('#set-button-icon-tags .set-filter-chevron')->getAttribute('class'));

		$browser->findById('tags-button')->click();
		$browser->waitUntilCssSelectorNotDisplayed('#dropdown-tags');
		$this->assertStringNotContainsString('rotateArrow', $browser->find('#set-button-icon-tags .set-filter-chevron')->getAttribute('class'));

		$browser->findById('difficulty-button')->click();
		$browser->waitUntilCssSelectorDisplayed('#dropdown-difficulty');
		$browser->find('.whitebox2')->click();
		$browser->waitUntilCssSelectorNotDisplayed('#dropdown-difficulty');
		$this->assertStringNotContainsString('rotateArrow', $browser->find('#set-button-icon-difficulty .set-filter-chevron')->getAttribute('class'));
	}
}
