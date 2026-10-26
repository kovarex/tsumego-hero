<?php

class HeroControllerTest extends IntegrationTestCase
{
	public function testSprintRequiresLevel()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'level' => 1]]);

		$this->post('/hero/sprint');

		$this->assertResponseCode(403);
	}

	public function testIntuitionRequiresLevel()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'level' => 1]]);

		$this->post('/hero/intuition');

		$this->assertResponseCode(403);
	}

	public function testRejuvenationRequiresLevel()
	{
		new ContextPreparator(['user' => ['name' => 'kovarex', 'level' => 1]]);

		$this->post('/hero/rejuvenation');

		$this->assertResponseCode(403);
	}

	public function testRevelationRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->post('/hero/revelation/1');

		$this->assertResponseCode(401);
	}

	public function testRevelationRespectsUseLimit()
	{
		$context = new ContextPreparator(['tsumego' => 1, 'user' => ['level' => 1]]);
		$tsumegoId = $context->tsumegos[0]['id'];

		$this->post('/hero/revelation/' . $tsumegoId);
		$this->assertSame(1, (int) $context->reloadUser()['used_revelation']);

		$this->post('/hero/revelation/' . $tsumegoId);
		$this->assertResponseCode(403);
	}

	public function testRefinementRequiresLevelOrPremium()
	{
		new ContextPreparator(['user' => ['name' => 'lowlevel', 'level' => 1, 'premium' => 0]]);

		$this->get('/hero/refinement');

		$this->assertResponseCode(403);
	}
}
