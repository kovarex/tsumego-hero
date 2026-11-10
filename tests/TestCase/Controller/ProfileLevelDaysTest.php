<?php

use App\Utility\UserProfile;

//The level chart of the profile a calendar quarter a page (UserProfile::levelDays, /users/levelDays/{id}?page=N):
//page 1 the current quarter, the last the quarter of the first attempt, a page out of range the nearest one.
class ProfileLevelDaysTest extends IntegrationTestCase
{
	//The first day of the quarter so many quarters before the current one
	public static function quarterStart(int $quartersAgo): string
	{
		$current = date('Y') . '-' . sprintf('%02d', intdiv((int) date('n') - 1, 3) * 3 + 1) . '-01';
		return date('Y-m-d', strtotime($current . ' -' . (3 * $quartersAgo) . ' months'));
	}

	public static function dayAfter(string $day, int $days): string
	{
		return date('Y-m-d', strtotime($day . ' ' . ($days < 0 ? '' : '+') . $days . ' days'));
	}

	//Attempts on the first two days of the current quarter, the first and the last day of the one before, and the
	//first day of the quarter a year ago: five pages, the middle two empty
	public static function attempts(): array
	{
		$attempt = fn(string $day, bool $solved, string $time = '10:00:00') => ['solved' => $solved, 'user_rating' => 1500, 'created' => $day . ' ' . $time];
		return [
			$attempt(self::quarterStart(0), true),
			$attempt(self::quarterStart(0), false, '11:00:00'),
			$attempt(self::quarterStart(0), true, '23:59:59'),
			$attempt(self::dayAfter(self::quarterStart(0), 1), false),
			$attempt(self::dayAfter(self::quarterStart(0), -1), false, '23:59:59'),
			$attempt(self::quarterStart(1), true, '00:00:00'),
			$attempt(self::quarterStart(4), true)];
	}

	private function prepare(): ContextPreparator
	{
		return new ContextPreparator([
			'user' => ['name' => 'alice'],
			'tsumegos' => [['sets' => [['name' => 'set', 'num' => 1]], 'attempts' => self::attempts()]]]);
	}

	public function testThePagesAreCalendarQuartersNewestFirst(): void
	{
		$userID = (int) $this->prepare()->user['id'];

		$this->assertSame([
			'page' => 1,
			'pages' => 5,
			'from' => self::quarterStart(0),
			'until' => self::dayAfter(self::quarterStart(-1), -1),
			'days' => [
				['day' => self::dayAfter(self::quarterStart(0), 1), 'Solves' => 0, 'Fails' => 1],
				['day' => self::quarterStart(0), 'Solves' => 2, 'Fails' => 1]]], UserProfile::levelDays($userID));

		$this->assertSame([
			'page' => 2,
			'pages' => 5,
			'from' => self::quarterStart(1),
			'until' => self::dayAfter(self::quarterStart(0), -1),
			'days' => [
				['day' => self::dayAfter(self::quarterStart(0), -1), 'Solves' => 0, 'Fails' => 1],
				['day' => self::quarterStart(1), 'Solves' => 1, 'Fails' => 0]]], UserProfile::levelDays($userID, 2), 'the quarter keeps its first and last moment');

		$empty = UserProfile::levelDays($userID, 3);
		$this->assertSame([self::quarterStart(2), self::dayAfter(self::quarterStart(1), -1), []], [$empty['from'], $empty['until'], $empty['days']], 'a quarter without attempts is an empty page');

		$last = UserProfile::levelDays($userID, 5);
		$this->assertSame([self::quarterStart(4), [['day' => self::quarterStart(4), 'Solves' => 1, 'Fails' => 0]]], [$last['from'], $last['days']], 'the last page is the quarter of the first attempt');
	}

	//A page out of range is the nearest one
	public function testThePageNumberClamps(): void
	{
		$userID = (int) $this->prepare()->user['id'];

		$this->assertSame(1, UserProfile::levelDays($userID, 0)['page']);
		$this->assertSame(1, UserProfile::levelDays($userID, -5)['page']);
		$this->assertSame(UserProfile::levelDays($userID, 5), UserProfile::levelDays($userID, 99));
	}

	public function testAPlayerWithoutAttemptsHasTheCurrentQuarterEmpty(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'alice']]);

		$this->assertSame([
			'page' => 1,
			'pages' => 1,
			'from' => self::quarterStart(0),
			'until' => self::dayAfter(self::quarterStart(-1), -1),
			'days' => []], UserProfile::levelDays((int) $context->user['id']));
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
		$this->assertSame(5, json_decode($this->view, true)['page'], 'a page past the last is the last');
		$this->get('/users/levelDays/' . $userID . '?page=nonsense');
		$this->assertSame(1, json_decode($this->view, true)['page']);
		$this->get('/users/levelDays/' . $userID);
		$this->assertSame(1, json_decode($this->view, true)['page'], 'the current quarter by default');
	}

	public function testAnUnknownPlayerIsNotFound(): void
	{
		new ContextPreparator([]);

		$this->get('/users/levelDays/999999');
		$this->assertResponseCode(404);
	}

	//The profile prints the current quarter, so the chart needs no request to show
	public function testTheProfilePrintsTheCurrentQuarter(): void
	{
		$context = $this->prepare();

		$this->get('/users/view/' . $context->user['id']);

		$this->assertStringContainsString('data-level-chart-root', $this->view);
		$this->assertStringContainsString(h(json_encode(['playerId' => (int) $context->user['id'], 'page' => UserProfile::levelDays((int) $context->user['id'])])), $this->view);
	}
}
