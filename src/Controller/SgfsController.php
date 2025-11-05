<?php

class SgfsController extends AppController {
	/**
	 * @return void
	 */
	public function index() {
		$this->Session->write('title', 'Tsumego Hero');
		$this->Session->write('page', 'play');
		$sgfs = $this->Sgf->find('all');
		if (!$sgfs) {
			$sgfs = [];
		}

		$this->set('sgfs', $sgfs);
	}

	/**
	 * @param string|int|null $id
	 * @return void
	 */
	public function view($id = null) {
		$this->Session->write('page', 'play');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('User');
		$this->loadModel('SetConnection');
		$name = '';
		$ux = '';
		$type = 'tsumego';
		$id2 = $id;
		$id /= 1337;
		$dId = [];
		$dTitle = [];

		if (isset(CakeRequest::$params['url']['delete'])) {
			$sDel = $this->Sgf->findById(CakeRequest::$params['url']['delete']);
			if (Auth::getUserID() == $sDel['Sgf']['user_id']) {
				$this->Sgf->delete($sDel['Sgf']['id']);
			}
		}

		if (isset(CakeRequest::$params['url']['duplicates'])) {
			$newDuplicates = explode('-', CakeRequest::$params['url']['duplicates']);
			foreach ($newDuplicates as $duplicateId) {
				$dupl = $this->Tsumego->findById($duplicateId);
				$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $dupl['Tsumego']['id']]]);
				$dupl['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
				$dSet = $this->Set->findById($dupl['Tsumego']['set_id']);
				$dId[] = $dupl['Tsumego']['id'];
				$dTitle[] = $dSet['Set']['title'] . ' - ' . $dupl['Tsumego']['num'];
			}
		}

		$t = $this->Tsumego->findById($id);
		$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
		$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
		$set = $this->Set->findById($t['Tsumego']['set_id']);
		$name = $set['Set']['title'] . ' ' . $set['Set']['title2'] . ' ' . $t['Tsumego']['num'];
		$this->Session->write('title', 'Upload History of ' . $name);

		if (isset(CakeRequest::$params['url']['user'])) {
			$s = $this->Sgf->find('all', [
				'order' => 'version DESC',
				'limit' => 100,
				'conditions' => [
					'user_id' => CakeRequest::$params['url']['user'],
					'NOT' => ['version' => 0],
				],
			]);
			if (!$s) {
				$s = [];
			}
			$type = 'user';
		} else {
			$s = $this->Sgf->find('all', [
				'order' => 'version DESC',
				'limit' => 100,
				'conditions' => [
					'tsumego_id' => $id,
					'NOT' => ['version' => 0],
				],
			]);
			if (!$s) {
				$s = [];
			}
		}

		$sCount = count($s);
		for ($i = 0; $i < $sCount; $i++) {
			$s[$i]['Sgf']['sgf'] = str_replace("\r", '', $s[$i]['Sgf']['sgf']);
			$s[$i]['Sgf']['sgf'] = str_replace("\n", '"+"\n"+"', $s[$i]['Sgf']['sgf']);

			$u = $this->User->findById($s[$i]['Sgf']['user_id']);
			$s[$i]['Sgf']['user'] = $u['User']['name'];
			$ux = $u['User']['name'];
			$t = $this->Tsumego->findById($s[$i]['Sgf']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$set = $this->Set->findById($t['Tsumego']['set_id']);
			$s[$i]['Sgf']['title'] = $set['Set']['title'] . ' ' . $set['Set']['title2'] . ' #' . $t['Tsumego']['num'];

			$s[$i]['Sgf']['num'] = $t['Tsumego']['num'];

			if ($s[$i]['Sgf']['user'] == 'noUser') {
				$s[$i]['Sgf']['user'] = 'automatically generated';
			}
			if (Auth::getUserID() == $s[$i]['Sgf']['user_id']) {
				$s[$i]['Sgf']['delete'] = true;
			} else {
				$s[$i]['Sgf']['delete'] = false;
			}
			if ($type == 'user') {
				$sDiff = $this->Sgf->find('all', ['order' => 'version DESC', 'limit' => 2, 'conditions' => ['tsumego_id' => $s[$i]['Sgf']['tsumego_id']]]);
				if (!$sDiff) {
					$sDiff = [];
				}
				$s[$i]['Sgf']['diff'] = $sDiff[1]['Sgf']['id'];
			} else {
				if ($i != count($s) - 1) {
					$s[$i]['Sgf']['diff'] = $s[$i + 1]['Sgf']['id'];
				}
			}
		}

		$this->set('ux', $ux);
		$this->set('type', $type);
		$this->set('name', $name);
		$this->set('s', $s);
		$this->set('id', $id);
		$this->set('id2', $id2);
		$this->set('tNum', $t['Tsumego']['num']);
		$this->set('dId', $dId);
		$this->set('dTitle', $dTitle);
	}

}
