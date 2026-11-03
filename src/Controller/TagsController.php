<?php

namespace App\Controller;

use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use App\Model\Entity\Tag;
use App\Model\Table\RejectTable;
use App\Model\Table\TagTable;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\ContributionRow;
use App\Utility\CookieFlash;
use App\Utility\HtmlSanitizer;
use App\Utility\ProposalFeedback;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;

//The tags of the problems: their pages, proposing a new one and an admin deciding on it, and the
//contributions page of a player (user()).
class TagsController extends AppController
{
	#[HttpGet]
	public function add(): void
	{
		$this->Authorization->authorize('Tag', 'add');
		$this->set('allTags', TagTable::approved());
	}

	#[HttpPost]
	public function addAction(): Response
	{
		$this->Authorization->authorize('Tag', 'add');
		$tagName = $this->input('tag_name');
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

		$tagDescription = $this->input('tag_description');
		if (empty($tagDescription))
		{
			CookieFlash::set('Tag description not provided', 'error');
			return $this->redirect('/tags/add');
		}

		$tag = $tagTable->saveOrFail($tagTable->newEntity([
			'name' => $tagName,
			...$this->describingFields('tag_reference'),
			'user_id' => Auth::getUserID(),
			'approved' => Auth::isAdmin() ? 1 : 0]));

		CookieFlash::set('Tag "' . $tagName . '" has been added.', 'success');
		return $this->redirect('/tags/view/' . $tag->id);
	}

	#[HttpGet]
	public function view(?string $id = null): void
	{
		$tag = $this->findTag($id) ?? throw new NotFoundException('Tag not found');
		$user = $tag->user_id ? UserTable::row($tag->user_id) : null;
		$this->set('allTags', TagTable::approved());
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
		$pageIndex = $this->query('page') !== null ? max(1, (int) $this->query('page')) : 1;

		[$contributions, $count] = ContributionRow::ofUser((int) $viewedUser['id'], $pageSize, ($pageIndex - 1) * $pageSize);
		$this->set('contributions', $contributions);
		$this->set('viewedUser', $viewedUser);
		$this->set('count', $count);
		$this->set('pageIndex', $pageIndex);
		$this->set('pageSize', $pageSize);
	}

	#[HttpGet]
	public function edit(string $tagID): ?Response
	{
		$this->Authorization->authorize('Tag');
		$tag = $this->findTag($tagID);
		if (!$tag)
		{
			CookieFlash::set('Tag to edit not found.', 'error');
			return $this->redirect('/tags');
		}

		$this->set('allTags', TagTable::approved());
		$this->set('tag', $tag);
		$this->set('canAddTag', $this->Authorization->can('Tag', 'add'));
		return null;
	}

	#[HttpPost]
	public function editAction(string $tagID): Response
	{
		$this->Authorization->authorize('Tag', 'editAction');
		$tagTable = $this->fetchTable('Tag');
		$tag = $this->findTag($tagID);
		if (!$tag)
		{
			CookieFlash::set('Tag to edit not found.');
			return $this->redirect('/tags');
		}

		$tagDescription = $this->input('tag_description');
		if (empty($tagDescription))
		{
			CookieFlash::set('Tag description not provided', 'error');
			return $this->redirect('/tags/edit/' . $tagID);
		}

		$tagTable->saveOrFail($tagTable->patchEntity($tag, $this->describingFields('tag_link')));
		return $this->redirect('/tags/view/' . $tagID);
	}

	//Deletes the tag and takes it off every problem.
	#[HttpPost]
	public function delete(string $id): Response
	{
		$this->Authorization->authorize('Tag');
		$tag = $this->findTag($id) ?? throw new NotFoundException('Tag not found');
		$problemCount = TagTable::deleteWithConnections($tag);
		CookieFlash::set('Tag "' . $tag->name . '" was deleted from ' . $problemCount . ' ' . ($problemCount === 1 ? 'problem' : 'problems') . '.', 'success');
		return $this->redirect('/tags');
	}

	//Every tag with how many problems carry it; each count opens the list of those problems.
	#[HttpGet]
	public function index(): void
	{
		$this->Authorization->authorize('Tag');
		$sort = ($this->query('sort') ?? '') === 'problems' ? 'problems' : 'name';
		$this->set('_title', 'Tsumego Hero - Tags');
		$this->set('tags', TagTable::listWithProblemCounts($sort === 'problems'));
		$this->set('sort', $sort);
		$this->set('canDeleteTag', $this->Authorization->can('Tag', 'delete'));
	}

	//The problems that carry the tag or have it proposed, as JSON for the tag overview.
	#[HttpGet]
	public function problems(string $id): Response
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
		return $this->respondJson($problems);
	}

	#[HttpPost]
	public function acceptTagProposal(string $tagID): Response
	{
		$this->Authorization->authorize('Tag');
		$tag = $this->findProposedTag($tagID, 'approve');
		if (!$tag)
			return $this->redirect('/admin/new-tags');

		$tag->approved = true;
		$this->fetchTable('Tag')->saveOrFail($tag);

		// like a tag on a problem, the proposer only hears about it when the admin had something to say
		$comment = ProposalFeedback::commentFromRequest($this->input('comment'));
		if ($comment !== '' && $tag->user_id)
			$this->tellProposer($tag, 'accepted', $comment);

		CookieFlash::set('Tag ' . $tag->name . ' was approved', 'success');
		return $this->redirect('/admin/new-tags');
	}

	#[HttpPost]
	public function rejectTagProposal(string $tagID): Response
	{
		$this->Authorization->authorize('Tag');
		$tag = $this->findProposedTag($tagID, 'reject');
		if (!$tag)
			return $this->redirect('/admin/new-tags');

		$comment = ProposalFeedback::commentFromRequest($this->input('comment'));
		if ($comment === '')
		{
			CookieFlash::set('Write the proposer why the tag is rejected.', 'error');
			return $this->redirect('/admin/new-tags');
		}

		$this->fetchTable('Tag')->deleteOrFail($tag);
		if ($tag->user_id)
		{
			// the tag is gone, the proposer still sees its name among their contributions
			$rejectTable = $this->fetchTable('Reject');
			$rejectTable->saveOrFail($rejectTable->newEntity([
				'reject_type_id' => RejectTable::TYPE_TAG_NAME,
				'tag_name' => $tag->name,
				'user_id' => $tag->user_id,
				'rejected_by_user_id' => Auth::getUserID(),
				'comment' => $comment]));
			$this->tellProposer($tag, 'rejected', $comment);
		}

		CookieFlash::set('Tag ' . $tag->name . ' was rejected', 'success');
		return $this->redirect('/admin/new-tags');
	}

	private function findTag(?string $id): ?Tag
	{
		return $this->fetchTable('Tag')->find()->where(['id' => (int) $id])->first();
	}

	//The proposed tag the admin decides on, null with the reason in the flash when it isn't one.
	//$decision: 'approve' or 'reject', for the flash
	private function findProposedTag(string $id, string $decision): ?Tag
	{
		$tag = $this->findTag($id);
		if (!$tag)
			CookieFlash::set('Tag to ' . $decision . ' not found', 'error');
		elseif ($tag->approved)
			CookieFlash::set('Tag to ' . $decision . ' was already approved', 'error');
		else
			return $tag;
		return null;
	}

	//$decision: 'accepted' or 'rejected'
	private function tellProposer(Tag $tag, string $decision, string $comment): void
	{
		ProposalFeedback::send(
			$tag->user_id,
			'Your new tag ' . $tag->name . ' was ' . $decision . ' by ' . ProposalFeedback::adminName() . '.',
			null,
			null,
			$comment,
			$this->_getEmailer());
	}

	//What the add and edit forms say about a tag: the description, whether it is a hint, and a link to read more.
	//$linkInput: The name of the link field of the form
	/** @return array{description: string, hint: int, link: string} */
	private function describingFields(string $linkInput): array
	{
		return [
			'description' => HtmlSanitizer::sanitize((string) $this->input('tag_description')),
			'hint' => (int) $this->input('tag_hint'),
			'link' => trim((string) ($this->input($linkInput) ?? '')),
		];
	}
}
