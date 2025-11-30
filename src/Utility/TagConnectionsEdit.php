<?php

class TagConnectionsEdit
{
	public $tsumegoID;
	public $tags = [];
	public $problemSolved;

	public function __construct($tsumegoID, $problemSolved)
	{
		$this->tsumegoID = $tsumegoID;
		$this->problemSolved = $problemSolved;
		$this->populateTags();
	}

	private function populateTags()
	{
		$this->tags = Util::query("
SELECT
	tag.name AS name,
	tag.popular AS isPopular,
	tag.id AS id,
	tag_connection.id IS NOT NULL AS isAdded,
	COALESCE(tag_connection.approved, 0) AS isApproved,
	COALESCE(tag_connection.user_id = ?, 0) AS isMine,
	tag.hint AS isHint
FROM tag
LEFT JOIN tag_connection ON tag_connection.tag_id = tag.id AND tag_connection.tsumego_id = ?", [Auth::getUserID(), $this->tsumegoID]);
	}

	public function renderJs()
	{
		$constructorParams = [];
		$constructorParams[] = 'tsumegoID:' . $this->tsumegoID;
		$constructorParams[] = 'isAdmin:' . Util::boolString(Auth::isAdmin());
		$constructorParams[] = 'problemSolved:' . Util::boolString($this->problemsSolved);

		$tags = [];
		foreach ($this->tags as $tag)
			$tags []= '{' . implode(', ', array_map(fn($k, $v) => $k . ': ' . var_export($v, true),array_keys($tag),$tag)) . '}' . PHP_EOL;
		$constructorParams[] = 'tags: [' . implode(", ", $tags) . ']';

		echo "var tagConnectionsEdit = new TagConnectionsEdit({" . implode(", ", $constructorParams) . "});" . PHP_EOL;
	}
}
