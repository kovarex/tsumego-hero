<?php

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
			'other-tsumegos' => [
				['sets' => [['name' => 'set 1', 'num' => 1]], 'status' => 'F'],
				['sets' => [['name' => 'set 1', 'num' => 2]], 'status' => 'X']]]);
		$this->testAction('/cron/daily/' . CRON_SECRET);
		$status1 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[0]['id']]]);
		$this->assertSame($status1['TsumegoStatus']['status'], 'V');
		$status2 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[1]['id']]]);
		$this->assertSame($status2['TsumegoStatus']['status'], 'W');
	}

	public function testCronChangesSolvedStatusToHalfSolved()
	{
		$oldEnoughToTransfer = date('Y-m-d H:i:s', strtotime('-8 days'));
		$newEnoughToTransfer = date('Y-m-d H:i:s', strtotime('-6 days'));
		$context = new ContextPreparator([
			'user' => ['mode' => Constants::$LEVEL_MODE, 'used_intuition' => 1, 'damage' => 7],
			'other-tsumegos' => [
				['sets' => [['name' => 'set 1', 'num' => 1]], 'status' => ['name' => 'S', 'updated' => $oldEnoughToTransfer]],
				['sets' => [['name' => 'set 1', 'num' => 2]], 'status' => ['name' => 'S', 'updated' => $newEnoughToTransfer]]]]);
		$this->testAction('/cron/daily/' . CRON_SECRET);
		$status1 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[0]['id']]]);
		$this->assertSame($status1['TsumegoStatus']['status'], 'W');
		$status2 = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[1]['id']]]);
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

	public function testPublish()
	{
		$context = new ContextPreparator([
			'other-tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 5, 'public' => 0]]],
				['sets' => [['name' => 'set 1', 'num' => 3]]]]]);

		// we are testing that the publish just correctly updates the SetConnection to the new set
		$tsumegoToMigrate = $context->otherTsumegos[0];
		$publicSetID = $context->otherTsumegos[1]['set-connections'][0]['set_id'];

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
}
