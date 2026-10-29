<?php


//Tests for SetsController admin functions (create set/tsumego)
/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 * @group browser
 */
class SetsControllerAdminTest extends IntegrationTestCase
{
	//Test SetsController::create() - creating new set with first tsumego
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
		$setTable = $this->fetchTable('Set');
		$tsumegoTable = $this->fetchTable('Tsumego');
		$initialSetCount = $setTable->find()->count();
		$initialTsumegoCount = $tsumegoTable->find()->count();

		// Create new set by submitting form
		$browser->findById('SetTitle')->sendKeys('Test Auto-Increment Set');
		$browser->find('form input[type="submit"]')->click();

		// Wait for redirect to the newly created set's view page
		$this->assertTrue($browser->titleContains('Test Auto-Increment Set'), 'Should redirect to the new set view after creating set');

		// Verify set was created
		$newSetCount = $setTable->find()->count();
		$this->assertEquals($initialSetCount + 1, $newSetCount, 'Should create exactly one new set');

		// Verify tsumego was created
		$newTsumegoCount = $tsumegoTable->find()->count();
		$this->assertEquals($initialTsumegoCount + 1, $newTsumegoCount, 'Should create exactly one new tsumego');

		// Verify the set and tsumego are properly linked
		$newSet = $setTable->find()->orderByDesc('id')->first();
		$this->assertEquals('Test Auto-Increment Set', $newSet->title, 'Set should have correct title');
		$this->assertNull($newSet->user_id, 'Sandbox set should not belong to any user');
		$this->assertEquals(false, $newSet->public, 'New set should be private');

		$newTsumego = $tsumegoTable->find()->orderByDesc('id')->first();
		$this->assertNotEmpty($newTsumego, 'Should create tsumego');

		// Verify SetConnection links them
		$connection = $this->fetchTable('SetConnection')->find()->where(['set_id' => $newSet->id, 'tsumego_id' => $newTsumego->id])->first();
		$this->assertNotEmpty($connection, 'SetConnection should link set and tsumego');
		$this->assertEquals(1, $connection->num, 'First tsumego should have num=1');
	}
}
