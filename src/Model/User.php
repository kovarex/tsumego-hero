<?php

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

	// Shown instead of the name of a deleted account.
	public const DELETED_NAME = '[account deleted]';

	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
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
	 * Ids of the deleted accounts, loaded once per request. There are few of them, so one small
	 * query lets every place showing a name check it without joining on user.deleted.
	 *
	 * @var array<int, true>|null
	 */
	private static ?array $deletedIDs = null;

	// To be called after an account was deleted or restored.
	public static function clearDeletedCache(): void
	{
		self::$deletedIDs = null;
	}

	public static function isDeletedID(int|string|null $userID): bool
	{
		if ($userID === null)
			return false;
		if (self::$deletedIDs === null)
		{
			self::$deletedIDs = [];
			foreach (Util::query('SELECT id FROM user WHERE deleted IS NOT NULL') as $row)
				self::$deletedIDs[(int) $row['id']] = true;
		}
		return isset(self::$deletedIDs[(int) $userID]);
	}

	private static function stripGooglePrefix(?string $name, ?string $externalID): string
	{
		if (str_starts_with((string) $name, 'g__') && $externalID != null)
			return substr((string) $name, 3);
		return (string) $name;
	}

	/**
	 * The name to show for a user: DELETED_NAME for a deleted account, otherwise the name without
	 * the g__ prefix of Google accounts.
	 */
	public static function displayName(int|string|null $userID, ?string $name, ?string $externalID = null): string
	{
		if (self::isDeletedID($userID))
			return self::DELETED_NAME;
		return self::stripGooglePrefix($name, $externalID);
	}

	/**
	 * The name to send to the frontend, null for a deleted account (UserLink then shows DELETED_NAME).
	 */
	public static function nameOrNull(int|string|null $userID, ?string $name): ?string
	{
		return self::isDeletedID($userID) ? null : $name;
	}

	public static function renderLink(array|int|string $id, ?string $name = null, ?string $externalID = null, ?string $picture = null, int|float|string|null $rating = null): string
	{
		if (is_array($id))
		{
			if (isset($id['user_id']))
				return self::renderLink($id['user_id'], $id['user_name'], $id['user_external_id'], $id['user_picture'], $id['user_rating']);
			return self::renderLink($id['id'], $id['name'], $id['external_id'], $id['picture'], $id['rating']);
		}
		return User::renderLinkWithOptionalRank($id, $rating === null ? '' : Rating::getReadableRankFromRating((float) $rating), $name, $externalID, $picture);
	}

	public static function renderLinkWithOptionalRank(array|int|string $id, string $rank = '', ?string $name = null, ?string $externalID = null, ?string $picture = null): string
	{
		if (is_array($id))
		{
			if (isset($id['user_id']))
				return self::renderLinkWithOptionalRank($id['user_id'], $rank, $id['user_name'], $id['user_external_id'], $id['user_picture']);
			return self::renderLinkWithOptionalRank($id['id'], $rank, $id['name'], $id['external_id'], $id['picture']);
		}

		if (self::isDeletedID($id))
			return h(self::DELETED_NAME);
		return '<a href="/users/view/' . $id . '">' . h(self::stripGooglePrefix($name, $externalID)) . (empty($rank) ? '' : ' ' . $rank) . '</a>';
	}
}
