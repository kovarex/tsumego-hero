<?php

App::uses('MistakeTraining', 'Utility');
App::uses('Play', 'Controller/Component');

class MistakeTrainingController extends AppController
{
	/**
	 * Show a training problem: the one the link names, or the next due one.
	 *
	 * This route owns the training mode. Every queue link comes back here, so a
	 * plain problem URL always means normal play, just like it does for the other
	 * modes.
	 */
	public function play($setConnectionID = null): mixed
	{
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
				$this->set('totalInTraining', MistakeTraining::totalInTraining($userId));
				$this->set('upcomingDays', $upcoming['days']);
				$this->set('furtherDays', $upcoming['furtherDays']);
				$this->set('lastUpcomingDay', $upcoming['lastDay']);
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
	 * Render the shared play page for a problem that is due for review.
	 */
	private function playSetConnection(int $setConnectionID): mixed
	{
		$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
		$tsumegoId = $setConnection ? (int) $setConnection['SetConnection']['tsumego_id'] : 0;
		if (!$tsumegoId || !MistakeTraining::isDue((int) Auth::getUserID(), $tsumegoId))
			return $this->redirect(MistakeTraining::$QUEUE_LINK);

		$play = new Play(function ($name, $value) {
			$this->set($name, $value);
		});
		$play->play($setConnectionID, $this->params, $this->data);
		$this->render('/Tsumegos/play');
		return null;
	}
}
