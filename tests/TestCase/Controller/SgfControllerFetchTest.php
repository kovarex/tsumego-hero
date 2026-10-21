<?php

class SgfControllerFetchTest extends TestCaseWithAuth
{
	public function testFetchRequiresLogin()
	{
		new ContextPreparator([
			'user' => ['name' => 'solver'],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])'],
		]);

		$sgf = ClassRegistry::init('Sgf')->find('first');

		$this->logout();

		$this->expectException(UnauthorizedException::class);

		$this->testAction('/sgf/fetch/' . $sgf['Sgf']['id'], ['method' => 'get']);
	}

	public function testFetchNotFound()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$this->expectException(NotFoundException::class);

		$this->testAction('/sgf/fetch/999999', ['method' => 'get']);
	}

	public function testFetchIsForbiddenForAUserWhoDidNotSolveTheProblem()
	{
		new ContextPreparator([
			'user' => ['name' => 'nosolver'],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])'],
		]);

		$sgf = ClassRegistry::init('Sgf')->find('first');

		$this->expectException(ForbiddenException::class);

		$this->testAction('/sgf/fetch/' . $sgf['Sgf']['id'], ['method' => 'get']);
	}

	public function testFetchReturnsSgfForTheUserWhoSolvedTheProblem()
	{
		new ContextPreparator([
			'user' => ['name' => 'solver'],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])', 'status' => 'S'],
		]);

		$sgf = ClassRegistry::init('Sgf')->find('first');

		$this->testAction('/sgf/fetch/' . $sgf['Sgf']['id'], ['method' => 'get']);

		$this->assertSame(200, $this->controller->response->statusCode());
		$this->assertSame($sgf['Sgf']['sgf'], $this->controller->response->body());
	}

	public function testFetchReturnsSgfForAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => ['set_order' => 1, 'sgf' => '(;FF[4]GM[1]SZ[19]AB[dd])'],
		]);

		$sgf = ClassRegistry::init('Sgf')->find('first');

		$this->testAction('/sgf/fetch/' . $sgf['Sgf']['id'], ['method' => 'get']);

		$this->assertSame(200, $this->controller->response->statusCode());
		$this->assertSame($sgf['Sgf']['sgf'], $this->controller->response->body());
	}
}
