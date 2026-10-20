<?php

use App\Attribute\HttpPost;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\Feature;
use App\Utility\FeatureKey;
use App\Utility\MistakeTraining;

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
				return $this->renderOverview($userId);

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
	 * The overview of the training, whatever is due: the training itself opens it only
	 * once nothing is left, so the homepage and the title of a training problem link
	 * here.
	 */
	public function overview(): mixed
	{
		if (!Feature::enabled(FeatureKey::MistakeTraining))
			throw new NotFoundException('Not found');

		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		return $this->renderOverview((int) Auth::getUserID());
	}

	/**
	 * Today's training (what is due, with the way in, or that it is all done), what
	 * comes up in the next days, the past mistakes to add when nothing is due, and the
	 * calendar and streak the profile shows.
	 */
	private function renderOverview(int $userId): null
	{
		$this->set('_page', 'mistake-training');
		$this->set('_title', 'Tsumego Hero - Mistake Training');
		$dueToday = MistakeTraining::dueCount($userId);
		$this->set('dueToday', $dueToday);
		$upcoming = MistakeTraining::upcomingByDay($userId);
		$this->set('totalInTraining', MistakeTraining::totalInTraining($userId));
		$this->set('everTrained', MistakeTraining::hasEverTrained($userId));
		// Offered on every visit that finds nothing due, so a player can keep pulling
		// old mistakes in as the training empties, and never more than a day at once.
		$this->set('importCandidates', $dueToday > 0 ? 0 : MistakeTraining::importCandidates($userId));
		$this->set('importLimit', MistakeTraining::IMPORT_LIMIT);
		$this->set('showDebugLink', Auth::isAdmin() && Configure::read('debug'));
		$this->set('upcomingDays', $upcoming['days']);
		$this->set('furtherDays', $upcoming['furtherDays']);
		$this->set('lastUpcomingDay', $upcoming['lastDay']);
		$todaysTraining = MistakeTraining::todaysTraining($userId);
		$solvedToday = count(array_filter($todaysTraining, fn(array $row) => MistakeTraining::wasSolvedToday($row)));
		$this->set('solvedToday', $solvedToday);
		$this->set('todaysTrainingLink', $todaysTraining
			? MistakeTraining::$PLAY_LINK_PREFIX . $todaysTraining[0]['set_connection_id']
			: null);
		$this->set('training', MistakeTraining::profile($userId, MistakeTraining::profileStart()));
		$this->set('poolRows', MistakeTraining::poolRows($userId));
		$this->render('/Tsumegos/mistake_training');
		return null;
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
	 * The admin toolbox: the state of the flag and the switch for it, and the ladder
	 * with its waits after lapses. It exists only in debug mode and only for admins,
	 * so production never serves it. It changes nothing in anyone's training: the
	 * shortcuts through time it once had were too easy to misuse on a real account.
	 *
	 * The guard deliberately ignores the flag: switching the training off must not
	 * take away the page that switches it back on.
	 */
	public function debug(): mixed
	{
		$this->assertToolboxAllowed();

		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		if ($this->request->is('post') && ($this->request->data['command'] ?? '') === 'flag')
		{
			$state = (string) ($this->request->data['state'] ?? '');
			if (in_array($state, [Feature::OFF, Feature::ADMINS, Feature::EVERYONE], true))
			{
				Feature::set(FeatureKey::MistakeTraining, $state);
				$notice = 'The training is now ' . $state . '.';
			}
			else
				$notice = 'Unknown state, nothing was changed.';
			return $this->redirect(MistakeTraining::$DEBUG_LINK . '?notice=' . urlencode($notice));
		}

		$userId = (int) Auth::getUserID();
		$this->set('notice', (string) ($this->request->query['notice'] ?? ''));
		$this->set('_page', 'mistake-training');
		$this->set('_title', 'Tsumego Hero - Mistake Training Debug');
		$this->set('totalInTraining', MistakeTraining::totalInTraining($userId));
		$this->set('importCandidates', MistakeTraining::importCandidates($userId));
		$this->set('flagState', Feature::state(FeatureKey::MistakeTraining));
		$this->set('ladder', MistakeTraining::ladder());
		$this->render('/Tsumegos/mistake_training_debug');
		return null;
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
