<?php

//Server-side auth guards: anonymous -> 401, logged-in-without-permission -> 403.
use App\Utility\Constants;

class AdminGuardTest extends IntegrationTestCase
{
	// ── UsersController admin pages ──

	private const ADMIN_AREA_PAGES = ['/admin', '/admin/activity', '/admin/sgf-proposals', '/admin/sgf-changes', '/admin/tag-proposals', '/admin/new-tags', '/admin/deleted-accounts'];

	public function testAdminAreaRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		foreach (self::ADMIN_AREA_PAGES as $url)
		{
			$this->get($url);
			$this->assertResponseCode(401, $url . ' let a guest through');
		}
	}

	public function testPreferenceActionsRequireLogin()
	{
		new ContextPreparator(['user' => null]);

		// The avatar endpoint is guarded the same way, see AvatarPreferenceTest::testRequiresLogin
		foreach (['/users/playerColorPreference', '/users/boardOrientationPreference', '/users/dateFormatPreference', '/users/emailPreference'] as $url)
		{
			$this->post($url, ['color' => 1, 'orientation' => 1, 'format' => 1, 'User' => ['email' => 'guest@example.com']]);
			$this->assertResponseCode(401, $url . ' let a guest through');
		}
	}

	public function testUserProposalActionsRequireAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		foreach ([
			'/users/acceptSGFProposal/1',
			'/users/rejectSGFProposal/1',
			'/users/acceptTagConnectionProposal/1',
			'/users/rejectTagConnectionProposal/1',
			'/users/reopenSGFProposal/1',
		] as $url)
		{
			$this->post($url);
			$this->assertResponseCode(403, $url . ' let a player who is not an admin through');
		}
	}

	public function testAdminAreaRequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		foreach (self::ADMIN_AREA_PAGES as $url)
		{
			$this->get($url);
			$this->assertResponseCode(403, $url . ' let a player who is not an admin through');
		}
	}

	public function testReviewOfAnSgfChangeRequiresAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'tsumego' => ['sgf' => '(;GM[1]FF[4]SZ[19])']]);
		$url = '/admin/sgf-changes/review/' . $context->tsumegos[0]['sgfs'][0]['id'];

		$this->post($url, ['reviewed' => 1]);
		$this->assertResponseCode(403);

		$this->logout();
		$this->post($url, ['reviewed' => 1]);
		$this->assertResponseCode(401);
		$this->assertSame(0, $this->fetchTable('SgfReview')->find()->count());
	}

	public function testUserstatsRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->get('/users/userstats');

		$this->assertResponseCode(401);
	}

	public function testUserstatsRequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		$this->get('/users/userstats');

		$this->assertResponseCode(403);
	}

	public function testUserstats3RequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->get('/users/userstats3');

		$this->assertResponseCode(401);
	}

	public function testUserstats3RequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		$this->get('/users/userstats3');

		$this->assertResponseCode(403);
	}

	public function testAdminAreaAllowsAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'admin', 'admin' => true]]);

		foreach (self::ADMIN_AREA_PAGES as $url)
		{
			$this->get($url);
			$this->assertResponseCode(200, $url);
		}
	}

	// ── SgfsController::view ──

	public function testSgfViewRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->get('/sgfs/view/1');

		$this->assertResponseCode(401);
	}

	public function testSgfViewRequiresAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'tsumego' => ['set_order' => 1],
		]);

		$this->get('/sgfs/view/' . $context->tsumegos[0]['id']);

		$this->assertResponseCode(403);
	}

	// ── TagsController::delete ──

	public function testTagDeleteRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->post('/tags/delete/1');

		$this->assertResponseCode(401);
	}

	public function testTagDeleteRequiresAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'tags' => [['name' => 'atari']],
		]);

		$this->post('/tags/delete/' . $context->tags[0]->id);

		$this->assertResponseCode(403);
	}

	// ── SetsController::sandbox ──

	public function testSandboxRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->get('/sets/sandbox');

		$this->assertResponseCode(401);
	}

	public function testSandboxRequiresPremiumOrAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		$this->get('/sets/sandbox');

		$this->assertResponseCode(403);
	}

	// ── SetsController::view private sets ──

	public function testPrivateSetViewRequiresLogin()
	{
		new ContextPreparator([
			'user' => null,
			'tsumegos' => [['sets' => [['name' => 'private set', 'public' => 0, 'num' => '1']]]],
		]);

		$this->get('/sets/view/' . $this->privateSetId());

		$this->assertResponseCode(401);
	}

	private function privateSetId()
	{
		return ClassRegistry::init('Set')->find('first', ['conditions' => ['public' => 0]])['Set']['id'];
	}

	// ── TsumegosController::performMerge ──

	public function testPerformMergeRequiresAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'tsumego' => ['set_order' => 1],
		]);

		$this->post('/tsumegos/performMerge', [
			'master-tsumego-id' => $context->tsumegos[0]['id'],
			'slave-tsumego-id' => $context->tsumegos[0]['id'],
		]);

		$this->assertResponseCode(403);
	}

	// ── TsumegosController::play ?requestSolution ──

	public function testRequestSolutionRequiresLogin()
	{
		$context = new ContextPreparator(['user' => null, 'tsumego' => ['set_order' => 1]]);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id'] . '?requestSolution=1');

		$this->assertResponseCode(401);
	}

	public function testRequestSolutionRequiresAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'tsumego' => ['set_order' => 1],
		]);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id'] . '?requestSolution=1');

		$this->assertResponseCode(403);
	}

	public function testRequestSolutionAllowsAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'admin', 'admin' => true],
			'tsumego' => ['set_order' => 1],
		]);
		$adminActivity = $this->fetchTable('AdminActivity');
		$before = $adminActivity->find()->count();

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id'] . '?requestSolution=1');

		$this->assertResponseCode(200);
		$this->assertSame($before + 1, $adminActivity->find()->count());
	}

	// ── ScheduleController::index ──

	public function testScheduleIndexRequiresAdmin()
	{
		new ContextPreparator(['user' => ['name' => 'regular', 'admin' => false]]);

		$this->get('/schedule');

		$this->assertResponseCode(403);
	}

	// ── TagsController::edit / editAction ──

	public function testTagEditRequiresAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'tags' => [['name' => 'atari']],
		]);

		$this->get('/tags/edit/' . $context->tags[0]->id);

		$this->assertResponseCode(403);
	}

	public function testTagEditActionRequiresAdmin()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'regular', 'admin' => false],
			'tags' => [['name' => 'atari']],
		]);

		$this->post('/tags/editAction/' . $context->tags[0]->id, ['tag_description' => 'test']);

		$this->assertResponseCode(403);
	}

	// ── TagsController::add / addAction ──

	public function testTagAddRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->get('/tags/add');

		$this->assertResponseCode(401);
	}

	public function testTagAddRequiresContribution()
	{
		new ContextPreparator(['user' => ['name' => 'lowrating', 'rating' => 100]]);

		$this->get('/tags/add');

		$this->assertResponseCode(403);
	}

	public function testTagAddActionRequiresLogin()
	{
		new ContextPreparator(['user' => null]);

		$this->post('/tags/addAction', ['tag_name' => 'foo', 'tag_description' => 'bar']);

		$this->assertResponseCode(401);
	}

	public function testTagAddActionRequiresContribution()
	{
		new ContextPreparator(['user' => ['name' => 'lowrating', 'rating' => 100]]);

		$this->post('/tags/addAction', ['tag_name' => 'foo', 'tag_description' => 'bar']);

		$this->assertResponseCode(403);
	}

	public function testTagAddActionSucceedsForContributor()
	{
		new ContextPreparator(['user' => ['name' => 'contributor', 'rating' => Constants::$MINIMUM_RATING_TO_CONTRIBUTE]]);

		$this->post('/tags/addAction', ['tag_name' => 'contribution tag', 'tag_description' => 'desc']);

		$tag = $this->fetchTable('Tag')->find()->where(['name' => 'contribution tag'])->first();
		$this->assertNotNull($tag);
		$this->assertFalse($tag->approved);
	}

	// ── SGF proposals require the contribution capability ──

	public function testSgfUploadRequiresContribution()
	{
		new ContextPreparator(['user' => ['name' => 'lowrating', 'rating' => 100]]);

		$this->post('/sgf/upload/1', ['sgfForBesogo' => '(;GM[1])']);

		$this->assertResponseCode(403);
	}

	public function testSetupNewSgfStep2RequiresContribution()
	{
		new ContextPreparator(['user' => ['name' => 'lowrating', 'rating' => 100]]);

		$this->post('/tsumegos/setupNewSgfStep2', ['setConnectionID' => 1]);

		$this->assertResponseCode(403);
	}
}
