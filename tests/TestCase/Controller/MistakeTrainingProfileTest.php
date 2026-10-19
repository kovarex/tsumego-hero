<?php

use App\Utility\Feature;
use App\Utility\FeatureKey;
use App\Utility\MistakeTraining;
use App\Utility\Util;

/**
 * The section of the mistake training on the profile of a player.
 */
class MistakeTrainingProfileTest extends TestCaseWithAuth
{
	public function setUp(): void
	{
		parent::setUp();
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::EVERYONE);
	}

	public function tearDown(): void
	{
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::ADMINS);
		parent::tearDown();
	}

	private function daysAgo(int $days): string
	{
		return date('Y-m-d', strtotime(MistakeTraining::today() . ' -' . $days . ' days'));
	}

	private function addDay(int $userId, int $daysAgo, int $reviewed, bool $cleared): void
	{
		Util::execute(
			'INSERT INTO mistake_training_day (user_id, day, due, reviewed, cleared, xp) VALUES (?, ?, ?, ?, ?, 0)',
			[$userId, $this->daysAgo($daysAgo), $reviewed, $reviewed, (int) $cleared]
		);
	}

	/**
	 * A player with three problems in the training, at the first and the fourth
	 * rung and graduated, who cleared today and yesterday, trained three days ago
	 * without clearing, and missed the day five days ago.
	 */
	private function playerWhoTrained(bool $viewedByAnother = false): ContextPreparator
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'tsumego' => ['set_order' => 1],
			'tsumegos' => [['set_order' => 2], ['set_order' => 3]],
			'other-users' => $viewedByAnother ? [['name' => 'trainee']] : [],
		]);
		$userId = $viewedByAnother ? $context->otherUsers[0]['id'] : $context->user['id'];
		foreach ($context->tsumegos as $tsumego)
			MistakeTraining::addProblem($userId, (int) $tsumego['id']);
		MistakeTraining::moveToRung($userId, (int) $context->tsumegos[1]['id'], 3);
		MistakeTraining::graduateNow($userId, (int) $context->tsumegos[2]['id']);
		$this->addDay($userId, 0, 6, true);
		$this->addDay($userId, 1, 5, true);
		$this->addDay($userId, 3, 2, false);
		$this->addDay($userId, 5, 0, false);
		return $context;
	}

	private function profileOf(int $userId): string
	{
		return $this->testAction('/users/view/' . $userId, ['return' => 'contents']);
	}

	public function testTheProfileShowsTheTrainingOfThePlayer(): void
	{
		$context = $this->playerWhoTrained();

		$html = $this->profileOf($context->user['id']);

		$this->assertStringContainsString('id="mistake-training-overview"', $html);
		$this->assertMatchesRegularExpression('/id="mistake-training-in-training">2</', $html);
		$this->assertMatchesRegularExpression('/id="mistake-training-graduated">1</', $html);
		$this->assertMatchesRegularExpression('/id="mistake-training-streak">2 days</', $html);
		$this->assertMatchesRegularExpression('/id="mistake-training-best-streak">2 days</', $html);
		$this->assertStringContainsString('aria-label="' . MistakeTraining::rungName(3) . '"', $html, 'The rungs wear their insignia');
		$this->assertStringContainsString('title="' . MistakeTraining::rungName(3) . ': 1"', $html);

		$this->assertDay($html, 0, 'training-calendar__day training-calendar__day--cleared', '6 reviewed, cleared');
		$this->assertDay($html, 3, 'training-calendar__day training-calendar__day--trained training-calendar__day--missed', '2 reviewed, not cleared');
		$this->assertDay($html, 5, 'training-calendar__day training-calendar__day--missed', 'missed');
		$this->assertDay($html, 2, 'training-calendar__day', 'no training');
		$this->assertStringNotContainsString('data-day="' . date('Y-m-d', strtotime(MistakeTraining::today() . ' +1 day')) . '"', $html,
			'The calendar ends today');
		$this->assertStringContainsString('data-day="' . MistakeTraining::profileStart() . '"', $html, 'And starts half a year ago');
	}

	private function assertDay(string $html, int $daysAgo, string $class, string $description): void
	{
		$day = $this->daysAgo($daysAgo);
		$this->assertMatchesRegularExpression(
			'/class="' . preg_quote($class, '/') . '" [^>]*data-day="' . $day . '" title="' . preg_quote(date('M j, Y', strtotime($day)) . ': ' . $description, '/') . '"/',
			$html
		);
	}

	public function testAnotherPlayerSeesTheTrainingToo(): void
	{
		$context = $this->playerWhoTrained(true);

		$html = $this->profileOf($context->otherUsers[0]['id']);

		$this->assertStringContainsString('id="mistake-training-overview"', $html);
		$this->assertMatchesRegularExpression('/id="mistake-training-in-training">2</', $html);
	}

	public function testNoSectionForAPlayerWhoNeverTrained(): void
	{
		$context = new ContextPreparator(['tsumego' => 1]);

		$this->assertStringNotContainsString('mistake-training-overview', $this->profileOf($context->user['id']));
	}

	public function testNoSectionForAViewerWithoutTheTraining(): void
	{
		$context = $this->playerWhoTrained();
		$this->setFeatureFlag(FeatureKey::MistakeTraining, Feature::ADMINS);

		$this->assertStringNotContainsString('mistake-training-overview', $this->profileOf($context->user['id']));
	}
}
