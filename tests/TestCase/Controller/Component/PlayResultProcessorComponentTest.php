<?php

require_once(__DIR__ . '/../TsumegoControllerTestCase.php');
App::uses('Constants', 'Utility');

class TsumegoVisitContext {
	public function __construct(?array $user = null, ?array $tsumego = null) {
		$this->user = $user;
		if (!$this->user) {
			$this->user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'kovarex']]);
		}

		$this->tsumego = $tsumego;
		if (!$this->tsumego) {
			$this->tsumego = ClassRegistry::init('Tsumego')->find('first');
		}
	}

	public function setStatus(string $originalStatus): TsumegoVisitContext {
		$this->originalStatus = $originalStatus;
		return $this;
	}

	public function setAttempt($originalTsumegoAttempt): TsumegoVisitContext {
		$this->originalTsumegoAttempt = $originalTsumegoAttempt;
		return $this;
	}

	public function setMode($mode): TsumegoVisitContext {
		$this->mode = $mode;
		return $this;
	}

	public function prepareTsumegoAttempt(): void {
		ClassRegistry::init('TsumegoAttempt')->deleteAll(['user_id' => $this->user['User']['id'],'tsumego_id' => $this->tsumego['Tsumego']['id']]);
		if (!$this->originalTsumegoAttempt) {
			return;
		}

		$tsumegoAttempt = [];
		$tsumegoAttempt['TsumegoAttempt']['user_id'] = $this->user['User']['id'];
		$tsumegoAttempt['TsumegoAttempt']['elo'] = $this->user['User']['elo_rating_mode'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_id'] = $this->tsumego['Tsumego']['id'];
		$tsumegoAttempt['TsumegoAttempt']['gain'] = 0;
		$tsumegoAttempt['TsumegoAttempt']['seconds'] = 0;
		$tsumegoAttempt['TsumegoAttempt']['solved'] = $this->originalTsumegoAttempt['solved'] ?: false;
		$tsumegoAttempt['TsumegoAttempt']['mode'] = $this->user['User']['mode'];
		$tsumegoAttempt['TsumegoAttempt']['tsumego_elo'] = $this->tsumego['Tsumego']['elo_rating_mode'];
		$tsumegoAttempt['TsumegoAttempt']['misplays'] = $this->originalTsumegoAttempt['misplays'] ?: 0;
		ClassRegistry::init('TsumegoAttempt')->save($tsumegoAttempt);
	}

	public function prepareUserMode(): void {
		if ($this->mode && $this->user['User']['mode'] != $this->mode) {
			$this->user['User']['mode'] = $this->mode;
			ClassRegistry::init('User')->save($this->user);
		}
	}

	public function prepareTsumegoStatus(): void {
		$statusCondition = [
			'conditions' => [
				'user_id' => $this->user['User']['id'],
				['tsumego_id' => $this->tsumego['Tsumego']['id']],
			],
		];
		$originalTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition);
		if ($originalTsumegoStatus) {
			if (!$this->originalStatus) {
				ClassRegistry::init('TsumegoStatus')->delete($originalTsumegoStatus['TsumegoStatus']['id']);
			} else {
				$originalTsumegoStatus['TsumegoStatus']['status'] = $this->originalStatus;
				ClassRegistry::init('TsumegoStatus')->save($originalTsumegoStatus);
			}
		} elseif ($this->originalStatus) {
			$originalTsumegoStatus = [];
			$originalTsumegoStatus['TsumegoStatus']['user_id'] = $this->user['User']['id'];
			$originalTsumegoStatus['TsumegoStatus']['tsumego_id'] = $this->tsumego['Tsumego']['id'];
			ClassRegistry::init('TsumegoStatus')->save($originalTsumegoStatus);
		}
	}

	public function checkNewTsumegoStatusCoreValues(CakeTestCase $testCase): void {
		$statusCondition = [
			'conditions' => [
				'user_id' => $this->user['User']['id'],
				['tsumego_id' => $this->tsumego['Tsumego']['id']],
			],
		];
		$this->resultTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition);
		$testCase->assertNotEmpty($this->resultTsumegoStatus);
		$testCase->assertSame($this->resultTsumegoStatus['TsumegoStatus']['user_id'], $this->user['User']['id']);
		$testCase->assertSame($this->resultTsumegoStatus['TsumegoStatus']['tsumego_id'], $this->tsumego['Tsumego']['id']);
	}

	public $user;
	public $tsumego;
	public $mode;
	public $originalStatus; // null=delete relevant statatus, oterwise specifies string code of status to exist
	public $originalTsumegoAttempt; // null=remove all relevant tsumego attempts
	public $resultTsumegoStatus;
}

class PlayResultProcessorComponentTest extends TsumegoControllerTestCase {
	private function performVisit(TsumegoVisitContext &$context): void {
		$context->prepareTsumegoAttempt();
		$context->prepareTsumegoStatus();
		$context->prepareUserMode();

		CakeSession::write('loggedInUserID', $context->user['User']['id']);
		$_COOKIE['previousTsumegoID'] = $context->tsumego['Tsumego']['id'];

		$this->testAction('sets/view/');
		$context->checkNewTsumegoStatusCoreValues($this);
	}

	private function performSolve(TsumegoVisitContext &$context): void {
		$_COOKIE['mode'] = '1';
		$_COOKIE['score'] = Util::wierdEncrypt('-1');
		$this->performVisit($context);
		$this->assertEmpty($_COOKIE['score']); // should be processed and cleared
	}

	private function performMisplay(TsumegoVisitContext &$context): void {
		$_COOKIE['misplay'] = '1';
		$this->performVisit($context);
		$this->assertEmpty($_COOKIE['misplay']); // should be processed and cleared
	}

	public function testVisitFromEmpty(): void {
		$context = new TsumegoVisitContext();
		$this->performVisit($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'V');
	}

	public function testSolveFromEmpty(): void {
		$context = new TsumegoVisitContext();
		$this->performSolve($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'S');
	}

	public function testFailFromEmpty(): void {
		$context = new TsumegoVisitContext();
		$this->performMisplay($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'F');
	}

	public function testVisitFromSolved(): void {
		$context = (new TsumegoVisitContext())->setStatus('S');
		$this->performVisit($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'S');
	}

	public function testHalfXpStatusToDoubleSolved(): void {
		$context = (new TsumegoVisitContext())->setStatus('W');
		$this->performSolve($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'C');
	}

	public function testSolveFromFailed(): void {
		$context = (new TsumegoVisitContext())->setStatus('F');
		$this->performSolve($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'S');
	}

	public function testFailFromVisited(): void {
		$context = (new TsumegoVisitContext())->setStatus('V');
		$this->performMisplay($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'F');
	}

	public function testFailFromFailed(): void {
		$context = (new TsumegoVisitContext())->setStatus('F');
		$this->performMisplay($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'X');
	}

	public function testFailFromSolved(): void {
		$context = (new TsumegoVisitContext())->setStatus('S');
		$this->performMisplay($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'S'); // shouldn't be affected
	}

	public function testFailFromDoubleSolved(): void {
		$context = (new TsumegoVisitContext())->setStatus('C');
		$this->performMisplay($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'C'); // shouldn't be affected
	}

	public function testSolvingAddsRating(): void {
		$context = (new TsumegoVisitContext())->setMode(Constants::$RATING_MODE);
		$originalRating = $context->user['User']['elo_rating_mode'];
		$this->performSolve($context);
		$newUser = ClassRegistry::init('User')->findById($context->user['User']['id']);
		$this->assertLessThan($newUser['User']['elo_rating_mode'], $originalRating);
	}

	public function testFailingDropsRating(): void {
		$context = (new TsumegoVisitContext())->setMode(Constants::$RATING_MODE);
		$originalRating = $context->user['User']['elo_rating_mode'];
		$this->performMisplay($context);
		$newUser = ClassRegistry::init('User')->findById($context->user['User']['id']);
		$this->assertLessThan($originalRating, $newUser['User']['elo_rating_mode']);
	}

	public function testSolvingAddsNewTsumegoAttempt(): void {
		$context = new TsumegoVisitContext();

		$this->performSolve($context);
		$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['Tsumego']['id'], 'user_id' => $context->user['User']['id']]]);
		$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 0);
	}

	public function testSolvingUpdatesExistingNotSolvedTsumegoAttempt(): void {
		$context = (new TsumegoVisitContext())->setAttempt(['solved' => false, 'misplays' => 66]);

		$this->performSolve($context);
		$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['Tsumego']['id'], 'user_id' => $context->user['User']['id']]]);
		$this->assertSame(count($newTsumegoAttempt), 1); // the existing one should be updated
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 66);
	}

	public function testSolvingDoesntUpdateExistingSolvedTsumegoAttempt(): void {
		$context = (new TsumegoVisitContext())->setAttempt(['solved' => true, 'misplays' => 66]);

		$this->performSolve($context);
		$tsumegoAttempts = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['Tsumego']['id'], 'user_id' => $context->user['User']['id']]]);
		$this->assertSame(count($tsumegoAttempts), 2); // the solved one wasn't updated
		$this->assertSame($tsumegoAttempts[0]['TsumegoAttempt']['solved'], true);
		$this->assertSame($tsumegoAttempts[0]['TsumegoAttempt']['misplays'], 66);
		$this->assertSame($tsumegoAttempts[1]['TsumegoAttempt']['solved'], true);
		$this->assertSame($tsumegoAttempts[1]['TsumegoAttempt']['misplays'], 0);
	}

	public function testFailingAddsNewTsumegoAttempt(): void {
		$context = new TsumegoVisitContext();

		$this->performMisplay($context);
		$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['Tsumego']['id'], 'user_id' => $context->user['User']['id']]]);
		$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], false);
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 1);
	}

	public function testFailingUpdatesExistingNotSolvedTsumegoAttempt(): void {
		$context = (new TsumegoVisitContext())->setAttempt(['solved' => false, 'misplays' => 66]);

		$this->performMisplay($context);
		$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['Tsumego']['id'], 'user_id' => $context->user['User']['id']]]);
		$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], false);
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 67);
	}

	public function testFailingDoesntUpdateExistingSolvedTsumegoAttempt(): void {
		$context = (new TsumegoVisitContext())->setAttempt(['solved' => true, 'misplays' => 66]);

		$this->performMisplay($context);
		$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['Tsumego']['id'], 'user_id' => $context->user['User']['id']]]);
		$this->assertSame(count($newTsumegoAttempt), 2); // exactly one should be created
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 66);

		$this->assertSame($newTsumegoAttempt[1]['TsumegoAttempt']['solved'], false);
		$this->assertSame($newTsumegoAttempt[1]['TsumegoAttempt']['misplays'], 1);
	}
}
