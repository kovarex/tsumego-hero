<?php

require_once(__DIR__ . '/../TestCaseWithAuth.php');
App::uses('Constants', 'Utility');
require_once(__DIR__ . '/../../../ContextPreparator.php');

class PlayResultProcessorComponentTest extends TestCaseWithAuth {
	private function performVisit(ContextPreparator &$context): void {
		$context->prepareTsumegoAttempt();
		$context->prepareTsumegoStatus();
		$context->prepareUserMode();

		CakeSession::write('loggedInUserID', $context->user['User']['id']);
		$_COOKIE['previousTsumegoID'] = $context->tsumego['Tsumego']['id'];

		$this->testAction('sets/view/');
		$context->checkNewTsumegoStatusCoreValues($this);
	}

	private function performSolve(ContextPreparator &$context): void {
		$_COOKIE['mode'] = '1';
		$_COOKIE['score'] = Util::wierdEncrypt('-1');
		$this->performVisit($context);
		$this->assertEmpty($_COOKIE['score']); // should be processed and cleared
	}

	private function performMisplay(ContextPreparator &$context): void {
		$_COOKIE['misplay'] = '1';
		$this->performVisit($context);
		$this->assertEmpty($_COOKIE['misplay']); // should be processed and cleared
	}

	public function testVisitFromEmpty(): void {
		$context = new ContextPreparator();
		$this->performVisit($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'V');
	}

	public function testSolveFromEmpty(): void {
		$context = new ContextPreparator();
		$this->performSolve($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'S');
	}

	public function testFailFromEmpty(): void {
		$context = new ContextPreparator();
		$this->performMisplay($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'F');
	}

	public function testVisitFromSolved(): void {
		$context = (new ContextPreparator())->setStatus('S');
		$this->performVisit($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'S');
	}

	public function testHalfXpStatusToDoubleSolved(): void {
		$context = (new ContextPreparator())->setStatus('W');
		$this->performSolve($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'C');
	}

	public function testSolveFromFailed(): void {
		$context = (new ContextPreparator())->setStatus('F');
		$this->performSolve($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'S');
	}

	public function testFailFromVisited(): void {
		$context = (new ContextPreparator())->setStatus('V');
		$this->performMisplay($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'F');
	}

	public function testFailFromFailed(): void {
		$context = (new ContextPreparator())->setStatus('F');
		$this->performMisplay($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'X');
	}

	public function testFailFromSolved(): void {
		$context = (new ContextPreparator())->setStatus('S');
		$this->performMisplay($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'S'); // shouldn't be affected
	}

	public function testFailFromDoubleSolved(): void {
		$context = (new ContextPreparator())->setStatus('C');
		$this->performMisplay($context);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['status'], 'C'); // shouldn't be affected
	}

	public function testSolvingAddsRating(): void {
		$context = (new ContextPreparator())->setMode(Constants::$RATING_MODE);
		$originalRating = $context->user['User']['elo_rating_mode'];
		$this->performSolve($context);
		$newUser = ClassRegistry::init('User')->findById($context->user['User']['id']);
		$this->assertLessThan($newUser['User']['elo_rating_mode'], $originalRating);
	}

	public function testFailingDropsRating(): void {
		$context = (new ContextPreparator())->setMode(Constants::$RATING_MODE);
		$originalRating = $context->user['User']['elo_rating_mode'];
		$this->performMisplay($context);
		$newUser = ClassRegistry::init('User')->findById($context->user['User']['id']);
		$this->assertLessThan($originalRating, $newUser['User']['elo_rating_mode']);
	}

	public function testSolvingAddsNewTsumegoAttempt(): void {
		$context = new ContextPreparator();

		$this->performSolve($context);
		$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['Tsumego']['id'], 'user_id' => $context->user['User']['id']]]);
		$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 0);
	}

	public function testSolvingUpdatesExistingNotSolvedTsumegoAttempt(): void {
		$context = (new ContextPreparator())->setAttempt(['solved' => false, 'misplays' => 66]);

		$this->performSolve($context);
		$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['Tsumego']['id'], 'user_id' => $context->user['User']['id']]]);
		$this->assertSame(count($newTsumegoAttempt), 1); // the existing one should be updated
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 66);
	}

	public function testSolvingDoesntUpdateExistingSolvedTsumegoAttempt(): void {
		$context = (new ContextPreparator())->setAttempt(['solved' => true, 'misplays' => 66]);

		$this->performSolve($context);
		$tsumegoAttempts = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['Tsumego']['id'], 'user_id' => $context->user['User']['id']]]);
		$this->assertSame(count($tsumegoAttempts), 2); // the solved one wasn't updated
		$this->assertSame($tsumegoAttempts[0]['TsumegoAttempt']['solved'], true);
		$this->assertSame($tsumegoAttempts[0]['TsumegoAttempt']['misplays'], 66);
		$this->assertSame($tsumegoAttempts[1]['TsumegoAttempt']['solved'], true);
		$this->assertSame($tsumegoAttempts[1]['TsumegoAttempt']['misplays'], 0);
	}

	public function testFailingAddsNewTsumegoAttempt(): void {
		$context = new ContextPreparator();

		$this->performMisplay($context);
		$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['Tsumego']['id'], 'user_id' => $context->user['User']['id']]]);
		$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], false);
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 1);
	}

	public function testFailingUpdatesExistingNotSolvedTsumegoAttempt(): void {
		$context = (new ContextPreparator())->setAttempt(['solved' => false, 'misplays' => 66]);

		$this->performMisplay($context);
		$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['Tsumego']['id'], 'user_id' => $context->user['User']['id']]]);
		$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], false);
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 67);
	}

	public function testFailingDoesntUpdateExistingSolvedTsumegoAttempt(): void {
		$context = (new ContextPreparator())->setAttempt(['solved' => true, 'misplays' => 66]);

		$this->performMisplay($context);
		$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['Tsumego']['id'], 'user_id' => $context->user['User']['id']]]);
		$this->assertSame(count($newTsumegoAttempt), 2); // exactly one should be created
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
		$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 66);

		$this->assertSame($newTsumegoAttempt[1]['TsumegoAttempt']['solved'], false);
		$this->assertSame($newTsumegoAttempt[1]['TsumegoAttempt']['misplays'], 1);
	}
}
