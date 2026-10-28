<?php

use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;
use App\Utility\RungInsignia;
use App\Utility\SetNavigationButtonsInput;
use App\Utility\TsumegoButton;
use App\Utility\TsumegoButtons;
use App\Utility\TsumegoFilters;

class TsumegoButtonsTest extends IntegrationTestCase
{
	public function testMistakeTrainingOneButtonPerTsumegoInMultipleSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'sets' => [
					['name' => 'Set A', 'num' => 1],
					['name' => 'Set B', 'num' => 2],
				],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$buttons = MistakeTraining::buildQueueButtons($scId);

		$this->assertCount(1, $buttons, 'A tsumego in multiple sets should appear once in the training queue');
		$this->assertSame($scId, $buttons[0]->setConnectionID, 'Button should point at the current set connection');
	}

	public function testMistakeTrainingPrefersCurrentSetConnection(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'sets' => [
					['name' => 'Set A', 'num' => 1],
					['name' => 'Set B', 'num' => 2],
				],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$secondScId = (int) $context->tsumegos[0]['set-connections'][1]['id'];

		$buttons = MistakeTraining::buildQueueButtons($secondScId);

		$this->assertCount(1, $buttons);
		$this->assertSame($secondScId, $buttons[0]->setConnectionID, 'Queue should prefer the set connection the user is on');
	}

	public function testMistakeTrainingQueueKeepsTheOrderTheProblemsCameIn(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
			'tsumegos' => [
				[
					'set_order' => 2,
					'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-2 days'))],
				],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$buttons = MistakeTraining::buildQueueButtons($scId);

		$this->assertCount(2, $buttons);
		$this->assertSame($context->tsumegos[0]['id'], $buttons[0]->tsumegoID,
			'The problem that came into the training first comes first, however overdue the others are');
		$this->assertSame(1, $buttons->currentOrder);
	}

	//The preview of a problem is its current version, never a proposal waiting for an admin or a rejected one.
	public function testButtonPreviewShowsTheCurrentVersion(): void
	{
		$current = '(;GM[1]FF[4]SZ[19]AB[cc];B[aa])';
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'sgfs' => [
					['data' => '(;GM[1]FF[4]SZ[19]AB[dd];B[aa])', 'sgf_status_id' => Sgf::STATUS_ACCEPTED],
					['data' => $current, 'sgf_status_id' => Sgf::STATUS_ACCEPTED],
					['data' => '(;GM[1]FF[4]SZ[19]AB[ee];B[aa])', 'sgf_status_id' => Sgf::STATUS_REJECTED],
					['data' => '(;GM[1]FF[4]SZ[19]AB[ff];B[aa])', 'sgf_status_id' => Sgf::STATUS_PROPOSED]],
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);

		$buttons = MistakeTraining::buildQueueButtons((int) $context->tsumegos[0]['set-connections'][0]['id']);

		$this->assertSame($current, $buttons[0]->sgf);
	}

	public function testMistakeTrainingQueueExcludesNotDueAndDeleted(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
			'tsumegos' => [
				[
					'set_order' => 2,
					'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('+5 days'))],
				],
				[
					'set_order' => 3,
					'deleted' => date('Y-m-d H:i:s'),
					'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
				],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$buttons = MistakeTraining::buildQueueButtons($scId);

		$this->assertCount(1, $buttons, 'Not-yet-due and soft-deleted problems should be excluded from the queue');
	}

	public function testMistakeTrainingEdgeLinksPointToQueueLanding(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
			'tsumegos' => [
				[
					'set_order' => 2,
					'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
				],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$buttons = MistakeTraining::buildQueueButtons($scId);
		$links = [];
		$setFunction = function ($name, $value) use (&$links) {
			$links[$name] = $value;
		};
		$buttons->exportCurrentAndPreviousLink($setFunction, null, $scId, null, '');

		$this->assertSame('', $links['previousLink'], 'Back at the start of the training has nowhere to go');
		$this->assertSame('/mistake-training/play/' . $context->tsumegos[1]['set-connections'][0]['id'], $links['nextLink'],
			'Queue navigation must stay on the training route, so the route can re-assert the mode');
	}

	//The buttons of a set come without their previews, which only the shown ones need, and loading
	//them gives the one stored with the current version.
	public function testSetButtonsLoadThePreviewOfTheCurrentVersionWhenAsked(): void
	{
		$current = '(;GM[1]FF[4]SZ[19]AB[cc];B[aa])';
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'sgfs' => [
					['data' => '(;GM[1]FF[4]SZ[19]AB[dd];B[aa])', 'sgf_status_id' => Sgf::STATUS_ACCEPTED],
					['data' => $current, 'sgf_status_id' => Sgf::STATUS_ACCEPTED],
					['data' => '(;GM[1]FF[4]SZ[19]AB[ee];B[aa])', 'sgf_status_id' => Sgf::STATUS_PROPOSED]]],
		]);

		$buttons = new TsumegoButtons(new TsumegoFilters('topics'), null, 0, (int) $context->tsumegos[0]['set-connections'][0]['set_id']);
		$this->assertCount(1, $buttons);
		$this->assertNull($buttons[0]->preview);

		$buttons->loadPreviews();
		$this->assertSame(TsumegoButton::sgfToPreviewJson($current), $buttons[0]->preview);
		$this->assertStringContainsString('"black":"cc"', $buttons[0]->preview);
	}

	//Every saved version keeps the preview of its SGF, which the buttons show without parsing it.
	public function testASavedVersionKeepsThePreviewOfItsSgf(): void
	{
		$context = new ContextPreparator(['tsumego' => ['set_order' => 1]]);
		$sgf = '(;GM[1]FF[4]SZ[19]AB[aa][ba]AW[ab];B[ca])';

		$saved = ClassRegistry::init('Sgf')->uploadSgf($sgf, (int) $context->tsumegos[0]['id'], (int) $context->user['id'], true);

		$this->assertSame(TsumegoButton::sgfToPreviewJson($sgf), $saved['Sgf']['preview']);
		$this->assertSame(['aa', 'ba'], str_split(json_decode($saved['Sgf']['preview'], true)['black'], 2));
	}

	public function testAnEmptySgfHasNoPreview(): void
	{
		$this->assertSame('', TsumegoButton::sgfToPreviewJson(''));
	}

	//The play page shows the dozen buttons around the opened problem, so only they load their previews.
	public function testPlayNavigationLoadsThePreviewsOfTheShownButtonsOnly(): void
	{
		$tsumegos = [];
		for ($i = 1; $i <= 30; $i++)
			$tsumegos[] = ['set_order' => $i];
		$context = new ContextPreparator(['user' => ['name' => 'testuser'], 'tsumegos' => $tsumegos]);
		$opened = $context->tsumegos[14]['set-connections'][0];

		$buttons = new TsumegoButtons(new TsumegoFilters('topics'), (int) $opened['id'], null, (int) $opened['set_id']);
		$shown = null;
		new SetNavigationButtonsInput(function ($name, $value) use (&$shown) {
			$shown = $value;
		})->execute($buttons, ['SetConnection' => $opened]);

		$this->assertCount(30, $buttons);
		$this->assertCount(13, $shown);
		foreach ($shown as $button)
			$this->assertNotNull($button->preview);
		$this->assertCount(13, array_filter((array) $buttons, fn($button) => $button->preview !== null));
	}

	public function testTheEndsOfTheListLeadToThePageAndTheMiddleToTheNeighbour(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'edge'],
			'tsumego' => ['set_order' => 1],
			'tsumegos' => [['set_order' => 2]],
		]);
		$first = $context->tsumegos[0]['set-connections'][0];
		$second = $context->tsumegos[1]['set-connections'][0];
		$buttons = TsumegoButtons::fromRows([
			['tsumego_id' => $context->tsumegos[0]['id'], 'set_connection_id' => $first['id'], 'num' => 1, 'status' => 'S'],
			['tsumego_id' => $context->tsumegos[1]['id'], 'set_connection_id' => $second['id'], 'num' => 2, 'status' => 'S'],
		], (int) $first['id'], 2);
		$links = [];
		$setFunction = function ($name, $value) use (&$links) {
			$links[$name] = $value;
		};

		$buttons->exportCurrentAndPreviousLink($setFunction, new TsumegoFilters('topics'), (int) $first['id'], ['Set' => ['id' => $first['set_id']]], '/page');
		$this->assertSame('/page', $links['previousLink'], 'The first problem has the page behind it');
		$this->assertSame('/' . $second['id'], $links['nextLink']);

		$buttons->exportCurrentAndPreviousLink($setFunction, new TsumegoFilters('topics'), (int) $second['id'], ['Set' => ['id' => $second['set_id']]], '/page');

		$this->assertSame('/' . $first['id'], $links['previousLink'], 'The last problem has its neighbour behind it');
		$this->assertSame('/page', $links['nextLink']);
	}

	public function testMistakeTrainingQueueButtonsStayOnTheTrainingRoute(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day'))],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];

		$buttons = MistakeTraining::buildQueueButtons($scId);
		ob_start();
		$buttons[0]->render(0, false);
		$html = ob_get_clean();

		$this->assertStringContainsString('href="/mistake-training/play/' . $scId . '"', $html,
			'Challenge buttons in the training queue must link back to the training route');
	}

	public function testMistakeTrainingButtonsShowTheRungBadge(): void
	{
		$tsumegos = [];
		foreach (MistakeTraining::ladder() as $rung => $days)
			$tsumegos[] = [
				'set_order' => $rung + 1,
				'status' => [
					'name' => 'S',
					'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')),
					'mistake_training_rung' => $rung,
				],
			];
		$context = new ContextPreparator(['user' => ['name' => 'testuser'], 'tsumegos' => $tsumegos]);

		$buttons = MistakeTraining::buildQueueButtons((int) $context->tsumegos[0]['set-connections'][0]['id']);

		// one to four chevrons, then one to four stars, like military ranks
		$expected = [['chevron', 1], ['chevron', 2], ['chevron', 3], ['chevron', 4], ['star', 1], ['star', 2], ['star', 3], ['star', 4]];
		foreach (MistakeTraining::ladder() as $rung => $days)
		{
			$html = $this->renderButton($buttons[$rung]);
			[$kind, $count] = $expected[$rung];
			$badge = self::badge($html);
			$this->assertStringStartsWith('<div class="problem-nav__rung problem-nav__rung--' . $kind . '" data-rung="' . $rung . '"'
				. ' role="img" aria-label="Rung ' . ($rung + 1) . ' of 8">', $badge,
				"The button of a problem on rung $rung shows its insignia, named for readers");
			$this->assertSame(RungInsignia::render($kind, $count), substr($badge, strpos($badge, '<svg'), -strlen('</div>')),
				"Rung $rung is drawn as $count $kind symbols");
			$this->assertDoesNotMatchRegularExpression('/\d+d</', $badge, 'The badge shows no wait, "1d" reads as one dan');
			$this->assertStringContainsString('Rung ' . ($rung + 1) . ' of 8', strip_tags(substr($html, strpos($html, 'tooltip-box'))),
				'The tooltip still names the rung');
			$this->assertStringNotContainsString('problem-nav__ratio', $html, 'The training has no ratio to show');
			$this->assertStringContainsString('problem-nav__item--N', $html, 'A problem due today is grey, whatever its status');
		}
	}

	public function testMistakeTrainingButtonOfAGraduationShowsItsOwnBadge(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'testuser'],
			'tsumego' => [
				'set_order' => 1,
				'status' => ['name' => 'V', 'mistake_training_due' => date('Y-m-d H:i:s', strtotime('-1 day')), 'mistake_training_rung' => 7],
			],
		]);
		$scId = (int) $context->tsumegos[0]['set-connections'][0]['id'];
		MistakeTraining::recordResult($context->user['id'], (int) $context->tsumegos[0]['id'], PlayOutcome::SolvedClean, null, true);

		$html = $this->renderButton(MistakeTraining::buildQueueButtons($scId)[0]);

		$this->assertSame('<div class="problem-nav__rung problem-nav__rung--graduated" role="img" aria-label="Graduated">'
			. RungInsignia::renderCrown() . '</div>', self::badge($html), 'A graduation of today wears the crown');
		$this->assertStringContainsString('problem-nav__item--S', $html, 'A graduation is solved today, so it is green');
		$this->assertStringContainsString('Graduated today', $html);
	}

	public function testButtonsOutsideTheTrainingHaveNoRungBadge(): void
	{
		$button = new TsumegoButton(1, 2, 3, 'S', 1000.0, '');
		$button->performance = '';

		$html = $this->renderButton($button);

		$this->assertStringNotContainsString('problem-nav__rung', $html, 'Normal play renders the button as it always did');
		$this->assertStringContainsString('<div class="problem-nav__ratio">-</div>', $html);
		$this->assertStringContainsString('<div class="problem-nav__time">-</div>', $html);
		$this->assertStringContainsString(TsumegoStatus::label('S'), $html, 'Outside the training the tooltip names the status');
	}

	private function renderButton(TsumegoButton $button): string
	{
		ob_start();
		$button->render();
		return ob_get_clean();
	}

	//The rung badge of a rendered button.
	private static function badge(string $html): string
	{
		preg_match('~<div class="problem-nav__rung.*?</svg></div>~', $html, $match);
		return $match[0] ?? '';
	}
}
