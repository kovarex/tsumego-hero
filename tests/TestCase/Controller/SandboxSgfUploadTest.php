<?php

use Facebook\WebDriver\WebDriverBy;

class SandboxSgfUploadTest extends TestCaseWithAuth
{
	public function testAddTsumegoWithSgfFileUpload()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'set' => ['title' => 'Test Sandbox Set', 'public' => 0]]);
		$setID = $context->set['id'];

		// Create a test SGF file
		$sgfContent = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd][pd][dp]AW[dc][pp][pq];B[qd];W[oc])';
		$tmpFile = tempnam(sys_get_temp_dir(), 'sgf');
		file_put_contents($tmpFile, $sgfContent);

		// Simulate file upload via $_FILES
		$_FILES['adminUpload'] = [
			'name' => 'test.sgf',
			'tmp_name' => $tmpFile,
			'size' => strlen($sgfContent),
			'error' => UPLOAD_ERR_OK];

		// POST to addTsumego endpoint
		$data = [
			'Tsumego' => [
				'num' => 1,
				'variance' => 100,
				'author' => $context->user['name'],
				'description' => '',
				'hint' => '']];

		$this->testAction('/sets/addTsumego/' . $setID, [
			'method' => 'post',
			'data' => $data]);

		// Verify tsumego was created
		$tsumego = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC']);
		$this->assertNotEmpty($tsumego, 'Tsumego should be created');
		$this->assertEquals('kovarex', $tsumego['Tsumego']['author']);

		// Verify set connection was created with correct num
		$setConnection = ClassRegistry::init('SetConnection')->find('first', [
			'conditions' => [
				'set_id' => $setID,
				'tsumego_id' => $tsumego['Tsumego']['id']]]);
		$this->assertNotEmpty($setConnection, 'SetConnection should be created');
		$this->assertEquals(1, $setConnection['SetConnection']['num']);

		// Verify SGF was uploaded
		$sgf = ClassRegistry::init('Sgf')->find('first', [
			'conditions' => ['tsumego_id' => $tsumego['Tsumego']['id']],
			'order' => 'id DESC']);
		$this->assertNotEmpty($sgf, 'SGF should be uploaded when file is provided');
		$this->assertEquals($sgfContent, $sgf['Sgf']['sgf']);

		unlink($tmpFile);
	}

	public function testAddTsumegoWithSgfTextarea()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'set' => ['title' => 'Test Sandbox Set 2', 'public' => 0, 'order' => 998]]);
		$setID = $context->set['id'];

		$sgfContent = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd][pd][dp]AW[dc][pp][pq];B[qd];W[oc])';

		// POST to addTsumego endpoint with SGF in data
		$data = [
			'Tsumego' => [
				'num' => 1,
				'variance' => 100,
				'author' => $context->user['name'],
				'description' => '',
				'hint' => '',
				'sgf' => $sgfContent]];

		$this->testAction('/sets/addTsumego/' . $setID, [
			'method' => 'post',
			'data' => $data]);

		// Verify tsumego was created
		$tsumego = ClassRegistry::init('Tsumego')->find('first', ['order' => 'id DESC']);
		$this->assertNotEmpty($tsumego, 'Tsumego should be created');

		// Verify SGF was uploaded
		$sgf = ClassRegistry::init('Sgf')->find('first', [
			'conditions' => ['tsumego_id' => $tsumego['Tsumego']['id']],
			'order' => 'id DESC']);
		$this->assertNotEmpty($sgf, 'SGF should be uploaded when SGF text is provided');
		$this->assertEquals($sgfContent, $sgf['Sgf']['sgf']);
	}
}
