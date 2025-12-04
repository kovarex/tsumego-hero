<?php

/**
 * Unified progress tracking for tsumego status.
 *
 * Provides the same interface for both logged-in users and guests:
 * - Logged-in: Uses tsumego_status table (persistent)
 * - Guests: Uses cookie-based storage (session-persistent, cleared when browser closes)
 *
 * Status values:
 * - V = Visited (viewed but not attempted)
 * - S = Solved (solved on first try)
 * - W = Worked (solved, but after failures)
 * - C = Cleared (solved, progress reset and solved again)
 * - F = Failed (attempted but not solved)
 *
 * Usage:
 *   Progress::getStatus($tsumegoId)    // Get status for single tsumego
 *   Progress::setStatus($tsumegoId, $status)  // Set status
 *   Progress::getMap()                 // Get all statuses as [id => status]
 *   Progress::getMap([1, 2, 3])        // Get statuses for specific IDs
 */
class Progress
{
	private const COOKIE_NAME = 'guest_progress';
	private const COOKIE_EXPIRY = 86400 * 365; // 1 year
	private const MAX_GUEST_ENTRIES = 200; // Limit to keep cookie size reasonable

	/**
	 * @var array<int, string>|null Internal cache for guest progress
	 */
	private static ?array $guestCache = null;

	/**
	 * Get the status for a single tsumego.
	 *
	 * @param int $tsumegoId The tsumego ID
	 * @return string|null The status (V/S/W/C/F) or null if not found
	 */
	public static function getStatus(int $tsumegoId): ?string
	{
		if (Auth::isLoggedIn())
		{
			$result = ClassRegistry::init('TsumegoStatus')->find('first', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsumegoId,
				],
			]);
			return $result ? $result['TsumegoStatus']['status'] : null;
		}

		// Guest: use cookie
		$cache = self::loadGuestCache();
		return $cache[$tsumegoId] ?? null;
	}

	/**
	 * Set the status for a tsumego.
	 *
	 * @param int $tsumegoId The tsumego ID
	 * @param string $status The status (V/S/W/C/F)
	 */
	public static function setStatus(int $tsumegoId, string $status): void
	{
		if (Auth::isLoggedIn())
		{
			$model = ClassRegistry::init('TsumegoStatus');
			$existing = $model->find('first', [
				'conditions' => [
					'user_id' => Auth::getUserID(),
					'tsumego_id' => $tsumegoId,
				],
			]);

			if ($existing)
			{
				$model->id = $existing['TsumegoStatus']['id'];
				$model->saveField('status', $status);
			}
			else
			{
				$model->create();
				$model->save([
					'TsumegoStatus' => [
						'user_id' => Auth::getUserID(),
						'tsumego_id' => $tsumegoId,
						'status' => $status,
					],
				]);
			}
			return;
		}

		// Guest: use cookie
		$cache = self::loadGuestCache();
		$cache[$tsumegoId] = $status;

		// Limit size to keep cookie reasonable
		if (count($cache) > self::MAX_GUEST_ENTRIES)
		{
			// Keep only the most recent entries (assuming numeric keys are added in order)
			$cache = array_slice($cache, -self::MAX_GUEST_ENTRIES, null, true);
		}

		self::$guestCache = $cache;
		self::saveGuestCache($cache);
	}

	/**
	 * Get a map of tsumego statuses.
	 *
	 * @param array<int>|null $tsumegoIds Optional array of specific IDs to fetch
	 * @return array<int, string> Map of [tsumegoId => status]
	 */
	public static function getMap(?array $tsumegoIds = null): array
	{
		if (Auth::isLoggedIn())
		{
			$conditions = ['user_id' => Auth::getUserID()];
			if ($tsumegoIds !== null)
				$conditions['tsumego_id'] = $tsumegoIds;

			$results = ClassRegistry::init('TsumegoStatus')->find('all', [
				'conditions' => $conditions,
			]) ?: [];

			$map = [];
			foreach ($results as $result)
				$map[$result['TsumegoStatus']['tsumego_id']] = $result['TsumegoStatus']['status'];

			return $map;
		}

		// Guest: use cookie
		$cache = self::loadGuestCache();

		if ($tsumegoIds === null)
			return $cache;

		// Filter to only requested IDs
		$result = [];
		foreach ($tsumegoIds as $id)
			if (isset($cache[$id]))
				$result[$id] = $cache[$id];
		return $result;
	}

	/**
	 * Clear the internal cache (for testing).
	 */
	public static function clearCache(): void
	{
		self::$guestCache = null;
	}

	/**
	 * Load guest progress from cookie.
	 *
	 * @return array<int, string>
	 */
	private static function loadGuestCache(): array
	{
		if (self::$guestCache !== null)
			return self::$guestCache;

		if (!isset($_COOKIE[self::COOKIE_NAME]) || empty($_COOKIE[self::COOKIE_NAME]))
		{
			self::$guestCache = [];
			return self::$guestCache;
		}

		$decoded = json_decode($_COOKIE[self::COOKIE_NAME], true);
		self::$guestCache = is_array($decoded) ? $decoded : [];
		return self::$guestCache;
	}

	/**
	 * Save guest progress to cookie.
	 *
	 * @param array<int, string> $cache
	 */
	private static function saveGuestCache(array $cache): void
	{
		$json = json_encode($cache);
		$_COOKIE[self::COOKIE_NAME] = $json;
		setcookie(self::COOKIE_NAME, $json, time() + self::COOKIE_EXPIRY, '/');
	}
}
