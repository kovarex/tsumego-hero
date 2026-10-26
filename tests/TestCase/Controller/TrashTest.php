<?php

use App\Utility\CookieFlash;
use App\Utility\Trash;
use App\Utility\TsumegoUtil;
use App\Utility\Util;

class TrashTest extends IntegrationTestCase
{
	private static function daysAgo(int $days): string
	{
		return date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
	}

	private static function tsumegoRow(int $id): ?array
	{
		return ClassRegistry::init('Tsumego')->findById($id)['Tsumego'] ?? null;
	}

	private static function setRow(int $id): ?array
	{
		return ClassRegistry::init('Set')->findById($id)['Set'] ?? null;
	}

	private static function rowCount(string $table, string $condition, array $params): int
	{
		return (int) Util::query('SELECT COUNT(*) AS total FROM `' . $table . '` WHERE ' . $condition, $params)[0]['total'];
	}

	/**
	 * Every table pointing to a problem loses its rows with the problem through its foreign key. A new
	 * table with a tsumego_id needs one too, otherwise purging a problem leaves rows pointing nowhere,
	 * or fails on the foreign key.
	 */
	public function testEveryTableOfAProblemIsCleanedByThePurge(): void
	{
		$rows = Util::query("
SELECT c.TABLE_NAME AS table_name, r.DELETE_RULE AS delete_rule
FROM information_schema.COLUMNS c
LEFT JOIN information_schema.KEY_COLUMN_USAGE k
	ON k.TABLE_SCHEMA = c.TABLE_SCHEMA AND k.TABLE_NAME = c.TABLE_NAME AND k.COLUMN_NAME = c.COLUMN_NAME
	AND k.REFERENCED_TABLE_NAME = 'tsumego'
LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS r
	ON r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE c.TABLE_SCHEMA = DATABASE() AND c.COLUMN_NAME = 'tsumego_id'");
		$this->assertNotEmpty($rows);
		$withoutCascade = [];
		foreach ($rows as $row)
			if ($row['delete_rule'] !== 'CASCADE')
				$withoutCascade[] = $row['table_name'];
		$this->assertSame([], $withoutCascade);
	}

	public function testAdminDeletesPublishedProblemToTheTrash(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'published', 'num' => 1]]],
				['sets' => [['name' => 'published', 'num' => 2]]]]]);
		$tsumegoID = $context->tsumegos[0]['id'];

		$this->post('/tsumegos/edit/' . $tsumegoID, ['delete' => 'delete']);

		$this->assertNotNull(self::tsumegoRow($tsumegoID)['deleted']);
		$this->assertNull(self::setRow($context->tsumegos[0]['sets'][0]['id'])['deleted'], 'The set still has a problem');
		$this->assertStringEndsWith(Set::url($context->tsumegos[0]['sets'][0]['id']), $this->headerValue('Location'));
		// the problem keeps its place in the set, so it can come back there
		$this->assertSame(1, self::rowCount('set_connection', 'tsumego_id = ?', [$tsumegoID]));
		$this->assertSame(1, TsumegoUtil::countTsumegosFromSet($context->tsumegos[0]['sets'][0]['id']));
	}

	public function testDeletingTheLastProblemTrashesTheSetAndRestoringBringsItBack(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'small', 'num' => 1]]],
				['sets' => [['name' => 'small', 'num' => 2], ['name' => 'other', 'num' => 1]]]]]);
		$setID = $context->tsumegos[0]['sets'][0]['id'];
		$otherSetID = $context->tsumegos[1]['sets'][1]['id'];

		Trash::deleteProblem($context->tsumegos[0]['id']);
		$this->assertNull(self::setRow($setID)['deleted']);

		$this->post('/tsumegos/edit/' . $context->tsumegos[1]['id'], ['delete' => 'delete']);
		$this->assertNotNull(self::setRow($setID)['deleted'], 'The set has no problem left');
		$this->assertNotNull(self::setRow($otherSetID)['deleted']);
		$this->assertStringEndsWith('/sets', $this->headerValue('Location'));

		$this->post('/trash/restore-problem/' . $context->tsumegos[1]['id']);
		$this->assertNull(self::tsumegoRow($context->tsumegos[1]['id'])['deleted']);
		$this->assertNull(self::setRow($setID)['deleted'], 'The set went to the trash with the problem, so it comes back with it');
		$this->assertNull(self::setRow($otherSetID)['deleted']);
		$this->assertNotNull(self::tsumegoRow($context->tsumegos[0]['id'])['deleted'], 'The other problem was deleted on its own');

		$activities = ClassRegistry::init('AdminActivity')->find('all', ['order' => 'id']);
		$this->assertSame(AdminActivityType::PROBLEM_DELETE, $activities[0]['AdminActivity']['type']);
		$this->assertSame(AdminActivityType::PROBLEM_RESTORE, $activities[1]['AdminActivity']['type']);
	}

	public function testRestoringAProblemLeavesASetDeletedOnItsOwnInTheTrash(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'big', 'num' => 1]]],
				['sets' => [['name' => 'big', 'num' => 2]]]]]);
		$setID = $context->tsumegos[0]['sets'][0]['id'];
		Trash::deleteProblem($context->tsumegos[0]['id']);
		Util::execute('UPDATE tsumego SET deleted = ? WHERE id = ?', [self::daysAgo(1), $context->tsumegos[0]['id']]);
		Trash::deleteSet($setID);

		Trash::restoreProblem($context->tsumegos[0]['id']);

		$this->assertNull(self::tsumegoRow($context->tsumegos[0]['id'])['deleted']);
		$this->assertNotNull(self::setRow($setID)['deleted']);
	}

	public function testTrashedProblemIsGoneForPlayers(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => false],
			'tsumegos' => [['deleted' => self::daysAgo(1), 'sets' => [['name' => 'published', 'num' => 1]]]]]);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);

		$this->assertResponseCode(404);
	}

	public function testLastVisitedProblemThatWasDeletedLeadsToCollections(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => false],
			'tsumegos' => [['deleted' => self::daysAgo(1), 'sets' => [['name' => 'published', 'num' => 1]]]]]);

		$this->get('/tsumegos/play/' . $context->tsumegos[0]['id']);

		$this->assertStringEndsWith('/sets', $this->headerValue('Location'));
	}

	public function testAdminSeesTrashedProblemWithRestoreButton(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [['deleted' => self::daysAgo(1), 'sets' => [['name' => 'published', 'num' => 1]]]]]);

		$this->get('/' . $context->tsumegos[0]['set-connections'][0]['id']);
		$view = $this->view;

		$this->assertStringContainsString('This problem is in the trash', $view);
		$this->assertStringContainsString('/trash/restore-problem/' . $context->tsumegos[0]['id'], $view);
		$this->assertStringNotContainsString('name="delete"', $view, 'A problem in the trash is not deleted again');
	}

	public function testTrashedSetIsGoneForPlayers(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => false],
			'tsumegos' => [['sets' => [['name' => 'gone', 'num' => 1, 'deleted' => self::daysAgo(1)]]]]]);

		$this->get('/sets');
		$view = $this->view;
		$this->assertStringNotContainsString('gone', $view);

		$this->get(Set::url($context->tsumegos[0]['sets'][0]['id']));

		$this->assertResponseCode(403);
	}

	public function testTrashedSetIsLeftOutOfTimeAndRatingMode(): void
	{
		new ContextPreparator(['tsumegos' => [
			['sets' => [['name' => 'gone', 'num' => 1, 'deleted' => self::daysAgo(1)]]],
			['deleted' => self::daysAgo(1), 'sets' => [['name' => 'live', 'num' => 1]]],
			['sets' => [['name' => 'live', 'num' => 2]]]]]);

		$official = Util::query('SELECT COUNT(DISTINCT sc.tsumego_id) AS total FROM set_connection sc'
			. ' JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL'
			. ' JOIN `set` ON `set`.id = sc.set_id WHERE ' . Set::officialCondition())[0]['total'];
		$this->assertSame(1, (int) $official);
	}

	public function testTrashPageListsProblemsAndSets(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				['deleted' => self::daysAgo(2), 'description' => 'doomed problem', 'sets' => [['name' => 'live', 'num' => 7]]],
				['sets' => [['name' => 'doomed collection', 'num' => 1, 'deleted' => self::daysAgo(3)]]]]]);

		$this->get('/trash');
		$view = $this->view;

		$this->assertStringContainsString('doomed problem', $view);
		$this->assertStringContainsString('live 7', $view);
		$this->assertStringContainsString('doomed collection', $view);
		$this->assertStringContainsString('restore-problem-' . $context->tsumegos[0]['id'], $view);
		$this->assertStringContainsString('restore-set-' . $context->tsumegos[1]['sets'][0]['id'], $view);
		$this->assertStringContainsString(substr(Trash::purgeDate(self::daysAgo(2)), 0, 10), $view);
	}

	public function testOnlyAdminSeesTrash(): void
	{
		new ContextPreparator(['user' => ['admin' => false]]);
		$this->get('/trash');

		$this->assertResponseCode(403);
	}

	public function testOnlyAdminRestores(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => false],
			'tsumegos' => [['deleted' => self::daysAgo(1), 'sets' => [['name' => 'live', 'num' => 1]]]]]);
		$this->post('/trash/restore-problem/' . $context->tsumegos[0]['id']);

		$this->assertResponseCode(403);
	}

	public function testAdminRestoresSet(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [['sets' => [['name' => 'back', 'num' => 1, 'deleted' => self::daysAgo(1)]]]]]);
		$setID = $context->tsumegos[0]['sets'][0]['id'];

		$this->post('/trash/restore-set/' . $setID);

		$this->assertNull(self::setRow($setID)['deleted']);
		$this->assertSame('The collection back was restored', CookieFlash::get());
		$activity = ClassRegistry::init('AdminActivity')->find('first')['AdminActivity'];
		$this->assertSame(AdminActivityType::SET_RESTORE, $activity['type']);
		$this->assertSame($setID, $activity['set_id']);
	}

	public function testDeletingPublishedSetNeedsItsTitle(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [['sets' => [['name' => 'Life and Death', 'num' => 1]]]]]);
		$setID = $context->tsumegos[0]['sets'][0]['id'];

		$this->post('/sets/delete/' . $setID, ['confirmation' => 'delete']);
		$this->assertNull(self::setRow($setID)['deleted']);

		$this->post('/sets/delete/' . $setID, ['confirmation' => 'Life and Death']);
		$this->assertNotNull(self::setRow($setID)['deleted']);
		$this->assertNull(self::tsumegoRow($context->tsumegos[0]['id'])['deleted'], 'Its problems wait in the set');
		$activity = ClassRegistry::init('AdminActivity')->find('first')['AdminActivity'];
		$this->assertSame(AdminActivityType::SET_DELETE, $activity['type']);
		$this->assertSame('Life and Death', $activity['old_value']);
	}

	public function testPlayerCollectionIsDeletedAtOnce(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => false],
			'tsumegos' => [['sets' => [['name' => 'published', 'num' => 1], ['name' => 'mine', 'num' => 1, 'user_id' => 'self']]]]]);
		$setID = $context->tsumegos[0]['sets'][1]['id'];

		$this->post('/sets/delete/' . $setID);

		$this->assertNull(self::setRow($setID));
		$this->assertNotNull(self::tsumegoRow($context->tsumegos[0]['id']));
	}

	public function testOnlyAdminDeletesPublishedSet(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => false],
			'tsumegos' => [['sets' => [['name' => 'published', 'num' => 1]]]]]);
		$this->post('/sets/delete/' . $context->tsumegos[0]['sets'][0]['id'], ['confirmation' => 'published']);

		$this->assertResponseCode(403);
	}

	public function testPurgeRemovesProblemAfterRetentionWithEverythingOfIt(): void
	{
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				[
					'deleted' => self::daysAgo(Trash::$RETENTION_DAYS + 1),
					'sets' => [['name' => 'published', 'num' => 1]],
					'status' => 'S',
					'comments' => [['message' => 'nice']],
					'variants' => [['tsumego_variant_type_id' => TsumegoVariant::TYPE_MULTIPLE_CHOICE, 'answer1' => 'a']]],
				['deleted' => self::daysAgo(Trash::$RETENTION_DAYS - 1), 'sets' => [['name' => 'published', 'num' => 2]]],
				['sets' => [['name' => 'published', 'num' => 3]]]]]);
		$purgedID = $context->tsumegos[0]['id'];
		ClassRegistry::init('Reject')->create();
		ClassRegistry::init('Reject')->save(['reject_type_id' => Reject::TYPE_PROPOSAL, 'tsumego_id' => $purgedID, 'user_id' => $context->user['id']]);

		$this->get('/cron/daily/' . CRON_SECRET);

		$this->assertNull(self::tsumegoRow($purgedID));
		foreach (['set_connection', 'tsumego_status', 'tsumego_comment', 'tsumego_variant', 'reject'] as $table)
			$this->assertSame(0, self::rowCount($table, 'tsumego_id = ?', [$purgedID]), $table);
		$this->assertNotNull(self::tsumegoRow($context->tsumegos[1]['id']), 'Still within the retention');
		$this->assertNotNull(self::tsumegoRow($context->tsumegos[2]['id']));
	}

	public function testPurgeRemovesSetWithProblemsInNoOtherSetOfTheSite(): void
	{
		$expired = self::daysAgo(Trash::$RETENTION_DAYS + 1);
		$context = new ContextPreparator([
			'user' => ['admin' => true],
			'tsumegos' => [
				['sets' => [['name' => 'expired', 'num' => 1, 'deleted' => $expired], ['name' => 'mine', 'num' => 1, 'user_id' => 'self']]],
				['sets' => [['name' => 'expired', 'num' => 2], ['name' => 'kept', 'num' => 1]]]]]);
		$expiredSetID = $context->tsumegos[0]['sets'][0]['id'];

		$result = Trash::purgeExpired();

		$this->assertSame(['sets' => 1, 'problems' => 1], $result);
		$this->assertNull(self::setRow($expiredSetID));
		$this->assertNull(self::tsumegoRow($context->tsumegos[0]['id']), 'Only a player collection had it');
		$this->assertNotNull(self::tsumegoRow($context->tsumegos[1]['id']), 'It is in another set of the site');
		$this->assertNotNull(self::setRow($context->tsumegos[0]['sets'][1]['id']), 'The player collection stays');
	}
}
