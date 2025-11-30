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
					'tags' => [['name' => 'atari', 'approved' => 0, 'user' => 'Ivan detkov', 'popular' => $popular]]]],
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
}
