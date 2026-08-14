<?php

use PHPUnitRetry\RetryTrait;

App::uses('ControllerTestCase', 'TestSuite');
App::uses('Browser', 'TestSuite');
App::uses('ContextPreparator', 'TestSuite');

/**
 * Tests for SetsController admin functions (create set/tsumego)
 *
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 *
 * @group browser
 */
class SetsControllerAdminTest extends ControllerTestCase
{
	use RetryTrait;
	/**
	 * Test SetsController::create() - creating new set with first tsumego
	 */
	public function testCreateNewSetWithFirstTsumego()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
		]);

		$browser = Browser::instance();

		// Navigate to create set page
		$browser->get('sets/create?sandbox=1');
		$this->assertTrue($browser->titleContains('Tsumego Hero'), 'Should load create set page');

		// Get current number of sets in database
		$setModel = ClassRegistry::init('Set');
		$tsumegoModel = ClassRegistry::init('Tsumego');
		$initialSetCount = $setModel->find('count');
		$initialTsumegoCount = $tsumegoModel->find('count');

		// Create new set by submitting form
		$browser->byId('SetTitle')->sendKeys('Test Auto-Increment Set');
		$browser->byCssSelector('form input[type="submit"]')->click();

		// Wait for redirect to the newly created set's view page
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function ($driver) {
			return str_contains($driver->getTitle(), 'Test Auto-Increment Set');
		});
		$this->assertTrue($browser->titleContains('Test Auto-Increment Set'), 'Should redirect to the new set view after creating set');

		// Verify set was created
		$newSetCount = $setModel->find('count');
		$this->assertEquals($initialSetCount + 1, $newSetCount, 'Should create exactly one new set');

		// Verify tsumego was created
		$newTsumegoCount = $tsumegoModel->find('count');
		$this->assertEquals($initialTsumegoCount + 1, $newTsumegoCount, 'Should create exactly one new tsumego');

		// Verify the set and tsumego are properly linked
		$newSet = $setModel->find('first', ['order' => 'id DESC']);
		$this->assertEquals('Test Auto-Increment Set', $newSet['Set']['title'], 'Set should have correct title');
		$this->assertNull($newSet['Set']['user_id'], 'Sandbox set should not belong to any user');
		$this->assertEquals(false, $newSet['Set']['public'], 'New set should be private');

		$newTsumego = $tsumegoModel->find('first', ['order' => 'id DESC']);
		$this->assertNotEmpty($newTsumego, 'Should create tsumego');

		// Verify SetConnection links them
		$setConnectionModel = ClassRegistry::init('SetConnection');
		$connection = $setConnectionModel->find('first', [
			'conditions' => [
				'set_id' => $newSet['Set']['id'],
				'tsumego_id' => $newTsumego['Tsumego']['id']
			]
		]);
		$this->assertNotEmpty($connection, 'SetConnection should link set and tsumego');
		$this->assertEquals(1, $connection['SetConnection']['num'], 'First tsumego should have num=1');
	}
}
