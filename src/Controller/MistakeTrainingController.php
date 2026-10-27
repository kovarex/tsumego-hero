<?php

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\MistakeTraining;

class MistakeTrainingController extends AppController
{
	//Show a training problem: the one the link names, or the next due one, which is
	//the first of today's list not tried yet, and once only misses are left, the
	//first of those.
	//This route owns the training mode. Every queue link comes back here, so a
	//plain problem URL always means normal play, just like it does for the other
	//modes.
	#[HttpGet]
	public function play(?string $setConnectionID = null): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		Auth::saveUserField('mode_id', Constants::$MISTAKE_TRAINING_MODE);

		if ($setConnectionID !== null)
			return $this->playSetConnection((int) $setConnectionID);

		$userId = (int) Auth::getUserID();
		$next = MistakeTraining::nextDue($userId);
		if (!$next)
			return $this->renderOverview($userId);

		return $this->playSetConnection((int) $next['set_connection_id']);
	}

	//The overview of the training, whatever is due: the training itself opens it only
	//once nothing is left, so the homepage and the title of a training problem link
	//here.
	#[HttpGet]
	public function overview(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		return $this->renderOverview((int) Auth::getUserID());
	}

	//Today's training (what is due, with the way in, or that it is all done), what
	//comes up in the next days, the past mistakes to add when nothing is due, and the
	//calendar and streak the profile shows.
	private function renderOverview(int $userId): null
	{
		$this->set('_page', 'mistake-training');
		$this->set('_title', 'Tsumego Hero - Mistake Training');
		$dueToday = MistakeTraining::dueCount($userId);
		$this->set('dueToday', $dueToday);
		$upcoming = MistakeTraining::upcomingByDay($userId);
		$this->set('totalInTraining', MistakeTraining::totalInTraining($userId));
		$this->set('everTrained', MistakeTraining::hasEverTrained($userId));
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

	//Bring the player's past mistakes into the training. Offered on the queue page
	//while nothing is due, because a problem solved before the training existed never
	//entered it by itself. What the import found is said here, so no page has to count
	//the attempt history to offer the button.
	#[HttpPost]
	public function import(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$added = MistakeTraining::importPastMistakes((int) Auth::getUserID());
		CookieFlash::set(
			$added > 0
				? 'Added ' . $added . ' past mistake' . ($added == 1 ? '' : 's') . ' to the training.'
				: 'Nothing left to add, every past mistake is already in the training.',
			$added > 0 ? 'success' : 'info'
		);

		return $this->redirect(MistakeTraining::$QUEUE_LINK);
	}

	//Render the shared play page for a problem of today's training. A problem that
	//is due is its review, also when it was missed earlier today. One solved today
	//stays in the row, so it can be opened again, but only as a replay that counts
	//for nothing, and the page says so. A link that stopped holding, because the
	//problem left the training or its collection is not open to the player, leads
	//back to the queue instead of the refusal of the play page.
	private function playSetConnection(int $setConnectionID): mixed
	{
		$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
		$tsumegoId = $setConnection ? (int) $setConnection['SetConnection']['tsumego_id'] : 0;
		$pool = $tsumegoId ? MistakeTraining::getPoolRow((int) Auth::getUserID(), $tsumegoId) : null;
		if (!$pool || !MistakeTraining::isInTodaysTraining($pool))
			return $this->redirect(MistakeTraining::$QUEUE_LINK);

		$set = ClassRegistry::init('Set')->findById($setConnection['SetConnection']['set_id']);
		if (!$set || !$this->Authorization->can(['Set' => $set['Set']], 'play'))
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
