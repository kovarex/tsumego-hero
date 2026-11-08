<?php

use App\Utility\Constants;
use Facebook\WebDriver\WebDriverBy;
use PHPUnit\Framework\Attributes\Group;

//The heart button and the set dropdown on the play page.
#[Group('browser')]
#[RetryOnBrowserFailure]
class HeartDropdownTest extends IntegrationTestCase
{
	public function testHeartButtonShowsWithoutDropdownWhenUserHasNoSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'set_order' => 1],
		]);

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->findById('favButton');
		$this->assertFalse($browser->idExists('favDropdownArrow'), 'Dropdown arrow should be hidden when the user has no sets');
	}

	public function testHeartButtonHidesDropdownWhenUserHasOneSet(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'sets' => [['name' => 'My Set', 'num' => 1, 'user_id' => 'self', 'public' => 0]]],
		]);

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->findById('favButton');
		$this->assertFalse($browser->idExists('favDropdownArrow'), 'Dropdown arrow should be hidden when the user has only one set');
	}

	public function testHeartDropdownShowsWhenUserHasMultipleSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'sets' => [
				['name' => 'First Set', 'num' => 1, 'user_id' => 'self', 'public' => 0],
				['name' => 'Second Set', 'num' => 2, 'user_id' => 'self', 'public' => 0],
			]],
		]);

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->findById('favButton');
		$this->assertTrue($browser->idExists('favDropdownArrow'), 'Dropdown arrow should show when the user has multiple sets');
	}

	public function testDropdownListsUserSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'sets' => [
				['name' => 'First Set', 'num' => 1, 'user_id' => 'self', 'public' => 0],
				['name' => 'Second Set', 'num' => 2, 'user_id' => 'self', 'public' => 0],
			]],
		]);

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->clickId('favDropdownArrow');

		$dropdownText = $browser->findById('fav-dropdown')->getText();
		$this->assertStringContainsString('First Set', $dropdownText);
		$this->assertStringContainsString('Second Set', $dropdownText);
	}

	public function testCheckingSetAddsTsumego(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'set_order' => 1],
		]);

		// Two empty user-owned sets, so the dropdown shows with a choice
		$setTable = $this->fetchTable('Set');
		$setId = null;
		foreach (['Target Set', 'Other Set'] as $title)
		{
			$createdSet = $setTable->saveOrFail($setTable->newEntity([
				'user_id' => $context->user['id'],
				'title' => $title,
				'public' => 0,
				'order' => Constants::$DEFAULT_SET_ORDER,
			]));
			if ($title === 'Target Set')
				$setId = $createdSet->id;
		}
		$tsumegoId = $context->tsumegos[0]['id'];

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->clickId('favDropdownArrow');
		$browser->find('#fav-dropdown');

		$targetRow = $browser->findBy(WebDriverBy::xpath("//div[@id='fav-dropdown']//*[input and contains(., 'Target Set')]"));
		$targetRow->findElement(WebDriverBy::cssSelector('input'))->click();
		$browser->waitUntilCssSelectorExistsWithText('#favButton', '❤️');

		$setConnection = $this->fetchTable('SetConnection')->find()->where(['set_id' => $setId, 'tsumego_id' => $tsumegoId])->first();
		$this->assertNotEmpty($setConnection, 'Checking a set should add the tsumego to it');
	}

	//The heart adds the problem to the default set, which the player may have renamed, and lights up
	//without counting another set called Favorites
	public function testHeartAddsToARenamedDefaultSet(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'set_order' => 1],
		]);
		$setTable = $this->fetchTable('Set');
		$setIDs = [];
		foreach (['My Problems', 'Favorites'] as $title)
			$setIDs[$title] = $setTable->saveOrFail($setTable->newEntity([
				'user_id' => $context->user['id'],
				'title' => $title,
				'public' => 0,
				'order' => Constants::$DEFAULT_SET_ORDER]))->id;
		$this->fetchTable('User')->updateAll(['default_set_id' => $setIDs['My Problems']], ['id' => $context->user['id']]);
		$tsumegoId = $context->tsumegos[0]['id'];

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('favButton');
		$browser->waitUntilCssSelectorExistsWithText('#favButton', '❤️');
		$browser->waitUntilJs('window.userSets.find(s => s.id == ' . $setIDs['My Problems'] . ').contains === true');

		$this->assertFalse($browser->driver->executeScript('return window.userSets.find(s => s.id == ' . $setIDs['Favorites'] . ').contains;'), 'The set called Favorites is not the default one');
		$this->assertTrue($this->fetchTable('SetConnection')->exists(['set_id' => $setIDs['My Problems'], 'tsumego_id' => $tsumegoId]));
		$this->assertFalse($this->fetchTable('SetConnection')->exists(['set_id' => $setIDs['Favorites'], 'tsumego_id' => $tsumegoId]));
	}
}
