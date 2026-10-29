<?php

use App\Model\Table\SetConnectionTable;
use App\Model\Table\SgfTable;
use App\Model\Entity\Tag;
use App\Model\Table\RejectTable;
use App\Model\Table\TagTable;
use App\Model\Table\UserTable;
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
	private static function approvedTags(): array
	{
		return Util::query("SELECT * FROM tag WHERE approved = 1 ORDER BY tag.name");
	}

	#[HttpGet]
	public function add(): void
	{
		$this->Authorization->authorize('Tag', 'add');
		$allTags = self::approvedTags();
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

		$tagTable = $this->fetchTable('Tag');
		if ($tagTable->exists(['name' => $tagName]))
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

		$tag = $tagTable->saveOrFail($tagTable->newEntity([
			'name' => $tagName,
			'description' => HtmlSanitizer::sanitize((string) $tagDescription),
			'hint' => (int) ($this->request->data['tag_hint'] ?? 0),
			'link' => trim((string) ($this->request->data['tag_reference'] ?? '')),
			'user_id' => Auth::getUserID(),
			'approved' => Auth::isAdmin() ? 1 : 0]));

		CookieFlash::set('Tag "' . $tagName . '" has been added.', 'success');
		return $this->redirect('/tags/view/' . $tag->id);
	}

	#[HttpGet]
	public function view(?string $id = null): void
	{
		$tag = $this->fetchTable('Tag')->find()->where(['id' => (int) $id])->first();
		if (!$tag)
			throw new NotFoundException('Tag not found');
		$user = $tag->user_id ? UserTable::row($tag->user_id) : null;
		$this->set('allTags', self::approvedTags());
		$this->set('tag', $tag);
		$this->set('creator', $user);
		$this->set('canAddTag', $this->Authorization->can('Tag', 'add'));
		$this->set('canDeleteTag', $this->Authorization->can('Tag', 'delete'));
	}

	#[HttpGet]
	public function user(string $id): void
	{
		$viewedUser = UserTable::row((int) $id);
		if (!$viewedUser)
			throw new NotFoundException('User not found');
		$this->assertUserVisible($viewedUser);

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
					. 'ORDER BY ' . SetConnectionTable::displayOrderSql('s2', 'sc2') . ' LIMIT 1) '
				. 'JOIN `set` s ON s.id = sc.set_id '
				. 'WHERE tc.user_id = ? '
				. 'UNION ALL '
				// Proposals (SGF uploads)
				. 'SELECT \'proposal\', IF(sg.sgf_status_id = ' . SgfTable::STATUS_ACCEPTED . ', \'accepted\', \'pending\'), sg.created, \'\', \'\', sg.tsumego_id, CONCAT(s.title, \' - \', sc.num), sg.id '
				. 'FROM sgf sg '
				. 'JOIN set_connection sc ON sc.id = ('
					. 'SELECT sc2.id FROM set_connection sc2 '
					. 'JOIN `set` s2 ON s2.id = sc2.set_id '
					. 'WHERE sc2.tsumego_id = sg.tsumego_id '
					. 'ORDER BY ' . SetConnectionTable::displayOrderSql('s2', 'sc2') . ' LIMIT 1) '
				. 'JOIN `set` s ON s.id = sc.set_id '
				// the rejected ones come from their reject rows below
				. 'WHERE sg.user_id = ? AND sg.sgf_status_id != ' . SgfTable::STATUS_REJECTED . ' '
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
					. 'ORDER BY ' . SetConnectionTable::displayOrderSql('s2', 'sc2') . ' LIMIT 1) '
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
		$this->set('viewedUser', $viewedUser);
		$this->set('count', $count);
		$this->set('pageIndex', $pageIndex);
		$this->set('pageSize', $pageSize);
	}

	#[HttpGet]
	public function edit(string $tagID): ?CakeResponse
	{
		$this->Authorization->authorize('Tag');
		$tag = $this->fetchTable('Tag')->find()->where(['id' => (int) $tagID])->first();
		if (!$tag)
		{
			CookieFlash::set('Tag to edit not found.', 'error');
			return $this->redirect('/tags');
		}

		$this->set('allTags', self::approvedTags());
		$this->set('tag', $tag);
		$this->set('canAddTag', $this->Authorization->can('Tag', 'add'));
		return null;
	}

	#[HttpPost]
	public function editAction(string $tagID)
	{
		$this->Authorization->authorize('Tag', 'editAction');
		$tagTable = $this->fetchTable('Tag');
		$tag = $tagTable->find()->where(['id' => (int) $tagID])->first();
		if (!$tag)
		{
			CookieFlash::set('Tag to edit not found.');
			return $this->redirect('/tags');
		}

		$tagDescription = $this->request->data['tag_description'];
		if (empty($tagDescription))
		{
			CookieFlash::set('Tag description not provided', 'error');
			return $this->redirect('/tags/edit/' . $tagID);
		}

		$tagTable->patchEntity($tag, [
			'description' => HtmlSanitizer::sanitize((string) $tagDescription),
			'hint' => (int) ($this->request->data['tag_hint']),
			'link' => trim((string) ($this->request->data['tag_link'] ?? ''))]);
		$tagTable->saveOrFail($tag);
		return $this->redirect('/tags/view/' . $tagID);
	}

	//Deletes the tag and takes it off every problem.
	#[HttpPost]
	public function delete(string $id): CakeResponse
	{
		$this->Authorization->authorize('Tag');
		$tag = $this->fetchTable('Tag')->find()->where(['id' => (int) $id])->first();
		if (!$tag)
			throw new NotFoundException('Tag not found');

		$problemCount = TagTable::deleteWithConnections($tag);
		CookieFlash::set('Tag "' . $tag->name . '" was deleted from ' . $problemCount . ' ' . ($problemCount === 1 ? 'problem' : 'problems') . '.', 'success');
		return $this->redirect('/tags');
	}

	//Every tag with how many problems carry it; each count opens the list of those problems.
	#[HttpGet]
	public function index(): void
	{
		$this->Authorization->authorize('Tag');
		$sort = ($this->request->query['sort'] ?? '') === 'problems' ? 'problems' : 'name';
		$this->set('_title', 'Tsumego Hero - Tags');
		$this->set('tags', TagTable::listWithProblemCounts($sort === 'problems'));
		$this->set('sort', $sort);
		$this->set('canDeleteTag', $this->Authorization->can('Tag', 'delete'));
	}

	//The problems that carry the tag or have it proposed, as JSON for the tag overview.
	#[HttpGet]
	public function problems(string $id): CakeResponse
	{
		$this->Authorization->authorize('Tag');
		if (!$this->fetchTable('Tag')->exists(['id' => (int) $id]))
			throw new NotFoundException('Tag not found');

		$problems = [];
		foreach (TagTable::problems((int) $id) as $row)
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

		$tagTable = $this->fetchTable('Tag');
		$tagToApprove = $tagTable->find()->where(['id' => (int) $tagID])->first();
		if (!$tagToApprove)
		{
			CookieFlash::set('Tag to approve not found', 'error');
			return $this->redirect('/admin/new-tags');
		}

		if ($tagToApprove->approved)
		{
			CookieFlash::set('Tag to approve was already approved', 'error');
			return $this->redirect('/admin/new-tags');
		}

		$tagToApprove->approved = true;
		$tagTable->saveOrFail($tagToApprove);

		// like a tag on a problem, the proposer only hears about it when the admin had something to say
		$comment = ProposalFeedback::commentFromRequest($this->request->data['comment'] ?? null);
		if ($comment !== '' && $tagToApprove->user_id)
			ProposalFeedback::send(
				$tagToApprove->user_id,
				'Your new tag ' . $tagToApprove->name . ' was accepted by ' . ProposalFeedback::adminName() . '.',
				null,
				null,
				$comment,
				$this->_getEmailer());

		CookieFlash::set('Tag ' . $tagToApprove->name . ' was approved', 'success');
		return $this->redirect('/admin/new-tags');
	}

	#[HttpPost]
	public function rejectTagProposal(string $tagID): CakeResponse
	{
		$this->Authorization->authorize('Tag');

		$tagTable = $this->fetchTable('Tag');
		$tagToReject = $tagTable->find()->where(['id' => (int) $tagID])->first();
		if (!$tagToReject)
		{
			CookieFlash::set('Tag to approve not found', 'error');
			return $this->redirect('/admin/new-tags');
		}

		if ($tagToReject->approved)
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

		$tagTable->deleteOrFail($tagToReject);

		if ($tagToReject->user_id)
		{
			// the tag is gone, the proposer still sees its name among their contributions
			$rejectTable = $this->fetchTable('Reject');
			$rejectTable->saveOrFail($rejectTable->newEntity([
				'reject_type_id' => RejectTable::TYPE_TAG_NAME,
				'tag_name' => $tagToReject->name,
				'user_id' => $tagToReject->user_id,
				'rejected_by_user_id' => Auth::getUserID(),
				'comment' => $comment]));
			ProposalFeedback::send(
				$tagToReject->user_id,
				'Your new tag ' . $tagToReject->name . ' was rejected by ' . ProposalFeedback::adminName() . '.',
				null,
				null,
				$comment,
				$this->_getEmailer());
		}

		CookieFlash::set('Tag ' . $tagToReject->name . ' was rejected', 'success');
		return $this->redirect('/admin/new-tags');
	}
}
