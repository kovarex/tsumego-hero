<?php

namespace App\Model\Table;

use App\Model\Entity\Tag;
use App\Utility\AdminActivityLogger;
use App\Utility\Auth;
use App\Utility\Util;
use Throwable;

//A tag problems can carry, like snapback or ko. A player's new tag is a proposal until an admin approves it.
class TagTable extends AppTable
{
	//Return all tags with their connection status for a given tsumego.
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

	//Every tag, proposed ones included, with how many problems carry it and how many
	//more only have it proposed. Deleted problems are not counted.
	//$byProblemCount: Most used first instead of by name
	public static function listWithProblemCounts(bool $byProblemCount = false): array
	{
		$order = $byProblemCount ? 'problem_count DESC, tag.name' : 'tag.name';
		return Util::query("
SELECT
	tag.id,
	tag.name,
	tag.approved,
	tag.hint,
	COUNT(CASE WHEN tag_connection.approved = 1 THEN 1 END) AS problem_count,
	COUNT(CASE WHEN tag_connection.approved = 0 THEN 1 END) AS proposed_count
FROM tag
LEFT JOIN (tag_connection
	JOIN tsumego ON tsumego.id = tag_connection.tsumego_id AND tsumego.deleted IS NULL)
	ON tag_connection.tag_id = tag.id
GROUP BY tag.id
ORDER BY $order");
	}

	//The problems that carry the tag or have it proposed, each named by the collection it is
	//shown in first, ordered as the collections are.
	public static function problems(int $tagId): array
	{
		return Util::query("
SELECT
	tag_connection.tsumego_id,
	tag_connection.approved,
	set_connection.id AS set_connection_id,
	set_connection.num,
	`set`.title AS set_title
FROM tag_connection
JOIN tsumego ON tsumego.id = tag_connection.tsumego_id AND tsumego.deleted IS NULL
LEFT JOIN set_connection ON set_connection.id = (
	SELECT sc2.id FROM set_connection sc2
	JOIN `set` s2 ON s2.id = sc2.set_id
	WHERE sc2.tsumego_id = tag_connection.tsumego_id
	ORDER BY " . SetConnectionTable::displayOrderSql('s2', 'sc2') . "
	LIMIT 1)
LEFT JOIN `set` ON `set`.id = set_connection.set_id
WHERE tag_connection.tag_id = ?
ORDER BY `set`.id IS NULL, " . SetConnectionTable::displayOrderForSetSql('`set`') . ", set_connection.num, tag_connection.tsumego_id", [$tagId]);
	}

	//Deletes the tag and takes it off every problem. The admin activity log keeps the tag
	//and the problems it was on, so a tag deleted by mistake can be put back by hand.
	//Returns how many problems had the tag or had it proposed
	public static function deleteWithConnections(Tag $tag): int
	{
		$connections = Util::query(
			"SELECT tsumego_id, user_id, approved, created FROM tag_connection WHERE tag_id = ? ORDER BY id",
			[$tag->id]);

		// The transaction is Cake 2's, the Cake 5 ORM shares its connection and never starts one.
		$database = Util::database();
		$database->begin();
		try
		{
			Util::query("DELETE FROM tag_connection WHERE tag_id = ?", [$tag->id]);
			Util::query("DELETE FROM tag WHERE id = ?", [$tag->id]);
			AdminActivityLogger::log(AdminActivityTypeTable::DELETE_TAG, null, null, json_encode([
				'tag' => $tag->toArray(),
				'connections' => $connections], JSON_UNESCAPED_UNICODE));
			$database->commit();
		}
		catch (Throwable $exception)
		{
			$database->rollback();
			throw $exception;
		}
		return count($connections);
	}
}
