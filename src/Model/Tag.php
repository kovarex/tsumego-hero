<?php

class Tag extends AppModel
{
	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'tag';
		parent::__construct($id, $table, $ds);
	}

	/**
	 * Return all tags with their connection status for a given tsumego.
	 */
	public static function getForTsumego(int $tsumegoId): array
	{
		return Util::query("
SELECT
	tag.id,
	tag.name,
	tag.hint,
	tag_connection.id AS tag_connection_id,
	tag_connection.approved,
	tag_connection.user_id = ? AS is_mine
FROM tag
LEFT JOIN tag_connection ON tag_connection.tag_id = tag.id AND tag_connection.tsumego_id = ?
ORDER BY tag.name", [Auth::getUserID(), $tsumegoId]);
	}
}
