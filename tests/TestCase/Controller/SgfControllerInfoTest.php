<?php

use App\Model\Table\UserTable;
use App\Utility\Util;

class SgfControllerInfoTest extends IntegrationTestCase
{
	public function testInfoTellsWhatTheAuthorWrote()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'proposer']],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])']]);
		$sgfID = $context->tsumegos[0]['sgfs'][0]['id'];
		Util::execute('UPDATE sgf SET user_id = ?, description = ? WHERE id = ?', [$context->otherUsers[0]['id'], "Added D16\nbecause", $sgfID]);

		$this->get('/sgf/info/' . $sgfID);

		$info = json_decode($this->view, true);
		$this->assertSame("Added D16\nbecause", $info['description']);
		$this->assertSame(UserTable::renderLink(UserTable::row((int) $context->otherUsers[0]['id'])), $info['authorLink']);
		$this->assertStringContainsString('href="/users/view/' . $context->otherUsers[0]['id'] . '"', $info['authorLink']);
	}

	public function testInfoIsForbiddenForAUserWhoMayNotFetchTheVersion()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'nosolver'],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])']]);
		Util::execute('UPDATE sgf SET user_id = NULL');

		$this->get('/sgf/info/' . $context->tsumegos[0]['sgfs'][0]['id']);

		$this->assertResponseCode(403);
	}
}
