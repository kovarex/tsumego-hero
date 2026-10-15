<?php

use App\Utility\Util;

class SgfsControllerTest extends ControllerTestCase
{
	public function testUploadHistoryOfADeletedUploader()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer', 'admin' => true],
			'other-users' => [['name' => 'goner', 'email' => 'goner@example.com', 'deleted' => true]],
			'tsumego' => [
				'sgf' => '(;FF[4]GM[1]SZ[19]ST[2]AB[dd]AW[dc];B[cd];W[cc]C[+])',
				'set_order' => 1,
				'status' => 'S']]);

		$uploader = $context->otherUsers[0];
		$tsumegoId = $context->tsumegos[0]['id'];
		Util::execute('UPDATE sgf SET user_id = ? WHERE tsumego_id = ?', [$uploader['id'], $tsumegoId]);

		$view = $this->testAction('/sgfs/view/' . $tsumegoId . '?user=' . $uploader['id'], ['return' => 'view']);
		$html = preg_replace('/\s+/', ' ', $view);

		$this->assertStringContainsString('Upload History of ' . User::DELETED_NAME, $html);
		$this->assertStringNotContainsString($uploader['display_name'], $html);
		$this->assertStringNotContainsString(md5('goner@example.com'), $html);
	}
}
