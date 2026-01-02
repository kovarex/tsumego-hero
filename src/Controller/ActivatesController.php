<?php

App::uses('CakeEmail', 'Network/Email');
class ActivatesController extends AppController
{
	/**
	 * @return void
	 */
	public function index()
	{
		$this->set('_page', 'home');
		$this->set('_title', 'Tsumego Hero - Activate');
		$this->loadModel('User');

		$us = $this->User->find('all', [
			'conditions' => [
				'premium' => 2,
				'NOT' => [
					'id' => [2781, 4580, 1543, 1206, 453, 4363, 4275, 72, 73, 81, 87, 89, 94],
				],
			],
		]);
		if (!$us)
			$us = [];

		$us2 = $this->User->find('all', [
			'conditions' => [
				'OR' => [
					['id' => 88],
					['id' => 4370],
				],
			],
		]);
		if (!$us2)
			$us2 = [];

		$key = 0;
		$a = [];
		$s = '';
		if (!empty($this->data))
		{
			$ac = $this->Activate->find('first', ['conditions' => ['string' => $this->data['Activate']['Key']]]);
			if ($ac)
			{
				$ac['Activate']['user_id'] = Auth::getUserID();
				$this->Activate->save($ac);
				$key = 1;
			}
			else
				$key = 2;
		}

		if ($this->Activate->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]))
			$key = 1;

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
