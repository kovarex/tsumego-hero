<?php

use App\Utility\Util;

class SgfControllerFetchTest extends IntegrationTestCase
{
	public function testFetchRequiresLogin()
	{
		new ContextPreparator([
			'user' => ['name' => 'solver'],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])'],
		]);

		$sgf = ClassRegistry::init('Sgf')->find('first');

		$this->logout();

		$this->get('/sgf/fetch/' . $sgf['Sgf']['id']);

		$this->assertResponseCode(401);
	}

	public function testFetchNotFound()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$this->get('/sgf/fetch/999999');

		$this->assertResponseCode(404);
	}

	public function testFetchIsForbiddenForAUserWhoDidNotSolveTheProblem()
	{
		new ContextPreparator([
			'user' => ['name' => 'nosolver'],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])'],
		]);

		$sgf = ClassRegistry::init('Sgf')->find('first');
		Util::execute('UPDATE sgf SET user_id = NULL');

		$this->get('/sgf/fetch/' . $sgf['Sgf']['id']);

		$this->assertResponseCode(403);
	}

	public function testFetchReturnsSgfForTheUserWhoSolvedTheProblem()
	{
		new ContextPreparator([
			'user' => ['name' => 'solver'],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])', 'status' => 'S'],
		]);

		$sgf = ClassRegistry::init('Sgf')->find('first');

		$this->get('/sgf/fetch/' . $sgf['Sgf']['id']);

		$this->assertResponseCode(200);
		$this->assertSame($sgf['Sgf']['sgf'], $this->view);
	}

	//The proposer gets the decision about their proposal with a link to its diff, so they see both versions.
	public function testFetchReturnsEveryVersionToAProposerOfTheProblem()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'proposer'],
			'tsumego' => ['set_order' => 1, 'sgfs' => [
				['data' => '(;FF[4]GM[1]SZ[19]AB[dd])', 'sgf_status_id' => Sgf::STATUS_ACCEPTED],
				['data' => '(;FF[4]GM[1]SZ[19]AB[dd]AB[ee])', 'sgf_status_id' => Sgf::STATUS_ACCEPTED]]],
		]);
		[$previous, $proposal] = $context->tsumegos[0]['sgfs'];
		Util::execute('UPDATE sgf SET user_id = NULL WHERE id = ?', [$previous['id']]);

		foreach ([$previous, $proposal] as $version)
		{
			$this->get('/sgf/fetch/' . $version['id']);
			$this->assertResponseCode(200);
			$this->assertSame(ClassRegistry::init('Sgf')->findById($version['id'])['Sgf']['sgf'], $this->view);
		}
	}

	public function testFetchReturnsSgfForAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])'],
		]);

		$sgf = ClassRegistry::init('Sgf')->find('first');

		$this->get('/sgf/fetch/' . $sgf['Sgf']['id']);

		$this->assertResponseCode(200);
		$this->assertSame($sgf['Sgf']['sgf'], $this->view);
	}
}
