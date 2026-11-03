<?php

namespace App\Model\Table;

use App\Utility\Util;
use Cake\ORM\TableRegistry;

//A problem waiting in a sandbox set to be published into a public set on its day, which the daily cron does.
class ScheduleTable extends AppTable
{
	//A sandbox set: an unpublished collection of the site that is not in the trash.
	private const SANDBOX_CONDITION = '`Set`.public = 0 AND `Set`.user_id IS NULL AND `Set`.deleted IS NULL';

	//The newest SGF of the problem, whatever its status: a sandbox problem has no accepted one yet.
	private static function latestSgfIdSql(string $tsumegoId): string
	{
		return '(SELECT MAX(latest_sgf.id) FROM sgf latest_sgf WHERE latest_sgf.tsumego_id = ' . $tsumegoId . ')';
	}

	//The entries waiting to be published, the earliest day first, each with the sandbox set the
	//problem comes from (its first one) and its number there, or its number in the target set
	//when it is there already.
	/** @return list<array<string, mixed>> */
	public static function upcoming(): array
	{
		return Util::query('SELECT
				schedule.id, schedule.date, schedule.tsumego_id, schedule.set_id,
				COALESCE(sandbox_sc.num, target_sc.num) AS num,
				sandbox_source.set_id AS sandbox_set_id,
				sandbox_set.title AS sandbox_set_title,
				target_set.title AS target_set_title,
				COALESCE(sandbox_sc.id, target_sc.id) AS sc_id,
				sgf.sgf,
				tsumego.deleted AS tsumego_deleted,
				creator.display_name AS created_by_name,
				creator.id AS created_by_id
			FROM schedule
			LEFT JOIN tsumego ON tsumego.id = schedule.tsumego_id
			LEFT JOIN set_connection target_sc ON target_sc.tsumego_id = schedule.tsumego_id AND target_sc.set_id = schedule.set_id
			LEFT JOIN `set` target_set ON target_set.id = schedule.set_id
			LEFT JOIN (
				SELECT sc.tsumego_id, MIN(sc.set_id) AS set_id
				FROM set_connection sc
				JOIN `set` st ON st.id = sc.set_id
				WHERE st.public = 0 AND st.user_id IS NULL
				GROUP BY sc.tsumego_id
			) sandbox_source ON sandbox_source.tsumego_id = schedule.tsumego_id
			LEFT JOIN set_connection sandbox_sc ON sandbox_sc.tsumego_id = schedule.tsumego_id AND sandbox_sc.set_id = sandbox_source.set_id
			LEFT JOIN `set` sandbox_set ON sandbox_set.id = sandbox_sc.set_id
			LEFT JOIN sgf ON sgf.id = ' . self::latestSgfIdSql('schedule.tsumego_id') . '
			LEFT JOIN `user` creator ON creator.id = schedule.created_by_user_id
			WHERE schedule.published = 0
			ORDER BY schedule.date ASC');
	}

	//The entries published in the last 7 days, the latest first.
	/** @return list<array<string, mixed>> */
	public static function recentlyPublished(): array
	{
		return Util::query('SELECT schedule.id, schedule.date, schedule.tsumego_id, schedule.set_id,
				target_sc.num, target_set.title AS target_set_title
			FROM schedule
			LEFT JOIN set_connection target_sc ON target_sc.tsumego_id = schedule.tsumego_id AND target_sc.set_id = schedule.set_id
			LEFT JOIN `set` target_set ON target_set.id = schedule.set_id
			WHERE schedule.published = 1 AND schedule.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
			ORDER BY schedule.date DESC, schedule.id DESC');
	}

	//The problems of the source set that the next schedule takes, in the order of the set: the
	//ones not waiting in the schedule already, not in the target set already and not in the
	//trash, from position $num on, at most $count of them when it is given.
	/** @return list<array{tsumego_id: int, sc_id: int, num: int, sgf: ?string}> */
	public static function candidates(int $sourceSetId, int $targetSetId, int $num, ?int $count): array
	{
		return Util::query('SELECT sc.tsumego_id, sc.id AS sc_id, sc.num, sgf.sgf
			FROM set_connection sc
			JOIN tsumego ON tsumego.id = sc.tsumego_id AND tsumego.deleted IS NULL
			LEFT JOIN sgf ON sgf.id = ' . self::latestSgfIdSql('sc.tsumego_id') . '
			WHERE sc.set_id = ?
				AND sc.num >= ?
				AND NOT EXISTS (SELECT 1 FROM schedule WHERE schedule.tsumego_id = sc.tsumego_id AND schedule.published = 0)
				AND NOT EXISTS (SELECT 1 FROM set_connection target WHERE target.tsumego_id = sc.tsumego_id AND target.set_id = ?)
			ORDER BY sc.num ASC' . ($count === null ? '' : ' LIMIT ' . $count),
			[$sourceSetId, $num, $targetSetId]);
	}

	//The sets problems are scheduled from, as id and title in the order of the collections page.
	/** @return list<array{id: int, title: string}> */
	public static function sandboxSets(): array
	{
		return self::sets(self::SANDBOX_CONDITION);
	}

	//The sets problems are published into: the public collections of the site.
	/** @return list<array{id: int, title: string}> */
	public static function targetSets(): array
	{
		return self::sets(SetTable::officialCondition('Set'));
	}

	public static function isSandboxSet(int $setId): bool
	{
		return TableRegistry::getTableLocator()->get('Set')->exists(['Set.id' => $setId, self::SANDBOX_CONDITION]);
	}

	public static function isTargetSet(int $setId): bool
	{
		return TableRegistry::getTableLocator()->get('Set')->exists(['Set.id' => $setId, SetTable::officialCondition('Set')]);
	}

	/** @return list<array{id: int, title: string}> */
	private static function sets(string $condition): array
	{
		return TableRegistry::getTableLocator()->get('Set')->find()
			->select(['id', 'title'])
			->where([$condition])
			->orderBy('Set.order')
			->disableHydration()
			->toArray();
	}
}
