<?php

class SgfControllerUploadTest extends TestCaseWithAuth
{
	public function testUploadSgfViaBesogoEditor()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd]AW[dc];B[cd];W[cc])',
				'sets' => [['name' => 'Test Set', 'num' => 1]],
				'status' => 'S']]);

		$setConnectionID = $context->tsumego['set-connections'][0]['id'];
		$tsumegoID = $context->tsumego['id'];

		// Verify initial SGF count
		$initialSgfCount = count(ClassRegistry::init('Sgf')->find('all', ['conditions' => ['tsumego_id' => $tsumegoID]]));
		$this->assertEquals(1, $initialSgfCount, 'Should have initial SGF from ContextPreparator');

		// Upload new SGF via besogo (uses 'sgfForBesogo' field)
		$newSgfContent = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd][pd]AW[dc][oc];B[cd];W[cc]C[Updated])';
		$data = ['sgfForBesogo' => $newSgfContent];

		$this->testAction('/sgf/upload/' . $setConnectionID, [
			'method' => 'post',
			'data' => $data]);

		// Verify new SGF was added
		$sgfs = ClassRegistry::init('Sgf')->find('all', [
			'conditions' => ['tsumego_id' => $tsumegoID],
			'order' => 'id DESC']);
		$this->assertEquals(2, count($sgfs), 'Should have 2 SGFs after upload');
		$this->assertEquals($newSgfContent, $sgfs[0]['Sgf']['sgf']);
		$this->assertEquals(Auth::getUserID(), $sgfs[0]['Sgf']['user_id']);
		$this->assertEquals(1, $sgfs[0]['Sgf']['accepted'], 'Admin uploads should be auto-accepted');
	}

	public function testUploadSgfViaFileUpload()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd]AW[dc];B[cd];W[cc])',
				'sets' => [['name' => 'Test Set', 'num' => 1]],
				'status' => 'S']]);

		$setConnectionID = $context->tsumego['set-connections'][0]['id'];
		$tsumegoID = $context->tsumego['id'];

		// Create temporary SGF file
		$newSgfContent = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd][pd][dp]AW[dc][oc][oq];B[cd];W[cc]C[From file])';
		$tmpFile = tempnam(sys_get_temp_dir(), 'sgf');
		file_put_contents($tmpFile, $newSgfContent);

		// Simulate file upload
		$_FILES['adminUpload'] = [
			'name' => 'test-upload.sgf',
			'tmp_name' => $tmpFile,
			'size' => strlen($newSgfContent),
			'error' => UPLOAD_ERR_OK];

		$this->testAction('/sgf/upload/' . $setConnectionID, [
			'method' => 'post',
			'data' => []]);

		// Verify new SGF was added
		$sgfs = ClassRegistry::init('Sgf')->find('all', [
			'conditions' => ['tsumego_id' => $tsumegoID],
			'order' => 'id DESC']);
		$this->assertEquals(2, count($sgfs), 'Should have 2 SGFs after file upload');
		$this->assertEquals($newSgfContent, $sgfs[0]['Sgf']['sgf']);

		unlink($tmpFile);
	}

	public function testNonAdminUploadNotAccepted()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => false],
			'tsumego' => [
				'sgf' => '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[dd]AW[dc];B[cd];W[cc])',
				'sets' => [['name' => 'Test Set', 'num' => 1]],
				'status' => 'S']]);

		$setConnectionID = $context->tsumego['set-connections'][0]['id'];
		$tsumegoID = $context->tsumego['id'];

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
