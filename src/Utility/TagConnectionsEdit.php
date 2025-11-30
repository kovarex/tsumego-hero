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
		$this->allTags = AppController::getAllTags($this->tags);
		$this->populatePopularTags();
	}

	private function populatePopularTags()
	{
		$result = ClassRegistry::init('Tag')->query("
SELECT
	tag.name as tag_name
FROM tag
LEFT JOIN tag_connection ON tag_connection.tsumego_id=? AND tag_connection.user_id=? AND tag_connection.tag_id = tag.id
LEFT JOIN tag_connection AS approved_connection ON approved_connection.tsumego_id=? AND approved_connection.tag_id = tag.id AND approved_connection.approved = 1
WHERE
	tag_connection.id IS NULL AND
	approved_connection.id IS NULL AND
	tag.popular = true",
			[$this->tsumegoID, Auth::getUserID(), $this->tsumegoID]);

		foreach ($result as $tag)
			$this->popularTags[] = $tag['tag']['tag_name'];
	}

	private function populateTags()
	{
		$result = ClassRegistry::init('TagConnection')->query("
SELECT
	tag.id as tag_id,
	tag.name as tag_name,
	tag.hint as tag_hint,
	tag_connection.approved as tag_approved
FROM tag_connection
JOIN tag ON tag_connection.tag_id = tag.id
WHERE
tag_connection.tsumego_id = ?", [$this->tsumegoID]);
		foreach ($result as $row)
		{
			$tag = [];
			$tag['tag_id'] = $row['tag']['tag_id'];
			$tag['name'] = $row['tag']['tag_name'];
			$tag['hint'] = $row['tag']['tag_hint'];
			$tag['approved'] = $row['tag_connection']['tag_approved'];
			$this->tags[] = $tag;
		}
	}

	public function renderJs()
	{
		echo "var tagConnectionsEdit = new TagConnectionsEdit();";

		foreach ($this->tags as $tag)
		{
			echo 'tagConnectionsEdit.tags.push("' . $tag['name'] . '");';
			echo 'tagConnectionsEdit.unapprovedTags.push("' . $tag['tag_approved'] . '");';
			echo 'tagConnectionsEdit.tagsGivesHint.push("' . $tag['hint'] . '");';
			echo 'tagConnectionsEdit.idTags.push("' . $tag['tag_id'] . '");';
		}

		foreach ($this->allTags as $tag)
		{
			echo 'tagConnectionsEdit.allTags.push("' . $tag['Tag']['name'] . '");';
			echo 'tagConnectionsEdit.idTags.push("' . $tag['Tag']['name'] . '");';
		}
		foreach ($this->popularTags as $tag)
			echo 'tagConnectionsEdit.popularTags.push("' . $tag . '");';
	}
}
