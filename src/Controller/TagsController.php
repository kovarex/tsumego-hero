<?php

App::uses('NotFoundException', 'Routing/Error');

class TagsController extends AppController
{
	public function add()
	{
		$allTags = $this->getAllTags();
		$this->set('allTags', $allTags);
	}

	public function addAction(): CakeResponse
	{
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
		$tag['description'] = $tagDescription;
		$tag['hint'] = (int) $this->data['tag_hint'];
		$tag['link'] = $this->data['tag_reference'];
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
			'SELECT SQL_CALC_FOUND_ROWS type, status, created, tag_id, tag, tsumego_id, tsumego_label FROM ('
				// Accepted tag names
				. 'SELECT \'tag name\' AS type, \'accepted\' AS status, t.created, t.id AS tag_id, t.name AS tag, \'\' AS tsumego_id, \'\' AS tsumego_label '
				. 'FROM tag t WHERE t.user_id = ? AND t.approved = 1 '
				. 'UNION ALL '
				// Rejected tag names
				. 'SELECT \'tag name\', \'rejected\', r.created, \'\', r.text, \'\', \'\' '
				. 'FROM reject r WHERE r.user_id = ? AND r.type = \'tag name\' '
				. 'UNION ALL '
				// Accepted tag connections
				. 'SELECT \'tag\', \'accepted\', tc.created, t.id, t.name, tc.tsumego_id, CONCAT(s.title, \' - \', sc.num) '
				. 'FROM tag_connection tc '
				. 'JOIN tag t ON t.id = tc.tag_id '
				. 'JOIN set_connection sc ON sc.tsumego_id = tc.tsumego_id '
				. 'JOIN `set` s ON s.id = sc.set_id '
				. 'WHERE tc.user_id = ? AND tc.approved = 1 '
				. 'UNION ALL '
				// Rejected tags
				. 'SELECT \'tag\', \'rejected\', r.created, \'\', r.text, r.tsumego_id, CONCAT(s.title, \' - \', sc.num) '
				. 'FROM reject r '
				. 'JOIN set_connection sc ON sc.tsumego_id = r.tsumego_id '
				. 'JOIN `set` s ON s.id = sc.set_id '
				. 'WHERE r.user_id = ? AND r.type = \'tag\' '
				. 'UNION ALL '
				// Accepted proposals
				. 'SELECT \'proposal\', \'accepted\', sg.created, \'\', \'\', sg.tsumego_id, CONCAT(s.title, \' - \', sc.num) '
				. 'FROM sgf sg '
				. 'JOIN set_connection sc ON sc.tsumego_id = sg.tsumego_id '
				. 'JOIN `set` s ON s.id = sc.set_id '
				. 'WHERE sg.user_id = ? '
				. 'UNION ALL '
				// Rejected proposals
				. 'SELECT \'proposal\', \'rejected\', r.created, \'\', \'\', r.tsumego_id, CONCAT(s.title, \' - \', sc.num) '
				. 'FROM reject r '
				. 'JOIN set_connection sc ON sc.tsumego_id = r.tsumego_id '
				. 'JOIN `set` s ON s.id = sc.set_id '
				. 'WHERE r.user_id = ? AND r.type = \'proposal\' '
			. ') AS contributions '
			. "ORDER BY created DESC "
			. "LIMIT {$pageSize} OFFSET {$offset}",
			[$id, $id, $id, $id, $id, $id]
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
		$tag = ClassRegistry::init('Tag')->findById($tagID);
		if (!$tag)
		{
			CookieFlash::set('Tag to edit not found.', 'error');
			return $this->redirect('/users/adminstats');
		}

		$this->set('allTags', $this->getAllTags());
		$this->set('tag', $tag['Tag']);
		return null;
	}

	public function editAction($tagID)
	{
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

		$tag['description'] = $tagDescription;
		$tag['hint'] = (int) ($this->data['tag_hint']);
		$tag['link'] = $this->data['tag_link'];
		ClassRegistry::init('Tag')->save($tag);
		return $this->redirect('/tags/view/' . $tagID);
	}

	/**
	 * @param string|int $id Tag name ID
	 * @return void
	 */
	public function delete($id)
	{
		$this->loadModel('Tag');
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
		if (!Auth::isAdmin())
			return $this->redirect('/');

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
		if (!Auth::isAdmin())
			return $this->redirect('/');

		$tagToReject = ClassRegistry::init('Tag')->findById($tagID);
		if (!$tagToReject)
		{
			CookieFlash::set('Tag to approve not found', 'error');
			return $this->redirect('/users/adminstats');
		}

		$tagToReject = $tagToReject['Tag'];

		if ($tagToReject['Tag']['approved'] == 1)
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
