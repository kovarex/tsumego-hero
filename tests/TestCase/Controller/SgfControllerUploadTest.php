<?php

use Facebook\WebDriver\Remote\LocalFileDetector;

class SgfControllerUploadTest extends TestCaseWithAuth
{
	public function testUploadSgfViaBesogoEditor()
	{
		$browser = new Browser();
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
		$browser = new Browser();
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sgf' => '(;FF[4]GM[1]SZ[19]ST[2]AB[dd]AW[dc];B[cd];W[cc])',
				'sets' => [['name' => 'Test Set', 'num' => 1]],
				'status' => 'S']]);

		$tsumegoID = $context->tsumegos[0]['id'];
		$browser->get('/' . $context->setConnections[0]['id']);
		$browser->clickId('show4');

		// Create temporary SGF file
		$newSgfContent = '(;FF[4]GM[1]SZ[19]ST[2]AB[dd][pd][dp]AW[dc][oc][oq];B[cd];W[cc]C[+From file])';
		$tmpFile = tempnam(sys_get_temp_dir(), 'sgf');
		file_put_contents($tmpFile, $newSgfContent);

		$uploadButton = $browser->find('#admin-upload-button');
		$uploadButton->setFileDetector(new LocalFileDetector());
		$uploadButton->sendKeys($tmpFile);
		$browser->clickId('admin-upload-submit');

		// Verify new SGF was added
		$sgfs = ClassRegistry::init('Sgf')->find('all', [
			'conditions' => ['tsumego_id' => $tsumegoID],
			'order' => 'id DESC']);
		$this->assertEquals(2, count($sgfs), 'Should have 2 SGFs after file upload');
		$this->assertEquals($newSgfContent, $sgfs[0]['Sgf']['sgf']);
		$this->assertEquals('B', $sgfs[0]['Sgf']['first_move_color']);
		$this->assertEquals('cd', $sgfs[0]['Sgf']['correct_moves']);
		unlink($tmpFile);
		Browser::shutdown();
	}

	public function testNonAdminUploadNotAccepted()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => false],
			'tsumego' => [
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd]AW[dc];B[cd];W[cc])',
				'sets' => [['name' => 'Test Set', 'num' => 1]],
				'status' => 'S']]);

		$setConnectionID = $context->tsumegos[0]['set-connections'][0]['id'];
		$tsumegoID = $context->tsumegos[0]['id'];

		// Upload SGF as non-admin
		$newSgfContent = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd]AW[dc];B[cd];W[cc]C[Non-admin upload])';
		$data = ['sgfForBesogo' => $newSgfContent];

		$this->testAction('/sgf/upload/' . $setConnectionID, [
			'method' => 'post',
			'data' => $data]);

		// Verify new SGF was added but NOT accepted
		$sgfs = ClassRegistry::init('Sgf')->find('all', [
			'conditions' => ['tsumego_id' => $tsumegoID],
			'order' => 'id DESC']);
		$this->assertEquals(2, count($sgfs));
		$this->assertEquals(0, $sgfs[0]['Sgf']['accepted'], 'Non-admin uploads should NOT be auto-accepted');
	}
}
