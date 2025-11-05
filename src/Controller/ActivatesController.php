<?php

App::uses('CakeEmail', 'Network/Email');
class ActivatesController extends AppController {
	/**
	 * @return void
	 */
	public function index() {
		$this->Session->write('page', 'home');
		$this->Session->write('title', 'Tsumego Hero - Activate');
		$this->loadModel('User');

		/*
		$this->Activate->create();
		$s = $this->rdm();
		$a = array();
		$a['Activate']['string'] = $s;
		$this->Activate->save($a);

		$this->Activate->create();
		$s = $this->rdm();
		$a = array();
		$a['Activate']['string'] = $s;
		$this->Activate->save($a);

		for ($i=3; $i<14; $i++) {
			$this->Activate->create();
			$s = $this->rdm();
			$a = array();
			$a['Activate']['id'] = $i;
			$a['Activate']['string'] = $s;
			$this->Activate->save($a);
		}
		*/

		$us = $this->User->find('all', [
			'conditions' => [
				'premium' => 2,
				'NOT' => [
					'id' => [2781, 4580, 1543, 1206, 453, 4363, 4275, 72, 73, 81, 87, 89, 94],
				],
			],
		]);
		if (!$us) {
			$us = [];
		}

		$us2 = $this->User->find('all', [
			'conditions' => [
				'OR' => [
					['id' => 88],
					['id' => 4370],
				],
			],
		]);
		if (!$us2) {
			$us2 = [];
		}

		foreach ($us as $user) {
			/*
			$this->Activate->create();
			$s = $this->rdm();
			$a = array();
			$a['Activate']['string'] = $s;
			$this->Activate->save($a);


			$Email = new CakeEmail();
			$Email->from(array('me@joschkazimdars.com' => 'https://tsumego-hero.com'));
			$Email->to($user['User']['email']);
			$Email->subject('Tsumego Hero - key for rating mode');
			$ans = '
			Hello '.$user['User']['name'].',

			this is an invitation for the new rating mode on Tsumego Hero. The rating mode gives you a rank for your ability to solve tsumego. The system is based on elo rating, which is used for tournaments in chess, go and other games. As in tournaments players get opponents around their rank, the rating mode matches you with go problems around your skill level.

			In the next few weeks, we evaluate the user data and try to find the best configuration. After that, the highscore will be reset and more users can play it. To have a bit of competition, the first three places in the highscore at the end of the beta phase get a secret area that has never been published. It contains 6 extremely hard problems for 2000 XP each and a board design.

			Here is your key: '.$a['Activate']['string'].'

			--
			Best Regards
			Joschka Zimdars

			';
			$Email->send($ans);*/
		}

		$key = 0;
		$a = [];
		$s = '';
		if (!empty(CakeRequest::$data)) {
			$ac = $this->Activate->find('first', ['conditions' => ['string' => CakeRequest::$data['Activate']['Key']]]);
			if ($ac) {
				$ac['Activate']['user_id'] = Auth::getUserID();
				$this->Activate->save($ac);
				$key = 1;
			} else {
				$key = 2;
			}
		}

		if ($this->Activate->find('first', ['conditions' => ['user_id' => Auth::getUserID()]])) {
			$key = 1;
		}

		$u = $this->User->findById(Auth::getUserID());
		$u['User']['readingTrial'] = 30;
		$this->User->save($u);

		$this->set('key', $key);
		$this->set('a', $a);
		$this->set('s', $s);
		$this->set('us', $us);
		$this->set('us2', $us2);
	}

}
