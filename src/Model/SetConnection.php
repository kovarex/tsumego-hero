<?php

use App\Utility\Auth;

class SetConnection extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'set_connection';
		parent::__construct($id, $table, $ds);
	}

	public $belongsTo = [
		'Tsumego',
		'Set',
	];

	/**
	 * Canonical ordering for listing sets: sets in the trash last, public before
	 * private, official (unowned) collections before user-owned, then curated
	 * order ascending (NULLs last), then earliest set. Shared by set listings so
	 * the rule lives in one place.
	 */
	public static function displayOrderForSetSql(string $setAlias): string
	{
		return "($setAlias.deleted IS NULL) DESC, $setAlias.public DESC, ($setAlias.user_id IS NULL) DESC, ($setAlias.order IS NULL) ASC, $setAlias.order ASC, $setAlias.id ASC";
	}

	/**
	 * Ordering that picks a representative set for display: the set-level
	 * display order, then earliest connection. A tsumego may belong to many
	 * sets; this is a display preference, not an identity.
	 */
	public static function displayOrderSql(string $setAlias, string $connectionAlias): string
	{
		return self::displayOrderForSetSql($setAlias) . ", $connectionAlias.id ASC";
	}

	/** The problems a collection needs before it can earn the collection achievements. */
	public const int ACHIEVEMENT_MINIMUM_PROBLEMS = 100;

	/**
	 * Subquery for the collections that can earn the collection achievements: collections
	 * built by the site itself, with at least self::ACHIEVEMENT_MINIMUM_PROBLEMS problems.
	 * Collections a player assembled are personal and do not count, not even when they are
	 * public.
	 */
	public static function publicCollectionsWithMinimumProblemsSql(): string
	{
		return 'SELECT sc.set_id FROM set_connection sc'
			. ' JOIN tsumego t ON t.id = sc.tsumego_id AND t.deleted IS NULL'
			. ' JOIN `set` st ON st.id = sc.set_id'
			. ' WHERE ' . Set::officialCondition('st')
			. ' GROUP BY sc.set_id HAVING COUNT(*) >= ' . self::ACHIEVEMENT_MINIMUM_PROBLEMS;
	}

	/**
	 * SQL predicate for the sets the current user may view: official public
	 * sets, the user's own sets, and (for admins/premium) sandbox sets.
	 */
	public static function visibilitySql(string $setAlias): string
	{
		$visibility = '(' . $setAlias . '.public != 0 AND ' . $setAlias . '.user_id IS NULL)';
		if (Auth::isLoggedIn())
			$visibility .= ' OR ' . $setAlias . '.user_id = ' . (int) Auth::getUserID();
		if (Auth::isAdmin() || Auth::hasPremium())
			$visibility .= ' OR (' . $setAlias . '.public = 0 AND ' . $setAlias . '.user_id IS NULL)';
		// a collection in the trash is reached only from the trash
		return '(' . $setAlias . '.deleted IS NULL AND (' . $visibility . '))';
	}

	/**
	 * SQL predicate for the sets the current user may play: every set that is not in the
	 * trash, and a sandbox set only for admins and premium players. The play page decides
	 * the same thing in SetPolicy::canPlay(), so a link built on this predicate never
	 * leads to a refusal.
	 */
	public static function playableSql(string $setAlias): string
	{
		$playable = $setAlias . '.deleted IS NULL';
		if (!Auth::isAdmin() && !Auth::hasPremium())
			$playable .= ' AND NOT ((' . $setAlias . '.public IS NULL OR ' . $setAlias . '.public = 0)'
				. ' AND ' . $setAlias . '.user_id IS NULL)';
		return '(' . $playable . ')';
	}

	/**
	 * Find a representative set connection for a tsumego, for display only.
	 */
	public function findDisplaySetConnection(int $tsumegoId): ?array
	{
		return $this->find('first', [
			'joins' => [[
				'table' => 'set',
				'alias' => 'S',
				'type' => 'INNER',
				'conditions' => ['S.id = SetConnection.set_id'],
			]],
			'conditions' => ['SetConnection.tsumego_id' => $tsumegoId],
			'order' => self::displayOrderSql('S', 'SetConnection'),
		]);
	}
}
