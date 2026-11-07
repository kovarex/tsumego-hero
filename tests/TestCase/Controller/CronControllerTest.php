<?php


use App\Model\Table\AchievementConditionTable;
use App\Utility\AchievementId;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\Live;
use App\Utility\Util;

class CronControllerTest extends IntegrationTestCase
{
	public function testCronResetsPowers()
	{
		foreach ([
			'used_refinement',
			'used_sprint',
			'used_rejuvenation',
			'used_potion',
			'used_intuition',
			'used_revelation'] as $name)
		{
			$context = new ContextPreparator([
				'user' => ['mode' => Constants::$LEVEL_MODE, $name => 1]]);
			$this->assertSame($context->reloadUser()[$name], 1);
			$this->get('/cron/daily/' . CRON_SECRET);
			$this->assertSame($context->reloadUser()[$name], 0);
		}
	}

	//The daily run takes the change stamps of a week ago (Live::pruneStale())
	public function testCronPrunesTheStaleChangeStamps()
	{
		new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE]]);
		Live::bump('test-cron-stamp');
		touch(Live::path('test-cron-stamp'), time() - (Live::STALE_AFTER_DAYS + 1) * 24 * 3600);
		try
		{
			$this->get('/cron/daily/' . CRON_SECRET);

			$this->assertResponseOk();
			$this->assertFileDoesNotExist(Live::path('test-cron-stamp'));
		}
		finally
		{
			@unlink(Live::path('test-cron-stamp'));
		}
	}

	public function testCronWithWrongSecret()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE, 'used_refinement' => 1]]);
		$this->assertSame($context->reloadUser()['used_refinement'], 1);

		$this->get('/cron/daily/wrongsecret');

		$this->assertResponseCode(403);
	}

	//An address change whose links have expired is forgotten overnight, so it does
	//not sit on the account waiting for a confirmation that can no longer come.
	public function testCronForgetsAnAddressChangeNobodyConfirmed()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE]]);
		Util::execute('UPDATE user SET unconfirmed_email = ?, unconfirmed_email_token = ?,
			unconfirmed_email_cancel_token = ?, unconfirmed_email_created = ?
			WHERE id = ?', [
			'new@example.com', 'confirm_token', 'cancel_token',
			date('Y-m-d H:i:s', strtotime('-2 days')), $context->user['id']]);

		$this->get('/cron/daily/' . CRON_SECRET);

		$user = $context->reloadUser();
		$this->assertNull($user['unconfirmed_email']);
		$this->assertNull($user['unconfirmed_email_token']);
		$this->assertNull($user['unconfirmed_email_cancel_token']);
		$this->assertNull($user['unconfirmed_email_created']);
	}


	public function testCronResetsDamage()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'damage' => 7]]);
		$this->assertSame($context->reloadUser()['damage'], 7);
		$this->get('/cron/daily/' . CRON_SECRET);
		$this->assertSame($context->reloadUser()['damage'], 0);
	}

	public function testCronResetsFailedTsumegos()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'used_intuition' => 1, 'damage' => 7],
			'tsumegos' => [
				['set_order' => 1, 'status' => 'F'],
				['set_order' => 2, 'status' => 'X']]]);
		$this->get('/cron/daily/' . CRON_SECRET);
		$status1 = $this->fetchTable('TsumegoStatus')->find()->where(['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[0]['id']])->first();
		$this->assertSame($status1->status, 'V');
		$status2 = $this->fetchTable('TsumegoStatus')->find()->where(['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[1]['id']])->first();
		$this->assertSame($status2->status, 'W');
	}

	public function testCronChangesSolvedStatusToHalfSolved()
	{
		$oldEnoughToTransfer = date('Y-m-d H:i:s', strtotime('-8 days'));
		$newEnoughToTransfer = date('Y-m-d H:i:s', strtotime('-6 days'));
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'used_intuition' => 1, 'damage' => 7],
			'tsumegos' => [
				['set_order' => 1, 'status' => ['name' => 'S', 'updated' => $oldEnoughToTransfer]],
				['set_order' => 2, 'status' => ['name' => 'S', 'updated' => $newEnoughToTransfer]]]]);
		$this->get('/cron/daily/' . CRON_SECRET);
		$status1 = $this->fetchTable('TsumegoStatus')->find()->where(['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[0]['id']])->first();
		$this->assertSame($status1->status, 'W');
		$status2 = $this->fetchTable('TsumegoStatus')->find()->where(['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[1]['id']])->first();
		$this->assertSame($status2->status, 'S');
	}

	//The reset of the failed and the old solved problems leaves every other status as it is
	public function testCronKeepsTheOtherStatuses()
	{
		$old = date('Y-m-d H:i:s', strtotime('-8 days'));
		$statuses = ['V', 'C', 'G', 'W'];
		$tsumegos = [];
		foreach ($statuses as $order => $status)
			$tsumegos[] = ['set_order' => $order + 1, 'status' => ['name' => $status, 'updated' => $old]];
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE], 'tsumegos' => $tsumegos]);

		$this->get('/cron/daily/' . CRON_SECRET);

		foreach ($statuses as $order => $status)
			$this->assertSame($status, $this->fetchTable('TsumegoStatus')->find()->where(['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[$order]['id']])->first()->status);
	}

	public function testUserOfTheDay()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'daily_xp' => 5, 'daily_solved' => 1],
			'other-users' => [['name' => 'Ivan Detkov', 'daily_xp' => 10, 'daily_solved' => 2]]]);

		$this->get('/cron/daily/' . CRON_SECRET);
		$this->assertSame($context->reloadUser()['daily_xp'], 0);
		$this->assertSame($context->reloadUser()['daily_solved'], 0);
		$detkov = ContextPreparator::findUserRow(['name' => 'Ivan Detkov']);
		$this->assertSame($detkov['daily_xp'], 0);
		$this->assertSame($detkov['daily_solved'], 0);
		$dayRecords = $this->fetchTable('DayRecord')->find()->toArray();
		$this->assertCount(1, $dayRecords);
		$this->assertSame($dayRecords[0]->user_id, $context->otherUsers[0]['id']);
	}

	//A player isn't user of the day twice within a week, so on a site of few players a day can have none
	public function testADayWithoutAUserOfTheDay()
	{
		$context = new ContextPreparator(['user' => ['daily_xp' => 5, 'daily_solved' => 1]]);
		$dayRecordTable = $this->fetchTable('DayRecord');
		$dayRecordTable->saveOrFail($dayRecordTable->newEntity([
			'user_id' => $context->user['id'],
			'date' => date('Y-m-d', strtotime('-1 day')),
			'quote' => 'q01',
			'gems' => '0-0-0',
			'emerald_solves' => 0,
			'sapphire_solves' => 0,
			'ruby_solves' => 0]));

		$this->get('/cron/daily/' . CRON_SECRET);
		$this->assertResponseOk();
		$this->assertNull($dayRecordTable->find()->where(['date' => date('Y-m-d')])->firstOrFail()->user_id);
		$this->assertSame(0, $context->reloadUser()['daily_xp']);

		$this->get('/');
		$this->assertResponseOk();
	}

	public function testDailyCronCreatesSingleDayRecord()
	{
		new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'daily_xp' => 5, 'daily_solved' => 1]]);

		$this->get('/cron/daily/' . CRON_SECRET);
		$this->get('/cron/daily/' . CRON_SECRET);

		$this->assertSame(1, $this->fetchTable('DayRecord')->find()->count());
		$this->assertSame(1, $this->fetchTable('AchievementCondition')->find()->where(['achievement_condition_category_id' => AchievementConditionTable::CATEGORY_USER_OF_THE_DAY])->count());
	}

	//The daily cron hides each gem in an easy, regular or difficult problem, stores the choice in
	//the day record and tells it in the description of the gem's achievement
	public function testDailyCronDescribesWhereEachGemIsHidden()
	{
		new ContextPreparator(['user' => ['daily_xp' => 5, 'daily_solved' => 1]]);

		$this->get('/cron/daily/' . CRON_SECRET);

		$gems = explode('-', $this->fetchTable('DayRecord')->find()->firstOrFail()->gems);
		$difficulties = ['an easy', 'a regular', 'a difficult'];
		foreach ([[AchievementId::EMERALD, 'ddk'], [AchievementId::SAPPHIRE, 'sdk'], [AchievementId::RUBY, 'dan']] as $index => [$achievementId, $rankRange])
			$this->assertSame(
				'Has a chance to trigger once a day on ' . $difficulties[(int) $gems[$index]] . ' ' . $rankRange . ' problem.',
				$this->fetchTable('Achievement')->get($achievementId->value)->description);
	}

	public function testPublish()
	{
		$context = new ContextPreparator([
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 5, 'public' => 0]]],
				['sets' => [['name' => 'set 1', 'num' => 3]]]]]);

		// we are testing that the publish just correctly updates the SetConnection to the new set
		$tsumegoToMigrate = $context->tsumegos[0];
		$publicSetID = $context->tsumegos[1]['set-connections'][0]['set_id'];

		$scheduleTable = $this->fetchTable('Schedule');
		$scheduleTable->saveOrFail($scheduleTable->newEntity([
			'tsumego_id' => $tsumegoToMigrate['id'],
			'set_id' => $publicSetID,
			'date' => date('Y-m-d'),
			'published' => 0,
		]));

		$this->get('/cron/daily/' . CRON_SECRET);
		$newTsumego = $this->fetchTable('Tsumego')->find()->where(['id' => $tsumegoToMigrate['id']])->first();
		$newSetConnection = $this->fetchTable('SetConnection')->find()->where(['tsumego_id' => $tsumegoToMigrate['id']])->first();
		$this->assertSame($newTsumego->id, $tsumegoToMigrate['id']);
		$this->assertSame($newSetConnection->id, $tsumegoToMigrate['set-connections'][0]['id']);
		$this->assertSame($newSetConnection->set_id, $publicSetID);
	}

	//The other daily work runs once a day, but publishing repeats on every call
	//and only takes what is still pending, so a due entry is never left behind by
	//a day that is already recorded as done.
	public function testPublishRunsWhenTheDayIsAlreadyRecorded()
	{
		new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'day-records' => [['date' => date('Y-m-d')]],
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 5, 'public' => 0]]],
				['sets' => [['name' => 'set 1', 'num' => 3]]]],
			'schedule' => [
				['tsumego' => 0, 'set' => 'set 1', 'date' => date('Y-m-d', strtotime('-2 days'))]]]);

		$this->get('/cron/daily/' . CRON_SECRET);

		$this->assertTrue($this->fetchTable('Schedule')->find()->orderBy('id')->firstOrFail()->published);
	}

	public function testUpdateSolved()
	{
		$context = new ContextPreparator([
			'tsumegos' => [
				['set_order' => 1, 'status' => 'S'],
				['set_order' => 2, 'status' => 'S']],
			'user' => ['mode' => Constants::$LEVEL_MODE, 'solved' => 2]]);
		$this->fetchTable('TsumegoStatus')->deleteAll(['tsumego_id' => $context->tsumegos[0]['id']]);
		$this->get('/cron/daily/' . CRON_SECRET);
		$this->assertSame($context->reloadUser()['solved'], 1);
	}

	public function testUpdateSolvedForPlayersWithoutSolvedProblems()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'solved' => 3],
			'other-users' => [['name' => 'Ivan Detkov']]]);
		Util::execute('UPDATE user SET solved = NULL WHERE id = ?', [$context->otherUsers[0]['id']]);

		$this->get('/cron/daily/' . CRON_SECRET);

		$this->assertSame(0, $context->reloadUser()['solved']);
		$detkov = UserTable::row((int) $context->otherUsers[0]['id']);
		$this->assertSame(0, $detkov['solved']);
	}

	public function testCronResetsOnlyThePotionCondition()
	{
		new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE],
			'achievement-conditions' => [
				['category' => AchievementConditionTable::CATEGORY_POTION, 'value' => 2],
				['category' => AchievementConditionTable::CATEGORY_GOLDEN, 'value' => 2]]]);

		$this->get('/cron/daily/' . CRON_SECRET);

		$values = $this->fetchTable('AchievementCondition')
			->find('list', keyField: 'achievement_condition_category_id', valueField: 'value')
			->where(['achievement_condition_category_id IN' => [AchievementConditionTable::CATEGORY_POTION, AchievementConditionTable::CATEGORY_GOLDEN]])
			->toArray();
		$this->assertEquals([AchievementConditionTable::CATEGORY_POTION => 0, AchievementConditionTable::CATEGORY_GOLDEN => 2], $values);
	}

	//The daily work runs under READ COMMITTED, and the session gets back the level it had, as the
	//connection outlives the request here.
	public function testCronPutsBackTheIsolationLevel()
	{
		new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE]]);
		Util::execute('SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE');
		try
		{
			$this->get('/cron/daily/' . CRON_SECRET);
			$this->assertSame('SERIALIZABLE', Util::isolationLevel());
		}
		finally
		{
			Util::execute('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
		}
	}
}
