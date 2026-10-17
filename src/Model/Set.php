<?php

use App\Utility\Util;

/**
 * @phpstan-import-type SetRow from \App\Utility\RowTypes
 */
class Set extends AppModel
{
	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'set';
		parent::__construct($id, $table, $ds);
	}

	public $hasMany = ['SetConnection'];

	/**
	 * A collection the site publishes: public and owned by nobody. A collection a player
	 * assembled and a sandbox build are not site content, so they do not count wherever
	 * the site's own collections are meant.
	 *
	 * @param SetRow $set Unwrapped set row
	 */
	public static function isOfficial(array $set): bool
	{
		return (int) $set['public'] === 1 && ($set['user_id'] ?? null) === null;
	}

	public static function getProblemCount(int $setID): int
	{
		return Util::query("SELECT COUNT(*) AS total FROM set_connection WHERE set_connection.set_id = ?", [$setID])[0]["total"];
	}
}
