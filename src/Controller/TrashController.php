<?php

use App\Model\Table\SetConnectionTable;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Model\Table\AdminActivityTypeTable;
use App\Utility\AdminActivityLogger;
use App\Utility\CookieFlash;
use App\Utility\Trash;
use App\Utility\Util;

//The problems and collections in the trash (see App\Utility\Trash), for the admins to take back
//what should not have been deleted before the daily cron removes it for good.
class TrashController extends AppController
{
	#[HttpGet]
	public function index(): void
	{
		$this->Authorization->authorize('Admin', 'viewTrash');
		$this->set('_page', 'user');
		$this->set('_title', 'Trash');

		$this->set('sets', Util::query('
SELECT s.id, s.title, s.public, s.deleted,
	(SELECT COUNT(*) FROM set_connection sc WHERE sc.set_id = s.id) AS problem_count,
	(SELECT u.display_name FROM admin_activity a JOIN user u ON u.id = a.user_id
		WHERE a.set_id = s.id AND a.type = ? ORDER BY a.id DESC LIMIT 1) AS deleted_by
FROM `set` s
WHERE s.deleted IS NOT NULL
ORDER BY s.deleted DESC, s.id DESC', [AdminActivityTypeTable::SET_DELETE]));

		$this->set('problems', Util::query('
SELECT t.id, t.description, t.deleted,
	sc.id AS set_connection_id, sc.num, s.title AS set_title,
	(SELECT u.display_name FROM admin_activity a JOIN user u ON u.id = a.user_id
		WHERE a.tsumego_id = t.id AND a.type = ? ORDER BY a.id DESC LIMIT 1) AS deleted_by
FROM tsumego t
LEFT JOIN set_connection sc ON sc.id = (
	SELECT sc2.id FROM set_connection sc2
	JOIN `set` s2 ON s2.id = sc2.set_id
	WHERE sc2.tsumego_id = t.id
	ORDER BY ' . SetConnectionTable::displayOrderSql('s2', 'sc2') . '
	LIMIT 1)
LEFT JOIN `set` s ON s.id = sc.set_id
WHERE t.deleted IS NOT NULL
ORDER BY t.deleted DESC, t.id DESC', [AdminActivityTypeTable::PROBLEM_DELETE]));
	}

	#[HttpPost]
	public function restoreProblem(?string $id = null): mixed
	{
		$this->Authorization->authorize('Admin', 'restoreFromTrash');
		$tsumego = $this->fetchTable('Tsumego')->find()->select(['id', 'deleted'])->where(['id' => (int) $id])->first();
		if (!$tsumego)
			throw new NotFoundException('Problem not found');

		if ($tsumego->deleted !== null)
		{
			Trash::restoreProblem((int) $id);
			AdminActivityLogger::log(AdminActivityTypeTable::PROBLEM_RESTORE, (int) $id);
			CookieFlash::set('The problem was restored', 'success');
		}
		return $this->redirect($this->referer('/trash', true));
	}

	#[HttpPost]
	public function restoreSet(?string $id = null): mixed
	{
		$this->Authorization->authorize('Admin', 'restoreFromTrash');
		$set = $this->fetchTable('Set')->find()->where(['id' => (int) $id])->first();
		if (!$set)
			throw new NotFoundException('Collection not found');

		if ($set->deleted)
		{
			Trash::restoreSet((int) $id);
			AdminActivityLogger::log(AdminActivityTypeTable::SET_RESTORE, null, (int) $id, null, $set->title);
			CookieFlash::set('The collection ' . $set->title . ' was restored', 'success');
		}
		return $this->redirect($this->referer('/trash', true));
	}
}
