<?php

use App\Utility\AchievementId;
/* Test AchievementId::SUPERIOR_ACCURACY
 * "Finish a collection with 100% accuracy"
 * Requires: set with 100+ tsumegos, 100% accuracy (acA.value >= 100) */
use App\Utility\AchievementChecker;

class SuperiorAccuracyAchievementTest extends AchievementTestCase
{
	public function testSuperiorAccuracyAchievement()
	{
		$context = new ContextPreparator();

		// Create set with 100 tsumegos + SetConnection records
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);

		// Create 100% accuracy condition
		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
			'user_id' => $context->user['id'],
			'set_id' => $setId,
			'category' => '%',
			'value' => 100]);

		new AchievementChecker()->checkSetAchievements($setId);
		$this->assertAchievementUnlocked(AchievementId::SUPERIOR_ACCURACY, 'Superior Accuracy (100%) should unlock');
	}

	public function testSuperiorAccuracyDoesNotUnlockBelow100Percent()
	{
		$context = new ContextPreparator();

		// Create set with 100 tsumegos
		$setId = $this->createSetWithTsumegosAndConnections(1200, 100);

		// Create 99% accuracy condition (just below threshold)
		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
			'user_id' => $context->user['id'],
			'set_id' => $setId,
			'category' => '%',
			'value' => 99]);

		new AchievementChecker()->checkSetAchievements($setId);
		$this->assertAchievementNotUnlocked(AchievementId::SUPERIOR_ACCURACY);
	}

	/**
	 * The first perfect collection pays out Superior Accuracy like any other
	 * achievement: its XP and its popup.
	 */
	public function testSuperiorAccuracyPaysOutOnTheFirstCollection()
	{
		$context = new ContextPreparator();

		$setId = $this->createFinishedCollection($context->user['id']);
		$checker = new AchievementChecker();
		$checker->checkSetAchievements($setId)->finalize();

		$this->assertContains(
			AchievementId::SUPERIOR_ACCURACY->value,
			array_column($checker->updated, 'id'),
			'Superior Accuracy should be among the achievements unlocked by the first perfect collection');

		// 5000 XP for Superior Accuracy itself + 26000 for the accuracy and speed
		// achievements the same collection unlocks
		$this->assertSame(31000, $context->XPGained());
	}

	/**
	 * A further perfect collection raises the count without paying out again.
	 */
	public function testSuperiorAccuracyGrantsNothingForAFurtherCollection()
	{
		$context = new ContextPreparator();

		$firstSet = $this->createFinishedCollection($context->user['id']);
		new AchievementChecker()->checkSetAchievements($firstSet)->finalize();
		$this->assertGreaterThan(0, $context->XPGained(), 'The first collection is the one that pays out');

		$secondSet = $this->createFinishedCollection($context->user['id']);
		$repeat = new AchievementChecker();
		$repeat->checkSetAchievements($secondSet)->finalize();

		$status = ClassRegistry::init('AchievementStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'achievement_id' => AchievementId::SUPERIOR_ACCURACY->value]]);
		$this->assertSame(2, (int) $status['AchievementStatus']['value'], 'The second collection should raise the count to 2');

		$this->assertSame([], $repeat->updated, 'A further collection should not queue a popup');
		$this->assertSame(0, $context->XPGained(), 'A further collection should not grant XP');
	}

	/**
	 * A collection of fewer than 100 problems does not count, however perfectly it was
	 * finished: the achievement is for finishing a big collection.
	 */
	public function testSuperiorAccuracyIgnoresACollectionSmallerThanTheSetRequirement()
	{
		$context = new ContextPreparator();

		$setId = $this->createFinishedCollection($context->user['id'], 99);

		new AchievementChecker()->checkSetAchievements($setId)->finalize();
		$this->assertAchievementNotUnlocked(AchievementId::SUPERIOR_ACCURACY);
	}

	/**
	 * Only collections of 100+ problems are counted, so a small perfect collection does
	 * not raise the count of a player who already has a big one.
	 */
	public function testOnlyLargeCollectionsRaiseTheCount()
	{
		$context = new ContextPreparator();

		$largeSet = $this->createFinishedCollection($context->user['id']);
		new AchievementChecker()->checkSetAchievements($largeSet)->finalize();

		$smallSet = $this->createFinishedCollection($context->user['id'], 99);
		new AchievementChecker()->checkSetAchievements($smallSet)->finalize();

		$status = ClassRegistry::init('AchievementStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'achievement_id' => AchievementId::SUPERIOR_ACCURACY->value]]);
		$this->assertSame(1, (int) $status['AchievementStatus']['value'],
			'the small collection should not be counted');
	}

	/**
	 * The count follows collections, not condition rows: a set whose accuracy row was
	 * written twice is still a single collection.
	 */
	public function testDuplicateAccuracyRowsCountAsOneCollection()
	{
		$context = new ContextPreparator();

		$setId = $this->createFinishedCollection($context->user['id']);

		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
			'user_id' => $context->user['id'],
			'set_id' => $setId,
			'category' => '%',
			'value' => 100]);

		new AchievementChecker()->checkSetAchievements($setId);

		$status = ClassRegistry::init('AchievementStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'achievement_id' => AchievementId::SUPERIOR_ACCURACY->value]]);
		$this->assertSame(1, (int) $status['AchievementStatus']['value'],
			'two accuracy rows for one set are one collection');
	}

	/**
	 * The stored count records what the player earned: it survives collections that can
	 * no longer be recounted (removed sets, collections finished before the count existed).
	 */
	public function testEarnedCountIsNotLoweredByALaterCollection()
	{
		$context = new ContextPreparator(['user' => ['achievement-statuses' => [
			['id' => AchievementId::SUPERIOR_ACCURACY->value, 'value' => 5]]]]);

		$setId = $this->createFinishedCollection($context->user['id']);
		new AchievementChecker()->checkSetAchievements($setId);

		$status = ClassRegistry::init('AchievementStatus')->find('first', [
			'conditions' => ['user_id' => $context->user['id'], 'achievement_id' => AchievementId::SUPERIOR_ACCURACY->value]]);
		$this->assertSame(5, (int) $status['AchievementStatus']['value'],
			'a later collection does not lower an earned count');
	}

	/**
	 * Only public collections earn it: a personal collection the player assembled is not
	 * one of the site's collections, however perfectly it is finished.
	 */
	public function testPersonalCollectionDoesNotCount()
	{
		$context = new ContextPreparator();

		$setId = $this->createFinishedCollection($context->user['id'], 100, 0);
		new AchievementChecker()->checkSetAchievements($setId);

		$this->assertAchievementNotUnlocked(AchievementId::SUPERIOR_ACCURACY);
	}

	/**
	 * A collection owned by a player is a personal collection even when it is public, so
	 * only collections built by the site itself earn it.
	 */
	public function testPublicCollectionOwnedByAPlayerDoesNotCount()
	{
		$context = new ContextPreparator();

		$setId = $this->createFinishedCollection($context->user['id']);
		$Set = ClassRegistry::init('Set');
		$Set->id = $setId;
		$Set->saveField('user_id', $context->user['id']);

		new AchievementChecker()->checkSetAchievements($setId);

		$this->assertAchievementNotUnlocked(AchievementId::SUPERIOR_ACCURACY);
	}

	/**
	 * The achievement page lists the public collections that can earn it, and marks the
	 * ones the player already finished at 100% accuracy.
	 */
	public function testAchievementPageListsCollectionsThatEarnIt()
	{
		$context = new ContextPreparator();

		$earnedSetId = $this->createFinishedCollection($context->user['id']);
		$plainSetId = $this->createSetWithTsumegosAndConnections(1200, 100);
		$personalSetId = $this->createFinishedCollection($context->user['id'], 100, 0);

		$page = $this->testAction('/achievements/view/' . AchievementId::SUPERIOR_ACCURACY->value, ['return' => 'view']);

		$this->assertStringContainsString('href="' . Set::url($earnedSetId) . '" class="acCollections__link acCollections__link--earned"', $page,
			'the collection finished at 100% accuracy is marked as earned');
		$this->assertStringContainsString('href="' . Set::url($plainSetId) . '" class="acCollections__link"', $page,
			'a collection that can still earn it is listed without the mark');
		$this->assertStringNotContainsString(Set::url($personalSetId) . '"', $page,
			'personal collections are not listed');
		$this->assertStringNotContainsString('no longer count', $page,
			'no explanation while nothing contradicts the marked collections');
	}

	/**
	 * The collections that did not earn it yet say how much accuracy the player reached on
	 * them, so the list shows how far each collection is.
	 */
	public function testAchievementPageShowsTheAccuracyReachedOnTheRemainingCollections()
	{
		$context = new ContextPreparator();

		$closeSetId = $this->createSetWithTsumegosAndConnections(1200, 100);
		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
			'user_id' => $context->user['id'],
			'set_id' => $closeSetId,
			'category' => '%',
			'value' => 96.5]);
		$finishedSetId = $this->createFinishedCollection($context->user['id']);
		$untouchedSetId = $this->createSetWithTsumegosAndConnections(1200, 100);

		$page = $this->testAction('/achievements/view/' . AchievementId::SUPERIOR_ACCURACY->value, ['return' => 'view']);

		$this->assertStringContainsString(
			'href="' . Set::url($closeSetId) . '" class="acCollections__link">Test Set (rating 1200)<span class="acCollections__percent">96.5%</span></a>',
			$page,
			'a collection the player got close on shows the accuracy reached on it');
		$this->assertStringContainsString(
			'href="' . Set::url($finishedSetId) . '" class="acCollections__link acCollections__link--earned">Test Set (rating 1200)</a>',
			$page,
			'a finished collection carries the mark instead of a number');
		$this->assertStringContainsString(
			'href="' . Set::url($untouchedSetId) . '" class="acCollections__link">Test Set (rating 1200)</a>',
			$page,
			'a collection the player never opened shows no accuracy');
	}

	/**
	 * A count earned on collections that no longer count is explained, so the marked
	 * collections never look like they contradict the number next to them.
	 */
	public function testAchievementPageExplainsACountWithoutMarkedCollections()
	{
		$context = new ContextPreparator(['user' => ['achievement-statuses' => [
			['id' => AchievementId::SUPERIOR_ACCURACY->value, 'value' => 14]]]]);
		$this->createSetWithTsumegosAndConnections(1200, 100);

		$page = $this->testAction('/achievements/view/' . AchievementId::SUPERIOR_ACCURACY->value, ['return' => 'view']);

		$this->assertStringContainsString('14 of the collections it was earned on no longer count', $page,
			'a count without marked collections is explained');
	}

	/**
	 * Opening another player's achievement shows their collections, not the viewer's.
	 */
	public function testAchievementPageShowsTheViewedUsersCollections()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [['name' => 'otherplayer']]]);
		$otherUserId = $context->otherUsers[0]['id'];

		$mySetId = $this->createFinishedCollection($context->user['id']);
		$theirSetId = $this->createFinishedCollection($otherUserId);

		$page = $this->testAction('/achievements/view/' . AchievementId::SUPERIOR_ACCURACY->value . '/' . $otherUserId, ['return' => 'view']);

		$this->assertStringContainsString('href="' . Set::url($theirSetId) . '" class="acCollections__link acCollections__link--earned"', $page,
			'the viewed player\'s perfect collection is marked');
		$this->assertStringContainsString('href="' . Set::url($mySetId) . '" class="acCollections__link"', $page,
			'the viewer\'s own perfect collection is not marked');
		$this->assertStringContainsString('href="/achievements/user/' . $otherUserId . '"', $page,
			'the achievements link stays with the viewed player');
		$this->assertStringContainsString($context->otherUsers[0]['display_name'] . "'s achievement", $page,
			'the page names whose achievement it is');
	}

	/**
	 * The achievements list of another player keeps them in the link, so opening a card
	 * does not switch to the viewer.
	 */
	public function testAchievementsListOfAnotherPlayerLinksToThatPlayer()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'viewer'],
			'other-users' => [['name' => 'otherplayer']]]);
		$otherUserId = $context->otherUsers[0]['id'];

		$page = $this->testAction('/achievements/user/' . $otherUserId, ['return' => 'view']);

		$this->assertStringContainsString(
			'href="/achievements/view/' . AchievementId::SUPERIOR_ACCURACY->value . '/' . $otherUserId . '"', $page,
			'a card of another player links to their achievement');
	}

	/**
	 * A collection of 100+ problems finished at 100% accuracy.
	 */
	private function createFinishedCollection(int $userId, int $problemCount = 100, int $public = 1): int
	{
		$setId = $this->createSetWithTsumegosAndConnections(1200, $problemCount, $public);

		$AchievementCondition = ClassRegistry::init('AchievementCondition');
		$AchievementCondition->create();
		$AchievementCondition->save([
			'user_id' => $userId,
			'set_id' => $setId,
			'category' => '%',
			'value' => 100]);

		return $setId;
	}
}
