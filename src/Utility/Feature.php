<?php

namespace App\Utility;

use ClassRegistry;
use Exception;

/**
 * Feature flags: the switch that lets either of us take a shipped feature out of
 * the site, or keep it to the admins, without a deploy.
 *
 * One row per feature in feature_flags, state being off, admins or everyone. A
 * missing row counts as off, because a flag has to be seeded before a feature
 * appears.
 *
 * The states avoid SQL keywords on purpose: `all` and `on` are reserved, and a raw
 * query writing `state = all` is a syntax error.
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
