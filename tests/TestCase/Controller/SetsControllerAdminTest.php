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

	/**
	 * Test SetsController::view() with ?add parameter - cloning tsumego in set
	 */
	public function testAddTsumegoToExistingSet()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
		]);

		// Manually create set and tsumego with SetConnection
		$setModel = ClassRegistry::init('Set');
		$setModel->create();
		$setModel->save(['title' => 'Test Set for Adding Problems', 'public' => 0, 'user_id' => $context->user['User']['id']]);
		$set = $setModel->find('first', ['order' => 'id DESC'])['Set'];

		$tsumegoModel = ClassRegistry::init('Tsumego');
		$tsumegoModel->create();
		$tsumegoModel->save(['rating' => 1000, 'author' => 'Original Author']);
		$tsumego = $tsumegoModel->find('first', ['order' => 'id DESC'])['Tsumego'];

		$setConnectionModel = ClassRegistry::init('SetConnection');
		$setConnectionModel->create();
		$setConnectionModel->save(['set_id' => $set['id'], 'tsumego_id' => $tsumego['id'], 'num' => 1]);

		$browser = Browser::instance();

		// Navigate to set view page
		$browser->get('sets/view/' . $set['id']);
		$this->assertTrue($browser->titleContains('Test Set'), 'Should load set view page');

		// Get current number of tsumegos in set
		$initialConnectionCount = $setConnectionModel->find('count', [
			'conditions' => ['set_id' => $set['id']]
		]);
		$this->assertEquals(1, $initialConnectionCount, 'Set should start with 1 tsumego');

		// Add new tsumego by accessing ?add URL
		$browser->get('sets/view/' . $set['id'] . '?add');

		// Wait for redirect
		sleep(1);

		// Verify tsumego was created
		$newTsumegoCount = $tsumegoModel->find('count');
		$this->assertEquals(2, $newTsumegoCount, 'Should have 2 tsumegos total');

		// Verify SetConnection was created
		$newConnectionCount = $setConnectionModel->find('count', [
			'conditions' => ['set_id' => $set['id']]
		]);
		$this->assertEquals(2, $newConnectionCount, 'Set should now have 2 tsumegos');

		// Verify the new tsumego has correct properties (cloned from first)
		$connections = $setConnectionModel->find('all', [
			'conditions' => ['set_id' => $set['id']],
			'order' => 'num ASC'
		]);
		$this->assertEquals(1, $connections[0]['SetConnection']['num'], 'First tsumego should have num=1');
		$this->assertEquals(2, $connections[1]['SetConnection']['num'], 'Second tsumego should have num=2');

		$originalTsumego = $tsumegoModel->findById($connections[0]['SetConnection']['tsumego_id']);
		$clonedTsumego = $tsumegoModel->findById($connections[1]['SetConnection']['tsumego_id']);

		$this->assertNotEquals($originalTsumego['Tsumego']['id'], $clonedTsumego['Tsumego']['id'], 'Cloned tsumego should have different ID');
		$this->assertEquals($originalTsumego['Tsumego']['rating'], $clonedTsumego['Tsumego']['rating'], 'Cloned tsumego should have same rating');
		$this->assertEquals(100, $clonedTsumego['Tsumego']['variance'], 'Cloned tsumego should have variance=100 (new problem)');
		$this->assertEquals('admin', $clonedTsumego['Tsumego']['author'], 'Cloned tsumego should have current user as author');
	}
}
