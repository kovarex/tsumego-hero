<?php

use App\Attribute\HttpPost;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\Feature;
use App\Utility\FeatureKey;
use App\Utility\MistakeTraining;
use App\Utility\PlayOutcome;

class MistakeTrainingController extends AppController
{
	/**
	 * Show a training problem: the one the link names, or the next due one, which is
	 * the first of today's list not tried yet, and once only misses are left, the
	 * first of those.
	 *
	 * This route owns the training mode. Every queue link comes back here, so a
	 * plain problem URL always means normal play, just like it does for the other
	 * modes.
	 */
	public function play(?string $setConnectionID = null): mixed
	{
		// Players outside the rollout get the same 404 a guest would, and never a
		// login redirect: the route does not exist for them.
		if (!Feature::enabled(FeatureKey::MistakeTraining))
			throw new NotFoundException('Not found');

		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		Auth::saveUserField('mode', Constants::$MISTAKE_TRAINING_MODE);

		if ($setConnectionID !== null)
			return $this->playSetConnection((int) $setConnectionID);

		$userId = (int) Auth::getUserID();
		while (true)
		{
			$next = MistakeTraining::nextDue($userId);
			if (!$next)
			{
				$this->set('_page', 'mistake-training');
				$this->set('_title', 'Tsumego Hero - Mistake Training');
				$upcoming = MistakeTraining::upcomingByDay($userId);
				$totalInTraining = MistakeTraining::totalInTraining($userId);
				$this->set('totalInTraining', $totalInTraining);
				$this->set('everTrained', MistakeTraining::hasEverTrained($userId));
				// Back to the level mode on the problem the player was on, like the homepage
				$this->set('continueLink', '/tsumegos/play/' . (int) ($_COOKIE['lastVisit'] ?? Constants::$DEFAULT_TSUMEGO_ID) . '?mode=' . Constants::$LEVEL_MODE);
				// Offered on every visit that finds nothing due, so a player can keep pulling
				// old mistakes in as the training empties, and never more than a day at once.
				$this->set('importCandidates', MistakeTraining::importCandidates($userId));
				$this->set('importLimit', MistakeTraining::IMPORT_LIMIT);
				$this->set('showDebugLink', Auth::isAdmin() && Configure::read('debug'));
				$this->set('upcomingDays', $upcoming['days']);
				$this->set('furtherDays', $upcoming['furtherDays']);
				$this->set('lastUpcomingDay', $upcoming['lastDay']);
				// Nothing is due, so whatever today's training still lists was solved today.
				$todaysTraining = MistakeTraining::todaysTraining($userId);
				$this->set('solvedToday', count($todaysTraining));
				$this->set('todaysTrainingLink', $todaysTraining
					? MistakeTraining::$PLAY_LINK_PREFIX . $todaysTraining[0]['set_connection_id']
					: null);
				$this->render('/Tsumegos/mistake_training');
				return null;
			}

			$tsumegoId = (int) $next['tsumego_id'];
			$tsumego = ClassRegistry::init('Tsumego')->find('first', [
				'conditions' => ['id' => $tsumegoId, 'deleted IS NULL'],
			]);
			$setConnection = ClassRegistry::init('SetConnection')->findDisplaySetConnection($tsumegoId);
			if (!$tsumego || !$setConnection)
			{
				MistakeTraining::removeFromPool($userId, $tsumegoId);
				continue;
			}

			return $this->playSetConnection((int) $setConnection['SetConnection']['id']);
		}
	}

	/**
	 * Bring the player's past mistakes into the training. Offered on the queue page
	 * while the training is empty, because a problem solved before the training
	 * existed never entered it by itself.
	 */
	#[HttpPost]
	public function import(): mixed
	{
		if (!Feature::enabled(FeatureKey::MistakeTraining))
			throw new NotFoundException('Not found');

		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		MistakeTraining::importPastMistakes((int) Auth::getUserID());

		return $this->redirect(MistakeTraining::$QUEUE_LINK);
	}

	/**
	 * The admin toolbox: look at the pool, move it in time, add problems, import, empty
	 * it, and switch the flag itself. It exists only in debug mode and only for
	 * admins, so production never serves it.
	 *
	 * The guard deliberately ignores the flag: switching the training off must not
	 * take away the page that switches it back on.
	 */
	public function debug(): mixed
	{
		$this->assertToolboxAllowed();

		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$userId = (int) Auth::getUserID();
		$command = (string) ($this->request->data['command'] ?? '');
		if ($this->request->is('post') && $command !== '')
		{
			$problemInput = (string) ($this->request->data['problem'] ?? '');
			// The rows of the pool post the set connection id, the forms above post the
			// address the user pasted.
			$setConnectionID = (int) ($this->request->data['set_connection_id'] ?? 0)
				?: self::setConnectionIDFromInput($problemInput);
			$tsumegoId = (int) ($this->request->data['tsumego_id'] ?? 0);
			if (!$tsumegoId && $setConnectionID)
			{
				$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
				$tsumegoId = $setConnection ? (int) $setConnection['SetConnection']['tsumego_id'] : 0;
			}

			$notice = '';
			switch ($command)
			{
				case 'shift':
					$days = (int) ($this->request->data['days'] ?? 1);
					$moved = MistakeTraining::shiftReviews($userId, $days);
					$notice = 'Moved ' . $moved . ' review' . ($moved == 1 ? '' : 's') . ' by ' . $days . ' day' . (abs($days) == 1 ? '' : 's') . '.';
					break;
				case 'rung':
					$rung = (int) ($this->request->data['rung'] ?? 0);
					if ($tsumegoId)
					{
						MistakeTraining::moveToRung($userId, $tsumegoId, $rung);
						$notice = 'Problem ' . $tsumegoId . ' sits on rung ' . $rung . ' and is due now.';
					}
					else
						$notice = self::noProblemNotice($problemInput);
					break;
				case 'add':
					if ($tsumegoId)
					{
						MistakeTraining::addProblem($userId, $tsumegoId);
						$notice = 'Problem ' . $tsumegoId . ' is in the training, due now.';
					}
					else
						$notice = self::noProblemNotice($problemInput);
					break;
				case 'solve':
				case 'fail':
					if ($tsumegoId)
					{
						MistakeTraining::recordResult($userId, $tsumegoId,
							$command === 'solve' ? PlayOutcome::SolvedClean : PlayOutcome::Failed, null);
						$notice = self::poolNotice($userId, $tsumegoId, 'Problem ' . $tsumegoId);
					}
					else
						$notice = self::noProblemNotice($problemInput);
					break;
				case 'graduate':
					if ($tsumegoId)
					{
						MistakeTraining::graduateNow($userId, $tsumegoId);
						$notice = 'Problem ' . $tsumegoId . ' graduated and left the training.';
					}
					else
						$notice = self::noProblemNotice($problemInput);
					break;
				case 'import':
					$imported = MistakeTraining::importPastMistakes($userId, (int) ($this->request->data['limit'] ?? 20));
					$notice = $imported
						? 'Imported ' . $imported . ' past mistake' . ($imported == 1 ? '' : 's') . '.'
						: 'Nothing left to import.';
					break;
				case 'reset':
					$removed = MistakeTraining::clearPool($userId);
					$notice = 'Removed ' . $removed . ' problem' . ($removed == 1 ? '' : 's') . ' from the training.';
					break;
				case 'flag':
					$state = (string) ($this->request->data['state'] ?? '');
					if (in_array($state, [Feature::OFF, Feature::ADMINS, Feature::EVERYONE], true))
					{
						Feature::set(FeatureKey::MistakeTraining, $state);
						$notice = 'The training is now ' . $state . '.';
					}
					else
						$notice = 'Unknown state, nothing was changed.';
					break;
			}

			return $this->redirect(MistakeTraining::$DEBUG_LINK . '?notice=' . urlencode($notice));
		}

		$this->set('notice', (string) ($this->request->query['notice'] ?? ''));
		$this->set('_page', 'mistake-training');
		$this->set('_title', 'Tsumego Hero - Mistake Training Debug');
		$this->set('poolRows', MistakeTraining::poolRows($userId));
		$this->set('totalInTraining', MistakeTraining::totalInTraining($userId));
		$this->set('importCandidates', MistakeTraining::importCandidates($userId));
		$this->set('flagState', Feature::state(FeatureKey::MistakeTraining));
		$this->set('ladder', MistakeTraining::ladder());
		$this->render('/Tsumegos/mistake_training_debug');
		return null;
	}

	/**
	 * Open one of the pool rows in the training from the number in the table: the
	 * problem is scheduled for now, so a review weeks away can be played today.
	 */
	public function debugPlay(int $setConnectionID): mixed
	{
		$this->assertToolboxAllowed();

		$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
		$tsumegoId = $setConnection ? (int) $setConnection['SetConnection']['tsumego_id'] : 0;
		if ($tsumegoId)
			MistakeTraining::makeDueNow((int) Auth::getUserID(), $tsumegoId);

		return $this->redirect(MistakeTraining::$PLAY_LINK_PREFIX . $setConnectionID);
	}

	/**
	 * The toolbox is for admins, and only with debug mode on, so production never
	 * serves it. It ignores the flag on purpose: switching the training off must not
	 * take away the page that switches it back on.
	 */
	private function assertToolboxAllowed(): void
	{
		if (!Auth::isAdmin() || !Configure::read('debug'))
			throw new NotFoundException('Not found');
	}

	/**
	 * The problem a person meant: the set connection id at the end of the path, so a
	 * pasted address (`https://tsumego.com/29358?sid=5`), a path or a bare number all
	 * work. The last number of the path is the problem, which keeps a scheme, a port
	 * or a query from being mistaken for it.
	 */
	private static function setConnectionIDFromInput(string $input): int
	{
		$path = (string) (parse_url($input, PHP_URL_PATH) ?: $input);
		if (!preg_match_all('/(\d+)/', $path, $matches))
			return 0;
		return (int) end($matches[1]);
	}

	private static function noProblemNotice(string $input): string
	{
		return 'No problem for "' . $input . '". Open the problem and copy its address.';
	}

	/**
	 * What a simulated result did, read back from the pool row.
	 */
	private static function poolNotice(int $userId, int $tsumegoId, string $prefix): string
	{
		$row = MistakeTraining::getPoolRow($userId, $tsumegoId);
		if (!$row || $row['next_due'] === null)
			return $prefix . ' graduated and left the training.';

		// A mistake on a problem that is due does not move its review: it waits in
		// today's training until it is solved.
		if (MistakeTraining::isRowDue($row))
			return $prefix . ' is on rung ' . (int) $row['rung'] . ' and stays due today until it is solved.';

		return $prefix . ' is on rung ' . (int) $row['rung'] . ', next review ' . $row['next_due'] . '.';
	}

	/**
	 * Render the shared play page for a problem of today's training. A problem that
	 * is due is its review, also when it was missed earlier today. One solved today
	 * stays in the row, so it can be opened again, but only as a replay that counts
	 * for nothing, and the page says so.
	 */
	private function playSetConnection(int $setConnectionID): mixed
	{
		$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
		$tsumegoId = $setConnection ? (int) $setConnection['SetConnection']['tsumego_id'] : 0;
		$pool = $tsumegoId ? MistakeTraining::getPoolRow((int) Auth::getUserID(), $tsumegoId) : null;
		if (!$pool || !MistakeTraining::isInTodaysTraining($pool))
			return $this->redirect(MistakeTraining::$QUEUE_LINK);

		$play = new Play(function ($name, $value) {
			$this->set($name, $value);
		});
		// Same problem, same address for search engines, whichever route got here.
		$this->set('canonicalPath', '/' . $setConnectionID);
		$this->set('_page', 'mistake-training');
		$this->set('mistakeTrainingReplay', !MistakeTraining::isRowDue($pool));
		$play->play($setConnectionID, $this->request, $this->request->data);
		$this->render('/Tsumegos/play');
		return null;
	}
}
