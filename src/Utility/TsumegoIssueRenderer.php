<?php

class TsumegoIssueRenderer
{
	public function __construct($tsumegoIssue)
	{
		$this->tsumegoIssue = $tsumegoIssue;
		$this->commentsSectionRenderer = new TsumegoCommentsSectionRenderer($tsumegoIssue['tsumego_id']);
	}

	public function render()
	{
		echo TsumegoIssue::statusName($this->tsumegoIssue['tsumego_issue_status_id']). " issue";
		$this->commentsSectionRenderer->render();
	}

	public TsumegoCommentsSectionRenderer $commentsSectionRenderer;
	public $tsumegoIssue;
}
