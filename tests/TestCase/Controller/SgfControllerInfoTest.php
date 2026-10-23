<?php

use App\Utility\Util;

class SgfControllerInfoTest extends TestCaseWithAuth
{
	public function testInfoTellsWhatTheAuthorWrote()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'proposer']],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])']]);
		$sgfID = $context->tsumegos[0]['sgfs'][0]['id'];
		Util::execute('UPDATE sgf SET user_id = ?, description = ? WHERE id = ?', [$context->otherUsers[0]['id'], "Added D16\nbecause", $sgfID]);

		$this->testAction('/sgf/info/' . $sgfID, ['method' => 'get']);

		$info = json_decode($this->controller->response->body(), true);
		$this->assertSame("Added D16\nbecause", $info['description']);
		$this->assertSame(User::renderLink(ClassRegistry::init('User')->findById($context->otherUsers[0]['id'])['User']), $info['authorLink']);
		$this->assertStringContainsString('href="/users/view/' . $context->otherUsers[0]['id'] . '"', $info['authorLink']);
	}

	public function testInfoIsForbiddenForAUserWhoMayNotFetchTheVersion()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'nosolver'],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])']]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/sgf/info/' . $context->tsumegos[0]['sgfs'][0]['id'], ['method' => 'get']);
	}
}
