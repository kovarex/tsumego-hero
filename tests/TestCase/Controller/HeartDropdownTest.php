<?php

use Facebook\WebDriver\WebDriverBy;

/**
 * Heart button and set dropdown on the play page.
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 *
 * @group browser
 */
class HeartDropdownTest extends TestCaseWithAuth
{
	public function testHeartButtonShowsWithoutDropdownWhenUserHasNoSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])', 'set_order' => 1],
		]);

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->byId('favButton');
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

		$browser->byId('favButton');
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

		$browser->byId('favButton');
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

		$browser->byId('favDropdownArrow')->click();
		$browser->waitUntilCssSelectorExists('#fav-dropdown');

		$dropdownText = $browser->driver->findElement(WebDriverBy::id('fav-dropdown'))->getText();
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
		$setModel = ClassRegistry::init('Set');
		$setId = null;
		foreach (['Target Set', 'Other Set'] as $title)
		{
			$setModel->create();
			$setModel->save(['Set' => [
				'user_id' => $context->user['id'],
				'title' => $title,
				'public' => 0,
				'order' => Constants::$DEFAULT_SET_ORDER,
			]]);
			if ($title === 'Target Set')
				$setId = $setModel->id;
		}
		$tsumegoId = $context->tsumegos[0]['id'];

		$browser = Browser::instance();
		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$browser->byId('favDropdownArrow')->click();
		$browser->waitUntilCssSelectorExists('#fav-dropdown');

		$targetRow = $browser->driver->findElement(WebDriverBy::xpath("//div[@id='fav-dropdown']//*[input and contains(., 'Target Set')]"));
		$targetRow->findElement(WebDriverBy::cssSelector('input'))->click();
		$browser->waitUntilCssSelectorExistsWithText('#favButton', '❤️');

		$sc = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => ['set_id' => $setId, 'tsumego_id' => $tsumegoId],
		]);
		$this->assertNotEmpty($sc, 'Checking a set should add the tsumego to it');
	}
}
