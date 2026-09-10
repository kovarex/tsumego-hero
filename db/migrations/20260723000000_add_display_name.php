<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Add display_name column to user table and clean up legacy data.
 *
 * This migration is IRREVERSIBLE - it cleans up legacy data that cannot be restored.
 *
 * Changes (in order):
 * 1. Add author_user_id FK column to tsumego table FIRST
 * 2. Populate author_user_id by matching tsumego.author to user.name (both still have g__ prefix)
 * 3. Clear old picture filenames (they pointed to non-deployed local files)
 * 4. Add display_name column
 * 5. Strip g__ prefix from email column (duplicates allowed)
 * 6. Strip g__ prefix from external_id column
 * 7. Strip g__ prefix from tsumego.author column
 * 8. Update tsumego.author to match user.display_name for tracked authors
 * 9. Make name column nullable and set to NULL for Google users
 *
 * Key insight: author_user_id matching must happen BEFORE any g__ stripping
 * so "g__John Smith" in tsumego.author matches "g__John Smith" in user.name.
 *
 * Google users (external_id IS NOT NULL) will have name=NULL because:
 * - They can't use password login (password_hash = 'google_oauth')
 * - Display should use display_name instead
 * - phpBB SSO uses display_name for usernames
 */
final class AddDisplayName extends AbstractMigration
{
	/**
	 * An account with fewer than this many attempts is considered a "non-real"
	 * account (a stray/accidental registration or an unused ghost) and is not
	 * allowed to keep a display name as long as any other account in the same
	 * duplicate group has at least this many attempts.
	 */
	private const REAL_ATTEMPTS = 10;

	/**
	 * An account is considered "actively used" (i.e. the person plays on it now)
	 * if it has at least this many distinct activity days within the recent
	 * window. This distinguishes real current use from a one-off stray login.
	 */
	private const RECENT_ACTIVITY_WINDOW_DAYS = 90;
	private const RECENT_ACTIVITY_MIN_DAYS = 3;

	/**
	 * An account with no activity at all for this long has stopped using the site,
	 * so it should not hold a contested name against someone who still plays.
	 */
	private const ABANDONED_AFTER_DAYS = 90;

	/**
	 * How many distinct activity days within the recent window a challenger needs
	 * before it takes a name away from an abandoned account. Ten days out of ninety
	 * is regular use; a burst of a few days on a fresh account is not.
	 */
	private const DISPLACEMENT_MIN_ACTIVE_DAYS = 10;

	/**
	 * Two normalized addresses count as the same person when one contains the other
	 * and the shared part is at least this long, so that "tom" vs "tomtom" or
	 * "david" vs "davidlee" stay separate accounts.
	 */
	private const SAME_PERSON_MIN_SHARED_CHARS = 6;

	/**
	 * Addresses that only differ by these many characters ("auraauarola@" vs
	 * "auraarola@", "geonikaido@" vs "gnikaido@") are the same person as well,
	 * as long as both are long enough for the match to mean something.
	 */
	private const SAME_PERSON_MAX_EDIT_DISTANCE = 2;
	private const SAME_PERSON_FUZZY_MIN_CHARS = 8;

	public function up(): void
	{
		// Disable FK checks for faster ALTER operations
		$this->execute("SET FOREIGN_KEY_CHECKS = 0");
		$this->execute("SET UNIQUE_CHECKS = 0");

		try
		{
			// Step 1: Add author_user_id column to tsumego table FIRST
			// This must happen BEFORE any g__ prefix stripping so we can match:
			// tsumego.author = "g__John Smith" matches user.name = "g__John Smith"
			$this->table('tsumego')
				->addColumn('author_user_id', 'integer', [
					'null' => true,
					'signed' => false,
					'after' => 'author',
				])
				->addForeignKey('author_user_id', 'user', 'id', [
					'delete' => 'SET_NULL',
					'update' => 'CASCADE',
				])
				->addIndex('author_user_id')
				->update();

			// Step 2: Populate author_user_id by matching tsumego.author to user.name
			// Both still have original values (including g__ prefixes for Google users)
			$this->execute("
				UPDATE tsumego t
				JOIN user u ON u.name = t.author
				SET t.author_user_id = u.id
				WHERE t.author_user_id IS NULL
			");

			// Step 2b: Fallback for Google users whose tsumego.author was already stripped
			// In some cases, tsumego.author = "Joschka Zimdars" but user.name = "g__Joschka Zimdars"
			// This happens when the PHP code stripped g__ from author when saving the tsumego
			$this->execute("
				UPDATE tsumego t
				JOIN user u ON u.name = CONCAT('g__', t.author)
				SET t.author_user_id = u.id
				WHERE t.author_user_id IS NULL
				AND t.author IS NOT NULL
				AND t.author != ''
			");

			// Step 3: Clear old picture filenames
			// The old `picture` values are filenames like `g__106434188846824891788.png`
			// that referenced locally-stored files in `/img/google/` which were never deployed.
			// New code will populate this with Google CDN URLs on next login.
			$this->execute("UPDATE user SET picture = NULL WHERE picture IS NOT NULL");

			// Step 4: Add display_name column (nullable initially)
			$this->table('user')
				->addColumn('display_name', 'string', [
					'limit' => 50,
					'null' => true,
					'after' => 'name',
					'comment' => 'Display name shown in UI (separate from login name)',
				])
				->addColumn('needs_display_name_change', 'boolean', [
					'default' => false,
					'null' => false,
					'after' => 'display_name',
					'comment' => 'True forces the user to choose a unique display name before continuing',
				])
				->update();

			// Step 5: Populate display_name for all existing users, normalized the same
			// way the app does (trim + collapse internal whitespace runs).
			// For Google users (external_id not null): strip g__ prefix if present
			// For regular users: copy from name
			$this->execute("
				UPDATE user
				SET display_name = TRIM(REGEXP_REPLACE(
					CASE
						WHEN external_id IS NOT NULL AND name LIKE 'g\\_\\_%' ESCAPE '\\\\' THEN SUBSTRING(name, 4)
						ELSE name
					END,
					'[[:space:]]+', ' '
				))
			");

			// Step 5b0: Fallback for users with no usable name (empty `name`).
			// Derive display_name from the email local part, and force them to pick
			// a proper name (needs_display_name_change = 1) via the change form.
			$this->execute("
				UPDATE user
				SET display_name = COALESCE(
						NULLIF(SUBSTRING_INDEX(SUBSTRING(email, IF(email LIKE 'g\\_\\_%', 4, 1)), '@', 1), ''),
						CONCAT('User', id)
					),
					needs_display_name_change = 1
				WHERE display_name = ''
			");

			// Step 5b: Handle duplicates by appending (N) suffix
			$this->execute("
				CREATE TEMPORARY TABLE temp_duplicates AS
				SELECT display_name, GROUP_CONCAT(id ORDER BY id) as ids, COUNT(*) as cnt
				FROM user
				WHERE display_name IS NOT NULL
				GROUP BY display_name
				HAVING COUNT(*) > 1;
			");

			$duplicates = $this->fetchAll("SELECT display_name, ids FROM temp_duplicates");

			foreach ($duplicates as $row)
			{
				$displayName = $row['display_name'];
				$ids = array_map('intval', explode(',', $row['ids']));

				// Keep the account that actually uses the site (most recent activity),
				// so the active user keeps their desired display name. Rename + flag
				// the inactive/duplicate accounts instead.
				$keepId = $this->pickKeptUserId($ids);
				$renameIds = array_values(array_diff($ids, [$keepId]));

				$suffix = 2;
				foreach ($renameIds as $id)
				{
					// Use (N) suffix format: "John Doe (2)", "John Doe (3)", etc.
					// Truncate the base so the result never exceeds VARCHAR(50).
					$maxLength = 50;
					$suffixStr = ' (' . $suffix . ')';
					$baseLength = $maxLength - strlen($suffixStr);
					$truncatedBase = $baseLength < strlen($displayName) ? substr($displayName, 0, $baseLength) : $displayName;
					$newName = $truncatedBase . $suffixStr;

					while ($this->fetchRow("SELECT id FROM user WHERE display_name = " . $this->getAdapter()->getConnection()->quote($newName)))
					{
						$suffix++;
						$suffixStr = ' (' . $suffix . ')';
						$baseLength = $maxLength - strlen($suffixStr);
						$truncatedBase = $baseLength < strlen($displayName) ? substr($displayName, 0, $baseLength) : $displayName;
						$newName = $truncatedBase . $suffixStr;
					}

					$this->execute("UPDATE user SET display_name = " . $this->getAdapter()->getConnection()->quote($newName) . ", needs_display_name_change = 1 WHERE id = $id");
					$suffix++;
				}
			}

			$this->execute("DROP TEMPORARY TABLE IF EXISTS temp_duplicates");

			// Step 5c: Make display_name NOT NULL and add UNIQUE constraint
			$this->execute("ALTER TABLE user MODIFY display_name VARCHAR(50) NOT NULL");

			$this->table('user')
				->addIndex(['display_name'], ['unique' => true, 'name' => 'idx_display_name_unique'])
				->update();

			// Step 6a: Strip g__ prefix from email column
			// Email does NOT have UNIQUE constraint, so duplicates are allowed
			// This affects Google users who were registered with 'g__email@example.com'
			$this->execute("
				UPDATE user
				SET email = SUBSTRING(email, 4)
				WHERE email LIKE 'g\\_\\_%' ESCAPE '\\\\'
			");

			// Step 6b: Strip g__ prefix from external_id column
			// The column name already tells us it's for external providers
			$this->execute("
				UPDATE user
				SET external_id = SUBSTRING(external_id, 4)
				WHERE external_id LIKE 'g\\_\\_%' ESCAPE '\\\\'
			");

			// Step 7: Strip g__ prefix from tsumego.author column (for display consistency)
			$this->execute("
				UPDATE tsumego
				SET author = SUBSTRING(author, 4)
				WHERE author LIKE 'g\\_\\_%' ESCAPE '\\\\'
			");

			// Step 8: Update tsumego.author to match user's display_name for tracked authors
			// This ensures author string stays in sync with the user's chosen display name
			$this->execute("
				UPDATE tsumego t
				JOIN user u ON t.author_user_id = u.id
				SET t.author = u.display_name
			");

			// Step 9: Make name nullable and set to NULL for Google users
			// Google users can't use password login, so they don't need a name for login
			$this->execute("ALTER TABLE user MODIFY name VARCHAR(50) NULL");
			$this->execute("UPDATE user SET name = NULL WHERE external_id IS NOT NULL");
		}
		finally
		{
			// Re-enable FK checks
			$this->execute("SET FOREIGN_KEY_CHECKS = 1");
			$this->execute("SET UNIQUE_CHECKS = 1");
		}
	}

	/**
	 * Among candidate user ids that share a display name, pick the one that should
	 * KEEP the display name.
	 *
	 * Policy:
	 * - Accounts that requested deletion (dbstorage = 1111) never keep the name.
	 * - Accounts that are the same person (same address, or one address contained
	 *   in the other) count as one: keep the account they actually play on, else
	 *   their MAIN account (the one with the MOST attempts).
	 * - An account with < REAL_ATTEMPTS attempts is a stray/accidental login and
	 *   cannot keep a name as long as any other account in the group is real.
	 * - A name held by an account that stopped playing (no activity for
	 *   ABANDONED_AFTER_DAYS) goes to a survivor that is in regular use
	 *   (DISPLACEMENT_MIN_ACTIVE_DAYS distinct days in the recent window), if any.
	 * - Otherwise an account is "alive" if it has >= 200 attempts OR had any
	 *   activity in the last 180 days, and the OLDEST (lowest id) alive one keeps
	 *   the name (seniority).
	 * - If none are alive (all effectively dead), keep the one with the MOST
	 *   attempts (least dead); ties go to the oldest (lowest id).
	 *
	 * Age is measured by `id` (auto-increment = true creation order), not by the
	 * `created` timestamp, which is unreliable for OAuth/Google accounts.
	 *
	 * @param int[] $ids User ids sharing a display name
	 * @return int The id of the account to keep
	 */
	private function pickKeptUserId(array $ids): int
	{
		// Accounts that asked to be deleted never keep the name.
		$candidates = array_filter($ids, fn($id) => !$this->isDeletionRequested($id));
		if (empty($candidates))
			$candidates = $ids;

		// One person can hold several accounts: Google sign-in used to create a
		// second account instead of linking the existing one, so the same human ends
		// up with both a password account and a g__ account.
		$byEmail = [];
		foreach ($candidates as $id)
			$byEmail[$this->emailCore($this->emailOf($id))][] = $id;

		$clusters = [];
		foreach ($byEmail as $core => $clusterIds)
		{
			$merged = false;
			foreach ($clusters as $index => $cluster)
			{
				if (!$this->isSamePersonEmail($cluster['core'], (string) $core))
					continue;
				$clusters[$index]['ids'] = array_merge($cluster['ids'], $clusterIds);
				$merged = true;
				break;
			}
			if (!$merged)
				$clusters[] = ['core' => (string) $core, 'ids' => $clusterIds];
		}

		// One survivor per person: the account they play on now, else their MAIN
		// account (most attempts).
		$survivors = [];
		foreach ($clusters as $cluster)
		{
			$active = array_filter($cluster['ids'], fn($id) => $this->isActivelyUsed($id));
			if (count($active) === 1)
			{
				// One person, so there is nobody to be unfair to: the account they use
				// keeps the name even when it is a "stray" by attempt count.
				$survivors[] = (int) reset($active);
				continue;
			}

			$real = array_filter($cluster['ids'], fn($id) => $this->attemptCount($id) >= self::REAL_ATTEMPTS);
			$pool = $real ? array_values($real) : array_values($cluster['ids']);
			$survivors[] = $this->pickMainAmong($pool);
		}

		// A non-real (stray) survivor may not keep the name while a real account exists.
		$anyReal = array_filter($survivors, fn($id) => $this->attemptCount($id) >= self::REAL_ATTEMPTS);
		if (!empty($anyReal))
			$survivors = array_values($anyReal);

		return $this->preferCurrentUser($survivors, $this->pickAmongSurvivors($survivors));
	}

	/**
	 * Hand the name to someone who still plays instead of an account that stopped.
	 *
	 * The holder is only displaced when it has been silent for ABANDONED_AFTER_DAYS
	 * AND another survivor is in regular use, so a veteran on a long break keeps the
	 * name against a fresh account that played a few puzzles.
	 *
	 * @param int[] $survivors One id per person, all sharing the display name
	 */
	private function preferCurrentUser(array $survivors, int $keep): int
	{
		if ($this->daysSinceLastActivity($keep) <= self::ABANDONED_AFTER_DAYS)
			return $keep;

		$challengers = array_filter(
			$survivors,
			fn($id) => $id !== $keep && $this->recentActivityDays($id) >= self::DISPLACEMENT_MIN_ACTIVE_DAYS
		);
		if (empty($challengers))
			return $keep;

		sort($challengers);

		return (int) $challengers[0]; // oldest of the current players
	}

	/**
	 * Among ids belonging to the SAME person (same email), keep the account the
	 * person actually plays on. If exactly one account is "actively used" (recent
	 * sustained activity) that one wins; otherwise keep the MAIN account (the one
	 * with the MOST attempts), ties going to the most recently used.
	 *
	 * @param int[] $ids
	 * @return int
	 */
	private function pickMainAmong(array $ids): int
	{
		$active = array_filter($ids, fn($id) => $this->isActivelyUsed($id));
		if (count($active) === 1)
			return (int) reset($active);

		$best = $ids[0];
		$bestAtt = $this->attemptCount($best);
		$bestLast = $this->lastActivityOf($best);
		foreach ($ids as $id)
		{
			$att = $this->attemptCount($id);
			$last = $this->lastActivityOf($id);
			if ($att > $bestAtt || ($att === $bestAtt && $last > $bestLast))
			{
				$best = $id;
				$bestAtt = $att;
				$bestLast = $last;
			}
		}

		return $best;
	}

	/**
	 * Whether an account is "actively used" now: at least RECENT_ACTIVITY_MIN_DAYS
	 * distinct activity days within RECENT_ACTIVITY_WINDOW_DAYS.
	 */
	private function isActivelyUsed(int $id): bool
	{
		return $this->recentActivityDays($id) >= self::RECENT_ACTIVITY_MIN_DAYS;
	}

	/**
	 * Number of distinct days the account had any activity within the recent
	 * window (attempts, statuses, comments, time-mode sessions, day records).
	 */
	private function recentActivityDays(int $id): int
	{
		$cutoff = date('Y-m-d 00:00:00', time() - self::RECENT_ACTIVITY_WINDOW_DAYS * 86400);
		$row = $this->fetchRow("
			SELECT COUNT(*) AS days FROM (
				SELECT DATE(created) AS d FROM tsumego_attempt WHERE user_id = $id AND created >= '$cutoff'
				UNION SELECT DATE(updated) FROM tsumego_status WHERE user_id = $id AND updated >= '$cutoff'
				UNION SELECT DATE(created) FROM tsumego_comment WHERE user_id = $id AND created >= '$cutoff'
				UNION SELECT DATE(created) FROM time_mode_session WHERE user_id = $id AND created >= '$cutoff'
				UNION SELECT date FROM day_record WHERE user_id = $id AND date >= '$cutoff'
			) x");
		return (int) $row['days'];
	}

	/**
	 * Among candidate ids (different people), apply the alive/seniority/least-dead
	 * rule. `id` ordering is the source of "older" (lower id = older).
	 *
	 * @param int[] $ids
	 * @return int
	 */
	private function pickAmongSurvivors(array $ids): int
	{
		sort($ids); // ascending: $ids[0] is the oldest (lowest id)

		$alive = array_filter($ids, fn($id) => $this->isAlive($id));
		if (!empty($alive))
			return min($alive); // keep the oldest alive (lowest id)

		// All dead: keep the least dead (most attempts), tie -> oldest (lowest id).
		$best = $ids[0];
		$bestAttempts = $this->attemptCount($best);
		foreach ($ids as $id)
		{
			$attempts = $this->attemptCount($id);
			if ($attempts > $bestAttempts || ($attempts === $bestAttempts && $id < $best))
			{
				$best = $id;
				$bestAttempts = $attempts;
			}
		}

		return $best;
	}

	/**
	 * Whether an account is "alive": has >= $attemptThreshold attempts, OR had any
	 * activity within $activityWindowDays of the migration run.
	 *
	 * The `solved` count is intentionally NOT used - it is inflated by time-mode
	 * and denormalized status transitions, so it is unreliable. `tsumego_attempt`
	 * count is a better volume signal.
	 */
	private function isAlive(int $id): bool
	{
		$attemptThreshold = 200;
		$activityWindowDays = 180;

		if ($this->attemptCount($id) >= $attemptThreshold)
			return true;

		$last = $this->lastActivityOf($id);
		if ($last <= '1970-01-01 00:00:00')
			return false;

		$lastTime = strtotime($last);
		if ($lastTime === false)
			return false;

		return (time() - $lastTime) <= $activityWindowDays * 86400;
	}

	/**
	 * Number of tsumego_attempt rows for a user (distinct problems attempted in
	 * regular mode). Activity-volume signal for the dedup policy.
	 */
	private function attemptCount(int $id): int
	{
		$row = $this->fetchRow("SELECT COUNT(*) AS c FROM tsumego_attempt WHERE user_id = $id");
		return (int) $row['c'];
	}

	/**
	 * Email of a user (used to detect duplicate accounts of the same person).
	 */
	private function emailOf(int $id): string
	{
		$row = $this->fetchRow("SELECT email FROM user WHERE id = $id");
		return $row['email'] ?? '';
	}

	/**
	 * Normalize an email for comparison: lowercase and strip a leading `g__`.
	 */
	private function normalizeEmail(string $email): string
	{
		$email = strtolower(trim($email));
		return str_starts_with($email, 'g__') ? substr($email, 3) : $email;
	}

	/**
	 * Local part of an address with everything but letters and digits removed, so
	 * two addresses of one person can be compared: "victor.diaz-caballero@" and
	 * "victordiazcaballerogo@" become "victordiazcaballero" and
	 * "victordiazcaballerogo".
	 */
	private function emailCore(string $email): string
	{
		$email = $this->normalizeEmail($email);
		$localPart = strstr($email, '@', true);
		if ($localPart === false)
			$localPart = $email;

		return (string) preg_replace('/[^a-z0-9]/', '', $localPart);
	}

	/**
	 * Whether two address cores belong to the same person: equal, one contained in
	 * the other, or only a couple of characters apart. Trailing digits are ignored
	 * ("peterlin741@" against "thepeterlin@") because they are usually a year or a
	 * counter. Accounts without an address are never treated as the same person.
	 */
	private function isSamePersonEmail(string $first, string $second): bool
	{
		if ($first === '' || $second === '')
			return false;

		$firstBase = rtrim($first, '0123456789');
		$secondBase = rtrim($second, '0123456789');
		foreach ([[$first, $second], [$firstBase, $secondBase]] as [$shorter, $longer])
		{
			if ($shorter === '' || $longer === '')
				continue;
			if ($shorter === $longer)
				return true;

			if (strlen($shorter) > strlen($longer))
				[$shorter, $longer] = [$longer, $shorter];
			if (strlen($shorter) >= self::SAME_PERSON_MIN_SHARED_CHARS && str_contains($longer, $shorter))
				return true;
		}

		return strlen($first) >= self::SAME_PERSON_FUZZY_MIN_CHARS
			&& strlen($second) >= self::SAME_PERSON_FUZZY_MIN_CHARS
			&& levenshtein($first, $second) <= self::SAME_PERSON_MAX_EDIT_DISTANCE;
	}

	/**
	 * Days since the account's last activity; PHP_INT_MAX when it never had any.
	 */
	private function daysSinceLastActivity(int $id): int
	{
		$last = $this->lastActivityOf($id);
		if ($last <= '1970-01-01 00:00:00')
			return PHP_INT_MAX;

		return (int) floor((time() - strtotime($last)) / 86400);
	}

	/**
	 * Whether a user has requested account deletion (dbstorage = 1111).
	 */
	private function isDeletionRequested(int $id): bool
	{
		$row = $this->fetchRow("SELECT dbstorage FROM user WHERE id = $id");
		return (int) ($row['dbstorage'] ?? 0) === 1111;
	}

	/**
	 * Last activity of a user as a comparable 'YYYY-MM-DD HH:MM:SS' string.
	 * Users with no activity return the '1970-01-01' sentinel.
	 */
	private function lastActivityOf(int $id): string
	{
		$row = $this->fetchRow("
			SELECT GREATEST(
				COALESCE((SELECT MAX(created) FROM tsumego_attempt WHERE user_id = $id), '1970-01-01 00:00:00'),
				COALESCE((SELECT MAX(updated) FROM tsumego_status WHERE user_id = $id), '1970-01-01 00:00:00'),
				COALESCE((SELECT MAX(created) FROM tsumego_comment WHERE user_id = $id), '1970-01-01 00:00:00'),
				COALESCE((SELECT MAX(created) FROM time_mode_session WHERE user_id = $id), '1970-01-01 00:00:00'),
				COALESCE((SELECT MAX(CAST(date AS DATETIME)) FROM day_record WHERE user_id = $id), '1970-01-01 00:00:00'),
				COALESCE((SELECT MAX(created) FROM tsumego_issue WHERE user_id = $id), '1970-01-01 00:00:00')
			) AS activity");
		return $row['activity'];
	}

	/**
	 * This migration is IRREVERSIBLE.
	 *
	 * Reasons:
	 * - picture column was cleared (old filenames are gone)
	 * - g__ prefixes stripped from email/external_id cannot be accurately restored
	 * - Restoring would break functionality anyway
	 *
	 * To "undo": restore from backup before this migration.
	 */
	public function down(): void
	{
		throw new \RuntimeException(
			'This migration is irreversible. Restore from database backup if needed.'
		);
	}
}
