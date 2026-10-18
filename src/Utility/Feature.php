<?php

namespace App\Utility;

use ClassRegistry;
use Exception;

/**
 * Feature flags: a shipped feature can be switched off, or kept to the admins,
 * without a deploy.
 *
 * One row per feature in feature_flags, state being off, admins or everyone. A
 * missing row counts as off, because a flag has to be seeded before a feature
 * appears. The values are not SQL keywords, so a hand-written
 * `SET state = everyone` needs no quoting.
 *
 * The check belongs at the entry points (menu, controller, the one write that
 * must not happen), never in the model: while a flag is on, everything behind it
 * behaves as it would with no flag at all.
 */
class Feature
{
	public const OFF = 'off';
	public const ADMINS = 'admins';
	public const EVERYONE = 'everyone';

	/**
	 * @var array<string, string>|null
	 */
	private static ?array $states = null;

	/**
	 * Whether the current visitor gets the feature behind the flag.
	 *
	 * Code passes a FeatureKey case, which makes a mistyped key impossible; tests
	 * and the generated changelog index pass raw keys, which is why both are
	 * accepted.
	 */
	public static function enabled(FeatureKey|string $flag): bool
	{
		return match (self::state($flag))
		{
			self::EVERYONE => true,
			self::ADMINS => Auth::isAdmin(),
			default => false,
		};
	}

	/**
	 * The configured state of a flag, off when it has no row.
	 */
	public static function state(FeatureKey|string $flag): string
	{
		return self::states()[self::key($flag)] ?? self::OFF;
	}

	/**
	 * Whether a flag still has a row. A flag is deleted when its feature becomes
	 * permanent, so a missing row also means "this shipped long ago".
	 */
	public static function exists(FeatureKey|string $flag): bool
	{
		return array_key_exists(self::key($flag), self::states());
	}

	/**
	 * Whether FeatureKey still names the flag. A feature is removed from the code as a
	 * whole, so a key that is no longer declared belongs to something that shipped for
	 * good, and a row left behind by it is only a leftover.
	 */
	public static function known(FeatureKey|string $flag): bool
	{
		return FeatureKey::tryFrom(self::key($flag)) !== null;
	}

	/**
	 * Point a flag at a state. The control surface is the database, so the only caller
	 * is the admin toolbox; a feature in production is switched with one statement.
	 */
	public static function set(FeatureKey|string $flag, string $state): void
	{
		Util::execute(
			'INSERT INTO feature_flags (flag_key, state) VALUES (?, ?)'
			. ' ON DUPLICATE KEY UPDATE state = VALUES(state)',
			[self::key($flag), $state]
		);
		self::reset();
	}

	private static function key(FeatureKey|string $flag): string
	{
		return $flag instanceof FeatureKey ? $flag->value : $flag;
	}

	/**
	 * Every configured flag, loaded once per request.
	 *
	 * A deploy may ship a check before the migration that creates the table, so a
	 * missing table reads as "no flags" instead of breaking the page.
	 *
	 * @return array<string, string>
	 */
	private static function states(): array
	{
		if (self::$states === null)
		{
			self::$states = [];
			try
			{
				$rows = ClassRegistry::init('FeatureFlag')->find('all', [
					'fields' => ['flag_key', 'state'],
				]) ?: [];
				foreach ($rows as $row)
					self::$states[$row['FeatureFlag']['flag_key']] = (string) $row['FeatureFlag']['state'];
			}
			catch (Exception $e)
			{
				self::$states = [];
			}
		}

		return self::$states;
	}

	/**
	 * Forget the rows read so far. Tests change a flag between assertions; a
	 * request re-reads them anyway.
	 */
	public static function reset(): void
	{
		self::$states = null;
	}
}
