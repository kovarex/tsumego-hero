<?php

namespace App\Model\Table;

use App\Utility\Util;
use Cake\Cache\Cache;

//The problems: what the player sees and the rating of each one. A problem in the trash has its
//deleted time set. A semeai problem has its values in tsumego_semeai.
class TsumegoTable extends AppTable
{
	public function initialize(array $config): void
	{
		parent::initialize($config);
		$this->hasOne('TsumegoSemeai', ['foreignKey' => 'tsumego_id']);
	}

	//Moves the rating of the problem by the change a result brought, within its minimum and maximum.
	//Only the rating is written, and the change is added to the rating stored now: the row a request
	//read at its start may be older than an admin's edit or another player's result on the problem.
	public static function addToRating(int $tsumegoID, float $change): void
	{
		Util::execute('UPDATE tsumego
			SET rating = CASE
				WHEN rating + ? < minimum_rating THEN minimum_rating
				WHEN rating + ? > maximum_rating THEN maximum_rating
				ELSE rating + ?
			END
			WHERE id = ?', [$change, $change, $change, $tsumegoID]);
	}

	//The problems the site holds: every problem that is not in the trash, whatever collection it
	//sits in and whether or not that collection is published yet. A problem that belongs to three
	//sets counts once. This is the number the solve counter of a player runs against on the
	//profile, so a solve of a problem of a collection nobody published yet is inside the fraction.
	public static function problemCount(): int
	{
		return (int) Util::query('SELECT COUNT(*) AS total FROM tsumego WHERE deleted IS NULL')[0]['total'];
	}

	//The problems of the site and how many of them the player solved.
	//Both numbers are counted over the same set of problems, so the second one can never
	//pass the first: a solve of a problem that does not belong to a collection of the site
	//(a sandbox build, an upload, a player collection) counts on neither side.
	/** @return array{total: int, solved: int} */
	public static function publicProblemProgress(int $userId): array
	{
		$inOfficialSet = fn(string $tsumegoRef): string
			=> 'EXISTS (SELECT 1 FROM set_connection sc JOIN `set` ON `set`.id = sc.set_id'
				. ' AND ' . SetTable::officialCondition() . ' WHERE sc.tsumego_id = ' . $tsumegoRef . ')';

		// The solved side only walks the statuses of that one player, which is what keeps this
		// from joining every problem of the site.
		$solved = Util::query(
			'SELECT COUNT(DISTINCT ts.tsumego_id) AS solved FROM tsumego_status ts'
			. ' JOIN tsumego t ON t.id = ts.tsumego_id AND t.deleted IS NULL'
			. ' WHERE ts.user_id = ? AND ' . TsumegoStatusTable::solvedCondition('ts.status')
			. ' AND ' . $inOfficialSet('ts.tsumego_id'),
			[$userId]
		)[0]['solved'];

		// the same number for every player, counted over all the problems, so it is kept until it changes
		$total = Cache::read(self::PUBLIC_PROBLEM_COUNT_CACHE_KEY, 'long');
		if ($total === null)
		{
			$total = (int) Util::query('SELECT COUNT(*) AS total FROM tsumego t WHERE t.deleted IS NULL AND ' . $inOfficialSet('t.id'))[0]['total'];
			Cache::write(self::PUBLIC_PROBLEM_COUNT_CACHE_KEY, $total, 'long');
		}

		return ['total' => (int) $total, 'solved' => (int) $solved];
	}

	private const string PUBLIC_PROBLEM_COUNT_CACHE_KEY = 'public_problem_count';

	//Drops the kept count of the problems of the official collections. It changes when a problem
	//joins or leaves an official collection, goes to the trash or comes back, and when a collection
	//does; SiteGrowth::forget() calls this, so the places that change both call that one.
	public static function forgetPublicProblemCount(): void
	{
		Cache::delete(self::PUBLIC_PROBLEM_COUNT_CACHE_KEY, 'long');
	}
}
