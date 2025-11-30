<?php

class TagConnectionsEdit
{
	public $tsumegoID;
	public $tags = [];
	public $allTags = [];
	public $popularTags = [];

	public function __construct($tsumegoID)
	{
		$this->tsumegoID = $tsumegoID;
		$this->populateTags();
		$this->allTags = $this->getTagsToAdd(false);
		$this->popularTags = $this->getTagsToAdd(true);
	}

	private function getTagsToAdd($popular)
	{
		$queryResult = ClassRegistry::init('Tag')->query("
SELECT
	tag.name as tag_name,
	tag_connection.id IS NOT NULL as is_added
FROM tag
LEFT JOIN tag_connection ON tag_connection.tsumego_id=? AND tag_connection.tag_id = tag.id
WHERE
	(tag_connection.id IS NULL OR (tag_connection.approved = false AND tag_connection.user_id != ?))" .
			($popular ? " AND popular = " . Util::boolString($popular) : ""),
			[$this->tsumegoID, Auth::getUserID()]);

		$result = [];
		foreach ($queryResult as $tag)
			$result[] = ['name' => $tag['tag']['tag_name'], 'is_added' => $tag[0]['is_added']];
		return $result;
	}

	private function populateTags()
	{
		$result = ClassRegistry::init('TagConnection')->query("
SELECT
	tag.id as tag_id,
	tag.name as tag_name,
	tag.hint as tag_hint,
	tag.popular as tag_popular
	tag_connection.approved as tag_approved
FROM tag_connection
JOIN tag ON tag_connection.tag_id = tag.id
WHERE
tag_connection.tsumego_id = ? AND
(tag_connection.approved = 1 OR tag_connection.user_id = ?)", [$this->tsumegoID, Auth::getUserID()]);
		foreach ($result as $row)
		{
			$tag = [];
			$tag['tag_id'] = $row['tag']['tag_id'];
			$tag['name'] = $row['tag']['tag_name'];
			$tag['hint'] = $row['tag']['tag_hint'];
			$tag['popular'] = $row['tag']['tag_popular'];
			$tag['approved'] = $row['tag_connection']['tag_approved'];
			$this->tags[] = $tag;
		}
	}

	public function renderJs()
	{
		echo "var tagConnectionsEdit = new TagConnectionsEdit(" . $this->tsumegoID . "," . Util::boolString(Auth::isAdmin()) . ");";

		foreach ($this->tags as $tag)
		{
			echo 'tagConnectionsEdit.tags.push({name: "' . $tag['name'] . '", isMyUnapproved: ' . Util::boolString(!$tag['approved']) . ', popular: ' . Util::boolString($tag['popular']) . '});' . PHP_EOL;
			echo 'tagConnectionsEdit.tagsGivesHint.push("' . $tag['hint'] . '");'. PHP_EOL;
			echo 'tagConnectionsEdit.idTags.push("' . $tag['tag_id'] . '");'. PHP_EOL;
		}

		foreach ($this->allTags as $tag)
		{
			echo 'tagConnectionsEdit.allTags.push({name: "' . $tag['name'] . '", isAdded: ' . Util::boolString($tag['is_added']) . '});'. PHP_EOL;
			echo 'tagConnectionsEdit.idTags.push("' . $tag['name'] . '");'. PHP_EOL;
		}
		foreach ($this->popularTags as $tag)
			echo 'tagConnectionsEdit.popularTags.push({name: "' . $tag['name'] . '", isAdded: ' . Util::boolString($tag['is_added']) . '});'. PHP_EOL;
	}
}
