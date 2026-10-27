<?php

use App\Utility\Util;

//The SGF changes of the admin area (SgfChangesRenderer): every version that became part of a
//problem, with who accepted it, the original author of an accepted proposal, the tick of the admin
//who reviewed it and the avatars of the other admins who did (SgfReview).
class SgfChangesTest extends IntegrationTestCase
{
	private const SGF = '(;GM[1]FF[4]CA[UTF-8]ST[2]SZ[19]AB[cc];B[aa];W[ab];B[ba]C[+])';

	//The admin, the admin who made the changes, another admin who reviews them and a player who proposes
	private function prepare(int $changeCount = 2, array $sgfs = []): ContextPreparator
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'other-users' => [['name' => 'author', 'admin' => true], ['name' => 'colleague', 'admin' => true], ['name' => 'proposer']],
			'tsumego' => [
				'sets' => [['name' => 'Reviewed set', 'num' => 3]],
				'sgfs' => $sgfs ?: array_fill(0, $changeCount, ['data' => self::SGF])]]);
		Util::execute('UPDATE sgf SET user_id = ?', [$this->authorID($context)]);
		Util::execute('UPDATE sgf SET accepted_by_user_id = ? WHERE sgf_status_id = ?', [$this->authorID($context), Sgf::STATUS_ACCEPTED]);
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

	private function proposerID(ContextPreparator $context): int
	{
		return (int) $context->otherUsers[2]['id'];
	}

	//The html of the cell of the change on the page last opened, by the class of the cell
	private function cell(string $class, int $sgfID): string
	{
		$this->assertMatchesRegularExpression('~<tr[^>]*><td class="sgf-review" id="review-' . $sgfID . '">~', $this->view, 'change ' . $sgfID . ' is listed');
		preg_match('~<td class="sgf-review" id="review-' . $sgfID . '">.*?</tr>~s', $this->view, $row);
		preg_match('~<td class="' . $class . '">(.*?)</td>~s', $row[0], $match);
		return $match[1];
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

		$this->get('/admin/sgf-changes?show=all');
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

		$this->get('/admin/sgf-changes?show=all');
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

		$this->get('/admin/sgf-changes?show=all');
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
		Util::execute('UPDATE sgf SET user_id = ?, accepted_by_user_id = ? WHERE id = ?', [$context->user['id'], $context->user['id'], $own]);
		SgfReview::mark($own, $this->colleagueID($context), true);

		$this->get('/admin/sgf-changes?show=all');
		$this->assertStringNotContainsString('sgf-review__tick', $this->reviewCell($own));
		$this->assertStringContainsString('Reviewed by DN_colleague', $this->reviewCell($own), 'the admin sees who reviewed their change');
		$this->assertStringContainsString('sgf-review__tick', $this->reviewCell($other));

		$this->post('/admin/sgf-changes/review/' . $own, ['reviewed' => 1]);
		$this->assertResponseCode(400);
		$this->assertSame([$this->colleagueID($context)], self::reviewerIDs($own));
	}

	//The admin who accepted a proposal answers for it like for their own change.
	public function testAcceptedProposalsAreReviewedByTheOthers(): void
	{
		$context = $this->prepare(1);
		[$accepted] = self::sgfIDs($context);
		Util::execute('UPDATE sgf SET user_id = ?, accepted_by_user_id = ? WHERE id = ?', [$this->proposerID($context), $context->user['id'], $accepted]);

		$this->get('/admin/sgf-changes?show=all');
		$this->assertStringContainsString('sgf-review__own', $this->reviewCell($accepted));
		$this->assertStringContainsString('Not reviewed by me (0)', $this->view);

		$this->post('/admin/sgf-changes/review/' . $accepted, ['reviewed' => 1]);
		$this->assertResponseCode(400);
		$this->assertSame([], self::reviewerIDs($accepted));
	}

	public function testAProposalIsNotReviewed(): void
	{
		$context = $this->prepare(sgfs: [['data' => self::SGF], ['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_PROPOSED]]);
		[, $proposal] = self::sgfIDs($context);

		$this->post('/admin/sgf-changes/review/' . $proposal, ['reviewed' => 1]);
		$this->assertResponseCode(400);
		$this->assertSame([], self::reviewerIDs($proposal));
	}

	public function testListsTheChangesTheAdminDidNotReview(): void
	{
		$context = $this->prepare(sgfs: [
			['data' => self::SGF],
			['data' => self::SGF],
			['data' => self::SGF],
			['data' => self::SGF],
			['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_PROPOSED],
			['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_REJECTED]]);
		[$reviewed, $own, $reviewedByColleague, $acceptedProposal, $proposal, $rejected] = self::sgfIDs($context);
		Util::execute('UPDATE sgf SET user_id = ?, accepted_by_user_id = ? WHERE id = ?', [$context->user['id'], $context->user['id'], $own]);
		Util::execute('UPDATE sgf SET user_id = ?, accepted_by_user_id = ? WHERE id = ?', [$this->proposerID($context), $this->colleagueID($context), $acceptedProposal]);
		SgfReview::mark($reviewed, $context->user['id'], true);
		SgfReview::mark($reviewedByColleague, $this->colleagueID($context), true);

		$this->get('/admin/sgf-changes?show=all');
		$this->assertStringContainsString('SGF changes (4)', $this->view, 'the proposals waiting for a decision and the rejected ones are not changes');
		foreach ([$proposal, $rejected] as $sgfID)
			$this->assertStringNotContainsString('id="review-' . $sgfID . '"', $this->view);

		// the list of what the admin did not review is the first one
		$this->get('/admin/sgf-changes');
		$this->assertStringContainsString('<a class="subnav__link subnav__link--active" href="?show=not-reviewed">Not reviewed by me (2)</a>', $this->view);
		$this->assertStringContainsString('<a class="subnav__link" href="?show=mine">My changes (1)</a>', $this->view);
		$this->assertStringContainsString('<a class="subnav__link" href="?show=all">All changes (4)</a>', $this->view);
		$this->assertStringContainsString('SGF changes you did not review (2)', $this->view);
		$this->assertSame(2, substr_count($this->view, 'class="sgf-review"'));
		$this->reviewCell($reviewedByColleague);
		$this->reviewCell($acceptedProposal);
		foreach ([$reviewed, $own, $proposal, $rejected] as $sgfID)
			$this->assertStringNotContainsString('id="review-' . $sgfID . '"', $this->view);
	}

	//What the admin made part of a problem, with who of the others reviewed it.
	public function testListsTheChangesOfTheAdmin(): void
	{
		$context = $this->prepare(3);
		[$other, $own, $accepted] = self::sgfIDs($context);
		Util::execute('UPDATE sgf SET user_id = ?, accepted_by_user_id = ? WHERE id = ?', [$context->user['id'], $context->user['id'], $own]);
		Util::execute('UPDATE sgf SET user_id = ?, accepted_by_user_id = ? WHERE id = ?', [$this->proposerID($context), $context->user['id'], $accepted]);
		SgfReview::mark($own, $this->colleagueID($context), true);

		$this->get('/admin/sgf-changes?show=mine');
		$this->assertStringContainsString('Your SGF changes (2)', $this->view);
		$this->assertStringContainsString('subnav__link--active" href="?show=mine"', $this->view);
		$this->assertStringContainsString('Reviewed by DN_colleague', $this->reviewCell($own));
		$this->assertStringNotContainsString('sgf-review__reviewer', $this->reviewCell($accepted), 'nobody reviewed it yet');
		$this->assertStringNotContainsString('id="review-' . $other . '"', $this->view);
	}

	public function testAnUnknownListIsRefused(): void
	{
		$this->prepare(1);
		$this->get('/admin/sgf-changes?show=everything');
		$this->assertResponseCode(400);
	}

	public function testListsTheChangesInPages(): void
	{
		$context = $this->prepare(1);
		$tsumegoID = $context->tsumegos[0]['id'];
		for ($i = 0; $i < 100; $i++)
			Util::execute('INSERT INTO sgf (sgf, user_id, accepted_by_user_id, accepted, tsumego_id, sgf_status_id) VALUES (?, ?, ?, NOW(), ?, ?)', [self::SGF, $this->authorID($context), $this->authorID($context), $tsumegoID, Sgf::STATUS_ACCEPTED]);
		[$oldest] = self::sgfIDs($context);
		$newest = (int) Util::query('SELECT MAX(id) AS id FROM sgf')[0]['id'];

		$this->get('/admin/sgf-changes?show=all');
		$this->assertStringContainsString('SGF changes (101)', $this->view);
		$this->assertSame(100, substr_count($this->view, 'class="sgf-review"'));
		$this->assertStringContainsString('Page 1 of 2', $this->view);
		$this->assertMatchesRegularExpression('~<table[^>]*>.*?</tr><tr[^>]*><td class="sgf-review" id="review-' . $newest . '">~s', $this->view, 'the newest version is first');
		$this->assertStringNotContainsString('id="review-' . $oldest . '"', $this->view);

		$this->get('/admin/sgf-changes?show=all&sgf_changes_page=2');
		$this->assertSame(1, substr_count($this->view, 'class="sgf-review"'));
		$this->reviewCell($oldest);
	}

	//A proposal accepted today is a change of today, however long it waited for the decision.
	public function testAnAcceptedProposalIsListedWhenItWasAccepted(): void
	{
		$context = $this->prepare(sgfs: [['data' => self::SGF], ['data' => self::SGF, 'sgf_status_id' => Sgf::STATUS_PROPOSED], ['data' => self::SGF]]);
		[$first, $proposal, $change] = self::sgfIDs($context);
		Util::execute('UPDATE sgf SET user_id = ?, created = NOW() - INTERVAL 3 DAY WHERE id = ?', [$this->proposerID($context), $proposal]);
		Util::execute('UPDATE sgf SET accepted = NOW() - INTERVAL 1 DAY WHERE id IN (?, ?)', [$first, $change]);

		$this->post('/users/acceptSGFProposal/' . $proposal);
		$accepted = ClassRegistry::init('Sgf')->findById($proposal)['Sgf'];
		$this->assertEquals(Sgf::STATUS_ACCEPTED, $accepted['sgf_status_id']);
		$this->assertEquals($context->user['id'], $accepted['accepted_by_user_id']);
		$this->assertEqualsWithDelta(time(), strtotime($accepted['accepted']), 60);

		$this->get('/admin/sgf-changes?show=all');
		$this->assertMatchesRegularExpression('~id="review-' . $proposal . '".*id="review-' . $change . '".*id="review-' . $first . '"~s', $this->view);
		$this->assertStringContainsString('href="/users/view/' . $context->user['id'] . '"', $this->cell('sgf-changes__accepted-by', $proposal));
		$this->assertStringContainsString('href="/users/view/' . $this->proposerID($context) . '"', $this->cell('sgf-changes__author', $proposal));
		$this->assertMatchesRegularExpression('~<time datetime="[^"]+" data-format="datetime" title="Proposed \\d{4}-\\d{2}-\\d{2}~', $this->view);
	}

	//What an admin saves is accepted by them right away, what a player saves waits for an admin.
	public function testWhatAnAdminSavesIsAcceptedByThem(): void
	{
		$context = $this->prepare(1);
		$tsumegoID = (int) $context->tsumegos[0]['id'];

		$this->post('/tsumegos/setupNewSgfStep2', ['sgf' => self::SGF, 'firstMoveColor' => 'B', 'correctMoves' => 'aa', 'setConnectionID' => $context->setConnections[0]['id']]);
		$saved = ClassRegistry::init('Sgf')->find('first', ['conditions' => ['tsumego_id' => $tsumegoID], 'order' => 'id DESC'])['Sgf'];
		$this->assertEquals(Sgf::STATUS_ACCEPTED, $saved['sgf_status_id']);
		$this->assertEquals($context->user['id'], $saved['accepted_by_user_id']);
		$this->assertEqualsWithDelta(time(), strtotime($saved['accepted']), 60);

		$uploaded = ClassRegistry::init('Sgf')->uploadSgf(self::SGF, $tsumegoID, $this->colleagueID($context), true)['Sgf'];
		$this->assertEquals($this->colleagueID($context), $uploaded['accepted_by_user_id']);
		$this->assertNotNull($uploaded['accepted']);

		$proposed = ClassRegistry::init('Sgf')->uploadSgf(self::SGF, $tsumegoID, $this->proposerID($context), false)['Sgf'];
		$this->assertEquals(Sgf::STATUS_PROPOSED, $proposed['sgf_status_id']);
		$this->assertNull($proposed['accepted_by_user_id']);
		$this->assertNull($proposed['accepted']);
	}

	//An admin who saved a version made the change and accepted it, there is no other author.
	public function testAnAdminChangeHasNoOriginalAuthor(): void
	{
		$context = $this->prepare(1);
		[$change] = self::sgfIDs($context);

		$this->get('/admin/sgf-changes?show=all');
		$this->assertStringContainsString('href="/users/view/' . $this->authorID($context) . '"', $this->cell('sgf-changes__accepted-by', $change));
		$this->assertSame('', $this->cell('sgf-changes__author', $change));
		$this->assertStringNotContainsString('title="Proposed', $this->view);
	}

	//Proposals accepted before the site recorded the decision say that the admin is not known.
	public function testAnOldAcceptedProposalHasNoKnownAdmin(): void
	{
		$context = $this->prepare(1);
		[$accepted] = self::sgfIDs($context);
		Util::execute('UPDATE sgf SET user_id = ?, accepted_by_user_id = NULL WHERE id = ?', [$this->proposerID($context), $accepted]);

		$this->get('/admin/sgf-changes?show=all');
		$this->assertStringContainsString('>unknown</span>', $this->cell('sgf-changes__accepted-by', $accepted));
		$this->assertStringContainsString('href="/users/view/' . $this->proposerID($context) . '"', $this->cell('sgf-changes__author', $accepted));
		$this->assertStringContainsString('aria-pressed="false"', $this->reviewCell($accepted), 'any admin reviews it');
	}

	public function testAChangeShowsItsProblemAuthorAndLinks(): void
	{
		$context = $this->prepare(3);
		[$first, $second, $third] = self::sgfIDs($context);
		Util::execute('UPDATE sgf SET description = ? WHERE id = ?', ['Added the ko variation', $third]);

		$this->get('/admin/sgf-changes?show=all');
		$this->assertStringContainsString('<a class="adminpanel-link" href="/sets/' . $context->tsumegos[0]['sets'][0]['id'] . '">Reviewed set</a>', $this->view, 'the number in the collection is on the problem button');
		$this->assertStringNotContainsString('Reviewed set - 3', $this->view);
		$this->assertMatchesRegularExpression('~<a href="/users/view/' . $this->authorID($context) . '"><img [^>]*class="user-avatar~', $this->cell('sgf-changes__accepted-by', $third), 'the admin has an avatar');
		$this->assertStringContainsString('Added the ko variation', $this->view);
		$this->assertStringContainsString('<a class="btn btn--small btn--navigation" id="open-' . $third . '" href="/editor?sgfID=' . $third . '">Open</a>', $this->view);
		$this->assertStringContainsString('<a class="btn btn--small btn--navigation" id="compare-' . $third . '" href="/editor?sgfID=' . $third . '&diffID=' . $second . '">Diff</a>', $this->view);
		$this->assertStringNotContainsString('id="compare-' . $first . '"', $this->view, 'the first version has nothing to compare with');
		$this->assertStringContainsString('btn--disabled', $this->view);
		$this->assertStringNotContainsString('id="download-', $this->view);
	}

	public function testAChangeOfAProblemInNoCollectionIsListed(): void
	{
		$context = $this->prepare(1);
		Util::execute('DELETE FROM set_connection WHERE tsumego_id = ?', [$context->tsumegos[0]['id']]);

		$this->get('/admin/sgf-changes?show=all');
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
		$browser->get('/admin/sgf-changes?show=all');
		$tick = $browser->find('#review-' . $sgfID . ' .sgf-review__tick');
		$this->assertSame('false', $tick->getAttribute('aria-pressed'));

		$tick->click();
		$browser->waitUntilJs('document.querySelector("#review-' . $sgfID . ' .sgf-review__tick").getAttribute("aria-pressed") === "true"');
		$this->assertStringContainsString('sgf-review__tick--reviewed', $tick->getAttribute('class'));
		$this->assertSame([(int) $context->user['id']], self::reviewerIDs($sgfID));

		$browser->get('/admin/sgf-changes?show=all');
		$tick = $browser->find('#review-' . $sgfID . ' .sgf-review__tick');
		$this->assertSame('true', $tick->getAttribute('aria-pressed'), 'the tick stays after a reload');

		$tick->click();
		$browser->waitUntilJs('document.querySelector("#review-' . $sgfID . ' .sgf-review__tick").getAttribute("aria-pressed") === "false"');
		$this->assertSame([], self::reviewerIDs($sgfID));
	}
}
