<?php

use App\Model\Table\SgfTable;
use App\Attribute\HttpGet;
use App\Utility\Auth;

class SgfsController extends AppController
{
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
			$sgfTable = $this->fetchTable('Sgf');
			$sDel = $sgfTable->find()->select(['id', 'user_id'])->where(['id' => $this->params['url']['delete']])->first();
			if ($sDel && Auth::getUserID() == $sDel->user_id)
				$sgfTable->deleteOrFail($sDel);
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
			$s = $this->versions(['user_id' => $this->params['url']['user'], 'sgf_status_id !=' => SgfTable::STATUS_REJECTED]);
			$type = 'user';
		}
		else
			$s = $this->versions(['tsumego_id' => $id, 'sgf_status_id !=' => SgfTable::STATUS_REJECTED]);

		//to display the correct version
		$versionCounter = [];

		$sCount = count($s);
		for ($i = 0; $i < $sCount; $i++)
		{


			$u = $this->User->findById($s[$i]['user_id']);
			$ux = $u ? User::displayName($u['User']) : '';
			$s[$i]['user'] = $ux;
			$t = $this->Tsumego->findById($s[$i]['tsumego_id']);
			$scT = $this->SetConnection->findDisplaySetConnection($t['Tsumego']['id']);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$set = $this->Set->findById($t['Tsumego']['set_id']);
			$s[$i]['title'] = $set['Set']['title'] . ' ' . $set['Set']['title2'] . ' #' . $scT['SetConnection']['num'];
			$s[$i]['num'] = $scT['SetConnection']['num'];

			if(isset($versionCounter[$scT['SetConnection']['tsumego_id']]))
				$versionCounter[$scT['SetConnection']['tsumego_id']]++;
			else
				$versionCounter[$scT['SetConnection']['tsumego_id']] = 0;
			$s[$i]['version'] = $this->fetchTable('Sgf')->find()->where(['tsumego_id' => $scT['SetConnection']['tsumego_id'], 'sgf_status_id !=' => SgfTable::STATUS_REJECTED])->count() - $versionCounter[$scT['SetConnection']['tsumego_id']];

			if ($s[$i]['user'] == 'noUser')
				$s[$i]['user'] = 'automatically generated';
			if (Auth::getUserID() == $s[$i]['user_id'])
				$s[$i]['delete'] = true;
			else
				$s[$i]['delete'] = false;
			$s[$i]['diff'] = SgfTable::findPreviousVersionID((int) $s[$i]['tsumego_id'], (int) $s[$i]['id']);
		}

		$this->set('ux', $ux);
		$this->set('type', $type);
		$this->set('name', $name);
		$this->set('s', $s);
		$this->set('id', $id);
		$this->set('dId', $dId);
		$this->set('dTitle', $dTitle);
	}

	//The newest 100 versions the conditions pick, as the rows of the database: the page adds what it shows.
	private function versions(array $conditions): array
	{
		return $this->fetchTable('Sgf')->find()
			->where($conditions)
			->orderByDesc('id')
			->limit(100)
			->disableHydration()
			->disableResultsCasting()
			->toArray();
	}
}
