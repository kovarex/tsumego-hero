<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Decodes the text that the code base before the 2022 charset fix stored double
 * encoded: the UTF-8 bytes were read as Windows-1252 and saved as UTF-8 again, so
 * "Jérémie" became "JÃ©rÃ©mie" and "Аня Нильга" 19 characters of mojibake. Every
 * non-ASCII value written before that fix is affected; the value itself can be
 * restored without any loss (the characters read back as Windows-1252 bytes are
 * the UTF-8 bytes of the original text).
 *
 * Rows that carry such a value on this data: user.display_name 291, user.name 67.
 * tsumego_comment.message, set.author, tag.description, user.deleted_name, tsumego.author,
 * set.title and the forum tables hold nothing that is still double encoded; they are in
 * the loop anyway, because a database from a different point in time may.
 *
 * user.name is deliberately left alone. It is the login identifier, unique under
 * a case and accent insensitive collation, and the site never shows it: players
 * see display_name. Rewriting it could invalidate a saved username and lock an
 * owner out, and nothing visible would be gained. Signing in with the stored
 * spelling, or with the e-mail address, keeps working for those owners.
 *
 * user.display_name is what players see and is unique as well. 71 of the 291
 * accounts would share a name after decoding, so the group keeps one account
 * under the name and numbers the others ("Gökhan (2)"), asking them to pick a
 * name of their own on the next sign in. The keeper is chosen by the rules the
 * display name migration uses for the duplicates it created: an account that
 * asked to be deleted never keeps the name, several accounts of one person count
 * as one (and the one still used keeps it), then the account that plays now, then
 * attempts, then age. An account that already shows the name it would get after
 * decoding keeps it, unless the account it competes with plays now and it does
 * not, so a readable name is only taken from somebody who stopped playing. The
 * comparison runs with the collation of the column, not in PHP, so "GÖKHAN" and
 * "Gökhan" count as the same name.
 *
 * Runs after 20261013000000, which adds user.display_name, its unique index and
 * the needs_display_name_change flag.
 *
 * IRREVERSIBLE: decoded text cannot be told apart from text that was always
 * correct, so there is nothing meaningful to roll back.
 */
final class DecodeLegacyDoubleEncodedText extends AbstractMigration
{
	/** The character the converter substitutes for characters it cannot express. */
	private const SUBSTITUTE = 0x3F;

	/** Length of user.display_name. */
	private const NAME_MAX_LENGTH = 50;

	/** An account with fewer attempts is a stray registration, not a real player. */
	private const REAL_ATTEMPTS = 10;

	/** Activity within this window, on this many distinct days, means "plays now". */
	private const RECENT_ACTIVITY_WINDOW_DAYS = 90;
	private const RECENT_ACTIVITY_MIN_DAYS = 3;

	/** An account silent for this long has stopped, so it should not hold a name. */
	private const ABANDONED_AFTER_DAYS = 90;

	/** Distinct active days a challenger needs to take a name from such an account. */
	private const DISPLACEMENT_MIN_ACTIVE_DAYS = 10;

	/** An account is alive with this many attempts, or with activity within this time. */
	private const ALIVE_ATTEMPTS = 200;
	private const ALIVE_ACTIVITY_DAYS = 180;

	/** Address cores count as one person when one contains the other, or nearly matches. */
	private const SAME_PERSON_MIN_SHARED_CHARS = 6;
	private const SAME_PERSON_MAX_EDIT_DISTANCE = 2;
	private const SAME_PERSON_FUZZY_MIN_CHARS = 8;

	private const PLANNED_TABLE = 'planned_display_names';

	public function up(): void
	{
		$previousCharset = (string) $this->fetchRow('SELECT @@character_set_connection AS charset')['charset'];

		// A decoded name can hold characters outside the basic multilingual plane,
		// and a connection opened by an older deploy still reports utf8mb3.
		$this->execute('SET NAMES utf8mb4');
		try
		{
			foreach ([
				['tsumego_comment', 'message'],
				['set', 'author'],
				['tag', 'description'],
				['user', 'deleted_name'],
			] as [$table, $column])
				$this->write($table . '.' . $column . ': decoded ' . $this->decodeColumn($table, $column) . ' values');

			$this->decodeDisplayNames();
		}
		finally
		{
			$this->execute('SET NAMES ' . $previousCharset);
		}
	}

	public function down(): void
	{
		throw new \RuntimeException('This migration cannot be rolled back. Decoded text cannot be told apart from text that was always correct.');
	}

	/**
	 * The original text, or null when the value is not double encoded and has to
	 * be left alone.
	 */
	private static function decode(string $value): ?string
	{
		$bytes = self::windows1252Bytes($value);

		return $bytes === null ? null : self::readableText($bytes);
	}

	/**
	 * The bytes Windows-1252 gives back, which are the UTF-8 bytes of the original
	 * text, or null when the value is not double encoded. A double encoded value only
	 * holds characters Windows-1252 has, so text that still contains something it
	 * cannot express (correctly written Cyrillic, CJK, an emoji) never went through
	 * that connection and must not be converted: the conversion would turn those
	 * characters into "?" and lose them.
	 */
	private static function windows1252Bytes(string $value): ?string
	{
		$previous = mb_substitute_character();
		mb_substitute_character(self::SUBSTITUTE);
		try
		{
			$bytes = mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
		}
		finally
		{
			mb_substitute_character($previous);
		}

		// Nothing to convert when the text is already plain ASCII or already decoded.
		if (!is_string($bytes) || $bytes === $value)
			return null;

		// A character that could not be expressed became "?", so the value was
		// not text that went through a Windows-1252 connection.
		if (substr_count($bytes, chr(self::SUBSTITUTE)) !== substr_count($value, chr(self::SUBSTITUTE)))
			return null;

		return $bytes;
	}

	/**
	 * The text of a byte string, when it is valid UTF-8 and does not still look
	 * double encoded. Null otherwise.
	 */
	private static function readableText(string $bytes): ?string
	{
		if (!mb_check_encoding($bytes, 'UTF-8'))
			return null;

		if (preg_match('/[ÐÃ][\x{80}-\x{BF}]/u', $bytes))
			return null;

		return $bytes;
	}

	/**
	 * Decodes every value of a column that is double encoded. Everything else is
	 * left alone: plain ASCII, text that is already correct, and text that still
	 * contains characters Windows-1252 has no code point for.
	 */
	private function decodeColumn(string $table, string $column): int
	{
		$decoded = 0;
		$rows = $this->fetchAll('SELECT `id`, `' . $column . '` AS `stored` FROM `' . $table . '` WHERE `' . $column . '` IS NOT NULL');

		foreach ($rows as $row)
		{
			$value = self::decode($row['stored']);
			if ($value === null)
				continue;

			$this->execute('UPDATE `' . $table . '` SET `' . $column . '` = ? WHERE `id` = ?', [$value, $row['id']]);
			$decoded++;
		}

		return $decoded;
	}

	/**
	 * Decodes the display names and numbers the ones that would share a name.
	 */
	private function decodeDisplayNames(): void
	{
		$plan = $this->planDisplayNames();
		$written = $this->applyNames($plan);

		$renumbered = array_filter($plan, fn(array $change) => $change['needsChange']);
		$this->write('user.display_name: rewrote ' . $written . ' names');
		if ($renumbered !== [])
			$this->write('user.display_name: ' . count($renumbered)
				. ' accounts would have shared a decoded name and were numbered');
		foreach ($renumbered as $id => $change)
			$this->write('  ' . $id . ' => ' . $change['name']);

		$this->repairBrokenNames();
	}

	/**
	 * A display name that is still unreadable after the decoding has lost the byte A0.
	 * It is the second byte of every two byte character ending in A0 (Р is D0 A0), and
	 * a whitespace rule that counts a non-breaking space as whitespace turns it into a
	 * plain space, which leaves "Ð" followed by " " and a byte stream that is no
	 * longer UTF-8.
	 *
	 * A space directly after a UTF-8 lead byte cannot be a real space (a lead byte is
	 * always followed by a continuation byte), so it is put back as A0. That is the
	 * only certain repair: a character that was lost to "?" as well cannot be
	 * recovered, and the login name is no substitute, because the owner may have picked
	 * a display name of their own. Such accounts are only asked to pick a name, so
	 * nothing uncertain is ever written.
	 */
	private function repairBrokenNames(): void
	{
		$restored = [];
		$flagged = [];
		foreach ($this->fetchAll('SELECT `id`, `display_name` FROM `user` WHERE `needs_display_name_change` = 0') as $row)
		{
			if (!preg_match('/[ÐÃ][\x{80}-\x{BF}]/u', $row['display_name']))
				continue;

			$id = (int) $row['id'];
			$name = self::restoreCollapsedSpaces($row['display_name']);
			if ($name === null)
			{
				$this->execute('UPDATE `user` SET `needs_display_name_change` = 1 WHERE `id` = ?', [$id]);
				$flagged[] = $id;
				continue;
			}

			// The name may already be shown by another account, and then it has to be
			// numbered like every other contested name.
			$needsChange = $this->isDisplayNameTaken($name, $id);
			if ($needsChange)
				$name = self::number($name, fn(string $candidate) => $this->isDisplayNameTaken($candidate, $id));

			$this->execute('UPDATE `user` SET `display_name` = ?, `needs_display_name_change` = ? WHERE `id` = ?',
				[$name, $needsChange ? 1 : 0, $id]);
			$restored[] = $id;
		}

		$this->write('user.display_name: restored ' . count($restored)
			. ' names by putting the collapsed byte back (ids ' . implode(', ', $restored) . ')');
		if ($flagged !== [])
			$this->write('user.display_name: ' . count($flagged)
				. ' names were damaged beyond that and left for the owner to pick (ids ' . implode(', ', $flagged) . ')');
	}

	/**
	 * The text of a display name whose non-breaking spaces were collapsed to plain
	 * spaces: the bytes Windows-1252 gives back, with every space that follows a
	 * UTF-8 lead byte turned back into A0. Null when the result is not valid UTF-8.
	 */
	private static function restoreCollapsedSpaces(string $stored): ?string
	{
		$bytes = self::windows1252Bytes($stored);
		if ($bytes === null)
			return null;

		$restored = '';
		for ($index = 0, $length = strlen($bytes); $index < $length; $index++)
		{
			$byte = $bytes[$index];
			$previous = $index > 0 ? ord($bytes[$index - 1]) : 0;
			if ($byte === ' ' && $previous >= 0xC2 && $previous <= 0xDF)
				$byte = "\xA0";
			$restored .= $byte;
		}

		return self::readableText($restored);
	}

	/**
	 * Whether another account already shows the name. The comparison uses the
	 * collation of the column.
	 */
	private function isDisplayNameTaken(string $name, int $id): bool
	{
		$statement = $this->connection()->prepare('SELECT `id` FROM `user` WHERE `display_name` = ? AND `id` <> ? LIMIT 1');
		$statement->execute([$name, $id]);

		return $statement->fetch(PDO::FETCH_ASSOC) !== false;
	}

	/**
	 * The names to write, keyed by account id. Only accounts whose display name
	 * changes are listed. Renumbered accounts come first: their old name has to be
	 * released before the account that keeps it can take it.
	 *
	 * @return array<int, array{name: string, needsChange: bool}>
	 */
	private function planDisplayNames(): array
	{
		$now = time();
		$this->fillPlannedNames();

		$groups = $this->groupsWithDuplicates();
		$changes = [];
		$renumbered = [];

		if (!empty($groups))
		{
			$facts = $this->loadFacts(array_merge(...$groups), $now);
			foreach ($groups as $ids)
			{
				$keeper = self::pickKeeper($ids, $facts, $now);
				foreach ($ids as $id)
				{
					if ($id === $keeper)
						continue;

					$name = self::number($this->plannedName($id),
						fn(string $candidate) => $this->isPlannedNameTaken($candidate, $id));
					$this->savePlannedName($id, $name);
					$renumbered[$id] = true;
				}
			}
		}

		if (!empty($this->groupsWithDuplicates()))
			throw new \RuntimeException('The decoded display names still share a name after numbering them.');

		foreach ($this->namesThatDiffer() as [$id, $name])
			$changes[$id] = ['name' => $name, 'needsChange' => isset($renumbered[$id])];

		uksort($changes,
			fn(int $first, int $second) => (isset($renumbered[$second]) <=> isset($renumbered[$first])) ?: $first <=> $second);

		return $changes;
	}

	/**
	 * Writes the planned names. The flag is only ever set, never cleared, so an
	 * account already asked to pick a name keeps that request.
	 *
	 * @param array<int, array{name: string, needsChange: bool}> $changes
	 */
	private function applyNames(array $changes): int
	{
		$connection = $this->connection();
		$renamed = $connection->prepare('UPDATE user SET display_name = ?, needs_display_name_change = 1 WHERE id = ?');
		$decoded = $connection->prepare('UPDATE user SET display_name = ? WHERE id = ?');

		// Phinx wraps the whole migration in a transaction, so a failure here leaves
		// every name as it was instead of half of them rewritten.
		foreach ($changes as $id => $change)
		{
			if ($change['needsChange'])
				$renamed->execute([$change['name'], $id]);
			else
				$decoded->execute([$change['name'], $id]);
		}

		return count($changes);
	}

	/**
	 * The account that keeps a contested name.
	 *
	 * @param int[] $ids accounts that would share the name
	 * @param array<int, array{email: string, readable: bool, attempts: int, lastActivity: string, recentDays: int, deletionRequested: bool}> $facts
	 */
	private static function pickKeeper(array $ids, array $facts, int $now): int
	{
		$candidates = array_values(array_filter($ids, fn(int $id) => !$facts[$id]['deletionRequested']));
		if (empty($candidates))
			$candidates = array_values($ids);

		// One person can hold several accounts, and only one of them can keep the name.
		$survivors = [];
		foreach (self::clusters($candidates, $facts) as $cluster)
			$survivors[] = self::pickMainAmong($cluster, $facts);

		$real = array_values(array_filter($survivors, fn(int $id) => $facts[$id]['attempts'] >= self::REAL_ATTEMPTS));
		if (!empty($real))
			$survivors = $real;

		$keeper = self::pickAmongSurvivors($survivors, $facts, $now);
		$keeper = self::preferCurrentPlayer($survivors, $keeper, $facts, $now);

		return self::preferReadableName($survivors, $keeper, $facts, $now);
	}

	/**
	 * Hands the name to an account that already shows it, so that the account that
	 * loses the name is one whose name was unreadable anyway. A readable name is only
	 * taken from an account that stopped playing, and only by somebody who plays now:
	 * when both sides are silent, the name stays where it is already visible.
	 *
	 * @param int[] $survivors
	 * @param array<int, array{email: string, readable: bool, attempts: int, lastActivity: string, recentDays: int, deletionRequested: bool}> $facts
	 */
	private static function preferReadableName(array $survivors, int $keeper, array $facts, int $now): int
	{
		if ($facts[$keeper]['readable'])
			return $keeper;

		$keeperPlays = self::isAlive($keeper, $facts, $now);
		$candidates = array_values(array_filter($survivors,
			fn(int $id) => $id !== $keeper && $facts[$id]['readable']
				&& (!$keeperPlays || self::isAlive($id, $facts, $now))));
		if (empty($candidates))
			return $keeper;

		sort($candidates);

		return (int) $candidates[0];
	}

	/**
	 * A free name for an account that cannot keep the contested one: the name
	 * itself when free, otherwise the first free " (N)" addition that still fits
	 * the column. An existing " (N)" at the end of the name is replaced instead of
	 * stacked, so a name that was numbered before does not become "Name (2) (2)".
	 *
	 * @param callable(string): bool $isTaken
	 */
	private static function number(string $name, callable $isTaken, int $maxLength = self::NAME_MAX_LENGTH): string
	{
		if (!$isTaken($name))
			return $name;

		// A name that was numbered before ("Name (2)") goes back to its base when
		// that is free, instead of growing a second suffix.
		$base = (string) preg_replace('/ \(\d+\)$/', '', $name);
		if ($base !== $name && !$isTaken($base))
			return $base;

		for ($number = 2; ; $number++)
		{
			$suffix = ' (' . $number . ')';
			$candidate = mb_substr($base, 0, $maxLength - mb_strlen($suffix)) . $suffix;
			if (!$isTaken($candidate))
				return $candidate;
		}
	}

	/**
	 * The account a person still uses, within the accounts of one person: the one
	 * with the most recent activity. A name must never end up on an account its
	 * owner stopped signing in to, so attempts only break a tie between accounts
	 * used on the same day.
	 *
	 * @param int[] $ids
	 * @param array<int, array{email: string, readable: bool, attempts: int, lastActivity: string, recentDays: int, deletionRequested: bool}> $facts
	 */
	private static function pickMainAmong(array $ids, array $facts): int
	{
		$best = (int) $ids[0];
		foreach ($ids as $id)
		{
			$last = $facts[$id]['lastActivity'];
			if ($last > $facts[$best]['lastActivity']
				|| ($last === $facts[$best]['lastActivity'] && $facts[$id]['attempts'] > $facts[$best]['attempts']))
					$best = (int) $id;
		}

		return $best;
	}

	/**
	 * Among accounts of different people: the oldest alive one, otherwise the one
	 * that played the most, otherwise the oldest.
	 *
	 * @param int[] $ids
	 * @param array<int, array{email: string, readable: bool, attempts: int, lastActivity: string, recentDays: int, deletionRequested: bool}> $facts
	 */
	private static function pickAmongSurvivors(array $ids, array $facts, int $now): int
	{
		sort($ids);
		$alive = array_values(array_filter($ids, fn(int $id) => self::isAlive($id, $facts, $now)));
		if (!empty($alive))
			return (int) $alive[0];

		$best = (int) $ids[0];
		foreach ($ids as $id)
			if ($facts[$id]['attempts'] > $facts[$best]['attempts'])
				$best = (int) $id;

		return $best;
	}

	/**
	 * Hands the name of an account that stopped playing to somebody who plays now.
	 *
	 * @param int[] $survivors
	 * @param array<int, array{email: string, readable: bool, attempts: int, lastActivity: string, recentDays: int, deletionRequested: bool}> $facts
	 */
	private static function preferCurrentPlayer(array $survivors, int $keeper, array $facts, int $now): int
	{
		if (self::daysSinceLastActivity($keeper, $facts, $now) <= self::ABANDONED_AFTER_DAYS)
			return $keeper;

		$challengers = array_values(array_filter($survivors,
			fn(int $id) => $id !== $keeper && $facts[$id]['recentDays'] >= self::DISPLACEMENT_MIN_ACTIVE_DAYS));
		if (empty($challengers))
			return $keeper;

		sort($challengers);

		return (int) $challengers[0];
	}

	/**
	 * @param array<int, array{email: string, readable: bool, attempts: int, lastActivity: string, recentDays: int, deletionRequested: bool}> $facts
	 */
	private static function isAlive(int $id, array $facts, int $now): bool
	{
		if ($facts[$id]['attempts'] >= self::ALIVE_ATTEMPTS)
			return true;

		$last = strtotime($facts[$id]['lastActivity']);

		return $last !== false && $last > 0 && ($now - $last) <= self::ALIVE_ACTIVITY_DAYS * 86400;
	}

	/**
	 * @param array<int, array{email: string, readable: bool, attempts: int, lastActivity: string, recentDays: int, deletionRequested: bool}> $facts
	 */
	private static function daysSinceLastActivity(int $id, array $facts, int $now): int
	{
		$last = strtotime($facts[$id]['lastActivity']);
		if ($last === false || $last <= 0)
			return PHP_INT_MAX;

		return (int) floor(($now - $last) / 86400);
	}

	/**
	 * Groups the accounts by person, so that several accounts of one person count
	 * as one candidate instead of competing with each other.
	 *
	 * @param int[] $ids
	 * @param array<int, array{email: string, readable: bool, attempts: int, lastActivity: string, recentDays: int, deletionRequested: bool}> $facts
	 * @return array<int, int[]>
	 */
	private static function clusters(array $ids, array $facts): array
	{
		$parent = array_combine($ids, $ids);
		$values = array_values($ids);
		foreach ($values as $index => $first)
			foreach (array_slice($values, $index + 1) as $second)
				if (self::isSamePerson($facts[$first]['email'], $facts[$second]['email']))
					$parent[self::rootOf($parent, $second)] = self::rootOf($parent, $first);

		$clusters = [];
		foreach ($ids as $id)
			$clusters[self::rootOf($parent, $id)][] = (int) $id;

		return array_values($clusters);
	}

	/**
	 * @param array<int, int> $parent
	 */
	private static function rootOf(array &$parent, int $id): int
	{
		while ($parent[$id] !== $id)
		{
			$parent[$id] = $parent[$parent[$id]];
			$id = $parent[$id];
		}

		return $id;
	}

	private static function isSamePerson(string $firstEmail, string $secondEmail): bool
	{
		$first = self::emailCore($firstEmail);
		$second = self::emailCore($secondEmail);
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

	private static function emailCore(string $email): string
	{
		$email = strtolower(trim($email));
		if (str_starts_with($email, 'g__'))
			$email = substr($email, 3);

		$localPart = strstr($email, '@', true);
		if ($localPart === false)
			$localPart = $email;

		return (string) preg_replace('/[^a-z0-9]/', '', $localPart);
	}

	/**
	 * Fills the planning table with the name every account would have after
	 * decoding. Accounts without a display name cannot be planned and are skipped.
	 */
	private function fillPlannedNames(): void
	{
		$connection = $this->connection();
		$connection->exec('DROP TEMPORARY TABLE IF EXISTS ' . self::PLANNED_TABLE);
		$connection->exec('CREATE TEMPORARY TABLE ' . self::PLANNED_TABLE
			. ' (id INT NOT NULL PRIMARY KEY, name VARCHAR(' . self::NAME_MAX_LENGTH . ')'
			. ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL)');

		$insert = $connection->prepare('INSERT INTO ' . self::PLANNED_TABLE . ' (id, name) VALUES (?, ?)');
		foreach ($connection->query('SELECT id, display_name FROM user WHERE display_name IS NOT NULL') as $row)
			$insert->execute([(int) $row['id'], self::decode($row['display_name']) ?? $row['display_name']]);
	}

	/**
	 * Groups of accounts that would share a name after decoding, compared with the
	 * collation of user.display_name.
	 *
	 * @return array<int, int[]>
	 */
	private function groupsWithDuplicates(): array
	{
		$groups = [];
		$rows = $this->connection()->query('SELECT GROUP_CONCAT(id ORDER BY id) AS ids FROM '
			. self::PLANNED_TABLE . ' GROUP BY name HAVING COUNT(*) > 1');
		foreach ($rows as $row)
			$groups[] = array_map('intval', explode(',', $row['ids']));

		return $groups;
	}

	private function plannedName(int $id): string
	{
		$statement = $this->connection()->prepare('SELECT name FROM ' . self::PLANNED_TABLE . ' WHERE id = ?');
		$statement->execute([$id]);
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		if ($row === false)
			throw new \RuntimeException('Account ' . $id . ' has no planned display name.');

		return $row['name'];
	}

	private function savePlannedName(int $id, string $name): void
	{
		$statement = $this->connection()->prepare('UPDATE ' . self::PLANNED_TABLE . ' SET name = ? WHERE id = ?');
		$statement->execute([$name, $id]);
	}

	/**
	 * Whether another account plans to use the name. The comparison uses the
	 * collation of the column, so a case or accent difference still counts.
	 */
	private function isPlannedNameTaken(string $name, int $id): bool
	{
		$statement = $this->connection()->prepare('SELECT id FROM ' . self::PLANNED_TABLE
			. ' WHERE name = ? AND id <> ? LIMIT 1');
		$statement->execute([$name, $id]);

		return $statement->fetch(PDO::FETCH_ASSOC) !== false;
	}

	/**
	 * Accounts whose stored display name differs from the planned one, byte for
	 * byte (the collation would call a case only change equal).
	 *
	 * @return array<int, array{int, string}>
	 */
	private function namesThatDiffer(): array
	{
		$rows = $this->connection()->query('SELECT p.id, p.name FROM ' . self::PLANNED_TABLE . ' p'
			. ' JOIN user u ON u.id = p.id WHERE BINARY p.name <> BINARY u.display_name ORDER BY p.id');

		$result = [];
		foreach ($rows as $row)
			$result[] = [(int) $row['id'], $row['name']];

		return $result;
	}

	/**
	 * What the policy needs to know about each account: who it is, how much it
	 * played and how recently. tsumego_status.updated is not used, because every row
	 * carries the same timestamp, so it says nothing about activity.
	 *
	 * @param int[] $ids
	 * @return array<int, array{email: string, readable: bool, attempts: int, lastActivity: string, recentDays: int, deletionRequested: bool}>
	 */
	private function loadFacts(array $ids, int $now): array
	{
		$cutoff = gmdate('Y-m-d H:i:s', $now - self::RECENT_ACTIVITY_WINDOW_DAYS * 86400);
		$facts = [];
		foreach (array_map('intval', $ids) as $id)
		{
			$row = $this->connection()->query("
				SELECT u.email, u.deleted, u.deletion_token, u.display_name,
					(SELECT COUNT(*) FROM tsumego_attempt a WHERE a.user_id = $id) AS attempts,
					(SELECT GREATEST(
						COALESCE((SELECT MAX(a.created) FROM tsumego_attempt a WHERE a.user_id = $id), '1970-01-01 00:00:00'),
						COALESCE((SELECT MAX(c.created) FROM tsumego_comment c WHERE c.user_id = $id), '1970-01-01 00:00:00'),
						COALESCE((SELECT MAX(t.created) FROM time_mode_session t WHERE t.user_id = $id), '1970-01-01 00:00:00'),
						COALESCE((SELECT MAX(CAST(r.date AS DATETIME)) FROM day_record r WHERE r.user_id = $id), '1970-01-01 00:00:00'),
						COALESCE((SELECT MAX(i.created) FROM tsumego_issue i WHERE i.user_id = $id), '1970-01-01 00:00:00')
					)) AS last_activity,
					(SELECT COUNT(*) FROM (
						SELECT DATE(a.created) AS day FROM tsumego_attempt a WHERE a.user_id = $id AND a.created >= '$cutoff'
						UNION SELECT DATE(c.created) FROM tsumego_comment c WHERE c.user_id = $id AND c.created >= '$cutoff'
						UNION SELECT DATE(t.created) FROM time_mode_session t WHERE t.user_id = $id AND t.created >= '$cutoff'
						UNION SELECT r.date FROM day_record r WHERE r.user_id = $id AND r.date >= '$cutoff'
					) days) AS recent_days
				FROM user u WHERE u.id = $id")->fetch(PDO::FETCH_ASSOC);

			if ($row === false)
				throw new \RuntimeException('Account ' . $id . ' does not exist.');

			$facts[$id] = [
				'email' => (string) $row['email'],
				// A name the decoding will not touch is one the owner already sees.
				'readable' => self::decode((string) $row['display_name']) === null,
				'attempts' => (int) $row['attempts'],
				'lastActivity' => (string) $row['last_activity'],
				'recentDays' => (int) $row['recent_days'],
				'deletionRequested' => !empty($row['deleted']) || !empty($row['deletion_token']),
			];
		}

		return $facts;
	}

	private function connection(): PDO
	{
		return $this->getAdapter()->getConnection();
	}

	private function write(string $message): void
	{
		$output = $this->getOutput();
		if ($output)
			$output->writeln($message);
	}
}
