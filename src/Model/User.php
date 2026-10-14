<?php

use App\Utility\PhpbbNames;
use App\Utility\Rating;
use App\Utility\Util;

/**
 * @phpstan-import-type UserRow from \App\Utility\RowTypes
 */
class User extends AppModel
{
	public const PREF_PLAYER_COLOR_RANDOM = 0;
	public const PREF_PLAYER_COLOR_ORIGINAL = 1;
	public const PREF_BOARD_ORIENTATION_RANDOM = 0;
	public const PREF_BOARD_ORIENTATION_ORIGINAL = 1;

	// Shown instead of the name of a user whose account is not there anymore.
	public const DELETED_NAME = '[account deleted]';

	// The user table stores a display name in this many characters.
	public const DISPLAY_NAME_MAX_LENGTH = 50;

	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'user';
		parent::__construct($id, $table, $ds);
	}

	public $validate = [
		'name' => [

			'notempty' => [
				'rule' => 'notBlank',
				'allowEmpty' => true,
				'message' => 'Please Insert Name',
			],

			'minmax' => [
				'rule' => ['between', 3, 30],
				'allowEmpty' => true,
				'message' => 'The length of the name should have at least 3 characters',
			],
		],

		'email' => [
			'notempty' => [
				'rule' => 'notBlank',
				'message' => 'Insert Email',
			],

			'email' => [
				'rule' => ['email', false],
				'message' => 'Enter a valid email-address',
			],
		],

		// A display name is required: every page shows it and only a flagged
		// account may change it. The login name above stays optional because a
		// Google account does not have one.
		'display_name' => [
			'notempty' => [
				'rule' => 'notBlank',
				'message' => 'Insert Display Name',
			],

			'maxlength' => [
				'rule' => ['maxLength', self::DISPLAY_NAME_MAX_LENGTH],
				'message' => 'The display name must not be longer than 50 characters',
			],
		],
	];

	/**
	 * @param UserRow $user
	 */
	public static function renderPremium(array $user): string
	{
		$premium = $user['premium'] ?? 0;
		if ($premium == 2 || $premium == 1)
			return '<img alt="Account Type" title="Account Type" src="/img/premium' . $premium . '.png" height="16px">';
		return '';
	}

	/**
	 * @param UserRow $user
	 */
	public static function getHighestRating(array $user): float
	{
		$highestTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('first', [
			'conditions' => ['user_id' => $user['id']],
			'order' => 'user_rating DESC']);
		if ($highestTsumegoAttempt)
			return max($highestTsumegoAttempt['TsumegoAttempt']['user_rating'], $user['rating']);
		return $user['rating'];
	}

	/**
	 * Marks the account as deleted. Everything it created stays, but its name and Google id move to the
	 * deleted_* columns, so they are free for a new account, and it can't sign in anymore.
	 */
	public static function deleteAccount(int $userID): void
	{
		Util::execute("UPDATE user SET
			deleted = NOW(),
			deleted_name = name,
			deleted_external_id = external_id,
			name = CONCAT('[deleted ', id, ']'),
			external_id = NULL,
			deletion_token = NULL,
			deletion_token_created = NULL
			WHERE id = ? AND deleted IS NULL", [$userID]);
		self::clearDeletedCache();
	}

	/**
	 * Restores a deleted account, unless its name or Google id was taken by another account in the
	 * meantime.
	 *
	 * @return string|null why the account can't be restored, null when it was restored
	 */
	public static function restoreAccount(int $userID): ?string
	{
		$user = ClassRegistry::init('User')->findById($userID);
		if (!$user || !$user['User']['deleted'])
			return 'The account is not deleted.';
		$user = $user['User'];

		if (Util::query('SELECT id FROM user WHERE name = ?', [$user['deleted_name']]))
			return 'The name ' . $user['deleted_name'] . ' is used by another account now.';
		if ($user['deleted_external_id'] !== null && Util::query('SELECT id FROM user WHERE external_id = ?', [$user['deleted_external_id']]))
			return 'The Google account is linked to another account now.';

		Util::execute("UPDATE user SET
			deleted = NULL,
			name = deleted_name,
			external_id = deleted_external_id,
			deleted_name = NULL,
			deleted_external_id = NULL
			WHERE id = ?", [$userID]);
		self::clearDeletedCache();
		return null;
	}

	/**
	 * Ids of the deleted accounts, loaded once per request. It answers the callers that have nothing
	 * but an id (the payloads read the name as a plain string out of a raw query); a caller that has a
	 * row reads the deleted column of that row instead, so a stale cache can't name a deleted account.
	 *
	 * @var array<int, true>|null
	 */
	private static ?array $deletedIDs = null;

	// To be called after an account was deleted or restored, otherwise the callers that pass a bare id
	// keep the old answer. A caller that passes a row is not affected.
	public static function clearDeletedCache(): void
	{
		self::$deletedIDs = null;
	}

	/**
	 * Whether the account of a user is not there anymore. A row answers from its own deleted column,
	 * which every find() and contain('User') already loads; a bare id is looked up in the ids loaded
	 * once per request. A row that carries neither an id nor the column is treated as gone, the same
	 * way renderLink treats it.
	 *
	 * @param UserRow|array<string, mixed>|int|string|null $user
	 */
	public static function isDeleted(array|int|string|null $user): bool
	{
		if (is_array($user))
		{
			if (array_key_exists('deleted', $user) || array_key_exists('user_deleted', $user))
				return !empty($user['deleted']) || !empty($user['user_deleted']);
			$user = $user['id'] ?? $user['user_id'] ?? null;
			if ($user === null)
				return true;
		}
		if (self::$deletedIDs === null)
		{
			self::$deletedIDs = [];
			foreach (Util::query('SELECT id FROM user WHERE deleted IS NOT NULL') as $row)
				self::$deletedIDs[(int) $row['id']] = true;
		}
		return isset(self::$deletedIDs[(int) $user]);
	}

	/**
	 * The display name of a user, null when there is none to show: the account is not there anymore,
	 * or the row the caller passed carries no name. This is what the frontend gets, it renders
	 * DELETED_NAME itself. Pass a user row, folded or built from a raw query, nothing else.
	 *
	 * @param UserRow|array<string, mixed>|null $user
	 */
	public static function displayNameOrNull(?array $user): ?string
	{
		if ($user === null || self::isDeleted($user))
			return null;

		$displayName = $user['display_name'] ?? $user['user_display_name'] ?? null;
		return $displayName === null || $displayName === '' ? null : $displayName;
	}

	/**
	 * The same rule as displayNameOrNull() for the places that print the name instead of sending it
	 * to the frontend: a user whose account is not there anymore is shown as DELETED_NAME.
	 *
	 * @param UserRow|array<string, mixed>|null $user
	 */
	public static function displayName(?array $user): string
	{
		return self::displayNameOrNull($user) ?? self::DELETED_NAME;
	}

	/**
	 * The avatar to send to the frontend, null when the account is not there anymore. The Gravatar URL
	 * is derived from the e-mail, which a deleted account keeps, so sending it would keep publishing
	 * the hash of the address of someone who asked to be forgotten.
	 *
	 * @param UserRow|array<string, mixed> $user Row with the id, external_id, picture and email
	 */
	public static function avatarOrNull(array $user, int $size = 20): ?string
	{
		return self::isDeleted($user) ? null : self::getAvatarUrl($user, $size);
	}

	/**
	 * Render a user link with avatar and rank calculated from rating.
	 *
	 * @param array|int|string|null $id User row (with display_name, external_id, picture, rating,
	 *                                  email), a bare user id, or null for a gone account
	 * @param string|null $displayName Display name, used when $id is a bare id
	 * @param string|null $externalID
	 * @param string|null $picture
	 * @param int|float|string|null $rating
	 * @return string HTML link (or plain text for a deleted account)
	 */
	public static function renderLink(array|int|string|null $id, ?string $displayName = null, ?string $externalID = null, ?string $picture = null, int|float|string|null $rating = null): string
	{
		// A bare id (or null for a gone account): the renderers pass whole rows, this is for the
		// handful of callers that only have an id.
		if (!is_array($id))
		{
			if ($id === null)
				return '<span>' . h(self::DELETED_NAME) . '</span>';
			if (self::isDeleted($id))
				return h(self::DELETED_NAME);
			$rank = $rating !== null ? Rating::getReadableRankFromRating((float) $rating) : '';
			return '<a href="/users/view/' . $id . '">' . h((string) $displayName) . ($rank === '' ? '' : ' ' . $rank) . '</a>';
		}

		$user = $id;

		// A row without an id means the account is gone
		if (empty($user['id']) && empty($user['user_id']))
			return '<span>' . h(self::DELETED_NAME) . '</span>';

		// A deleted account is shown as a placeholder, never linked or named
		if (self::isDeleted($user))
			return h(self::DELETED_NAME);

		// Prefixed format (from raw queries): user_id, user_display_name, etc.
		if (isset($user['user_id']))
		{
			$displayName = (string) ($user['user_display_name'] ?? '');
			if ($displayName === '')
				return '<span>' . h(self::DELETED_NAME) . '</span>';
			$rating = $user['user_rating'] ?? null;
			$rank = $rating !== null ? Rating::getReadableRankFromRating((float) $rating) : '';
			return self::renderLinkInternal($user['user_id'], $displayName, $user['user_external_id'] ?? null, $user['user_picture'] ?? null, $user['user_email'] ?? null, $rank);
		}

		// Standard format: id, display_name, etc. A row without a display name gets the same
		// placeholder as a gone account, never the login name
		$displayName = (string) ($user['display_name'] ?? '');
		if ($displayName === '')
			return '<span>' . h(self::DELETED_NAME) . '</span>';
		$rating = $user['rating'] ?? null;
		$rank = $rating !== null ? Rating::getReadableRankFromRating((float) $rating) : '';
		return self::renderLinkInternal($user['id'], $displayName, $user['external_id'] ?? null, $user['picture'] ?? null, $user['email'] ?? null, $rank);
	}

	/**
	 * Internal function to render user link HTML.
	 */
	private static function renderLinkInternal(int $id, string $displayName, ?string $externalID, ?string $picture, ?string $email, string $rank): string
	{
		assert(($externalID !== null && $picture !== null) || $email !== null, 'Must have either (externalID + picture) or email for avatar');

		// Generate avatar HTML
		$avatarUrl = self::getAvatarUrl([
			'external_id' => $externalID,
			'picture' => $picture,
			'email' => $email,
		], 20);
		$image = '<img src="' . h((string) $avatarUrl) . '" alt="" class="user-avatar" loading="lazy" decoding="async" referrerpolicy="no-referrer" style="width:20px;height:20px;border-radius:50%;vertical-align:middle;margin-right:4px;">';

		return '<a href="/users/view/' . $id . '">' . $image . h((string) $displayName) . ($rank === '' ? '' : ' ' . $rank) . '</a>';
	}

	/**
	 * Normalize a display name: trim surrounding whitespace and collapse any
	 * internal runs of whitespace (including newlines/tabs) into single spaces.
	 * This prevents lookalike names (" admin ", "admin  name") and keeps the
	 * uniqueness check meaningful.
	 *
	 * @param string $name The raw display name input
	 * @return string The normalized display name
	 */
	public static function normalizeDisplayName(string $name): string
	{
		$name = trim($name);
		$name = preg_replace('/\s+/u', ' ', $name);
		return $name;
	}

	/**
	 * Generates a unique display name by adding (2), (3), etc. suffix if display_name already exists.
	 *
	 * @param string $name The desired base display name
	 * @return string A unique display_name (either the original or with numbered suffix)
	 */
	public static function generateUniqueDisplayName(string $name): string
	{
		$name = self::normalizeDisplayName($name);

		// The column holds 50 characters and a Google name is not length limited,
		// so a longer name is shortened before the uniqueness check.
		if (mb_strlen($name) > self::DISPLAY_NAME_MAX_LENGTH)
			$name = mb_substr($name, 0, self::DISPLAY_NAME_MAX_LENGTH);

		$userModel = ClassRegistry::init('User');

		// Check if base value is available
		$exists = $userModel->find('count', ['conditions' => ['display_name' => $name]]);
		if ($exists == 0)
			return $name;

		// Find next available suffix (2), (3), etc.
		$suffix = 2;
		while (true)
		{
			$suffixStr = " ($suffix)";
			$maxLength = self::DISPLAY_NAME_MAX_LENGTH;
			$baseLength = $maxLength - mb_strlen($suffixStr);

			// Truncate base value if needed to fit suffix
			$truncatedBase = $baseLength < mb_strlen($name) ? mb_substr($name, 0, $baseLength) : $name;
			$candidate = $truncatedBase . $suffixStr;

			$exists = $userModel->find('count', ['conditions' => ['display_name' => $candidate]]);
			if ($exists == 0)
				return $candidate;

			$suffix++;

			// Safety limit to prevent infinite loop
			if ($suffix > 1000)
				throw new RuntimeException("Unable to generate unique display_name for: $name");
		}
	}

	/**
	 * Updates a user's display_name in the main database and syncs to phpBB forum.
	 *
	 * @param int $userId The user ID
	 * @param string $newDisplayName The new display name to set
	 * @return bool True on success
	 */
	public static function updateDisplayName(int $userId, string $newDisplayName): bool
	{
		$connection = Util::connection();
		$connection->beginTransaction();
		try
		{
			// 1. Update display_name in main app user table
			Util::query(
				"UPDATE user SET display_name = ? WHERE id = ?",
				[$newDisplayName, $userId]
			);

			// 2. Update tsumego author strings for problems created by this user
			Util::query(
				"UPDATE tsumego SET author = ? WHERE author_user_id = ?",
				[$newDisplayName, $userId]
			);
			$connection->commit();
		}
		catch (Throwable $exception)
		{
			$connection->rollBack();
			throw $exception;
		}

		// 3. Sync to phpBB forum. The forum name is a copy of this one and the next
		// forum sign in repairs it (jwtauth::sync_rename), so a forum that can not
		// be written to right now must not fail the rename itself.
		try
		{
			self::syncToForum($userId, $newDisplayName);
		}
		catch (Throwable $exception)
		{
			CakeLog::write('warning', 'Forum name not synced for user ' . $userId . ': ' . $exception->getMessage());
		}

		return true;
	}

	/**
	 * Syncs a user's display name to the phpBB forum tables.
	 *
	 * The forum shares the application database and its single sign-on reads the user table
	 * directly, so the phpbb_* rows are written through the default connection.
	 *
	 * @param int $userId The user ID (maps to external_id in phpBB)
	 * @param string $displayName The display name to sync
	 */
	private static function syncToForum(int $userId, string $displayName): void
	{
		PhpbbNames::sync(Util::connection(), $userId, $displayName);
	}

	/**
	 * Check if user is using Gravatar (not Google picture).
	 *
	 * @param UserRow $user
	 * @return bool True if user avatar comes from Gravatar
	 */
	public static function isUsingGravatar(array $user): bool
	{
		// Google users with pictures don't use Gravatar
		if (!empty($user['external_id']) && !empty($user['picture']))
			return false;
		return true;
	}

	/**
	 * Check if user is a Google user (has external_id).
	 *
	 * @param UserRow $user
	 * @return bool True if user is a Google user
	 */
	public static function isGoogleUser(array $user): bool
	{
		return !empty($user['external_id']);
	}

	/**
	 * Find a local (non-Google) user by email.
	 * Local users have external_id = NULL and use password authentication.
	 * Use this for password login and password reset to exclude Google users.
	 *
	 * The address is not unique in the database, so the order makes the result of
	 * several matching accounts deterministic (the oldest account wins).
	 *
	 * @param string $email Email to search for
	 * @return array|null User data or null if not found
	 */
	public function findLocalUserByEmail(string $email): ?array
	{
		return $this->find('first', [
			'conditions' => [
				'email' => $email,
				'external_id IS NULL',
			],
			'order' => ['id ASC'],
		]) ?: null;
	}

	/**
	 * Get the avatar URL for a user with priority: Google picture > Gravatar > default.
	 *
	 * Only the avatar fields are needed, the callers pass either a full user row or
	 * just these three values.
	 *
	 * @param array{external_id?: string|null, picture?: string|null, email?: string|null} $user
	 * @param int $size Desired avatar size in pixels (default 40)
	 * @return string Avatar URL
	 */
	public static function getAvatarUrl(array $user, int $size = 40): string
	{
		// Priority 1: Google picture (if Google user with picture)
		if (!empty($user['external_id']) && !empty($user['picture']))
		{
			$picture = $user['picture'];
			// Google CDN (lh3.googleusercontent.com) supports =s{SIZE} parameter for resizing
			// Only apply Google-style sizing to Google CDN URLs
			if (str_contains($picture, 'googleusercontent.com'))
			{
				// Remove existing size parameter if present and add new one
				$picture = preg_replace('/=s\d+(-c)?/', '', $picture);
				return $picture . '=s' . $size;
			}
			// For non-Google URLs, return as-is (don't mangle the URL)
			return $picture;
		}

		// Priority 2: Gravatar
		$email = $user['email'] ?? '';
		$hash = md5(strtolower(trim($email)));
		return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=identicon";
	}
}
