<?php

use App\Attribute\HttpGet;
use App\Utility\Auth;

class SgfsController extends AppController
{
	/**
	 * @param string|null $id
	 * @return void
	 */
	#[HttpGet]
	public function view(?string $id = null): void
	{
		$this->Authorization->authorize('Sgf');
		$this->set('_page', 'play');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('User');
		$this->loadModel('SetConnection');
		$ux = '';
		$type = 'tsumego';
		$dId = [];
		$dTitle = [];

		if (isset($this->params['url']['delete']))
		{
			$sDel = $this->Sgf->findById($this->params['url']['delete']);
			if ($sDel && Auth::getUserID() == $sDel['Sgf']['user_id'])
				$this->Sgf->delete($sDel['Sgf']['id']);
		}

		if (isset($this->params['url']['duplicates']))
		{
			$newDuplicates = explode('-', $this->params['url']['duplicates']);
			foreach ($newDuplicates as $duplicateId)
			{
				$dupl = $this->Tsumego->findById($duplicateId);
				if (!$dupl)
					continue;
				$scT = $this->SetConnection->findDisplaySetConnection($dupl['Tsumego']['id']);
				if (!$scT)
					continue;
				$dupl['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
				$dSet = $this->Set->findById($dupl['Tsumego']['set_id']);
				if (!$dSet)
					continue;
				$dId[] = $dupl['Tsumego']['id'];
				$dTitle[] = $dSet['Set']['title'] . ' - ' . $dupl['Tsumego']['num'];
			}
		}

		$t = $this->Tsumego->findById($id);
		if (!$t)
			throw new NotFoundException('Tsumego not found');
		$scT = $this->SetConnection->findDisplaySetConnection($t['Tsumego']['id']);
		if (!$scT)
			throw new NotFoundException('Tsumego not found in any set');
		$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
		$set = $this->Set->findById($t['Tsumego']['set_id']);
		if (!$set)
			throw new NotFoundException('Set not found');
		$name = $set['Set']['title'] . ' ' . $set['Set']['title2'] . ' ' . $scT['SetConnection']['num'];
		$this->set('_title', 'Upload History of ' . $name);

		if (isset($this->params['url']['user']))
		{
			$s = $this->Sgf->find('all', [
				'order' => 'id DESC',
				'limit' => 100,
				'conditions' => ['user_id' => $this->params['url']['user'], 'sgf_status_id !=' => Sgf::STATUS_REJECTED],
			]);
			if (!$s)
				$s = [];
			$type = 'user';
		}
		else
		{
			$s = $this->Sgf->find('all', [
				'order' => 'id DESC',
				'limit' => 100,
				'conditions' => ['tsumego_id' => $id, 'sgf_status_id !=' => Sgf::STATUS_REJECTED]]);
			if (!$s)
				$s = [];
		}

		//to display the correct version
		$versionCounter = [];

		$sCount = count($s);
		for ($i = 0; $i < $sCount; $i++)
		{


			$u = $this->User->findById($s[$i]['Sgf']['user_id']);
			$ux = $u ? User::displayName($u['User']) : '';
			$s[$i]['Sgf']['user'] = $ux;
			$t = $this->Tsumego->findById($s[$i]['Sgf']['tsumego_id']);
			$scT = $this->SetConnection->findDisplaySetConnection($t['Tsumego']['id']);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$set = $this->Set->findById($t['Tsumego']['set_id']);
			$s[$i]['Sgf']['title'] = $set['Set']['title'] . ' ' . $set['Set']['title2'] . ' #' . $scT['SetConnection']['num'];
			$s[$i]['Sgf']['num'] = $scT['SetConnection']['num'];

			if(isset($versionCounter[$scT['SetConnection']['tsumego_id']]))
				$versionCounter[$scT['SetConnection']['tsumego_id']]++;
			else
				$versionCounter[$scT['SetConnection']['tsumego_id']] = 0;
			$s[$i]['Sgf']['version'] = count($this->Sgf->find('all', ['conditions' => ['tsumego_id' => $scT['SetConnection']['tsumego_id'], 'sgf_status_id !=' => Sgf::STATUS_REJECTED]])) - $versionCounter[$scT['SetConnection']['tsumego_id']];

			if ($s[$i]['Sgf']['user'] == 'noUser')
				$s[$i]['Sgf']['user'] = 'automatically generated';
			if (Auth::getUserID() == $s[$i]['Sgf']['user_id'])
				$s[$i]['Sgf']['delete'] = true;
			else
				$s[$i]['Sgf']['delete'] = false;
			$s[$i]['Sgf']['diff'] = $this->Sgf->findPreviousVersionID($s[$i]['Sgf']['tsumego_id'], $s[$i]['Sgf']['id']);
		}

		$this->set('ux', $ux);
		$this->set('type', $type);
		$this->set('name', $name);
		$this->set('s', $s);
		$this->set('id', $id);
		$this->set('dId', $dId);
		$this->set('dTitle', $dTitle);
	}

}
