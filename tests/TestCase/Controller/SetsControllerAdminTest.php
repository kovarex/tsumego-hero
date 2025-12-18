<?php

App::uses('ControllerTestCase', 'TestSuite');
App::uses('Browser', 'TestSuite');
App::uses('ContextPreparator', 'TestSuite');

/**
 * Tests for SetsController admin functions (create set/tsumego)
 *
 * @group browser
 */
class SetsControllerAdminTest extends ControllerTestCase
{
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
		$browser->get('sets/create');
		$this->assertTrue($browser->titleContains('Tsumego Hero'), 'Should load create set page');

		// Get current number of sets in database
		$setModel = ClassRegistry::init('Set');
		$tsumegoModel = ClassRegistry::init('Tsumego');
		$initialSetCount = $setModel->find('count');
		$initialTsumegoCount = $tsumegoModel->find('count');

		// Create new set by submitting form
		$browser->byId('SetTitle')->sendKeys('Test Auto-Increment Set');
		$browser->byCssSelector('form input[type="submit"]')->click();

		// Wait for redirect back to sandbox page
		sleep(1);
		$this->assertTrue($browser->titleContains('Tsumego Hero - Collections'), 'Should redirect to sandbox after creating set');

		// Verify set was created
		$newSetCount = $setModel->find('count');
		$this->assertEquals($initialSetCount + 1, $newSetCount, 'Should create exactly one new set');

		// Verify tsumego was created
		$newTsumegoCount = $tsumegoModel->find('count');
		$this->assertEquals($initialTsumegoCount + 1, $newTsumegoCount, 'Should create exactly one new tsumego');

		// Verify the set and tsumego are properly linked
		$newSet = $setModel->find('first', ['order' => 'id DESC']);
		$this->assertEquals('Test Auto-Increment Set', $newSet['Set']['title'], 'Set should have correct title');
		$this->assertEquals($context->user['User']['id'], $newSet['Set']['user_id'], 'Set should belong to admin user');
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
