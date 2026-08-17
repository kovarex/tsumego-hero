<?php

App::uses('ForbiddenException', 'Routing/Error');
App::uses('NotFoundException', 'Routing/Error');

class SgfControllerFetchTest extends ControllerTestCase
{
	public function testFetchRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/sgf/fetch/1', ['method' => 'get']);
	}

	public function testFetchNotFound()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex']]);

		$this->expectException(NotFoundException::class);

		$this->testAction('/sgf/fetch/999999', ['method' => 'get']);
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
