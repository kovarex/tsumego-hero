<?php

use App\Model\Table\RejectsTable;
use App\Utility\Constants;

//The tabs over a player's pages: a player's own content is one click away, and a tab that leads
//to nothing is not there at all.
class ProfileTabsTest extends IntegrationTestCase
{
	public function testProfileLinksToThePublicCollectionsOfAPlayer(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$bobbyId = $context->otherUsers[0]['id'];
		$this->_createSet('Bobby Collection', $bobbyId, 1);
		$this->login('alice');

		$this->get('/users/view/' . $bobbyId);
		$page = $this->view;

		$this->assertStringContainsString('href="/sets/user/' . $bobbyId . '"', $page);
	}

	public function testProfileLeavesTheCollectionsTabOutWhenOnlyPrivateCollectionsExist(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$bobbyId = $context->otherUsers[0]['id'];
		$this->_createSet('Bobby Collection', $bobbyId, 0);
		$this->login('alice');

		$this->get('/users/view/' . $bobbyId);
		$page = $this->view;

		$this->assertStringNotContainsString('href="/sets/user/' . $bobbyId . '"', $page);
	}

	public function testProfileLeavesTheCollectionsTabOutForAPlayerWithoutCollections(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$this->login('alice');

		$this->get('/users/view/' . $context->otherUsers[0]['id']);
		$page = $this->view;

		$this->assertStringNotContainsString('href="/sets/user/' . $context->otherUsers[0]['id'] . '"', $page);
	}

	public function testOwnProfileHasTheCollectionsTabForPrivateCollections(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$this->_createSet('My Collection', $context->user['id'], 0);
		$this->login('alice');

		$this->get('/users/view/' . $context->user['id']);
		$page = $this->view;

		$this->assertStringContainsString('href="/sets/user/' . $context->user['id'] . '"', $page);
	}

	public function testProfileLeavesTheContributionsTabOutForAPlayerWithoutContributions(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$this->login('alice');

		$this->get('/users/view/' . $context->otherUsers[0]['id']);
		$page = $this->view;

		$this->assertStringNotContainsString('href="/tags/user/' . $context->otherUsers[0]['id'] . '"', $page);
	}

	public function testProfileLinksToTheContributionsOfAPlayerWhoMadeSome(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$bobbyId = $context->otherUsers[0]['id'];
		ClassRegistry::init('Tag')->create();
		ClassRegistry::init('Tag')->save(['Tag' => [
			'name' => 'tesuji',
			'description' => 'A tesuji problem',
			'user_id' => $bobbyId,
			'approved' => 1,
		]]);
		$this->login('alice');

		$this->get('/users/view/' . $bobbyId);
		$page = $this->view;

		$this->assertStringContainsString('href="/tags/user/' . $bobbyId . '"', $page);
	}

	public function testProfileLinksToTheContributionsOfAPlayerWhoseNewTagWasRejected(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob']],
		]);
		$bobbyId = $context->otherUsers[0]['id'];
		$rejects = $this->fetchTable('Rejects');
		$rejects->saveOrFail($rejects->newEntity(['user_id' => $bobbyId, 'reject_type_id' => RejectsTable::TYPE_TAG_NAME, 'tag_name' => 'tesuji']));
		$this->login('alice');

		$this->get('/users/view/' . $bobbyId);

		$this->assertStringContainsString('href="/tags/user/' . $bobbyId . '"', $this->view);
	}

	public function testTheCollectionsPageCarriesTheSameTabs(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);
		$userId = $context->user['id'];
		$this->_createSet('My Collection', $userId, 0);
		$this->login('alice');

		$this->get('/sets/user/' . $userId);
		$page = $this->view;

		$this->assertStringContainsString('href="/users/solveHistory/' . $userId . '"', $page);
		$this->assertStringContainsString(
			'href="/sets/user/' . $userId . '" class="subnav__link subnav__link--active"',
			$page);
	}

	public function testEveryTabCarriesTheSameHeader(): void
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob', 'display_name' => 'Bobby']],
		]);
		$bobbyId = $context->otherUsers[0]['id'];
		$this->_createSet('Bobby Collection', $bobbyId, 1);
		ClassRegistry::init('Tag')->create();
		ClassRegistry::init('Tag')->save(['Tag' => [
			'name' => 'tesuji',
			'description' => 'A tesuji problem',
			'user_id' => $bobbyId,
			'approved' => 1,
		]]);
		$this->login('alice');

		foreach ([
			'/users/view/' . $bobbyId,
			'/users/solveHistory/' . $bobbyId,
			'/tags/user/' . $bobbyId,
			'/achievements/user/' . $bobbyId,
			'/sets/user/' . $bobbyId,
		] as $address)
		{
			$this->get($address);
			$page = $this->view;
			$this->assertStringContainsString('user-avatar--lg', $page, $address . ' shows the avatar');
			$this->assertStringContainsString('Bobby', $page, $address . ' names the player');
		}
	}

	private function _createSet(string $title, ?int $userId, int $public): int
	{
		$setModel = ClassRegistry::init('Set');
		$setModel->create();
		$setModel->save([
			'Set' => [
				'title' => $title,
				'user_id' => $userId,
				'public' => $public,
				'order' => Constants::$DEFAULT_SET_ORDER,
			],
		]);
		return (int) $setModel->id;
	}
}
