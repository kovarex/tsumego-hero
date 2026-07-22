<?php

class User extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'user';
		parent::__construct($id, $table, $ds);
	}

	public $validate = [
		'name' => [

			'notempty' => [
				'rule' => 'notBlank',
				'required' => true,
				'message' => 'Please Insert Name',
			],

			'minmax' => [
				'rule' => ['between', 3, 30],
				'message' => 'The length of the name should have at least 3 characters',
			],

			'checkUnique' => [
				'rule' => ['checkUnique', 'name'],
				'message' => 'Name already exists',
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

			'checkUnique' => [
				'rule' => ['checkUnique', 'email'],
				'message' => 'Email already exists',
			],
		],
	];

	public static function renderPremium($user): string
	{
		if ($user['premium'] == 2 || $user['premium'] == 1)
			return '<img alt="Account Type" title="Account Type" src="/img/premium' . $user['premium'] . '.png" height="16px">';
		return '';
	}

	public static function getHeroPowersCount($user): int
	{
		$heroPowers = 0;
		if($user['level'] >= HeroPowers::$SPRINT_MINIMUM_LEVEL)
			$heroPowers++;
		if($user['level'] >= HeroPowers::$INTUITION_MINIMUM_LEVEL)
			$heroPowers++;
		if($user['level'] >= HeroPowers::$REJUVENATION_MINIMUM_LEVEL)
			$heroPowers++;
		if($user['premium'] > 0 || $user['level'] >= HeroPowers::$REFINEMENT_MINIMUM_LEVEL)
			$heroPowers++;
		return $heroPowers;
	}

	public static function getHighestRating($user): float
	{
		$highestTsumegoAttempt = ClassRegistry::init('TsumegoAttempt')->find('first', [
			'conditions' => ['user_id' => $user['id']],
			'order' => 'user_rating DESC']);
		if ($highestTsumegoAttempt)
			return max($highestTsumegoAttempt['TsumegoAttempt']['user_rating'], $user['rating']);
		return $user['rating'];
	}

	/**
	 * Render a user link with avatar and rank calculated from rating.
	 *
	 * @param array|null $user User array with id, display_name, external_id, picture, rating, email
	 *                         OR prefixed format (from raw SQL): user_id, user_display_name, etc.
	 *                         Null or empty array = deleted user
	 * @return string HTML link (or plain text for deleted users)
	 */
	public static function renderLink(?array $user, bool $showRank = true): string
	{
		// Handle deleted users - return plain text, not a link
		if ($user === null || (empty($user['id']) && empty($user['user_id'])))
			return '<span class="deleted-user">[deleted user]</span>';

		// Prefixed format (from raw queries): user_id, user_display_name, etc.
		if (isset($user['user_id']))
		{
			$rating = $showRank ? ($user['user_rating'] ?? null) : null;
			$rank = $rating !== null ? Rating::getReadableRankFromRating((float) $rating) : '';
			return self::renderLinkInternal($user['user_id'], $user['user_display_name'], $user['user_external_id'] ?? null, $user['user_picture'] ?? null, $user['user_email'] ?? null, $rank);
		}

		// Standard format: id, display_name, etc.
		$rating = $showRank ? ($user['rating'] ?? null) : null;
		$rank = $rating !== null ? Rating::getReadableRankFromRating((float) $rating) : '';
		return self::renderLinkInternal($user['id'], $user['display_name'], $user['external_id'] ?? null, $user['picture'] ?? null, $user['email'] ?? null, $rank);
	}

	/**
	 * Internal function to render user link HTML.
	 */
	private static function renderLinkInternal(int $id, ?string $displayName, ?string $externalID, ?string $picture, ?string $email, string $rank): string
	{
		// Generate avatar HTML
		$avatarUrl = self::getAvatarUrl([
			'external_id' => $externalID,
			'picture' => $picture,
			'email' => $email,
		], 20);
		$image = '<img src="' . h((string) $avatarUrl) . '" alt="" class="user-avatar" style="width:20px;height:20px;border-radius:50%;vertical-align:middle;margin-right:4px;">';

		return '<a href="/users/view/' . $id . '">' . $image . h((string) $displayName) . ($rank === '' ? '' : ' ' . $rank) . '</a>';
	}

	/**
	 * Generates a unique display name by adding (2), (3), etc. suffix if display_name already exists.
	 *
	 * @param string $name The desired base display name
	 * @return string A unique display_name (either the original or with numbered suffix)
	 */
	public static function generateUniqueDisplayName(string $name): string
	{
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
			$maxLength = 50;
			$baseLength = $maxLength - strlen($suffixStr);

			// Truncate base value if needed to fit suffix
			$truncatedBase = $baseLength < strlen($name) ? substr($name, 0, $baseLength) : $name;
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

		// 3. Sync to phpBB forum
		self::syncToForum($userId, $newDisplayName);

		return true;
	}

	/**
	 * Syncs a user's display name to the phpBB forum database.
	 *
	 * @param int $userId The user ID (maps to external_id in phpBB)
	 * @param string $displayName The display name to sync
	 */
	private static function syncToForum(int $userId, string $displayName): void
	{
		try
		{
			$forumDb = ConnectionManager::getDataSource('forum');
			/** @phpstan-ignore-next-line */
			$stmt = $forumDb->getConnection()->prepare(
				"UPDATE phpbb_users SET username = ?, username_clean = ? WHERE external_id = ?"
			);
			$stmt->execute([$displayName, strtolower($displayName), $userId]);
		}
		catch (Exception $e)
		{
			// Forum DB might not be available in all environments (dev, test)
			CakeLog::write('warning', "Forum sync failed for user $userId: " . $e->getMessage());
		}
	}

	/**
	 * Check if user is using Gravatar (not Google picture).
	 *
	 * @param array $user User data array
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
	 * @param array $user User data array
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
		]) ?: null;
	}

	/**
	 * Get the avatar URL for a user with priority: Google picture > Gravatar > default.
	 *
	 * @param array $user User data array (must have 'external_id', 'picture', 'email')
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
