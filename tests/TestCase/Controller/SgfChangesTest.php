<?php

use App\Utility\Util;

//The SGF changes of the admin area (SgfChangesRenderer): every saved version, with the tick of the
//admin who reviewed it and the avatars of the other admins who did (SgfReview).
class SgfChangesTest extends IntegrationTestCase
{
	private const SGF = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';

	//The admin, the author of the changes and another admin who reviews them
	private function prepare(int $changeCount = 2, array $sgfs = []): ContextPreparator
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'author', 'admin' => true], ['name' => 'colleague', 'admin' => true]],
			'tsumego' => [
				'sets' => [['name' => 'Reviewed set', 'num' => 3]],
				'sgfs' => $sgfs ?: array_fill(0, $changeCount, ['data' => self::SGF])]]);
		Util::execute('UPDATE sgf SET user_id = ?', [$this->authorID($context)]);
		return $context;
	}

	private function authorID(ContextPreparator $context): int
	{
		return (int) $context->otherUsers[0]['id'];
	}

	private function colleagueID(ContextPreparator $context): int
	{
		return (int) $context->otherUsers[1]['id'];
	}

	/** @return list<int> */
	private static function sgfIDs(ContextPreparator $context): array
	{
		return array_map('intval', array_column($context->tsumegos[0]['sgfs'], 'id'));
	}

	//The html of the review cell of the change on the page last opened
	private function reviewCell(int $sgfID): string
	{
		$this->assertMatchesRegularExpression('~<td class="sgf-review" id="review-' . $sgfID . '">.*?</td>~s', $this->view, 'change ' . $sgfID . ' is listed');
		preg_match('~<td class="sgf-review" id="review-' . $sgfID . '">(.*?)</td>~s', $this->view, $match);
		return $match[1];
	}

	/** @return list<int> The users who reviewed the change */
	private static function reviewerIDs(int $sgfID): array
	{
		return array_map('intval', array_column(Util::query('SELECT user_id FROM sgf_review WHERE sgf_id = ? ORDER BY user_id', [$sgfID]), 'user_id'));
	}

	public function testAdminTicksAChangeAndTakesTheTickBack(): void
	{
		$context = $this->prepare();
		[$first, $second] = self::sgfIDs($context);

		$this->get('/admin/sgf-changes');
		$this->assertResponseOk();
		$this->assertStringContainsString('aria-pressed="false"', $this->reviewCell($first));
		$this->assertStringNotContainsString('class="sgf-review__tick sgf-review__tick--reviewed"', $this->view, 'nothing is reviewed yet');

		$this->post('/admin/sgf-changes/review/' . $first, ['reviewed' => 1]);
		$this->assertResponseOk();
		$this->assertSame(['reviewed' => true], json_decode($this->view, true));
		$this->assertSame([(int) $context->user['id']], self::reviewerIDs($first));

		// ticking again changes nothing
		$this->post('/admin/sgf-changes/review/' . $first, ['reviewed' => 1]);
		$this->assertResponseOk();
		$this->assertSame([(int) $context->user['id']], self::reviewerIDs($first));

		$this->get('/admin/sgf-changes');
		$this->assertStringContainsString('sgf-review__tick sgf-review__tick--reviewed', $this->reviewCell($first));
		$this->assertStringContainsString('aria-pressed="false"', $this->reviewCell($second));

		$this->post('/admin/sgf-changes/review/' . $first, ['reviewed' => 0]);
		$this->assertSame(['reviewed' => false], json_decode($this->view, true));
		$this->assertSame([], self::reviewerIDs($first));
	}

	public function testShowsTheOtherAdminsWhoReviewedAChange(): void
	{
		$context = $this->prepare();
		[$first, $second] = self::sgfIDs($context);
		SgfReview::mark($first, $this->colleagueID($context), true);
		SgfReview::mark($first, $context->user['id'], true);

		$this->get('/admin/sgf-changes');
		$cell = $this->reviewCell($first);
		$this->assertSame(1, substr_count($cell, 'class="sgf-review__reviewer"'), 'the colleague, the tick of the admin is the button');
		$this->assertMatchesRegularExpression('~href="/users/view/' . $this->colleagueID($context) . '" title="Reviewed by DN_colleague on \\d{4}-\\d{2}-\\d{2}"~', $cell);
		$this->assertStringContainsString('class="user-avatar', $cell);
		$this->assertStringContainsString('sgf-review__tick--reviewed', $cell);
		$this->assertStringNotContainsString('sgf-review__reviewer', $this->reviewCell($second));
	}

	public function testOwnChangesAreReviewedByTheOthers(): void
	{
		$context = $this->prepare();
		[$own, $other] = self::sgfIDs($context);
		Util::execute('UPDATE sgf SET user_id = ? WHERE id = ?', [$context->user['id'], $own]);
		SgfReview::mark($own, $this->colleagueID($context), true);

		$this->get('/admin/sgf-changes');
		$this->assertStringNotContainsString('sgf-review__tick', $this->reviewCell($own));
		$this->assertStringContainsString('Reviewed by DN_colleague', $this->reviewCell($own), 'the admin sees who reviewed their change');
		$this->assertStringContainsString('sgf-review__tick', $this->reviewCell($other));

		$this->post('/admin/sgf-changes/review/' . $own, ['reviewed' => 1]);
		$this->assertResponseCode(400);
		$this->assertSame([$this->colleagueID($context)], self::reviewerIDs($own));
	}

	public function testListsTheChangesTheAdminDidNotReview(): void
	{
		$context = $this->prepare(sgfs: [
			['data' => self::SGF],
			['data' => self::SGF],
			['data' => self::SGF],
			['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_PROPOSED],
			['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_REJECTED]]);
		[$reviewed, $own, $reviewedByColleague, $proposal, $rejected] = self::sgfIDs($context);
		Util::execute('UPDATE sgf SET user_id = ? WHERE id = ?', [$context->user['id'], $own]);
		SgfReview::mark($reviewed, $context->user['id'], true);
		SgfReview::mark($reviewedByColleague, $this->colleagueID($context), true);

		$this->get('/admin/sgf-changes');
		$this->assertStringContainsString('SGF changes (4)', $this->view, 'the rejected proposal is not a change');
		$this->assertStringContainsString('href="?not_reviewed=1">Not reviewed by me (2)</a>', $this->view);

		$this->get('/admin/sgf-changes?not_reviewed=1');
		$this->assertStringContainsString('SGF changes you did not review (2)', $this->view);
		$this->assertSame(2, substr_count($this->view, 'class="sgf-review"'));
		$this->reviewCell($reviewedByColleague);
		$this->reviewCell($proposal);
		$this->assertStringContainsString('>proposal</a>', $this->view, 'a proposal waiting for a decision says so');
		foreach ([$reviewed, $own, $rejected] as $sgfID)
			$this->assertStringNotContainsString('id="review-' . $sgfID . '"', $this->view);
	}

	public function testListsTheChangesInPages(): void
	{
		$context = $this->prepare(1);
		$tsumegoID = $context->tsumegos[0]['id'];
		for ($i = 0; $i < 100; $i++)
			Util::execute('INSERT INTO sgf (sgf, user_id, tsumego_id, sgf_status_id) VALUES (?, ?, ?, ?)', [self::SGF, $this->authorID($context), $tsumegoID, Sgf::STATUS_ACCEPTED]);
		[$oldest] = self::sgfIDs($context);

		$this->get('/admin/sgf-changes');
		$this->assertStringContainsString('SGF changes (101)', $this->view);
		$this->assertSame(100, substr_count($this->view, 'class="sgf-review"'));
		$this->assertStringContainsString('Page 1 of 2', $this->view);
		$this->assertStringContainsString('v101</a>', $this->view, 'the newest version is first');
		$this->assertStringNotContainsString('id="review-' . $oldest . '"', $this->view);

		$this->get('/admin/sgf-changes?sgf_changes_page=2');
		$this->assertSame(1, substr_count($this->view, 'class="sgf-review"'));
		$this->reviewCell($oldest);
		$this->assertStringContainsString('v1</a>', $this->view);
	}

	public function testAChangeShowsItsProblemAuthorAndLinks(): void
	{
		$context = $this->prepare(3);
		[$first, $second, $third] = self::sgfIDs($context);
		Util::execute('UPDATE sgf SET description = ? WHERE id = ?', ['Added the ko variation', $third]);

		$this->get('/admin/sgf-changes');
		$this->assertStringContainsString('Reviewed set - 3', $this->view);
		$this->assertMatchesRegularExpression('~<a href="/users/view/' . $this->authorID($context) . '"><img [^>]*class="user-avatar~', $this->view, 'the author has an avatar');
		$this->assertStringContainsString('Added the ko variation', $this->view);
		$this->assertStringContainsString('<a id="open-' . $third . '" href="/editor?sgfID=' . $third . '">open</a>', $this->view);
		$this->assertStringContainsString('<a id="compare-' . $third . '" href="/editor?sgfID=' . $third . '&diffID=' . $second . '">diff</a>', $this->view);
		$this->assertStringNotContainsString('id="compare-' . $first . '"', $this->view, 'the first version has nothing to compare with');
		$this->assertStringContainsString('<a id="download-' . $third . '" href="/sgf/fetch/' . $third . '" download="Reviewed set 3 v3.sgf">download</a>', $this->view);
	}

	public function testAChangeOfAProblemInNoCollectionIsListed(): void
	{
		$context = $this->prepare(1);
		Util::execute('DELETE FROM set_connection WHERE tsumego_id = ?', [$context->tsumegos[0]['id']]);

		$this->get('/admin/sgf-changes');
		$this->assertResponseOk();
		$this->assertStringContainsString('Problem ' . $context->tsumegos[0]['id'] . ' (in no collection)', $this->view);
	}

	public function testTheUploadsMovedToTheSgfChanges(): void
	{
		new ContextPreparator(['user' => ['admin' => true]]);
		$this->get('/users/uploads');
		$this->assertRedirectContains('/admin/sgf-changes');
	}

	public function testTheTickIsSavedFromThePage(): void
	{
		$context = $this->prepare(1);
		[$sgfID] = self::sgfIDs($context);

		$browser = Browser::instance();
		$browser->get('/admin/sgf-changes');
		$tick = $browser->find('#review-' . $sgfID . ' .sgf-review__tick');
		$this->assertSame('false', $tick->getAttribute('aria-pressed'));

		$tick->click();
		$browser->waitUntilJs('document.querySelector("#review-' . $sgfID . ' .sgf-review__tick").getAttribute("aria-pressed") === "true"');
		$this->assertStringContainsString('sgf-review__tick--reviewed', $tick->getAttribute('class'));
		$this->assertSame([(int) $context->user['id']], self::reviewerIDs($sgfID));

		$browser->get('/admin/sgf-changes');
		$tick = $browser->find('#review-' . $sgfID . ' .sgf-review__tick');
		$this->assertSame('true', $tick->getAttribute('aria-pressed'), 'the tick stays after a reload');

		$tick->click();
		$browser->waitUntilJs('document.querySelector("#review-' . $sgfID . ' .sgf-review__tick").getAttribute("aria-pressed") === "false"');
		$this->assertSame([], self::reviewerIDs($sgfID));
	}
}
