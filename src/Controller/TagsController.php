<?php

App::uses('NotFoundException', 'Routing/Error');
App::uses('HtmlSanitizer', 'Utility');

use App\Attribute\HttpPost;

class TagsController extends AppController
{
	public function add()
	{
		$this->Authorization->authorize('Tag', 'add');
		$allTags = $this->getAllTags();
		$this->set('allTags', $allTags);
	}

	#[HttpPost]
	public function addAction(): CakeResponse
	{
		$this->Authorization->authorize('Tag', 'add');
		$tagName = $this->data['tag_name'];
		if (empty($tagName))
		{
			CookieFlash::set('Tag name not provided', 'error');
			return $this->redirect('/tags/add');
		}

		$existingTag = ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => $tagName]]);
		if ($existingTag)
		{
			CookieFlash::set('Tag "' . $tagName . '" already exists.', 'error');
			return $this->redirect('/tags/add');
		}

		$tagDescription = $this->data['tag_description'];
		if (empty($tagDescription))
		{
			CookieFlash::set('Tag description not provided', 'error');
			return $this->redirect('/tags/add');
		}

		$tag = [];
		$tag['name'] = $tagName;
		$tag['description'] = HtmlSanitizer::sanitize((string) $tagDescription);
		$tag['hint'] = (int) $this->data['tag_hint'];
		$tag['link'] = trim((string) ($this->data['tag_reference'] ?? ''));
		$tag['user_id'] = Auth::getUserID();
		$tag['approved'] = Auth::isAdmin() ? 1 : 0;
		ClassRegistry::init('Tag')->save($tag);
		$saved = ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => $tagName]])['Tag'];

		CookieFlash::set('Tag "' . $tagName . '" has been added.', 'success');
		return $this->redirect('/tags/view/' . $saved['id']);
	}

	/**
	 * @param string|int|null $id
	 * @return void
	 */
	public function view($id = null)
	{
		$tn = $this->Tag->findById($id);
		if (!$tn)
			throw new NotFoundException('Tag not found');
		$allTags = $this->getAllTags();
		$user = $this->User->findById($tn['Tag']['user_id']);
		$tn['Tag']['user'] = $user['User']['name'];
		$this->set('allTags', $allTags);
		$this->set('tn', $tn);
		$this->set('canAddTag', $this->Authorization->can('Tag', 'add'));
	}

	/**
	 * @param string|int|null $id User ID
	 * @return void
	 */
	public function user($id)
	{
		$u = $this->User->findById($id);
		if (!$u)
			throw new NotFoundException('User not found');

		$pageSize = 50;
		$pageIndex = isset($this->request->query['page']) ? max(1, (int) $this->request->query['page']) : 1;
		$offset = ($pageIndex - 1) * $pageSize;

		$rows = Util::query(
			'SELECT SQL_CALC_FOUND_ROWS type, status, created, tag_id, tag, tsumego_id, tsumego_label, row_id FROM ('
				// Tag names: accepted + pending
				. 'SELECT \'tag name\' AS type, IF(t.approved, \'accepted\', \'pending\') AS status, t.created, t.id AS tag_id, t.name AS tag, \'\' AS tsumego_id, \'\' AS tsumego_label, t.id AS row_id '
				. 'FROM tag t WHERE t.user_id = ? '
				. 'UNION ALL '
				// Tag connections: accepted + pending
				. 'SELECT \'tag\', IF(tc.approved, \'accepted\', \'pending\'), tc.created, t.id, t.name, tc.tsumego_id, CONCAT(s.title, \' - \', sc.num), tc.id '
				. 'FROM tag_connection tc '
				. 'JOIN tag t ON t.id = tc.tag_id '
				. 'JOIN set_connection sc ON sc.id = ('
					. 'SELECT sc2.id FROM set_connection sc2 '
					. 'JOIN `set` s2 ON s2.id = sc2.set_id '
					. 'WHERE sc2.tsumego_id = tc.tsumego_id '
					. 'ORDER BY s2.`order` ASC, sc2.id ASC LIMIT 1) '
				. 'JOIN `set` s ON s.id = sc.set_id '
				. 'WHERE tc.user_id = ? '
				. 'UNION ALL '
				// Proposals (SGF uploads)
				. 'SELECT \'proposal\', IF(sg.accepted, \'accepted\', \'pending\'), sg.created, \'\', \'\', sg.tsumego_id, CONCAT(s.title, \' - \', sc.num), sg.id '
				. 'FROM sgf sg '
				. 'JOIN set_connection sc ON sc.id = ('
					. 'SELECT sc2.id FROM set_connection sc2 '
					. 'JOIN `set` s2 ON s2.id = sc2.set_id '
					. 'WHERE sc2.tsumego_id = sg.tsumego_id '
					. 'ORDER BY s2.`order` ASC, sc2.id ASC LIMIT 1) '
				. 'JOIN `set` s ON s.id = sc.set_id '
				. 'WHERE sg.user_id = ? '
				. 'UNION ALL '
				// Rejected items (tag names have tsumego_id = 0, so LEFT JOIN gives empty label)
				. 'SELECT r.type, \'rejected\', r.created, \'\', r.text, r.tsumego_id, '
				. 'COALESCE(CONCAT(s.title, \' - \', sc.num), \'\'), r.id '
				. 'FROM reject r '
				. 'LEFT JOIN set_connection sc ON sc.id = ('
					. 'SELECT sc2.id FROM set_connection sc2 '
					. 'JOIN `set` s2 ON s2.id = sc2.set_id '
					. 'WHERE sc2.tsumego_id = r.tsumego_id '
					. 'ORDER BY s2.`order` ASC, sc2.id ASC LIMIT 1) '
				. 'LEFT JOIN `set` s ON s.id = sc.set_id '
				. 'WHERE r.user_id = ? AND (sc.id IS NOT NULL OR r.tsumego_id = 0) '
			. ') AS contributions '
			. "ORDER BY created DESC, row_id DESC "
			. "LIMIT {$pageSize} OFFSET {$offset}",
			[$id, $id, $id, $id]
		);

		$count = Util::query('SELECT FOUND_ROWS()')[0]['FOUND_ROWS()'];

		$contributions = [];
		foreach ($rows as $row)
			$contributions[] = ContributionRow::fromQueryRow($row);

		$this->set('contributions', $contributions);
		$this->set('viewedUser', $u['User']);
		$this->set('count', $count);
		$this->set('pageIndex', $pageIndex);
		$this->set('pageSize', $pageSize);
	}

	public function edit($tagID): ?CakeResponse
	{
		$this->Authorization->authorize('Tag');
		$tag = ClassRegistry::init('Tag')->findById($tagID);
		if (!$tag)
		{
			CookieFlash::set('Tag to edit not found.', 'error');
			return $this->redirect('/users/adminstats');
		}

		$this->set('allTags', $this->getAllTags());
		$this->set('tag', $tag['Tag']);
		$this->set('canAddTag', $this->Authorization->can('Tag', 'add'));
		return null;
	}

	#[HttpPost]
	public function editAction($tagID)
	{
		$this->Authorization->authorize('Tag', 'editAction');
		$tag = ClassRegistry::init('Tag')->findById($tagID);
		if (!$tag)
		{
			CookieFlash::set('Tag to edit not found.');
			$this->redirect('/users/adminstats');
		}

		$tag = $tag['Tag'];

		$tagDescription = $this->data['tag_description'];
		if (empty($tagDescription))
		{
			CookieFlash::set('Tag description not provided', 'error');
			return $this->redirect('/tags/edit/' . $tagID);
		}

		$tag['description'] = HtmlSanitizer::sanitize((string) $tagDescription);
		$tag['hint'] = (int) ($this->data['tag_hint']);
		$tag['link'] = trim((string) ($this->data['tag_link'] ?? ''));
		ClassRegistry::init('Tag')->save($tag);
		return $this->redirect('/tags/view/' . $tagID);
	}

	/**
	 * @param string|int $id Tag name ID
	 * @return void
	 */
	#[HttpPost]
	public function delete($id)
	{
		$this->Authorization->authorize('Tag');
		$this->loadModel('Tag');
		$this->loadModel('TagConnection');
		$tn = $this->Tag->findById($id);
		if (!$tn)
			throw new NotFoundException('Tag not found');

		if (isset($this->data['Tag']))
			if ($this->data['Tag']['delete'] == $id)
			{
				$tags = $this->TagConnection->find('all', ['conditions' => ['tag_id' => $id]]);
				if (!$tags)
					$tags = [];
				foreach ($tags as $tag)
					$this->TagConnection->delete($tag['TagConnection']['id']);
				$this->Tag->delete($id);
				$this->set('del', 'del');
			}

		$this->set('tn', $tn);
	}

	public function index() {}

	public function acceptTagProposal($tagID): CakeResponse
	{
		$this->Authorization->authorize('Tag');

		$tagToApprove = ClassRegistry::init('Tag')->findById($tagID);
		if (!$tagToApprove)
		{
			CookieFlash::set('Tag to approve not found', 'error');
			return $this->redirect('/users/adminstats');
		}

		$tagToApprove = $tagToApprove['Tag'];

		if ($tagToApprove['approved'] == 1)
		{
			CookieFlash::set('Tag to approve was already approved', 'error');
			return $this->redirect('/users/adminstats');
		}

		AppController::handleContribution(Auth::getUserID(), 'reviewed');
		$tagToApprove['approved'] = '1';
		ClassRegistry::init('Tag')->save($tagToApprove);
		AppController::handleContribution($tagToApprove['user_id'], 'created_tag');
		CookieFlash::set('Tag ' . $tagToApprove['name'] . ' was approved', 'success');
		return $this->redirect('/users/adminstats');
	}

	public function rejectTagProposal($tagID): CakeResponse
	{
		$this->Authorization->authorize('Tag');

		$tagToReject = ClassRegistry::init('Tag')->findById($tagID);
		if (!$tagToReject)
		{
			CookieFlash::set('Tag to approve not found', 'error');
			return $this->redirect('/users/adminstats');
		}

		$tagToReject = $tagToReject['Tag'];

		if ($tagToReject['approved'] == 1)
		{
			CookieFlash::set('Tag to reject was already approved', 'error');
			return $this->redirect('/users/adminstats');
		}

		AppController::handleContribution(Auth::getUserID(), 'reviewed');

		ClassRegistry::init('Tag')->delete($tagToReject);

		CookieFlash::set('Tag ' . $tagToReject['name'] . ' was rejected', 'success');
		return $this->redirect('/users/adminstats');
	}
}
