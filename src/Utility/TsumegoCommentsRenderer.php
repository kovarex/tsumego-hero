<?php

App::uses('TsumegoCommentsSectionRenderer', 'Utility');
App::uses('TsumegoIssuesRenderer', 'Utility');

class TsumegoCommentsRenderer
{
	public function __construct($tsumegoID)
	{
		$tsumegoIssues = ClassRegistry::init('TsumegoIssue')->find('all', ['conditions' => ['tsumego_id' => $tsumegoID]]);
		foreach ($tsumegoIssues as $tsumegoIssue) {
			$this->issueSections []= new TsumegoIssuesRenderer($tsumegoIssue['TsumegoIssue']);
		}
		$this->plainSection = new TsumegoCommentsSectionRenderer($tsumegoID, null);
	}

	public function render()
	{
		echo '<div id="commentSpace">';
		if (!$this->empty())
			echo '<div id="msg1x"><a id="show2">Comments<img id="greyArrow" src="/img/greyArrow2.png"></a></div><br>';
		foreach ($this->issueSections as $section)
			$section->render();
		$this->plainSection->render();
		echo "</div>";
	}

	public function empty(): bool
	{
		return empty($this->issueSections) && $this->plainSection->empty();
	}

	public $issueSections = [];
	public $plainSection;
}
