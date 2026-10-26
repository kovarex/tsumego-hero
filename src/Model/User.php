<?php

use App\Utility\AdminActivityLogger;
use App\Utility\Auth;
use App\Utility\Avatar;
use App\Utility\Constants;
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

	/** The ids of the date_format table, how the dates are written. app/shared/dates.ts has the patterns. */
	public const DATE_FORMAT_ISO = 1; // 2026-10-21 14:05
	public const DATE_FORMAT_DAY_MONTH_YEAR = 2; // 21. 10. 2026 14:05, the Czech way
	public const DATE_FORMAT_MONTH_DAY_YEAR = 3; // 10/21/2026 2:05 PM
	public const DATE_FORMAT_BROWSER = 4; // whatever the language of the browser uses

	/**
	 * The ids of the user_role table, what a user may do on the site. An admin edits the problems
	 * and collections and decides the proposals. An owner can do everything an admin can, and also
	 * decides who is an admin or an owner.
	 */
	public const ROLE_PLAYER = 1;
	public const ROLE_ADMIN = 2;
	public const ROLE_OWNER = 3;

	/** The roles with the rights of an admin. */
	public const ADMIN_ROLES = [self::ROLE_ADMIN, self::ROLE_OWNER];

	/** The names of the roles, as the user_role table has them. */
	public const ROLE_NAMES = [
		self::ROLE_PLAYER => 'player',
		self::ROLE_ADMIN => 'admin',
		self::ROLE_OWNER => 'owner',
	];

	/** Whether the player wants sound. The cookie spells them 'on' and 'off'. */
	public const SOUND_OFF = 0;
	public const SOUND_ON = 1;

	/** The theme the player picked. */
	public const THEME_LIGHT = 0;
	public const THEME_DARK = 1;

	/**
	 * What the bar in the header shows. There is no 0 because the cookie that carries
	 * the choice reads 0 as "nothing picked yet".
	 */
	public const LEVEL_BAR_LEVEL = 1;
	public const LEVEL_BAR_RATING = 2;

	// Shown instead of the name of a user whose account is not there anymore.
	public const DELETED_NAME = '[account deleted]';

	// The user table stores a display name in this many characters.
	public const DISPLAY_NAME_MAX_LENGTH = 50;

	// The longest e-mail address there can be (RFC 5321), and what the user table stores.
	public const EMAIL_MAX_LENGTH = 254;

	/** The avatar a player picked: the board the site draws. */
	public const AVATAR_BOARD = 0;

	/** The avatar a player picked: their Gravatar. */
	public const AVATAR_GRAVATAR = 1;

	/** The avatar a player picked: the picture of their Google account. */
	public const AVATAR_GOOGLE = 2;

	/**
	 * The pixels asked of an avatar host for the avatar in a list: twice the 24px
	 * the list shows (--avatar-size in tokens.css), so a sharp screen has enough.
	 */
	public const AVATAR_PIXELS_INLINE = 48;

	/** Twice the 64px a profile shows (--avatar-size-lg in tokens.css). */
	public const AVATAR_PIXELS_PROFILE = 128;

	/**
	 * A character above U+FFFF (an emoji, for one) takes four bytes in UTF-8. The phpBB
	 * tables the site shares store names as utf8mb3, which cannot hold one, so a display
	 * name with such a character would differ between the site and the forum.
	 */
	public const UNSTORABLE_PATTERN = '/[\x{10000}-\x{10FFFF}]/u';

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

			'maxlength' => [
				'rule' => ['maxLength', self::EMAIL_MAX_LENGTH],
				'message' => 'An email address has at most 254 characters',
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

			// The forum cannot store a character above U+FFFF (an emoji, a rare kanji), so a
			// name the site could not copy there would be the result. It guards the name an
			// account is created with; an account that already carries one keeps working, and
			// a rename is checked by UsersController::updatename().
			'storableByForum' => [
				'rule' => 'hasOnlyCharactersTheForumCanStore',
				'message' => 'The display name must not contain emoji or other rare symbols.',
				'on' => 'create',
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
	 * Whether a user has the rights of an admin, which an owner has too. False for a visitor who is
	 * not logged in (null).
	 *
	 * @param UserRow|array<string, mixed>|null $user
	 */
	public static function isAdmin(?array $user): bool
	{
		return $user !== null && in_array((int) ($user['user_role_id'] ?? 0), self::ADMIN_ROLES, true);
	}

	/**
	 * @param UserRow|array<string, mixed>|null $user
	 */
	public static function isOwner(?array $user): bool
	{
		return $user !== null && (int) ($user['user_role_id'] ?? 0) === self::ROLE_OWNER;
	}

	/**
	 * SQL that tells whether the user behind $roleColumn has the rights of an admin, for the raw
	 * queries that read the role together with something else.
	 */
	public static function isAdminSql(string $roleColumn): string
	{
		return $roleColumn . ' IN (' . implode(', ', self::ADMIN_ROLES) . ')';
	}

	/**
	 * Gives a user another role, which is the decision of an owner (see OwnerPolicy). The site
	 * always keeps an owner, so the last one can't stop being one until there is another.
	 *
	 * @return string|null why the role can't be changed, null when the user has the role now
	 */
	public static function changeRole(int $userID, int $roleID): ?string
	{
		if (!array_key_exists($roleID, self::ROLE_NAMES))
			return 'There is no such role.';
		$user = ClassRegistry::init('User')->findById($userID);
		if (!$user || $user['User']['deleted'])
			return 'The account is not there.';
		$user = $user['User'];
		$oldRoleID = (int) $user['user_role_id'];
		if ($oldRoleID === $roleID)
			return null;
		if ($oldRoleID === self::ROLE_OWNER && self::ownerCount() === 1)
			return $user['display_name'] . ' is the last owner, make someone else an owner first.';

		if (Auth::getUserID() === $userID)
			Auth::saveUserField('user_role_id', $roleID);
		else
			Util::execute('UPDATE user SET user_role_id = ? WHERE id = ?', [$roleID, $userID]);
		AdminActivityLogger::log(
			AdminActivityType::ROLE_CHANGE,
			null,
			null,
			json_encode(['user_id' => $userID, 'display_name' => $user['display_name'], 'role' => self::ROLE_NAMES[$oldRoleID]]),
			self::ROLE_NAMES[$roleID]);
		return null;
	}

	/** How many accounts (not deleted) have the owner role. */
	public static function ownerCount(): int
	{
		return (int) Util::query('SELECT COUNT(*) AS count FROM user WHERE user_role_id = ? AND deleted IS NULL', [self::ROLE_OWNER])[0]['count'];
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
	 * Whether a player ever proposed a tag, tagged a problem, uploaded a solution or had a proposal
	 * rejected. A player who never did any of that has no contributions page worth opening.
	 */
	public static function hasContributions(int $userId): bool
	{
		$rows = Util::query(
			'SELECT
				EXISTS(SELECT 1 FROM tag WHERE user_id = ?)
				OR EXISTS(SELECT 1 FROM tag_connection WHERE user_id = ?)
				OR EXISTS(SELECT 1 FROM sgf WHERE user_id = ?)
				OR EXISTS(SELECT 1 FROM reject WHERE user_id = ? AND (tsumego_id = 0
					OR EXISTS(SELECT 1 FROM set_connection WHERE tsumego_id = reject.tsumego_id))) AS found',
			[$userId, $userId, $userId, $userId]);
		return (bool) $rows[0]['found'];
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
	 * The avatar to send to the frontend, null when the account is not there anymore. A deleted
	 * account keeps its row, and a board can be drawn from any name, so without this it would keep
	 * showing a board of someone who asked to be forgotten.
	 *
	 * @param UserRow|array<string, mixed> $user Row with the id, external_id, picture and email
	 */
	public static function avatarOrNull(array $user, int $size = self::AVATAR_PIXELS_INLINE): ?string
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
			return self::renderLinkInternal($user['user_id'], $displayName, $user['user_external_id'] ?? null, $user['user_picture'] ?? null, $user['user_email'] ?? null, $user['user_pref_avatar'] ?? null, $rank);
		}

		// Standard format: id, display_name, etc. A row without a display name gets the same
		// placeholder as a gone account, never the login name
		$displayName = (string) ($user['display_name'] ?? '');
		if ($displayName === '')
			return '<span>' . h(self::DELETED_NAME) . '</span>';
		$rating = $user['rating'] ?? null;
		$rank = $rating !== null ? Rating::getReadableRankFromRating((float) $rating) : '';
		return self::renderLinkInternal($user['id'], $displayName, $user['external_id'] ?? null, $user['picture'] ?? null, $user['email'] ?? null, $user['pref_avatar'] ?? null, $rank);
	}

	/**
	 * Internal function to render user link HTML.
	 */
	private static function renderLinkInternal(int $id, string $displayName, ?string $externalID, ?string $picture, ?string $email, ?int $prefAvatar, string $rank): string
	{
		assert(($externalID !== null && $picture !== null) || $email !== null, 'Must have either (externalID + picture) or email for avatar');

		// Generate avatar HTML
		$avatarUrl = self::getAvatarUrl([
			'id' => $id,
			'display_name' => $displayName,
			'pref_avatar' => $prefAvatar,
			'external_id' => $externalID,
			'picture' => $picture,
			'email' => $email,
		]);
		$image = self::avatarImage($avatarUrl, '', true, self::boardUrl(['display_name' => $displayName]));

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

	/** Whether the name holds a character the forum cannot store, see UNSTORABLE_PATTERN. */
	public static function containsCharactersTheForumCannotStore(string $name): bool
	{
		return (bool) preg_match(self::UNSTORABLE_PATTERN, $name);
	}

	/**
	 * The display name rule: a name the site can copy into the forum beside it. CakePHP hands
	 * a method rule the data as an array keyed by the field.
	 *
	 * @param array<string, string>|string $check
	 */
	public function hasOnlyCharactersTheForumCanStore(mixed $check): bool
	{
		$name = is_array($check) ? (string) reset($check) : (string) $check;
		return !self::containsCharactersTheForumCannotStore($name);
	}

	/** The name without the characters the forum cannot store, see UNSTORABLE_PATTERN. */
	public static function withoutCharactersTheForumCannotStore(string $name): string
	{
		return (string) preg_replace(self::UNSTORABLE_PATTERN, '', $name);
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
	 * True when the account has a picture from Google to show.
	 *
	 * @param array<string, mixed> $user
	 * @return bool
	 */
	public static function hasGooglePicture(array $user): bool
	{
		return !empty($user['external_id']) && !empty($user['picture']);
	}

	/**
	 * Whether the account wants sound.
	 *
	 * @param array<string, mixed> $user
	 * @return bool
	 */
	public static function soundIsOn(array $user): bool
	{
		return (int) ($user['sound'] ?? self::SOUND_OFF) === self::SOUND_ON;
	}

	/**
	 * The avatars a player can pick, as value => label. The Google picture is only
	 * offered to an account that has one.
	 *
	 * @param array<string, mixed> $user
	 * @return array<int, string>
	 */
	public static function avatarChoices(array $user): array
	{
		$choices = [
			self::AVATAR_BOARD => 'Go board',
			self::AVATAR_GRAVATAR => 'Gravatar',
		];
		if (self::hasGooglePicture($user))
			$choices[self::AVATAR_GOOGLE] = 'Google picture';
		return $choices;
	}

	/**
	 * The avatar an account shows. Without a stored choice it is the board, and a
	 * stored choice that is not on offer (the Google picture of an account that lost
	 * it) falls back to the board too.
	 *
	 * @param array<string, mixed> $user
	 * @return int
	 */
	public static function avatarChoice(array $user): int
	{
		$stored = (int) ($user['pref_avatar'] ?? self::AVATAR_BOARD);
		return array_key_exists($stored, self::avatarChoices($user)) ? $stored : self::AVATAR_BOARD;
	}

	/**
	 * The Gravatar identifier of an e-mail address: the sha256 of the trimmed lower
	 * case address, which is what Gravatar documents. The older md5 is not used.
	 *
	 * @param string $email
	 * @return string
	 */
	public static function gravatarHash(string $email): string
	{
		return hash('sha256', strtolower(trim($email)));
	}

	/**
	 * Get the avatar URL for a user: the avatar the player picked, which is the
	 * picture of a Google account, their Gravatar or the board the site draws.
	 *
	 * Only the avatar fields are needed, the callers pass either a full user row or
	 * just these values.
	 *
	 * @param array{id?: int, display_name?: string|null, pref_avatar?: int|null, external_id?: string|null, picture?: string|null, email?: string|null} $user
	 * @param int $size Pixels to ask an avatar host for, the board ignores it
	 * @return string Avatar URL
	 */
	public static function getAvatarUrl(array $user, int $size = self::AVATAR_PIXELS_INLINE): string
	{
		$choice = self::avatarChoice($user);

		if ($choice === self::AVATAR_GOOGLE)
		{
			$picture = $user['picture'];
			// The Google CDN answers with the size its url asks for, so the suffix the sign
			// in stored ("=s96-c", "=w100-h100") is exchanged for the size the avatar is
			// drawn in. A url that carries its own parameters is left alone.
			if (str_contains($picture, 'googleusercontent.com') && !str_contains($picture, '?'))
			{
				$base = preg_replace('/=[a-z0-9\-]+$/', '', $picture);
				return $base . '=s' . $size;
			}
			return $picture;
		}

		if ($choice === self::AVATAR_GRAVATAR)
			return 'https://www.gravatar.com/avatar/' . self::gravatarHash((string) ($user['email'] ?? ''))
				. '?s=' . $size . '&d=identicon';

		// The board the site draws for this account, from the name the player picked.
		// No request leaves for an avatar host and one url serves every size.
		assert(!empty($user['display_name']), 'A board avatar needs the display name of the account');
		return self::boardUrl($user);
	}

	/**
	 * The board of an account, which is what an avatar of another host falls back to.
	 * The drawing comes from the display name, so the account shows the same board
	 * wherever its name is shown.
	 *
	 * @param array{display_name?: string|null} $user
	 * @return string
	 */
	public static function boardUrl(array $user): string
	{
		return Avatar::url((string) ($user['display_name'] ?? ''));
	}

	/**
	 * The classes of the avatar element. A board keeps its corners, a picture of a
	 * face is round, and the size of both lives in components.css.
	 *
	 * @param string|null $avatarUrl
	 * @return string
	 */
	public static function avatarClass(?string $avatarUrl): string
	{
		return $avatarUrl !== null && str_starts_with($avatarUrl, Avatar::URL_PREFIX)
			? 'user-avatar user-avatar--board'
			: 'user-avatar';
	}

	/**
	 * The avatar element of a player, ready to be dropped next to a name or into the
	 * profile header. Every place that shows an avatar goes through here, so the class,
	 * the lazy loading and the referrer policy stay the same everywhere.
	 *
	 * @param string|null $avatarUrl Avatar from getAvatarUrl(), null for a deleted account
	 * @param string $extraClass Additional class, e.g. the larger avatar of the profile
	 * @param bool $lazy False for an avatar that is on screen right away (the profile header)
	 * @param string|null $fallbackUrl Board of the account, shown when the picture is gone
	 * @return string
	 */
	public static function avatarImage(?string $avatarUrl, string $extraClass = '', bool $lazy = true, ?string $fallbackUrl = null): string
	{
		if ($avatarUrl === null)
			return '';
		$class = self::avatarClass($avatarUrl) . ($extraClass === '' ? '' : ' ' . $extraClass);
		$loading = $lazy ? ' loading="lazy"' : '';
		// A picture of another host can stop working (a player who removes the photo of
		// their Google account), which the browser draws as a broken image. The board of
		// the account is always there, so it takes over, with the square corners the
		// board class gives it.
		$fallback = $fallbackUrl === null || str_starts_with($avatarUrl, Avatar::URL_PREFIX)
			? ''
			: ' onerror="this.onerror=null;this.classList.add(\'user-avatar--board\');this.src=\'' . h($fallbackUrl) . '\'"';
		return '<img src="' . h($avatarUrl) . '" alt="" class="' . $class . '"' . $loading . $fallback . ' decoding="async" referrerpolicy="no-referrer">';
	}

	/**
	 * Whether the account solved enough of the problems of the site to throw away the
	 * progress it has not touched for years.
	 *
	 * @param UserRow $user
	 */
	public static function canResetOldProgress(array $user): bool
	{
		$progress = ClassRegistry::init('Tsumego')->publicProblemProgress((int) $user['id']);
		return Util::getPercent($progress['solved'], $progress['total'])
			>= Constants::$MINIMUM_PERCENT_OF_TSUMEGOS_TO_BE_SOLVED_BEFORE_RESET_IS_ALLOWED;
	}
}
