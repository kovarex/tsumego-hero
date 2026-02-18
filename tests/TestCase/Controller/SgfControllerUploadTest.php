<?php

class SgfControllerUploadTest extends TestCaseWithAuth
{
	public function testUploadSgfViaBesogoEditor()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sgf' => '(;FF[4]GM[1]SZ[19]ST[2]AB[dd]AW[dc];B[cd];W[cc]C[+])',
				'set_order' => 1,
				'status' => 'S']]);

		// Verify initial SGF count
		$initialSgfCount = count(ClassRegistry::init('Sgf')->find('all', [
			'conditions' => ['tsumego_id' => $context->tsumegos[0]['id']]]));
		$this->assertEquals(1, $initialSgfCount, 'Should have initial SGF from ContextPreparator');

		$browser->get('/' . $context->setConnections[0]['id']);

		$openLink = $browser->find('#openSgfLink');
		$this->assertTrue($openLink->isDisplayed());
		$this->assertSame($openLink->getText(), "Open");
		$openLink->click();
		$browser->waitUntilIDExists('#sgfCommentButton');
		$browser->clickId('sgfCommentButton');
		$browser->clickId('commentEditField');
		$browser->driver->getKeyboard()->sendKeys("Hello from test");
		$browser->clickId('sgfCommentButton');
		$browser->clickId('saveSGFButton');

		// Wait for redirect after saving SGF
		$wait = new \Facebook\WebDriver\WebDriverWait($browser->driver, 10, 200);
		$wait->until(function () use ($browser) {
			return strpos($browser->driver->getCurrentURL(), '/editor/') === false;
		});

		// Upload new SGF via besogo (uses 'sgfForBesogo' field)
		$newSgfContent = '(;FF[4]GM[1]SZ[19]ST[2]AB[dd]AW[dc]C[Hello from test];B[cd];W[cc]C[+])';

		// Verify new SGF was added
		$sgfs = ClassRegistry::init('Sgf')->find('all', [
			'conditions' => ['tsumego_id' => $context->tsumegos[0]['id']],
			'order' => 'id DESC']);
		$this->assertEquals(2, count($sgfs), 'Should have 2 SGFs after upload');
		$this->assertEquals($newSgfContent, $sgfs[0]['Sgf']['sgf']);
		$this->assertEquals(Auth::getUserID(), $sgfs[0]['Sgf']['user_id']);
		$this->assertEquals(1, $sgfs[0]['Sgf']['accepted'], 'Admin uploads should be auto-accepted');
		$this->assertSame('B', $sgfs[0]['Sgf']['first_move_color']);
		$this->assertSame('cd', $sgfs[0]['Sgf']['correct_moves']);
	}

	public function testUploadSgfViaFileUpload()
	{
		$browser = Browser::instance();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['status' => 'S', 'set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]ST[2]AB[dd]AW[dc];B[cd];W[cc])']]);

		$browser->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$browser->clickId('show4');

		// Set file content via JavaScript (avoids LocalFileDetector issues with geckodriver in Docker)
		$newSgfContent = '(;FF[4]GM[1]SZ[19]ST[2]AB[dd][pd][dp]AW[dc][oc][oq];B[cd];W[cc]C[+From file])';
		$browser->driver->executeScript("
			var content = arguments[0];
			var input = document.getElementById('admin-upload-button');
			var file = new File([content], 'test.sgf', {type: 'application/x-sgf'});
			var dt = new DataTransfer();
			dt.items.add(file);
			input.files = dt.files;
			input.dispatchEvent(new Event('change', { bubbles: true }));
		", [$newSgfContent]);
		$browser->clickId('admin-upload-submit');

		// Verify new SGF was added
		$sgfs = ClassRegistry::init('Sgf')->find('all', [
			'conditions' => ['tsumego_id' => $context->tsumegos[0]['id']],
			'order' => 'id DESC']);
		$this->assertEquals(2, count($sgfs), 'Should have 2 SGFs after file upload');
		$this->assertEquals($newSgfContent, $sgfs[0]['Sgf']['sgf']);
		$this->assertEquals('B', $sgfs[0]['Sgf']['first_move_color']);
		$this->assertEquals('cd', $sgfs[0]['Sgf']['correct_moves']);
	}
}
