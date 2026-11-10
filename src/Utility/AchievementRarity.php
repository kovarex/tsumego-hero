<?php

namespace App\Utility;

use Cake\Cache\Cache;

//How rare each achievement is: the share of the active players who have it. An active player is an
//account not deleted that solved at least one problem; only those count as holders too, so the share
//never passes 100%. It is one count per achievement over achievement_status, the same for every page,
//kept for an hour (CACHE_SECONDS): rarity moves slowly, and an unlock does not recount it.
class AchievementRarity
{
	public const int CACHE_SECONDS = 3600;

	//The active players and the holders of each achievement among them, by achievement id
	/** @return array{players: int, holders: array<int, int>} */
	public static function counts(): array
	{
		// read once a request: a page asks for the rarity of every achievement it lists
		if (self::$counted !== null && self::$counted['counted'] > time() - self::CACHE_SECONDS)
			return self::$counted['counts'];
		$cached = Cache::read(self::CACHE_KEY, 'long');
		if (is_array($cached) && $cached['counted'] > time() - self::CACHE_SECONDS)
			return (self::$counted = $cached)['counts'];

		$players = (int) Util::query('SELECT COUNT(*) AS players FROM user WHERE ' . self::ACTIVE_PLAYER)[0]['players'];
		$holders = [];
		foreach (Util::query('SELECT achievement_status.achievement_id, COUNT(*) AS holders FROM achievement_status'
			. ' JOIN user ON user.id = achievement_status.user_id AND ' . self::ACTIVE_PLAYER
			. ' GROUP BY achievement_status.achievement_id') as $row)
				$holders[(int) $row['achievement_id']] = (int) $row['holders'];
		$counts = ['players' => $players, 'holders' => $holders];
		self::$counted = ['counted' => time(), 'counts' => $counts];
		Cache::write(self::CACHE_KEY, self::$counted, 'long');
		return $counts;
	}

	//The percent of the active players who have the achievement. A player looking at one they have
	//counts as a holder, so an achievement unlocked since the last count is never shown as nobody's.
	public static function percent(int $achievementID, bool $held = false): float
	{
		$counts = self::counts();
		$holders = $counts['holders'][$achievementID] ?? 0;
		if ($held)
			$holders = max($holders, 1);
		return min(100.0, $holders / max($counts['players'], 1) * 100);
	}

	//Drops the kept counts, so the next ask counts again
	public static function forget(): void
	{
		self::$counted = null;
		Cache::delete(self::CACHE_KEY, 'long');
	}

	private const string CACHE_KEY = 'achievement_rarity';

	/** @var array{counted: int, counts: array{players: int, holders: array<int, int>}}|null */
	private static ?array $counted = null;

	private const string ACTIVE_PLAYER = 'user.deleted IS NULL AND user.solved > 0';
}
