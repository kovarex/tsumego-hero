<?php

use App\Model\Table\RejectTable;
use App\Utility\Auth;
use App\Utility\ContributionRow;
use App\Utility\CookieFlash;
use App\Utility\HtmlSanitizer;
use App\Utility\ProposalFeedback;
use App\Utility\Util;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;

class TagsController extends AppController
{
	#[HttpGet]
	public function add(): void
	{
		$this->Authorization->authorize('Tag', 'add');
		$allTags = $this->_getAllTags();
		$this->set('allTags', $allTags);
	}

	#[HttpPost]
	public function addAction(): CakeResponse
	{
		$this->Authorization->authorize('Tag', 'add');
		$tagName = $this->request->data['tag_name'];
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

		$tagDescription = $this->request->data['tag_description'];
		if (empty($tagDescription))
		{
			CookieFlash::set('Tag description not provided', 'error');
			return $this->redirect('/tags/add');
		}

		$tag = [];
		$tag['name'] = $tagName;
		$tag['description'] = HtmlSanitizer::sanitize((string) $tagDescription);
		$tag['hint'] = (int) $this->request->data['tag_hint'];
		$tag['link'] = trim((string) ($this->request->data['tag_reference'] ?? ''));
		$tag['user_id'] = Auth::getUserID();
		$tag['approved'] = Auth::isAdmin() ? 1 : 0;
		ClassRegistry::init('Tag')->save($tag);
		$saved = ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => $tagName]])['Tag'];

		CookieFlash::set('Tag "' . $tagName . '" has been added.', 'success');
		return $this->redirect('/tags/view/' . $saved['id']);
	}

	#[HttpGet]
	public function view(?string $id = null): void
	{
		$tn = $this->Tag->findById($id);
		if (!$tn)
			throw new NotFoundException('Tag not found');
		$allTags = $this->_getAllTags();
		$user = $this->User->findById($tn['Tag']['user_id']);
		$tn['Tag']['user'] = $user ? $user['User'] : null;
		$this->set('allTags', $allTags);
		$this->set('tn', $tn);
		$this->set('canAddTag', $this->Authorization->can('Tag', 'add'));
		$this->set('canDeleteTag', $this->Authorization->can('Tag', 'delete'));
	}

	#[HttpGet]
	public function user(string $id): void
	{
		$u = $this->User->findById($id);
		if (!$u)
			throw new NotFoundException('User not found');
		$this->assertUserVisible($u['User']);

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
					. 'ORDER BY ' . SetConnection::displayOrderSql('s2', 'sc2') . ' LIMIT 1) '
				. 'JOIN `set` s ON s.id = sc.set_id '
				. 'WHERE tc.user_id = ? '
				. 'UNION ALL '
				// Proposals (SGF uploads)
				. 'SELECT \'proposal\', IF(sg.sgf_status_id = ' . Sgf::STATUS_ACCEPTED . ', \'accepted\', \'pending\'), sg.created, \'\', \'\', sg.tsumego_id, CONCAT(s.title, \' - \', sc.num), sg.id '
				. 'FROM sgf sg '
				. 'JOIN set_connection sc ON sc.id = ('
					. 'SELECT sc2.id FROM set_connection sc2 '
					. 'JOIN `set` s2 ON s2.id = sc2.set_id '
					. 'WHERE sc2.tsumego_id = sg.tsumego_id '
					. 'ORDER BY ' . SetConnection::displayOrderSql('s2', 'sc2') . ' LIMIT 1) '
				. 'JOIN `set` s ON s.id = sc.set_id '
				// the rejected ones come from their reject rows below
				. 'WHERE sg.user_id = ? AND sg.sgf_status_id != ' . Sgf::STATUS_REJECTED . ' '
				. 'UNION ALL '
				// Rejected items, reject_type names them like the rows above; a rejected new tag has no problem
				. 'SELECT rt.name, \'rejected\', r.created, COALESCE(t.id, \'\'), COALESCE(t.name, r.tag_name, \'\'), COALESCE(r.tsumego_id, \'\'), '
				. 'COALESCE(CONCAT(s.title, \' - \', sc.num), \'\'), r.id '
				. 'FROM reject r '
				. 'JOIN reject_type rt ON rt.id = r.reject_type_id '
				. 'LEFT JOIN tag t ON t.id = r.tag_id '
				. 'LEFT JOIN set_connection sc ON sc.id = ('
					. 'SELECT sc2.id FROM set_connection sc2 '
					. 'JOIN `set` s2 ON s2.id = sc2.set_id '
					. 'WHERE sc2.tsumego_id = r.tsumego_id '
					. 'ORDER BY ' . SetConnection::displayOrderSql('s2', 'sc2') . ' LIMIT 1) '
				. 'LEFT JOIN `set` s ON s.id = sc.set_id '
				. 'WHERE r.user_id = ? AND (sc.id IS NOT NULL OR r.reject_type_id = ' . RejectTable::TYPE_TAG_NAME . ') '
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

	#[HttpGet]
	public function edit(string $tagID): ?CakeResponse
	{
		$this->Authorization->authorize('Tag');
		$tag = ClassRegistry::init('Tag')->findById($tagID);
		if (!$tag)
		{
			CookieFlash::set('Tag to edit not found.', 'error');
			return $this->redirect('/tags');
		}

		$this->set('allTags', $this->_getAllTags());
		$this->set('tag', $tag['Tag']);
		$this->set('canAddTag', $this->Authorization->can('Tag', 'add'));
		return null;
	}

	#[HttpPost]
	public function editAction(string $tagID)
	{
		$this->Authorization->authorize('Tag', 'editAction');
		$tag = ClassRegistry::init('Tag')->findById($tagID);
		if (!$tag)
		{
			CookieFlash::set('Tag to edit not found.');
			$this->redirect('/tags');
		}

		$tag = $tag['Tag'];

		$tagDescription = $this->request->data['tag_description'];
		if (empty($tagDescription))
		{
			CookieFlash::set('Tag description not provided', 'error');
			return $this->redirect('/tags/edit/' . $tagID);
		}

		$tag['description'] = HtmlSanitizer::sanitize((string) $tagDescription);
		$tag['hint'] = (int) ($this->request->data['tag_hint']);
		$tag['link'] = trim((string) ($this->request->data['tag_link'] ?? ''));
		ClassRegistry::init('Tag')->save($tag);
		return $this->redirect('/tags/view/' . $tagID);
	}

	//Deletes the tag and takes it off every problem.
	#[HttpPost]
	public function delete(string $id): CakeResponse
	{
		$this->Authorization->authorize('Tag');
		$tag = ClassRegistry::init('Tag')->findById($id);
		if (!$tag)
			throw new NotFoundException('Tag not found');

		$problemCount = Tag::deleteWithConnections($tag['Tag']);
		CookieFlash::set('Tag "' . $tag['Tag']['name'] . '" was deleted from ' . $problemCount . ' ' . ($problemCount === 1 ? 'problem' : 'problems') . '.', 'success');
		return $this->redirect('/tags');
	}

	//Every tag with how many problems carry it; each count opens the list of those problems.
	#[HttpGet]
	public function index(): void
	{
		$this->Authorization->authorize('Tag');
		$sort = ($this->request->query['sort'] ?? '') === 'problems' ? 'problems' : 'name';
		$this->set('_title', 'Tsumego Hero - Tags');
		$this->set('tags', Tag::listWithProblemCounts($sort === 'problems'));
		$this->set('sort', $sort);
		$this->set('canDeleteTag', $this->Authorization->can('Tag', 'delete'));
	}

	//The problems that carry the tag or have it proposed, as JSON for the tag overview.
	#[HttpGet]
	public function problems(string $id): CakeResponse
	{
		$this->Authorization->authorize('Tag');
		if (!ClassRegistry::init('Tag')->findById($id))
			throw new NotFoundException('Tag not found');

		$problems = [];
		foreach (Tag::problems((int) $id) as $row)
			$problems[] = [
				'url' => $row['set_connection_id'] ? '/' . $row['set_connection_id'] : '/tsumegos/play/' . $row['tsumego_id'],
				'name' => $row['set_title'] !== null ? $row['set_title'] . ' ' . $row['num'] : 'Problem ' . $row['tsumego_id'],
				'approved' => (bool) $row['approved']];
		$this->response->type('json');
		$this->response->body(json_encode($problems));
		return $this->response;
	}

	#[HttpPost]
	public function acceptTagProposal(string $tagID): CakeResponse
	{
		$this->Authorization->authorize('Tag');

		$tagToApprove = ClassRegistry::init('Tag')->findById($tagID);
		if (!$tagToApprove)
		{
			CookieFlash::set('Tag to approve not found', 'error');
			return $this->redirect('/admin/new-tags');
		}

		$tagToApprove = $tagToApprove['Tag'];

		if ($tagToApprove['approved'] == 1)
		{
			CookieFlash::set('Tag to approve was already approved', 'error');
			return $this->redirect('/admin/new-tags');
		}

		$tagToApprove['approved'] = '1';
		ClassRegistry::init('Tag')->save($tagToApprove);

		// like a tag on a problem, the proposer only hears about it when the admin had something to say
		$comment = ProposalFeedback::commentFromRequest($this->request->data['comment'] ?? null);
		if ($comment !== '' && $tagToApprove['user_id'])
			ProposalFeedback::send(
				(int) $tagToApprove['user_id'],
				'Your new tag ' . $tagToApprove['name'] . ' was accepted by ' . ProposalFeedback::adminName() . '.',
				null,
				null,
				$comment,
				$this->_getEmailer());

		CookieFlash::set('Tag ' . $tagToApprove['name'] . ' was approved', 'success');
		return $this->redirect('/admin/new-tags');
	}

	#[HttpPost]
	public function rejectTagProposal(string $tagID): CakeResponse
	{
		$this->Authorization->authorize('Tag');

		$tagToReject = ClassRegistry::init('Tag')->findById($tagID);
		if (!$tagToReject)
		{
			CookieFlash::set('Tag to approve not found', 'error');
			return $this->redirect('/admin/new-tags');
		}

		$tagToReject = $tagToReject['Tag'];

		if ($tagToReject['approved'] == 1)
		{
			CookieFlash::set('Tag to reject was already approved', 'error');
			return $this->redirect('/admin/new-tags');
		}

		$comment = ProposalFeedback::commentFromRequest($this->request->data['comment'] ?? null);
		if ($comment === '')
		{
			CookieFlash::set('Write the proposer why the tag is rejected.', 'error');
			return $this->redirect('/admin/new-tags');
		}

		ClassRegistry::init('Tag')->delete($tagToReject);

		if ($tagToReject['user_id'])
		{
			// the tag is gone, the proposer still sees its name among their contributions
			$rejectTable = $this->fetchTable('Reject');
			$rejectTable->saveOrFail($rejectTable->newEntity([
				'reject_type_id' => RejectTable::TYPE_TAG_NAME,
				'tag_name' => $tagToReject['name'],
				'user_id' => $tagToReject['user_id'],
				'rejected_by_user_id' => Auth::getUserID(),
				'comment' => $comment]));
			ProposalFeedback::send(
				(int) $tagToReject['user_id'],
				'Your new tag ' . $tagToReject['name'] . ' was rejected by ' . ProposalFeedback::adminName() . '.',
				null,
				null,
				$comment,
				$this->_getEmailer());
		}

		CookieFlash::set('Tag ' . $tagToReject['name'] . ' was rejected', 'success');
		return $this->redirect('/admin/new-tags');
	}
}
