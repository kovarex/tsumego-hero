<?php

use App\Model\Table\FriendshipStatusTable;
use App\Model\Table\FriendshipTable;
use App\Model\Table\TimeModeRankTable;
use App\Model\Table\TimeModeSessionStatusTable;
use App\Utility\FriendActivity;
use App\Utility\HeaderCounts;
use App\Utility\Util;

//Friends: one asks, the other accepts or declines, either ends it; friends see on the homepage
//what the other did lately, and nobody else does.
class FriendsControllerTest extends IntegrationTestCase
{
	private function prepare(): void
	{
		new ContextPreparator([
			'user' => ['name' => 'alice'],
			'other-users' => [['name' => 'bob'], ['name' => 'carol']],
			'time-mode-ranks' => ['5k'],
		]);
	}

	private function userId(string $name): int
	{
		return (int) ContextPreparator::getUserIdFromName($name);
	}

	private function state(string $viewer, string $other): string
	{
		return FriendshipTable::stateBetween($this->userId($viewer), $this->userId($other));
	}

	private function befriend(string $asker, string $asked): void
	{
		Util::execute('INSERT INTO friendship (user_id, friend_user_id, friendship_status_id, accepted) VALUES (?, ?, ?, NOW())',
			[$this->userId($asker), $this->userId($asked), FriendshipStatusTable::ACCEPTED]);
	}

	public function testAFriendshipNeedsTheYesOfTheAskedPlayer(): void
	{
		$this->prepare();

		$this->post('/friends/add/' . $this->userId('bob'));
		$this->assertRedirect('/users/view/' . $this->userId('bob'));
		$this->assertSame(FriendshipTable::ASKED_BY_VIEWER, $this->state('alice', 'bob'));
		$this->assertSame(1, HeaderCounts::forUser($this->userId('bob'))->friendRequests);
		$this->assertSame(0, HeaderCounts::forUser($this->userId('alice'))->friendRequests);

		// the asker can't answer for the other one
		$this->post('/friends/accept/' . $this->userId('bob'));
		$this->assertSame(FriendshipTable::ASKED_BY_VIEWER, $this->state('alice', 'bob'));

		$this->login('bob');
		$this->get('/friends');
		$this->assertResponseOk();
		$this->assertResponseContains('id="friend-requests"');
		$this->assertResponseContains('DN_alice');

		$this->post('/friends/accept/' . $this->userId('alice'), ['from' => 'friends']);
		$this->assertRedirect('/friends');
		$this->assertSame(FriendshipTable::FRIENDS, $this->state('alice', 'bob'));
		$this->assertSame(FriendshipTable::FRIENDS, $this->state('bob', 'alice'));
		$this->assertSame(0, HeaderCounts::forUser($this->userId('bob'))->friendRequests);
	}

	public function testAskingAPlayerWhoAskedYouMakesYouFriends(): void
	{
		$this->prepare();
		$this->post('/friends/add/' . $this->userId('bob'));

		$this->login('bob');
		$this->post('/friends/add/' . $this->userId('alice'));

		$this->assertSame(FriendshipTable::FRIENDS, $this->state('alice', 'bob'));
		$this->assertSame(1, (int) Util::query('SELECT COUNT(*) AS count FROM friendship')[0]['count']);
	}

	public function testTheSecondRowOfAPairIsRefused(): void
	{
		$this->prepare();
		FriendshipTable::ask($this->userId('alice'), $this->userId('bob'));

		$this->expectException(PDOException::class);
		Util::execute('INSERT INTO friendship (user_id, friend_user_id, friendship_status_id) VALUES (?, ?, ?)',
			[$this->userId('bob'), $this->userId('alice'), FriendshipStatusTable::PENDING]);
	}

	public function testDecliningTakingBackAndRemovingEndIt(): void
	{
		$this->prepare();

		// declined
		$this->post('/friends/add/' . $this->userId('bob'));
		$this->login('bob');
		$this->post('/friends/remove/' . $this->userId('alice'));
		$this->assertSame(FriendshipTable::NONE, $this->state('alice', 'bob'));

		// taken back
		$this->post('/friends/add/' . $this->userId('alice'));
		$this->post('/friends/remove/' . $this->userId('alice'));
		$this->assertSame(FriendshipTable::NONE, $this->state('alice', 'bob'));

		// removed by either side
		$this->befriend('alice', 'bob');
		$this->post('/friends/remove/' . $this->userId('alice'));
		$this->assertSame(FriendshipTable::NONE, $this->state('alice', 'bob'));
	}

	public function testNoFriendshipWithYourselfOrAGoneAccount(): void
	{
		$this->prepare();

		$this->post('/friends/add/' . $this->userId('alice'));
		$this->assertResponseCode(404);

		Util::execute('UPDATE user SET deleted = NOW() WHERE id = ?', [$this->userId('carol')]);
		$this->post('/friends/add/' . $this->userId('carol'));
		$this->assertResponseCode(404);

		$this->post('/friends/add/999999');
		$this->assertResponseCode(404);
		$this->assertSame(0, (int) Util::query('SELECT COUNT(*) AS count FROM friendship')[0]['count']);
	}

	public function testFriendsNeedAnAccount(): void
	{
		$this->prepare();
		$this->logout();

		$this->post('/friends/add/' . $this->userId('bob'));
		$this->assertResponseCode(401);
		$this->get('/friends');
		$this->assertRedirect('/users/login');
	}

	public function testTheProfileButtonFollowsTheFriendship(): void
	{
		$this->prepare();
		$bobProfile = '/users/view/' . $this->userId('bob');
		$friendship = fn() => preg_match('/data-friendship="([a-z-]+)"/', (string) $this->_response->getBody(), $match) ? $match[1] : null;

		$this->get($bobProfile);
		$this->assertSame(FriendshipTable::NONE, $friendship());
		$this->assertResponseContains('formaction="/friends/add/' . $this->userId('bob') . '"');

		FriendshipTable::ask($this->userId('alice'), $this->userId('bob'));
		$this->get($bobProfile);
		$this->assertSame(FriendshipTable::ASKED_BY_VIEWER, $friendship());

		$this->login('bob');
		$this->get('/users/view/' . $this->userId('alice'));
		$this->assertSame(FriendshipTable::ASKED_BY_OTHER, $friendship());
		$this->assertResponseContains('Accept friend request');

		FriendshipTable::accept($this->userId('bob'), $this->userId('alice'));
		$this->get('/users/view/' . $this->userId('alice'));
		$this->assertSame(FriendshipTable::FRIENDS, $friendship());

		// no button on your own profile
		$this->get('/users/view/' . $this->userId('bob'));
		$this->assertNull($friendship());
	}

	//What a player did: an achievement, a finished challenge, a streak at a milestone and solves of today
	private function makeActive(string $name): int
	{
		$userId = $this->userId($name);
		Util::execute('INSERT INTO achievement_status (user_id, achievement_id, created) VALUES (?, 1, NOW() - INTERVAL 1 HOUR)', [$userId]);
		Util::execute('INSERT INTO challenge (created_by_user_id, minimum_time_mode_rank_id, maximum_time_mode_rank_id, seconds_per_problem) VALUES (?, ?, ?, 60)',
			[$userId, TimeModeRankTable::RANK_5K, TimeModeRankTable::RANK_5K]);
		$challengeId = (int) Util::connection()->lastInsertId();
		Util::execute('INSERT INTO time_mode_session (user_id, points, time_mode_session_status_id, challenge_id, created) VALUES (?, 87.5, ?, ?, NOW() - INTERVAL 2 HOUR)',
			[$userId, TimeModeSessionStatusTable::SOLVED, $challengeId]);
		Util::execute('UPDATE user SET streak_length = 30, streak_last_day = CURDATE(), daily_solved = 12 WHERE id = ?', [$userId]);
		return $challengeId;
	}

	public function testTheHomepageShowsWhatFriendsDidLately(): void
	{
		$this->prepare();
		$this->befriend('bob', 'alice');
		$challengeId = $this->makeActive('bob');
		// carol is no friend, only asked, so nothing of hers shows
		FriendshipTable::ask($this->userId('carol'), $this->userId('alice'));
		$this->makeActive('carol');

		$this->get('/');
		$this->assertResponseOk();
		$this->assertResponseContains('id="friend-activity"');
		$box = (string) $this->_response->getBody();
		$box = substr($box, strpos($box, 'id="friend-activity"'));
		$box = substr($box, 0, strpos($box, '</section>'));
		$this->assertStringContainsString('DN_bob', $box);
		$this->assertStringNotContainsString('DN_carol', $box);
		$this->assertStringContainsString('href="/achievements/view/1"', $box);
		$this->assertStringContainsString('href="/challenges/' . $challengeId . '"', $box);
		$this->assertStringContainsString('87.5 points', $box);
		$this->assertStringContainsString('30-day streak', $box);
		$this->assertStringContainsString('Solved today:', $box);
		$this->assertMatchesRegularExpression('/<time class="hint" datetime="\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d" data-format="datetime">/', $box);
	}

	public function testTheBoxIsOnlyForPlayersWithFriends(): void
	{
		$this->prepare();
		$this->makeActive('bob');

		$this->get('/');
		$this->assertResponseNotContains('id="friend-activity"');

		$this->logout();
		$this->get('/');
		$this->assertResponseNotContains('id="friend-activity"');
	}

	public function testOldActivityAndStreaksBetweenMilestonesAreLeftOut(): void
	{
		$this->prepare();
		$this->befriend('alice', 'bob');
		$this->makeActive('bob');
		Util::execute('UPDATE achievement_status SET created = NOW() - INTERVAL ? DAY', [FriendActivity::DAYS + 1]);
		Util::execute('UPDATE time_mode_session SET created = NOW() - INTERVAL ? DAY', [FriendActivity::DAYS + 1]);
		Util::execute('UPDATE user SET streak_length = 31, daily_solved = 0');

		$activity = FriendActivity::forUser($this->userId('alice'));

		$this->assertNotNull($activity);
		$this->assertSame([], $activity->events);
		$this->assertSame([], $activity->solvedToday);
	}

	public function testStreakMilestones(): void
	{
		$this->assertTrue(FriendActivity::isStreakMilestone(7));
		$this->assertTrue(FriendActivity::isStreakMilestone(365));
		$this->assertTrue(FriendActivity::isStreakMilestone(400));
		$this->assertFalse(FriendActivity::isStreakMilestone(8));
		$this->assertFalse(FriendActivity::isStreakMilestone(0));
		$this->assertFalse(FriendActivity::isStreakMilestone(250 + 100));
	}
}
