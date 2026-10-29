<?php

namespace App\Utility;

use App\Model\Table\UserTable;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\TableRegistry;
use Exception;

/** @phpstan-import-type UserRow from \App\Utility\RowTypes */
class Auth
{
	//Signs in the user of the row (UserRow, see UserTable::row()) and sets the cookie of the session.
	//Without a row it takes the user of the auth_token cookie, if there is one.
	/** @param UserRow|null $user */
	public static function init(?array $user = null): void
	{

		// a hack to inject login in test environment
		if (Util::isInTestEnvironment() && !empty($_COOKIE["hackedLoggedInUserID"]))
		{
			$userData = UserTable::row((int) $_COOKIE["hackedLoggedInUserID"]);
			if ($userData && empty($userData['deleted']))
			{
				Auth::$user = $userData;
				return;
			}
		}

		if ($user && empty($user['deleted']))
		{
			Auth::$user = $user;
			// Set JWT cookie for stateless auth
			JwtAuth::setAuthCookie(Auth::getUserID(), $user['password_hash'] ?? null);
			return;
		}

		// Try JWT cookie (stateless auth)
		$userIdFromJwt = JwtAuth::getUserIdFromCookie();
		if ($userIdFromJwt)
		{
			$userData = UserTable::row($userIdFromJwt);
			if ($userData && empty($userData['deleted']) && self::tokenFitsTheAccount($userData))
			{
				Auth::$user = $userData;
				return;
			}
			// the account was deleted (or doesn't exist anymore), which also ends its existing sessions
			JwtAuth::clearAuthCookie();
		}

		// Not logged in
		Auth::$user = null;
	}

	//A token is issued with the password the session was opened with, so changing the
	//password ends the sessions that were opened with the old one. A token without the
	//claim is taken at face value.
	/** @param UserRow $user */
	private static function tokenFitsTheAccount(array $user): bool
	{
		$claim = JwtAuth::getPasswordClaimFromCookie();
		return $claim === null || $claim === JwtAuth::claimOf($user['password_hash'] ?? null);
	}

	public static function isLoggedIn(): bool
	{
		return (bool) Auth::$user;
	}

	//Returns the identity (user array), or null when not logged in.
	//Matches CakePHP 5's $request->getAttribute('identity') contract.
	/** @return UserRow|null */
	public static function getIdentity(): ?array
	{
		return Auth::$user;
	}

	public static function getUserID(): int
	{
		return Auth::$user ? Auth::$user['id'] : 0;
	}

	/** @return UserRow */
	public static function &getUser(): array
	{
		if (!Auth::$user)
			throw new Exception("Accessing user for writing when null");
		return Auth::$user;
	}

	public static function isAdmin(): bool
	{
		return UserTable::isAdmin(Auth::$user);
	}

	public static function isOwner(): bool
	{
		return UserTable::isOwner(Auth::$user);
	}

	public static function hasPremium(): bool
	{
		return Auth::isLoggedIn() && Auth::getUser()['premium_id'] !== null;
	}

	public static function getPlayerColorSetting(): int
	{
		return Auth::isLoggedIn() ? (int) Auth::getUser()['player_color_setting'] : UserTable::PLAYER_COLOR_SETTING_RANDOM;
	}

	public static function getBoardOrientationSetting(): int
	{
		return Auth::isLoggedIn() ? (int) Auth::getUser()['board_orientation_setting'] : UserTable::BOARD_ORIENTATION_SETTING_RANDOM;
	}

	public static function getDateFormat(): int
	{
		return Auth::isLoggedIn() ? (int) Auth::getUser()['date_format_id'] : UserTable::DATE_FORMAT_ISO;
	}

	//The avatar the logged in player picked, which is the board for a visitor who is not logged in at all.
	public static function getAvatarSetting(): int
	{
		return Auth::isLoggedIn() ? UserTable::avatarChoice(Auth::getUser()) : UserTable::AVATAR_BOARD;
	}

	//Throws when $field is not an existing column on the user table, so a typo
	//or schema mismatch fails loudly instead of being silently dropped.
	private static function assertUserFieldExists(string $field): void
	{
		if (!TableRegistry::getTableLocator()->get('User')->getSchema()->hasColumn($field))
			throw new Exception("Cannot write unknown user column '{$field}' - it does not exist in the user table schema.");
	}

	public static function saveUserField(string $field, mixed $value): void
	{
		self::saveUserFields([$field => $value]);
	}

	//The fields are written by plain SQL with the values as strings, as Cake 2 sent them, so MySQL
	//rounds a float into an integer column (xp, level...) where the ORM would cut its fraction off.
	/** @param array<string, mixed> $fieldValues */
	public static function saveUserFields(array $fieldValues): void
	{
		assert(Auth::isLoggedIn());
		foreach ($fieldValues as $field => $value)
			self::assertUserFieldExists($field);
		foreach ($fieldValues as $field => $value)
			Auth::$user[$field] = $value;
		$assignments = implode(', ', array_map(fn(string $field) => '`' . $field . '` = ?', array_keys($fieldValues)));
		$values = array_map(fn(mixed $value) => is_bool($value) ? (int) $value : $value, array_values($fieldValues));
		Util::execute('UPDATE user SET ' . $assignments . ' WHERE id = ?', [...$values, Auth::getUserID()]);
	}

	public static function incrementUserField(string $field, int $delta): void
	{
		assert(Auth::isLoggedIn());
		self::assertUserFieldExists($field);
		Auth::$user[$field] = Auth::getUser()[$field] + $delta;
		Util::execute('UPDATE user SET `' . $field . '` = `' . $field . '` + ? WHERE id = ?', [$delta, Auth::getUserID()]);
	}

	//Atomically increments a field only while the row satisfies $conditions.
	//Used for bounded counters (e.g. used_revelation) where the bound must be
	//enforced by the database, not only by a PHP check that can race.
	//Bypasses callbacks/validation/timestamps, like incrementUserField().
	//Returns whether the increment was applied (condition still held).
	/** @param array<string, mixed> $conditions */
	public static function incrementUserFieldIf(string $field, int $delta, array $conditions): bool
	{
		assert(Auth::isLoggedIn());
		self::assertUserFieldExists($field);
		$conditions['id'] = Auth::getUserID();
		$affected = TableRegistry::getTableLocator()->get('User')->updateAll(
			[$field => new QueryExpression('`' . $field . '` + ' . $delta)],
			$conditions);
		if ($affected)
			Auth::$user[$field] = Auth::getUser()[$field] + $delta;
		return $affected > 0;
	}

	public static function logout(): void
	{
		// Clear JWT auth cookie
		JwtAuth::clearAuthCookie();
		Auth::$user = null;
	}

	public static function getWithDefault(string $key, mixed $default): mixed
	{
		if (!Auth::isLoggedIn())
			return $default;
		return Auth::getUser()[$key];
	}

	public static function getMode(): int
	{
		if (Auth::$modeOverride !== null)
			return Auth::$modeOverride;

		return Auth::isLoggedIn() ? (int) Auth::getUser()['mode_id'] : Constants::$LEVEL_MODE;
	}

	//Play by $mode instead of the stored one until it is cleared with null: a result
	//counts in the mode of the page it was played on, which another tab may have
	//switched away from since. See PlayResultProcessorComponent::playMode().
	public static function overrideMode(?int $mode): void
	{
		Auth::$modeOverride = $mode;
	}

	public static function isInLevelMode(): bool
	{
		return Auth::getMode() == Constants::$LEVEL_MODE;
	}

	public static function isInRatingMode(): bool
	{
		return Auth::getMode() == Constants::$RATING_MODE;
	}

	public static function isInTimeMode(): bool
	{
		return Auth::getMode() == Constants::$TIME_MODE;
	}

	public static function isInMistakeTrainingMode(): bool
	{
		return Auth::getMode() == Constants::$MISTAKE_TRAINING_MODE;
	}

	public static function XPisGainedInCurrentMode(): bool
	{
		if (!Auth::isLoggedIn())
			return false;
		return Auth::isInLevelMode() || Auth::isInRatingMode();
	}

	public static function ratingisGainedInCurrentMode(): bool
	{
		if (!Auth::isLoggedIn())
			return false;
		return Auth::isInLevelMode() || Auth::isInRatingMode();
	}

	public static function getRemainingHealth(): int
	{
		if (!Auth::isLoggedIn())
			return 1000;
		return Util::getHealthBasedOnLevel(Auth::getUser()['level']) - Auth::getUser()['damage'];
	}

	private static ?array $user = null;
	private static ?int $modeOverride = null;
}
