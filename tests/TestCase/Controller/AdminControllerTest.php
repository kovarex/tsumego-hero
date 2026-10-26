<?php

use App\Utility\Util;

/**
 * The admin page links everything an admin does and counts what waits there (AdminController),
 * the admin activity and the uploads count what is new since the admin last opened them (AdminPage).
 */
class AdminControllerTest extends IntegrationTestCase
{
	private const SGF = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';

	/**
	 * The red count on the link of the admin page to the url, 0 when there is none
	 */
	private function countOnLink(string $url): int
	{
		$this->get('/admin');
		$this->assertMatchesRegularExpression('~<a class="admin-area__link" href="' . preg_quote($url, '~') . '">~', $this->view, $url . ' is linked');
		preg_match('~href="' . preg_quote($url, '~') . '">[^<]*<span class="admin-area__badge"[^>]*>(\d+)</span>~', $this->view, $match);
		return (int) ($match[1] ?? 0);
	}

	/**
	 * Pretends the admin opened the page an hour ago, so what happens now is new to them
	 */
	private static function viewedAnHourAgo(int $adminPageID): void
	{
		Util::execute('UPDATE admin_page_view SET viewed = NOW() - INTERVAL 1 HOUR WHERE admin_page_id = ?', [$adminPageID]);
	}

	private static function logActivity(int $userID): void
	{
		Util::execute('INSERT INTO admin_activity (user_id, type, old_value, new_value) VALUES (?, ?, ?, ?)',
			[$userID, AdminActivityType::DESCRIPTION_EDIT, 'Old desc', 'New desc']);
	}

	public function testCountsWhatWaitsForADecision(): void
	{
		new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'set_order' => 1,
				'sgfs' => [
					['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_ACCEPTED],
					['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_PROPOSED],
					['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_PROPOSED],
					['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_REJECTED]],
				'tags' => [['name' => 'snapback', 'approved' => 0], ['name' => 'ko', 'approved' => 1]],
				'issues' => [['message' => 'The ko is not solved'], ['message' => 'Solved already', 'status' => TsumegoIssue::$CLOSED_STATUS]]],
			'tags' => [['name' => 'two-step ko', 'approved' => 0], ['name' => 'ladder breaker', 'approved' => 0]]]);

		$this->assertSame(2, $this->countOnLink('/admin/sgf-proposals'));
		$this->assertSame(1, $this->countOnLink('/admin/tag-proposals'));
		$this->assertSame(2, $this->countOnLink('/admin/new-tags'));
		$this->assertSame(1, $this->countOnLink('/tsumego-issues'));
		$this->assertSame(0, $this->countOnLink('/tags'));
	}

	/**
	 * No new set has a subtitle, and the lists still name the set of each problem.
	 */
	public function testListsNameASetWithoutSubtitle(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumego' => [
				'sets' => [['name' => 'Set without subtitle', 'num' => 7]],
				'sgfs' => [
					['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_ACCEPTED],
					['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_PROPOSED],
					['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_REJECTED]],
				'tags' => [['name' => 'snapback', 'approved' => 0]]]]);
		Util::execute('INSERT INTO admin_activity (user_id, type, tsumego_id, old_value, new_value) VALUES (?, ?, ?, ?, ?)',
			[$context->user['id'], AdminActivityType::DESCRIPTION_EDIT, $context->tsumegos[0]['id'], 'Old desc', 'New desc']);

		// the proposals and the rejected proposals
		$this->get('/admin/sgf-proposals');
		$this->assertSame(2, substr_count($this->view, 'Set without subtitle - 7'));
		foreach (['/admin/tag-proposals', '/admin/activity'] as $page)
		{
			$this->get($page);
			$this->assertTextContains('Set without subtitle - 7', $this->view, $page);
		}
	}

	public function testNothingWaitsNoCountIsShown(): void
	{
		new ContextPreparator(['user' => ['admin' => true]]);
		$this->get('/admin');
		$this->assertStringNotContainsString('admin-area__badge', $this->view);
	}

	public function testCountsWhatOtherAdminsDidSinceTheLastLook(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'other', 'admin' => true]]]);
		$otherID = $context->otherUsers[0]['id'];

		// before the first look, nothing is new
		self::logActivity($otherID);
		$this->assertSame(0, $this->countOnLink('/admin/activity'));

		$this->get('/admin/activity');
		$this->assertStringNotContainsString('data-table__row--new', $this->view, 'the first look marks nothing');
		self::viewedAnHourAgo(AdminPage::ACTIVITY);
		self::logActivity($otherID);
		self::logActivity($context->user['id']); // what the admin did is not new to them
		$this->assertSame(2, $this->countOnLink('/admin/activity'));

		$this->get('/admin/activity');
		$this->assertSame(2, substr_count($this->view, 'data-table__row--new'), 'what is new is marked in the list');
		$this->assertSame(0, $this->countOnLink('/admin/activity'));
	}

	public function testCountsWhatOthersUploadedSinceTheLastLook(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'proposer']],
			'tsumego' => [
				'set_order' => 1,
				'sgfs' => [
					['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_ACCEPTED],
					['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_ACCEPTED],
					['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_PROPOSED],
					['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_REJECTED]]]]);
		[$oldUpload, , $proposal, $rejected] = array_column($context->tsumegos[0]['sgfs'], 'id');

		$this->get('/users/uploads');
		self::viewedAnHourAgo(AdminPage::UPLOADS);
		Util::execute('UPDATE sgf SET user_id = ? WHERE id IN (?, ?, ?)', [$context->otherUsers[0]['id'], $oldUpload, $proposal, $rejected]);
		Util::execute('UPDATE sgf SET created = NOW() - INTERVAL 2 HOUR WHERE id = ?', [$oldUpload]);
		$this->assertSame(1, $this->countOnLink('/users/uploads'), 'the proposal only: the own upload and the rejected one do not count, the old one was seen');

		$this->get('/users/uploads');
		$this->assertSame(1, substr_count($this->view, 'data-table__row--new'));
		$this->assertStringContainsString('<tr id="' . $proposal . '" class="data-table__row--new">', $this->view);
		$this->assertSame(0, $this->countOnLink('/users/uploads'));
	}

	public function testOnlyOwnersSeeTheLinkToTheRoles(): void
	{
		new ContextPreparator(['user' => ['admin' => true]]);
		$this->get('/admin');
		$this->assertStringNotContainsString('href="/roles"', $this->view);

		new ContextPreparator(['user' => ['owner' => true]]);
		$this->get('/admin');
		$this->assertStringContainsString('href="/roles"', $this->view);
	}

	public function testMenuHasOneLinkToTheAdminPage(): void
	{
		new ContextPreparator(['user' => ['admin' => true]]);
		$this->get('/changelog');
		$page = $this->view;
		$this->assertSame(1, substr_count($page, 'class="adminLink'));
		$this->assertStringContainsString('href="/admin">Admin</a>', $page);
		$this->assertStringNotContainsString('adminLink-more', $page);

		new ContextPreparator(['user' => ['admin' => false]]);
		$this->get('/changelog');
		$page = $this->view;
		$this->assertStringNotContainsString('href="/admin"', $page);
	}

	public function testOldAdminPanelAddressLeadsToTheAdminPage(): void
	{
		new ContextPreparator(['user' => ['admin' => true]]);
		$this->get('/users/adminstats');
		$this->assertStringEndsWith('/admin', $this->headerValue('Location'));
	}
}
