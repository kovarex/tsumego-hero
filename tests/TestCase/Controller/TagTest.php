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
		$this->assertSame($addTagLinks[0]->getText(), "snapback");
		$this->assertSame($addTagLinks[1]->getText(), "[Create new tag]");
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
				$this->assertSame($addTagLinks[0]->getText(), "[more]");
			}
			else
			{
				$this->assertSame(2, count($addTagLinks));
				$this->assertSame($addTagLinks[0]->getText(), "snapback");
				$this->assertSame($addTagLinks[1]->getText(), "[Create new tag]");
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
				$this->assertSame($addTagLinks[0]->getText(), "snapback");
				$this->assertSame($addTagLinks[1]->getText(), "[more]");

				// cick to add the snapback
				$addTagLinks[0]->click();
				$addTagLinks = $browser->getCssSelect('.add-tag-list-popular .add-tag-list-anchor');

				// tag is not in the list
				$this->assertSame(1, count($addTagLinks));
				$this->assertSame($addTagLinks[0]->getText(), "[more]");
			}
			else
			{
				$this->assertSame(2, count($addTagLinks));
				$this->assertSame($addTagLinks[0]->getText(), "snapback");
				$this->assertSame($addTagLinks[1]->getText(), "[Create new tag]");

				//add the snapback and test, that it will be no longer offered as tag to add
				$addTagLinks[0]->click();
				$addTagLinks = $browser->getCssSelect('.' . $sourceList . ' .add-tag-list-anchor');
				$this->assertSame(1, count($addTagLinks));
				$this->assertSame($addTagLinks[0]->getText(), "[Create new tag]");
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
			$this->assertSame($addTagLinks[0]->getText(), "atari");
			$this->assertSame($addTagLinks[1]->getText(), "snapback");
			if ($popular)
				$this->assertSame($addTagLinks[2]->getText(), "[more]");
			else
				$this->assertSame($addTagLinks[2]->getText(), "[Create new tag]");

			$this->assertSame($addTagLinks[0]->getTagName(), 'span'); // added by someone else, not addable
			$this->assertSame($addTagLinks[1]->getTagName(), 'a');
		}
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
		$browser->driver->manage()->deleteAllCookies(); // we suddenly get logged off
		try
		{
			$browser->clickId('tag-snapback');
			$this->fail('Expected alert was not thrown.');
		}
		catch (\Facebook\WebDriver\Exception\UnexpectedAlertOpenException $e)
		{
			$this->assertTextContains("Not logged in", $e->getMessage());
		}
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
		try
		{
			$browser->clickId('tag-snapback');
			$this->fail('Expected alert was not thrown.');
		}
		catch (\Facebook\WebDriver\Exception\UnexpectedAlertOpenException $e)
		{
			$this->assertTextContains('Tag "snapback" doesn\'t exist.', $e->getMessage());
		}
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
		try
		{
			$browser->clickId('tag-snapback');
			$this->fail('Expected alert was not thrown.');
		}
		catch (\Facebook\WebDriver\Exception\UnexpectedAlertOpenException $e)
		{
			$this->assertTextContains('Tsumego with id="'. $context->otherTsumegos[0]['id'] . '" wasn\'t found.', $e->getMessage());
		}
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
		try
		{
			$browser->clickId('tag-snapback');
			$this->fail('Expected alert was not thrown.');
		}
		catch (\Facebook\WebDriver\Exception\UnexpectedAlertOpenException $e)
		{
			$this->assertTextContains('The tsumego already has tag snapback.', $e->getMessage());
		}
	}
}
