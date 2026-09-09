<?php

App::uses('MistakeTraining', 'Utility');
App::uses('Play', 'Controller/Component');

class MistakeTrainingController extends AppController
{
	/**
	 * Show the next due mistake-training problem, or the "all caught up" view.
	 *
	 * Decides which tsumego to show next, puts the user into mistake-training
	 * mode, and renders the shared play page directly, so navigation stays under
	 * /mistake-training.
	 */
	public function play(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$userId = (int) Auth::getUserID();

		while (true)
		{
			$next = MistakeTraining::nextDue($userId);
			if (!$next)
			{
				$this->set('_page', 'mistake-training');
				$this->set('_title', 'Tsumego Hero - Mistake Training');
				$this->set('totalInTraining', MistakeTraining::totalInTraining($userId));
				$this->set('upcomingByDay', MistakeTraining::upcomingByDay($userId));
				$this->render('/Tsumegos/mistake_training');
				return null;
			}

			$tsumegoId = (int) $next['tsumego_id'];
			$tsumego = ClassRegistry::init('Tsumego')->find('first', [
				'conditions' => ['id' => $tsumegoId, 'deleted IS NULL'],
			]);
			if (!$tsumego)
			{
				MistakeTraining::removeFromPool($userId, $tsumegoId);
				continue;
			}

			$setConnection = ClassRegistry::init('SetConnection')->findDisplaySetConnection($tsumegoId);
			if (!$setConnection)
			{
				MistakeTraining::removeFromPool($userId, $tsumegoId);
				continue;
			}

			Auth::saveUserField('mode', Constants::$MISTAKE_TRAINING_MODE);
			$play = new Play(function ($name, $value) {
				$this->set($name, $value);
			});
			$play->play($setConnection['SetConnection']['id'], $this->params, $this->data);
			$this->render('/Tsumegos/play');
			return null;
		}
	}
}
