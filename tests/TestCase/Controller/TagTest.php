<?php

class TagTest extends ControllerTestCase
{
	public function testAddTagConnection()
	{
		foreach ([false, true] as $isAdmin)
		{
			$context = new ContextPreparator([
				'user' => ['admin' => $isAdmin],
				'other-tsumegos' => [['sets' => [['name' => 'set-1', 'num' => 1]]]],
				'tags' => [['name' => 'snapback']]]);
			$browser = Browser::instance();
			$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
			$browser->clickId('open-add-tag-menu');
			$browser->clickId('open-more-tags');
			$browser->clickId('tag-snapback');
			$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
			$tagConnection = ClassRegistry::init('TagConnection')->find('first', []);
			$this->assertNotNull($tagConnection);
			$this->assertSame($tagConnection['TagConnection']['approved'], $isAdmin ? 1 : 0);
			$this->assertCount(1, $browser->getCssSelect(".tag-list #tag-snapback")); // tag was added to the list
		}
	}

	public function testAddTagDoesntOfferAlreadyExistingTag()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [[
				'sets' => [['name' => 'set-1', 'num' => 1]],
				'tags' => [['name' => 'atari']]]],
			'tags' => [['name' => 'snapback']]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('open-add-tag-menu');
		$browser->clickId('open-more-tags');
		$addTagLinks = $browser->getCssSelect('.add-tag-list .add-tag-list-anchor');
		$this->assertSame(2, count($addTagLinks));
		$this->assertSame("snapback", $addTagLinks[0]->getText());
		$this->assertSame("[Create new tag]", $addTagLinks[1]->getText());
	}

	public function testShowMyUnapprovedTagsInTagListAndNotInTagsToAdd()
	{
		foreach ([false, true] as $popular)
		{
			$context = new ContextPreparator([
				'other-tsumegos' => [[
					'sets' => [['name' => 'set-1', 'num' => 1]],
					'tags' => [['name' => 'atari', 'approved' => 0, 'popular' => $popular]]]],
				'tags' => [['name' => 'snapback']]]);
			$browser = Browser::instance();
			$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
			$this->assertCount(1, $browser->getCssSelect(".tag-list #tag-atari")); // tag is in the list
			$browser->clickId('open-add-tag-menu');
			if (!$popular)
				$browser->clickId('open-more-tags');

			// the atari is not in the add tags offer
			$sourceList = $popular ? 'add-tag-list-popular' : 'add-tag-list';
			$addTagLinks = $browser->getCssSelect('.' . $sourceList . ' .add-tag-list-anchor');
			if ($popular)
			{
				$this->assertSame(1, count($addTagLinks));
				$this->assertSame("[more]", $addTagLinks[0]->getText());
			}
			else
			{
				$this->assertSame(2, count($addTagLinks));
				$this->assertSame("snapback", $addTagLinks[0]->getText());
				$this->assertSame("[Create new tag]", $addTagLinks[1]->getText());
			}
		}
	}

	public function testDontShowOthersPopularApprovedTagsInAddTags()
	{
		foreach ([false, true] as $popular)
		{
			$context = new ContextPreparator([
				'user' => ['mode' => Constants::$LEVEL_MODE],
				'other-users' => [['name' => 'Ivan detkov']],
				'other-tsumegos' => [[
					'sets' => [['name' => 'set-1', 'num' => 1]],
					'tags' => [['name' => 'atari', 'approved' => 1, 'user' => 'Ivan detkov', 'popular' => $popular]]]],
				'tags' => [['name' => 'snapback', 'popular' => $popular]]]);
			$browser = Browser::instance();
			$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
			$this->assertCount(1, $browser->getCssSelect(".tag-list #tag-atari")); // tag is in the list
			$browser->clickId('open-add-tag-menu');
			if (!$popular)
				$browser->clickId('open-more-tags');

			// the atari is not in the add tags offer
			$sourceList = $popular ? 'add-tag-list-popular' : 'add-tag-list';
			$addTagLinks = $browser->getCssSelect('.' . $sourceList . ' .add-tag-list-anchor');
			if ($popular)
			{
				$this->assertSame(2, count($addTagLinks));
				$this->assertSame("snapback", $addTagLinks[0]->getText());
				$this->assertSame("[more]", $addTagLinks[1]->getText());

				// cick to add the snapback
				$addTagLinks[0]->click();
				// Wait for AJAX to complete and tag to be removed from add list - Chrome is fast, use WebDriverWait
				$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
				$wait->until(function () use ($browser, $popular) {
					$sourceList = $popular ? 'add-tag-list-popular' : 'add-tag-list';
					$links = $browser->getCssSelect('.' . $sourceList . ' .add-tag-list-anchor');
					// Wait until snapback is removed (only 1 link remains)
					return count($links) === 1;
				});
				$addTagLinks = $browser->getCssSelect('.add-tag-list-popular .add-tag-list-anchor');

				// tag is not in the list
				$this->assertSame(1, count($addTagLinks));
				$this->assertSame("[more]", $addTagLinks[0]->getText());
			}
			else
			{
				$this->assertSame(2, count($addTagLinks));
				$this->assertSame("snapback", $addTagLinks[0]->getText());
				$this->assertSame("[Create new tag]", $addTagLinks[1]->getText());

				//add the snapback and test, that it will be no longer offered as tag to add
				$addTagLinks[0]->click();
				// Wait for AJAX to complete and tag to be removed from add list - Chrome is fast, use WebDriverWait
				$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
				$wait->until(function () use ($browser, $sourceList) {
					$links = $browser->getCssSelect('.' . $sourceList . ' .add-tag-list-anchor');
					// Wait until snapback is removed (only 1 link remains)
					return count($links) === 1;
				});
				$addTagLinks = $browser->getCssSelect('.' . $sourceList . ' .add-tag-list-anchor');
				$this->assertSame(1, count($addTagLinks));
				$this->assertSame("[Create new tag]", $addTagLinks[0]->getText());
			}
		}
	}

	public function testShowOthersUnapprovedTagsInAddTagsButNotClickable()
	{
		foreach ([false, true] as $popular)
		{
			$context = new ContextPreparator([
				'user' => ['mode' => Constants::$LEVEL_MODE],
				'other-users' => [['name' => 'Ivan detkov']],
				'other-tsumegos' => [[
					'sets' => [['name' => 'set-1', 'num' => 1]],
					'tags' => [['name' => 'atari', 'approved' => 0, 'user' => 'Ivan detkov', 'popular' => $popular]]]],
				'tags' => [['name' => 'snapback', 'popular' => $popular]]]);
			$browser = Browser::instance();
			$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
			$this->assertCount(0, $browser->getCssSelect(".tag-list #tag-atari")); // tag is not in the list
			$browser->clickId('open-add-tag-menu');
			if (!$popular)
				$browser->clickId('open-more-tags');

			$sourceList = $popular ? 'add-tag-list-popular' : 'add-tag-list';
			$addTagLinks = $browser->getCssSelect('.' . $sourceList . ' .add-tag-list-anchor');

			$this->assertSame(3, count($addTagLinks));
			$this->assertSame("atari", $addTagLinks[0]->getText());
			$this->assertSame("snapback", $addTagLinks[1]->getText());
			if ($popular)
				$this->assertSame("[more]", $addTagLinks[2]->getText());
			else
				$this->assertSame("[Create new tag]", $addTagLinks[2]->getText());

			$addTagSpans = $browser->getCssSelect('.' . $sourceList . ' span[title="Already proposed by someone"]');
			$this->assertSame(1, count($addTagSpans));
			$this->assertSame($addTagSpans[0]->getText(), "atari");
		}
	}

	public function testShowTagWhichIsHintAfterProblemGetsSolved()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [[
				'sets' => [['name' => 'set-1', 'num' => 1]],
				'tags' => [
					['name' => 'no-hint-tag'],
					['name' => 'hint-tag', 'is_hint' => 1]]]]]);

		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$this->assertCount(1, $browser->getCssSelect(".tag-list #tag-no-hint-tag"));
		$this->assertCount(0, $browser->getCssSelect(".tag-list #tag-hint-tag"));
		$this->assertTextContains("(1 hidden)", $browser->getCssSelect(".tag-list")[0]->getText());
		$browser->playWithResult('S'); // solve the problem
		$this->assertCount(1, $browser->getCssSelect(".tag-list #tag-no-hint-tag"));
		$this->assertCount(1, $browser->getCssSelect(".tag-list #tag-hint-tag"));
		$this->assertTextNotContains("hidden", $browser->getCssSelect(".tag-list")[0]->getText());
	}

	public function testRemoveMyUnapprovedTag()
	{
		foreach ([false, true] as $popular)
		{
			$context = new ContextPreparator([
				'other-tsumegos' => [[
					'sets' => [['name' => 'set-1', 'num' => 1]],
					'tags' => [['name' => 'atari', 'approved' => 0, 'popular' => $popular]]]]]);
			$browser = Browser::instance();
			$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
			$this->assertCount(1, $browser->getCssSelect(".tag-list #tag-atari"));
			$this->assertNotEmpty(ClassRegistry::init('TagConnection')->find('first', ['conditions' => ['tsumego_id' => $context->otherTsumegos[0]['id']]]));

			$browser->clickId("remove-tag-atari");
			$browser->waitUntilCssSelectorDoesntExist(".tag-list #tag-atari");
			$this->assertCount(0, $browser->getCssSelect(".tag-list #tag-atari"));
			$this->assertEmpty(ClassRegistry::init('TagConnection')->find('first', ['conditions' => ['tsumego_id' => $context->otherTsumegos[0]['id']]]));
		}
	}

	public function testTryToAddTagWhenNotLoggedIn()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [['sets' => [['name' => 'set-1', 'num' => 1]]]],
			'tags' => [['name' => 'snapback', 'popular' => true]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('open-add-tag-menu');
		$browser->deleteAuthCookies(); // we suddenly get logged off
		$alertText = $browser->clickIdAndExpectAlert('tag-snapback');
		$this->assertTextContains("Not logged in", $alertText);
	}

	public function testTryToAddNonExistingTag()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [['sets' => [['name' => 'set-1', 'num' => 1]]]],
			'tags' => [['name' => 'snapback', 'popular' => true]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('open-add-tag-menu');
		ClassRegistry::init('Tag')->delete($context->tags[0]['id']);
		$alertText = $browser->clickIdAndExpectAlert('tag-snapback');
		$this->assertTextContains('Tag "snapback" doesn\'t exist.', $alertText);
	}

	public function testTryToAddTagToNonExistingTsumego()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [['sets' => [['name' => 'set-1', 'num' => 1]]]],
			'tags' => [['name' => 'snapback', 'popular' => true]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('open-add-tag-menu');
		ClassRegistry::init('Tsumego')->delete($context->otherTsumegos[0]['id']);
		$alertText = $browser->clickIdAndExpectAlert('tag-snapback');
		$this->assertTextContains('Tsumego with id="' . $context->otherTsumegos[0]['id'] . '" wasn\'t found.', $alertText);
	}

	public function testTryToAddDupliciteTag()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [['sets' => [['name' => 'set-1', 'num' => 1]]]],
			'tags' => [['name' => 'snapback', 'popular' => true]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('open-add-tag-menu');

		$tagConnection = [];
		$tagConnection['tag_id'] = $context->tags[0]['id'];
		$tagConnection['tsumego_id'] = $context->otherTsumegos[0]['id'];
		$tagConnection['user_id'] = $context->user['id'];
		$tagConnection['approved'] = 1;
		ClassRegistry::init('TagConnection')->create();
		ClassRegistry::init('TagConnection')->save($tagConnection);
		usleep(100000); // 100ms - ensure DB commit before AJAX request
		$alertText = $browser->clickIdAndExpectAlert('tag-snapback');
		$this->assertTextContains('The tsumego already has tag snapback.', $alertText);
	}

	public function testTryToRemoveTagWhenNotLoggedIn()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [[
				'sets' => [['name' => 'set-1', 'num' => 1]],
				'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		$browser->deleteAuthCookies(); // we suddenly get logged off
		$alertText = $browser->clickIdAndExpectAlert('remove-tag-snapback');
		$this->assertTextContains("Not logged in", $alertText);
	}

	public function testTryToRemoveNonExistingTag()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [[
				'sets' => [['name' => 'set-1', 'num' => 1]],
				'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		ClassRegistry::init('Tag')->delete($context->tags[0]['id']);
		$alertText = $browser->clickIdAndExpectAlert('remove-tag-snapback');
		$this->assertTextContains('Tag "snapback" doesn\'t exist.', $alertText);
	}

	public function testTryToRemoveFromNonExistingTsumego()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [[
				'sets' => [['name' => 'set-1', 'num' => 1]],
				'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		ClassRegistry::init('Tsumego')->delete($context->otherTsumegos[0]['id']);
		$alertText = $browser->clickIdAndExpectAlert('remove-tag-snapback');
		$this->assertTextContains('Tsumego with id="' . $context->otherTsumegos[0]['id'] . '" wasn\'t found.', $alertText);
	}

	public function testTryToRemoveTagConnectionWhichDoesntExist()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [[
				'sets' => [['name' => 'set-1', 'num' => 1]],
				'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);
		ClassRegistry::init('TagConnection')->deleteAll(['1=1']);
		// Longer timeout for Chrome speed - AJAX call is faster
		$alertText = $browser->clickIdAndExpectAlert('remove-tag-snapback', 10);
		$this->assertTextContains('Tag to remove isn\'t assigned to this tsumego.', $alertText);
	}

	public function testTryToRemoveApprovedTagAsNonAdmin()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [[
				'sets' => [['name' => 'set-1', 'num' => 1]],
				'tags' => [['name' => 'snapback', 'user' => 'kovarex', 'approved' => 0]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);

		// the tag gets approved in the meantime
		$tagConnection = ClassRegistry::init('TagConnection')->findById($context->otherTsumegos[0]['tag-connections'][0]['id']);
		$tagConnection['TagConnection']['approved'] = true;
		ClassRegistry::init('TagConnection')->save($tagConnection);

		$alertText = $browser->clickIdAndExpectAlert('remove-tag-snapback');
		$this->assertTextContains('Only admins can remove approved tags.', $alertText);
	}

	public function testTryToRemoveTagProposedBySomeoneElse()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex'],
			'other-users' => [['name' => 'Ivan Detkov']],
			'other-tsumegos' => [[
				'sets' => [['name' => 'set-1', 'num' => 1]],
				'tags' => [['name' => 'snapback', 'approved' => 0]]]]]);
		$browser = Browser::instance();
		$browser->get('/' . $context->otherTsumegos[0]['set-connections'][0]['id']);

		// the tag is changed to be created by Ivan Detkov
		$tagConnection = ClassRegistry::init('TagConnection')->findById($context->otherTsumegos[0]['tag-connections'][0]['id']);
		$tagConnection['TagConnection']['user_id'] = $context->otherUsers[0]['id'];
		ClassRegistry::init('TagConnection')->save($tagConnection);

		$alertText = $browser->clickIdAndExpectAlert('remove-tag-snapback');
		$this->assertTextContains('You can\'t remove tag proposed by someone else.', $alertText);
	}
}
