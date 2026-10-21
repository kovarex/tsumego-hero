<?php


use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\Util;

class CronControllerTest extends TestCaseWithAuth
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
			$this->testAction('/cron/daily/' . CRON_SECRET);
			$this->assertSame($context->reloadUser()[$name], 0);
		}
	}

	public function testCronWithWrongSecret()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE, 'used_refinement' => 1]]);
		$this->assertSame($context->reloadUser()['used_refinement'], 1);

		$this->expectException(ForbiddenException::class);

		$this->testAction('/cron/daily/wrongsecret');
	}

	/**
	 * An address change whose links have expired is forgotten overnight, so it does
	 * not sit on the account waiting for a confirmation that can no longer come.
	 */
	public function testCronForgetsAnAddressChangeNobodyConfirmed()
	{
		$context = new ContextPreparator(['user' => ['mode' => Constants::$LEVEL_MODE]]);
		Util::execute('UPDATE user SET unconfirmed_email = ?, unconfirmed_email_token = ?,
			unconfirmed_email_cancel_token = ?, unconfirmed_email_created = ?
			WHERE id = ?', [
			'new@example.com', 'confirm_token', 'cancel_token',
			date('Y-m-d H:i:s', strtotime('-2 days')), $context->user['id']]);

		$this->testAction('/cron/daily/' . CRON_SECRET);

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
		$this->testAction('/cron/daily/' . CRON_SECRET);
		$this->assertSame($context->reloadUser()['damage'], 0);
	}

	public function testCronResetsFailedTsumegos()
	{
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'used_intuition' => 1, 'damage' => 7],
			'tsumegos' => [
				['set_order' => 1, 'status' => 'F'],
				['set_order' => 2, 'status' => 'X']]]);
		$this->testAction('/cron/daily/' . CRON_SECRET);
		$status1 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[0]['id']]]);
		$this->assertSame($status1['TsumegoStatus']['status'], 'V');
		$status2 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[1]['id']]]);
		$this->assertSame($status2['TsumegoStatus']['status'], 'W');
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
		$this->testAction('/cron/daily/' . CRON_SECRET);
		$status1 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[0]['id']]]);
		$this->assertSame($status1['TsumegoStatus']['status'], 'W');
		$status2 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->tsumegos[1]['id']]]);
		$this->assertSame($status2['TsumegoStatus']['status'], 'S');
	}

	public function testUserOfTheDay()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'kovarex', 'daily_xp' => 5, 'daily_solved' => 1],
			'other-users' => [['name' => 'Ivan Detkov', 'daily_xp' => 10, 'daily_solved' => 2]]]);

		$this->testAction('/cron/daily/' . CRON_SECRET);
		$this->assertSame($context->reloadUser()['daily_xp'], 0);
		$this->assertSame($context->reloadUser()['daily_solved'], 0);
		$detkov = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => 'Ivan Detkov']]);
		$this->assertSame($detkov['User']['daily_xp'], 0);
		$this->assertSame($detkov['User']['daily_solved'], 0);
		$dayRecords = ClassRegistry::init('DayRecord')->find('all');
		$this->assertCount(1, $dayRecords);
		$this->assertSame($dayRecords[0]['DayRecord']['user_id'], $context->otherUsers[0]['id']);
	}

	public function testDailyCronCreatesSingleDayRecord()
	{
		new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'daily_xp' => 5, 'daily_solved' => 1]]);

		$this->testAction('/cron/daily/' . CRON_SECRET);
		$this->testAction('/cron/daily/' . CRON_SECRET);

		$this->assertSame(1, ClassRegistry::init('DayRecord')->find('count'));
		$this->assertSame(1, ClassRegistry::init('AchievementCondition')->find('count', [
			'conditions' => ['category' => 'uotd']]));
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

		ClassRegistry::init('Schedule')->create();
		$scheduleItem = [];
		$scheduleItem['tsumego_id'] = $tsumegoToMigrate['id'];
		$scheduleItem['set_id'] = $publicSetID;
		$scheduleItem['date'] = date('Y-m-d');
		$scheduleItem['published'] = 0;
		ClassRegistry::init('Schedule')->save($scheduleItem);

		$this->testAction('/cron/daily/' . CRON_SECRET);
		$newTsumego = ClassRegistry::init('Tsumego')->find('first', ['conditions' => ['id' => $tsumegoToMigrate['id']]]);
		$newSetConnection = ClassRegistry::init('SetConnection')->find('first', ['conditions' => ['tsumego_id' => $tsumegoToMigrate['id']]]);
		$this->assertSame($newTsumego['Tsumego']['id'], $tsumegoToMigrate['id']);
		$this->assertSame($newSetConnection['SetConnection']['id'], $tsumegoToMigrate['set-connections'][0]['id']);
		$this->assertSame($newSetConnection['SetConnection']['set_id'], $publicSetID);
	}

	/**
	 * The other daily work runs once a day, but publishing repeats on every call
	 * and only takes what is still pending, so a due entry is never left behind by
	 * a day that is already recorded as done.
	 */
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

		$this->testAction('/cron/daily/' . CRON_SECRET);

		$schedule = ClassRegistry::init('Schedule')->find('first', ['order' => 'id ASC']);
		$this->assertSame(1, (int) $schedule['Schedule']['published']);
	}

	public function testUpdateSolved()
	{
		$context = new ContextPreparator([
			'tsumegos' => [
				['set_order' => 1, 'status' => 'S'],
				['set_order' => 2, 'status' => 'S']],
			'user' => ['mode' => Constants::$LEVEL_MODE, 'solved' => 2]]);
		ClassRegistry::init('TsumegoStatus')->deleteAll(['tsumego_id' => $context->tsumegos[0]['id']]);
		$this->testAction('/cron/daily/' . CRON_SECRET);
		$this->assertSame($context->reloadUser()['solved'], 1);
	}
}
