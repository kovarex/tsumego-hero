<?php

use App\Utility\UserProfile;

//The level chart of the profile in pages of 60 days with attempts (UserProfile::levelDays, /users/levelDays/{id}?page=N):
//page 1 the newest, a page out of range the nearest one.
class ProfileLevelDaysTest extends IntegrationTestCase
{
	private static function day(int $daysAgo): string
	{
		return date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
	}

	//An attempt on each of the 130 days before today, a solve every second day, and two more on the day before
	//yesterday: 130 days with attempts, three pages of 60, 60 and 10
	private function prepare(): ContextPreparator
	{
		$attempts = [];
		for ($daysAgo = 1; $daysAgo <= 130; $daysAgo++)
			$attempts[] = ['solved' => $daysAgo % 2 == 0, 'user_rating' => 1500, 'created' => self::day($daysAgo) . ' 10:00:00'];
		$attempts[] = ['solved' => true, 'user_rating' => 1500, 'created' => self::day(2) . ' 11:00:00'];
		$attempts[] = ['solved' => false, 'user_rating' => 1500, 'created' => self::day(2) . ' 12:00:00'];
		return new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]], 'attempts' => $attempts]]]);
	}

	public function testThePagesAreSixtyDaysWithAttemptsNewestFirst(): void
	{
		$userID = (int) $this->prepare()->user['id'];

		$first = UserProfile::levelDays($userID);
		$this->assertSame([1, 3], [$first['page'], $first['pages']]);
		$this->assertCount(UserProfile::LEVEL_DAYS_PAGE, $first['days']);
		$this->assertSame([self::day(60), self::day(1)], [$first['from'], $first['until']]);
		$this->assertSame([
			['day' => self::day(1), 'Solves' => 0, 'Fails' => 1],
			['day' => self::day(2), 'Solves' => 2, 'Fails' => 1]], array_slice($first['days'], 0, 2), 'a day sums its attempts');

		$second = UserProfile::levelDays($userID, 2);
		$this->assertCount(UserProfile::LEVEL_DAYS_PAGE, $second['days']);
		$this->assertSame([self::day(120), self::day(61)], [$second['from'], $second['until']]);

		$last = UserProfile::levelDays($userID, 3);
		$this->assertCount(10, $last['days'], 'the last page has the days left');
		$this->assertSame([self::day(130), self::day(121)], [$last['from'], $last['until']]);
	}

	//A page out of range is the nearest one
	public function testThePageNumberClamps(): void
	{
		$userID = (int) $this->prepare()->user['id'];

		$this->assertSame(1, UserProfile::levelDays($userID, 0)['page']);
		$this->assertSame(1, UserProfile::levelDays($userID, -5)['page']);
		$this->assertSame(UserProfile::levelDays($userID, 3), UserProfile::levelDays($userID, 99));
	}

	public function testAPlayerWithoutAttemptsHasOneEmptyPage(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);

		$this->assertSame(['page' => 1, 'pages' => 1, 'from' => null, 'until' => null, 'days' => []], UserProfile::levelDays((int) $context->user['id']));
	}

	public function testThePageAsJson(): void
	{
		$context = $this->prepare();
		$userID = (int) $context->user['id'];
		$this->logout();

		$this->get('/users/levelDays/' . $userID . '?page=2');
		$this->assertResponseOk();
		$this->assertSame(UserProfile::levelDays($userID, 2), json_decode($this->view, true));

		$this->get('/users/levelDays/' . $userID . '?page=99');
		$this->assertSame(3, json_decode($this->view, true)['page'], 'a page past the last is the last');
		$this->get('/users/levelDays/' . $userID . '?page=nonsense');
		$this->assertSame(1, json_decode($this->view, true)['page']);
		$this->get('/users/levelDays/' . $userID);
		$this->assertSame(1, json_decode($this->view, true)['page'], 'the newest by default');
	}

	public function testAnUnknownPlayerIsNotFound(): void
	{
		new ContextPreparator([]);

		$this->get('/users/levelDays/999999');
		$this->assertResponseCode(404);
	}

	//The profile prints the newest page, so the chart needs no request to show
	public function testTheProfilePrintsTheNewestPage(): void
	{
		$context = $this->prepare();

		$this->get('/users/view/' . $context->user['id']);

		$this->assertStringContainsString('data-level-chart-root', $this->view);
		$this->assertStringContainsString(h(json_encode(['playerId' => (int) $context->user['id'], 'page' => UserProfile::levelDays((int) $context->user['id'])])), $this->view);
	}
}
