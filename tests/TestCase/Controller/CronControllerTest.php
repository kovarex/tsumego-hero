<?php

App::uses('TsumegoUtil', 'Utility');

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
		$this->testAction('/cron/daily/wrongsecret');
		$this->assertSame($context->reloadUser()['used_refinement'], 1); // nothing happened
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

	public function testTsumegoStatisticsInDayRecord()
	{
		new ContextPreparator([
			'tsumegos' => [
				['sets' => [['name' => 'sandbox set', 'num' => 5, 'public' => 0]]], // in sandbox
				['sets' => [['name' => 'set 1', 'num' => 3]]], // normal
				['sets' => [['name' => 'set 1', 'num' => 4], ['name' => 'set 2', 'num' => 5]]], // two set occurances counted as one
				['sets' => [['name' => 'set 1', 'num' => 4]], 'deleted' => '2025-05-05 00:00:00']]]); // deleted

		// 1 is in private set, one is deleted, only 2 remaining normal ones.
		$this->assertSame(TsumegoUtil::currentTsumegoCount(), 2);
		$this->testAction('/cron/daily/' . CRON_SECRET);
		$dayRecord = ClassRegistry::init('DayRecord')->find('first', ['order' => 'id DESC']);
		$this->assertSame($dayRecord['DayRecord']['tsumego_count'], 2);
	}

	public function testPopularTagsUpdate()
	{
		$contextInput = [];
		$contextInput['tsumegos'] = [];
		for ($i = 0; $i < Tag::$POPULAR_COUNT; $i++)
			$contextInput['tsumegos'][] = ['tags' => [['name' => 'tag-' . $i]]];
		$contextInput['tags'][] = ['name' => 'unpopular-tag'];
		$context = new ContextPreparator($contextInput);
		$this->testAction('/cron/daily/' . CRON_SECRET);
		$popularCount = ClassRegistry::init('Tag')->find('count', ['conditions' => ['popular' => 1]]);
		$this->assertSame($popularCount, Tag::$POPULAR_COUNT);
		$unpopularTag = ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => 'unpopular-tag']]);
		$this->assertSame($unpopularTag['Tag']['popular'], false);
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
