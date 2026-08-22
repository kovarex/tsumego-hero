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

	public function testRevelationRespectsUseLimit()
	{
		$context = new ContextPreparator(['tsumego' => 1, 'user' => ['level' => 1]]);
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->testAction('/hero/revelation/' . $tsumegoId, ['method' => 'post']);
		$this->assertSame(1, (int) $context->reloadUser()['used_revelation']);

		$this->expectException(ForbiddenException::class);
		$this->testAction('/hero/revelation/' . $tsumegoId, ['method' => 'post']);
	}

	public function testRefinementRequiresLevelOrPremium()
	{
		new ContextPreparator(['user' => ['name' => 'lowlevel', 'level' => 1, 'premium' => 0]]);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/hero/refinement', ['method' => 'post']);
	}
}
