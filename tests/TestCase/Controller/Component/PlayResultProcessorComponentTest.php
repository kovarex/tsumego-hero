<?php

class PlayResultProcessorComponentTest extends ControllerTestCase {
	private function performVisit(TsumegoVisitContext &$context): void {
		$statusCondition = [
			'conditions' => [
				'user_id' => $context->user['User']['id'],
				['tsumego_id' => $context->tsumego['Tsumego']['id']],
			],
		];
		$originalTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition);
		if ($originalTsumegoStatus) {
			if (!$context->originalStatus) {
				ClassRegistry::init('TsumegoStatus')->delete($originalTsumegoStatus['TsumegoStatus']['id']);
			} else {
				$originalTsumegoStatus['TsumegoStatus']['status'] = $context->originalStatus;
				ClassRegistry::init('TsumegoStatus')->save($originalTsumegoStatus);
			}
		} elseif ($context->originalStatus) {
			$originalTsumegoStatus = [];
			$originalTsumegoStatus['TsumegoStatus']['user_id'] = $context->user['User']['id'];
			$originalTsumegoStatus['TsumegoStatus']['tsumego_id'] = $context->tsumego['Tsumego']['id'];
			ClassRegistry::init('TsumegoStatus')->save($originalTsumegoStatus);
		}

		CakeSession::write('loggedInUserID', $context->user['User']['id']);
		$_COOKIE['previousTsumegoID'] = $context->tsumego['Tsumego']['id'];

		$this->testAction('sets/view/');

		$context->resultTsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', $statusCondition);
		$this->assertTrue(!empty($context->resultTsumegoStatus));
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['user_id'], $context->user['User']['id']);
		$this->assertSame($context->resultTsumegoStatus['TsumegoStatus']['tsumego_id'], $context->tsumego['Tsumego']['id']);
	}

	private function performSolve(TsumegoVisitContext &$context): void {
		$_COOKIE['mode'] = '1';
		$_COOKIE['score'] = '1';
		$this->performVisit($context);
		$this->assertTrue(empty($_COOKIE['score'])); // should be processed and cleared
	}

	private function performMisplay(TsumegoVisitContext &$context): void {
		$_COOKIE['misplay'] = '1';
		$this->performVisit($context);
		$this->assertTrue(empty($_COOKIE['misplay'])); // should be processed and cleared
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
		$context = new TsumegoVisitContext();
		$originalRating = $context->user['User']['elo_rating_mode'];
		$this->performSolve($context);
		$newUser = ClassRegistry::init('User')->findById($context->user['User']['id']);
		$this->assertLessThan($newUser['User']['elo_rating_mode'], $originalRating);
	}

	public function testFailingDropsRating(): void {
		$context = new TsumegoVisitContext();
		$originalRating = $context->user['User']['elo_rating_mode'];
		$this->performMisplay($context);
		$newUser = ClassRegistry::init('User')->findById($context->user['User']['id']);
		$this->assertLessThan($originalRating, $newUser['User']['elo_rating_mode']);
	}

}

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

	function setStatus(string $originalStatus): TsumegoVisitContext
	{
      $this->originalStatus = $originalStatus;
	  return $this;
	}

	public $user;
	public $tsumego;
	public $originalStatus;
	public $originalTsumegoAttempt;
	public $resultTsumegoStatus;
}
