<?php

use App\Model\Table\SetConnectionTable;
use App\Model\Table\SgfTable;
use App\Attribute\HttpGet;
use App\Utility\Auth;

class SgfsController extends AppController
{
	#[HttpGet]
	public function view(int $id): void
	{
		$this->Authorization->authorize('Sgf');
		$this->set('_page', 'play');
		$this->loadModel('Set');
		$this->loadModel('User');
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

		$tsumegoTable = $this->fetchTable('Tsumego');
		if (isset($this->params['url']['duplicates']))
		{
			$newDuplicates = array_map('intval', explode('-', $this->params['url']['duplicates']));
			foreach ($newDuplicates as $duplicateId)
			{
				if (!$tsumegoTable->exists(['id' => $duplicateId]))
					continue;
				$displaySetConnection = SetConnectionTable::findDisplaySetConnection($duplicateId);
				if (!$displaySetConnection)
					continue;
				$dSet = $this->Set->findById($displaySetConnection->set_id);
				if (!$dSet)
					continue;
				$dId[] = $duplicateId;
				$dTitle[] = $dSet['Set']['title'] . ' - ';
			}
		}

		if (!$tsumegoTable->exists(['id' => $id]))
			throw new NotFoundException('Tsumego not found');
		$displaySetConnection = SetConnectionTable::findDisplaySetConnection($id);
		if (!$displaySetConnection)
			throw new NotFoundException('Tsumego not found in any set');
		$set = $this->Set->findById($displaySetConnection->set_id);
		if (!$set)
			throw new NotFoundException('Set not found');
		$name = $set['Set']['title'] . ' ' . $set['Set']['title2'] . ' ' . $displaySetConnection->num;
		$this->set('_title', 'Upload History of ' . $name);

		if (isset($this->params['url']['user']))
		{
			$versions = $this->versions(['user_id' => $this->params['url']['user'], 'sgf_status_id !=' => SgfTable::STATUS_REJECTED]);
			$type = 'user';
		}
		else
			$versions = $this->versions(['tsumego_id' => $id, 'sgf_status_id !=' => SgfTable::STATUS_REJECTED]);

		//to display the correct version
		$versionCounter = [];

		$sCount = count($versions);
		for ($i = 0; $i < $sCount; $i++)
		{


			$user = $this->User->findById($versions[$i]['user_id']);
			$ux = $user ? User::displayName($user['User']) : '';
			$versions[$i]['user'] = $ux;
			$displaySetConnection = SetConnectionTable::findDisplaySetConnection((int) $versions[$i]['tsumego_id']);
			$set = $this->Set->findById($displaySetConnection->set_id);
			$versions[$i]['title'] = $set['Set']['title'] . ' ' . $set['Set']['title2'] . ' #' . $displaySetConnection->num;
			$versions[$i]['num'] = $displaySetConnection->num;

			if(isset($versionCounter[$displaySetConnection->tsumego_id]))
				$versionCounter[$displaySetConnection->tsumego_id]++;
			else
				$versionCounter[$displaySetConnection->tsumego_id] = 0;
			$versions[$i]['version'] = $this->fetchTable('Sgf')->find()->where(['tsumego_id' => $displaySetConnection->tsumego_id, 'sgf_status_id !=' => SgfTable::STATUS_REJECTED])->count() - $versionCounter[$displaySetConnection->tsumego_id];

			if ($versions[$i]['user'] == 'noUser')
				$versions[$i]['user'] = 'automatically generated';
			if (Auth::getUserID() == $versions[$i]['user_id'])
				$versions[$i]['delete'] = true;
			else
				$versions[$i]['delete'] = false;
			$versions[$i]['diff'] = SgfTable::findPreviousVersionID((int) $versions[$i]['tsumego_id'], (int) $versions[$i]['id']);
		}

		$this->set('ux', $ux);
		$this->set('type', $type);
		$this->set('name', $name);
		$this->set('versions', $versions);
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
