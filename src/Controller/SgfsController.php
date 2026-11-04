<?php

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use App\Model\Table\SetConnectionTable;
use App\Model\Table\SgfTable;
use App\Attribute\HttpGet;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Util;

class SgfsController extends AppController
{
	#[HttpGet]
	public function view(int $id): void
	{
		$this->Authorization->authorize('Sgf');
		$this->set('_page', 'play');
		$ux = '';
		$type = 'tsumego';

		if ($this->query('delete') !== null)
		{
			$sgfTable = $this->fetchTable('Sgf');
			$sDel = $sgfTable->find()->select(['id', 'user_id'])->where(['id' => $this->query('delete')])->first();
			// an automatic version (no user) is nobody's to delete
			if ($sDel && $sDel->user_id !== null && $sDel->user_id === Auth::getUserID())
				$sgfTable->deleteOrFail($sDel);
		}

		$tsumegoTable = $this->fetchTable('Tsumego');
		$setTable = $this->fetchTable('Set');
		if (!$tsumegoTable->exists(['id' => $id]))
			throw new NotFoundException('Tsumego not found');
		$displaySetConnection = SetConnectionTable::findDisplaySetConnection($id);
		if (!$displaySetConnection)
			throw new NotFoundException('Tsumego not found in any set');
		$set = $setTable->find()->select(['title', 'title2'])->where(['id' => $displaySetConnection->set_id])->first();
		if (!$set)
			throw new NotFoundException('Set not found');
		$name = $set->title . ' ' . $set->title2 . ' ' . $displaySetConnection->num;
		$this->set('_title', 'Upload History of ' . $name);

		if ($this->query('user') !== null)
		{
			$versions = $this->versions(['user_id' => $this->query('user'), 'sgf_status_id !=' => SgfTable::STATUS_REJECTED]);
			$type = 'user';
		}
		else
			$versions = $this->versions(['tsumego_id' => $id, 'sgf_status_id !=' => SgfTable::STATUS_REJECTED]);

		$this->describeVersions($versions);
		// the author of the page of a player's versions, as the last version names them
		$ux = empty($versions) ? '' : end($versions)['user'];

		$this->set('ux', $ux);
		$this->set('type', $type);
		$this->set('name', $name);
		$this->set('versions', $versions);
		$this->set('id', $id);
	}

	//Adds what the page shows of each version: its author, the problem as its collection shows it, the
	//number of the version among those of the problem that weren't rejected (the versions come newest
	//first) and the accepted version before it to compare with. Each is read for all the versions at once.
	private function describeVersions(array &$versions): void
	{
		if (empty($versions))
			return;
		$tsumegoIDs = array_values(array_unique(array_map(fn($version) => (int) $version['tsumego_id'], $versions)));
		$tsumegoPlaceholders = implode(',', array_fill(0, count($tsumegoIDs), '?'));

		$userNames = [];
		$userIDs = array_values(array_unique(array_filter(array_map(fn($version) => $version['user_id'] === null ? null : (int) $version['user_id'], $versions))));
		if (!empty($userIDs))
			foreach ($this->fetchTable('User')->find()->where(['id IN' => $userIDs])->all() as $user)
				$userNames[$user->id] = UserTable::displayName(UserTable::rowOf($user));

		$shownIn = [];
		foreach (Util::query('SELECT tsumego.id AS tsumego_id, sc.num, s.title, s.title2 FROM tsumego'
			. ' JOIN set_connection sc ON sc.id = ' . SetConnectionTable::displaySetConnectionIdSql('tsumego.id')
			. ' JOIN `set` s ON s.id = sc.set_id'
			. " WHERE tsumego.id IN ($tsumegoPlaceholders)", $tsumegoIDs) as $row)
				$shownIn[(int) $row['tsumego_id']] = $row;

		$versionCounts = [];
		$acceptedIDs = [];
		foreach (Util::query("SELECT id, tsumego_id, sgf_status_id FROM sgf WHERE tsumego_id IN ($tsumegoPlaceholders) AND sgf_status_id != ? ORDER BY id",
			[...$tsumegoIDs, SgfTable::STATUS_REJECTED]) as $row)
		{
			$tsumegoID = (int) $row['tsumego_id'];
			$versionCounts[$tsumegoID] = ($versionCounts[$tsumegoID] ?? 0) + 1;
			if ((int) $row['sgf_status_id'] === SgfTable::STATUS_ACCEPTED)
				$acceptedIDs[$tsumegoID][] = (int) $row['id'];
		}

		$newerVersions = [];
		foreach ($versions as &$version)
		{
			$tsumegoID = (int) $version['tsumego_id'];
			// an automatic version has no user
			$version['user'] = $version['user_id'] === null ? '' : ($userNames[(int) $version['user_id']] ?? '');
			$version['title'] = $shownIn[$tsumegoID]['title'] . ' ' . $shownIn[$tsumegoID]['title2'] . ' #' . $shownIn[$tsumegoID]['num'];
			$version['num'] = (int) $shownIn[$tsumegoID]['num'];
			$version['version'] = $versionCounts[$tsumegoID] - ($newerVersions[$tsumegoID] ?? 0);
			$newerVersions[$tsumegoID] = ($newerVersions[$tsumegoID] ?? 0) + 1;
			$version['delete'] = $version['user_id'] !== null && (int) $version['user_id'] === Auth::getUserID();
			$previousIDs = array_filter($acceptedIDs[$tsumegoID] ?? [], fn($id) => $id < (int) $version['id']);
			$version['diff'] = empty($previousIDs) ? null : max($previousIDs);
		}
		unset($version);
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
