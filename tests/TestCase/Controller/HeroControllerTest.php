<?php

App::uses('ForbiddenException', 'Routing/Error');

class HeroControllerTest extends ControllerTestCase
{
	public function testSprintRequiresLevel()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'level' => 1]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/hero/sprint', ['method' => 'post']);
	}

	public function testIntuitionRequiresLevel()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'level' => 1]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/hero/intuition', ['method' => 'post']);
	}

	public function testRejuvenationRequiresLevel()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'level' => 1]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/hero/rejuvenation', ['method' => 'post']);
	}

	public function testRevelationRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/hero/revelation/1', ['method' => 'post']);
	}
}
