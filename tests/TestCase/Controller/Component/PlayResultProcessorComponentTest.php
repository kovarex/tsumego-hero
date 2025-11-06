<?php

require_once(__DIR__ . '/../TestCaseWithAuth.php');
App::uses('Constants', 'Utility');
require_once(__DIR__ . '/../../../ContextPreparator.php');

class PlayResultProcessorComponentTest extends TestCaseWithAuth {
	private $PAGES = ['sets', 'tsumego'];

	private static function getUrlFromPage(string $page, $context): string {
		if ($page === 'sets') {
			return '/sets/view';
		}
		if ($page === 'tsumego') {
			return '/' . $context->tsumego['set-connections'][0]['id'];
		}
		throw new Exception("Unknown page: " . $page);
	}

	private function performVisit(ContextPreparator &$context, $page): void {
		CakeSession::write('loggedInUserID', $context->user['id']);
		$_COOKIE['previousTsumegoID'] = $context->tsumego['id'];
		$this->testAction(self::getUrlFromPage($page, $context));
		$context->checkNewTsumegoStatusCoreValues($this);
	}

	private function performSolve(ContextPreparator &$context, $page): void {
		$_COOKIE['mode'] = '1';
		$_COOKIE['scoreCheck'] = Util::wierdEncrypt($context->tsumego['id'] . '-' . time());
		$this->performVisit($context, $page);
		$this->assertEmpty($_COOKIE['score']); // should be processed and cleared
	}

	private function performMisplay(ContextPreparator &$context, $page): void {
		$_COOKIE['misplay'] = '1';
		$this->performVisit($context, $page);
		$this->assertEmpty($_COOKIE['misplay']); // should be processed and cleared
	}

	public function testVisitFromEmpty(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performVisit($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'V');
		}
	}

	public function testSolveFromEmpty(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performSolve($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'S');
		}
	}

	public function testFailFromEmpty(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performMisplay($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'F');
		}
	}

	public function testVisitFromSolved(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator(['tsumego' => ['status' => 'S', 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performVisit($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'S');
		}
	}

	public function testHalfXpStatusToDoubleSolved(): void {
		foreach ($this->PAGES as $page) {
			$context = (new ContextPreparator(['tsumego' => ['status' => 'W', 'sets' => [['name' => 'set 1', 'num' => 1]]]]));
			$this->performSolve($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'C');
		}
	}

	public function testSolveFromFailed(): void {
		foreach ($this->PAGES as $page) {
			$context = (new ContextPreparator(['tsumego' => ['status' => 'F', 'sets' => [['name' => 'set 1', 'num' => 1]]]]));
			$this->performSolve($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'S');
		}
	}

	public function testFailFromVisited(): void {
		foreach ($this->PAGES as $page) {
			$context = (new ContextPreparator(['tsumego' => ['status' => 'V', 'sets' => [['name' => 'set 1', 'num' => 1]]]]));
			$this->performMisplay($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'F');
		}
	}

	public function testFailFromFailed(): void {
		foreach ($this->PAGES as $page) {
			$context = (new ContextPreparator(['tsumego' => ['status' => 'F', 'sets' => [['name' => 'set 1', 'num' => 1]]]]));
			$this->performMisplay($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'X');
		}
	}

	public function testFailFromSolved(): void {
		foreach ($this->PAGES as $page) {
			$context = (new ContextPreparator(['tsumego' => ['status' => 'S', 'sets' => [['name' => 'set 1', 'num' => 1]]]]));
			$this->performMisplay($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'S'); // shouldn't be affected
		}
	}

	public function testFailFromDoubleSolved(): void {
		foreach ($this->PAGES as $page) {
			$context = (new ContextPreparator(['tsumego' => ['status' => 'C', 'sets' => [['name' => 'set 1', 'num' => 1]]]]));
			$this->performMisplay($context, $page);
			$this->assertSame($context->resultTsumegoStatus['status'], 'C'); // shouldn't be affected
		}
	}

	public function testSolvingAddsRating(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator([
				'user' => [
					'rating' => 1000,
					'mode' => Constants::$RATING_MODE],
				'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$originalRating = $context->user['rating'];
			$this->performSolve($context, $page);
			$newUser = ClassRegistry::init('User')->findById($context->user['id'])['User'];
			$this->assertLessThan($newUser['rating'], $originalRating);
			$this->assertWithinMargin($originalRating, $newUser['rating'], 100); // shouldn't move more than 100 points
		}
	}

	public function testFailingDropsRating(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator([
				'user' => [
					'rating' => 1000,
					'mode' => Constants::$RATING_MODE],
				'tsumego' => ['rating' => 1000, 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$originalRating = $context->user['rating'];
			$this->performMisplay($context, $page);
			$newUser = ClassRegistry::init('User')->findById($context->user['id'])['User'];
			$this->assertLessThan($originalRating, $newUser['rating']);
			$this->assertWithinMargin($originalRating, $newUser['rating'], 100); // shouldn't move more than 100 points
		}
	}

	public function testSolvingAddsNewTsumegoAttempt(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]]]);

			$this->performSolve($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 0);
		}
	}

	public function testSolvingUpdatesExistingNotSolvedTsumegoAttempt(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator(['tsumego' => [
				'attempt' => ['solved' => false, 'misplays' => 66],
				'sets' => [['name' => 'set 1', 'num' => 1]]]]);

			$this->performSolve($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 1); // the existing one should be updated
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 66);
		}
	}

	public function testSolvingDoesntUpdateExistingSolvedTsumegoAttempt(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator(['tsumego' => [
				'attempt' => ['solved' => true, 'misplays' => 66],
				'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performSolve($context, $page);
			$tsumegoAttempts = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($tsumegoAttempts), 2); // the solved one wasn't updated
			$this->assertSame($tsumegoAttempts[0]['TsumegoAttempt']['solved'], true);
			$this->assertSame($tsumegoAttempts[0]['TsumegoAttempt']['misplays'], 66);
			$this->assertSame($tsumegoAttempts[1]['TsumegoAttempt']['solved'], true);
			$this->assertSame($tsumegoAttempts[1]['TsumegoAttempt']['misplays'], 0);
		}
	}

	public function testFailingAddsNewTsumegoAttempt(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performMisplay($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], false);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 1);
		}
	}

	public function testFailingUpdatesExistingNotSolvedTsumegoAttempt(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator(['tsumego' => [
				'attempt' => ['solved' => false, 'misplays' => 66],
				'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performMisplay($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 1); // exactly one should be created
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], false);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 67);
		}
	}

	public function testFailingDoesntUpdateExistingSolvedTsumegoAttempt(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator(['tsumego' => ['attempt' => ['solved' => true, 'misplays' => 66], 'sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$this->performMisplay($context, $page);
			$newTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('all', ['conditions' => ['tsumego_id' => $context->tsumego['id'], 'user_id' => $context->user['id']]]);
			$this->assertSame(count($newTsumegoAttempt), 2); // exactly one should be created
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['solved'], true);
			$this->assertSame($newTsumegoAttempt[0]['TsumegoAttempt']['misplays'], 66);
			$this->assertSame($newTsumegoAttempt[1]['TsumegoAttempt']['solved'], false);
			$this->assertSame($newTsumegoAttempt[1]['TsumegoAttempt']['misplays'], 1);
		}
	}

	public function testFailAddsDamage(): void {
		foreach ($this->PAGES as $page) {
			$context = new ContextPreparator(['tsumego' => ['sets' => [['name' => 'set 1', 'num' => 1]]]]);
			$originalDamage = intval($context->user['damage']);
			$this->performMisplay($context, $page);
			Auth::init();
			$this->assertSame($originalDamage + 1, Auth::getUser()['damage']);
		}
	}
}
