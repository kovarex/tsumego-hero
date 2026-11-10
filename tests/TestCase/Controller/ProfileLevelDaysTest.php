<?php

use App\Utility\UserProfile;

//The level chart of the profile pages through the days 30 at a time (UserProfile::levelDays, /users/levelDays):
//the latest window by default, Older and Newer to the windows around it, no Older before the first day with data
//and no Newer on the latest window.
class ProfileLevelDaysTest extends IntegrationTestCase
{
	private static function day(int $daysAgo): string
	{
		return date('Y-m-d', strtotime('-' . $daysAgo . ' days'));
	}

	//Attempts 2 days ago (two solves, a fail), yesterday (a fail) and 40 days ago (a solve)
	private function prepare(): ContextPreparator
	{
		return new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [[
				'sets' => [['name' => 'set', 'num' => 1]],
				'attempts' => [
					['solved' => true, 'user_rating' => 1500, 'created' => self::day(40) . ' 10:00:00'],
					['solved' => true, 'user_rating' => 1500, 'created' => self::day(2) . ' 10:00:00'],
					['solved' => true, 'user_rating' => 1510, 'created' => self::day(2) . ' 11:00:00'],
					['solved' => false, 'user_rating' => 1490, 'created' => self::day(2) . ' 12:00:00'],
					['solved' => false, 'user_rating' => 1480, 'created' => self::day(1) . ' 10:00:00']]]]]);
	}

	public function testTheDefaultWindowIsTheLast30Days(): void
	{
		$context = $this->prepare();

		$window = UserProfile::levelDays((int) $context->user['id']);

		$this->assertSame(self::day(29), $window['from']);
		$this->assertSame(self::day(0), $window['until']);
		$this->assertSame([
			['day' => self::day(1), 'Solves' => 0, 'Fails' => 1],
			['day' => self::day(2), 'Solves' => 2, 'Fails' => 1]], $window['days'], 'the days of the window, newest first');
		$this->assertSame(self::day(30), $window['older'], 'there is an attempt before the window');
		$this->assertNull($window['newer'], 'no newer window than the latest');
	}

	//Older goes back 30 days and stops at the first day with data; Newer comes back to the latest
	public function testPagingBackAndForward(): void
	{
		$context = $this->prepare();
		$userID = (int) $context->user['id'];

		$older = UserProfile::levelDays($userID, UserProfile::levelDays($userID)['older']);
		$this->assertSame(self::day(59), $older['from']);
		$this->assertSame(self::day(30), $older['until']);
		$this->assertSame([['day' => self::day(40), 'Solves' => 1, 'Fails' => 0]], $older['days']);
		$this->assertNull($older['older'], 'the first day with data is in this window');
		$this->assertSame(self::day(0), $older['newer']);

		$this->assertSame(UserProfile::levelDays($userID), UserProfile::levelDays($userID, $older['newer']));
	}

	public function testAWindowEndsTodayAtTheLatest(): void
	{
		$context = $this->prepare();

		$this->assertSame(self::day(0), UserProfile::levelDays((int) $context->user['id'], date('Y-m-d', strtotime('+10 days')))['until']);
	}

	public function testAPlayerWithoutAttemptsHasNoOlderWindow(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);

		$window = UserProfile::levelDays((int) $context->user['id']);

		$this->assertSame([], $window['days']);
		$this->assertNull($window['older']);
		$this->assertNull($window['newer']);
	}

	public function testTheWindowAsJson(): void
	{
		$context = $this->prepare();
		$this->logout();

		$this->get('/users/levelDays/' . $context->user['id'] . '?until=' . self::day(30));
		$this->assertResponseOk();
		$this->assertSame(UserProfile::levelDays((int) $context->user['id'], self::day(30)), json_decode($this->view, true));

		$this->get('/users/levelDays/' . $context->user['id'] . '?until=nonsense');
		$this->assertSame(self::day(0), json_decode($this->view, true)['until'], 'an until that is no day is today');
	}

	public function testAnUnknownPlayerIsNotFound(): void
	{
		new ContextPreparator([]);

		$this->get('/users/levelDays/999999');
		$this->assertResponseCode(404);
	}

	//The profile prints the latest window, so the chart needs no request to show
	public function testTheProfilePrintsTheLatestWindow(): void
	{
		$context = $this->prepare();

		$this->get('/users/view/' . $context->user['id']);

		$this->assertStringContainsString('data-level-chart-root', $this->view);
		$this->assertStringContainsString(h(json_encode(['playerId' => (int) $context->user['id'], 'window' => UserProfile::levelDays((int) $context->user['id'])])), $this->view);
	}
}
