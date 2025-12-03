<?php

class CommentsRenderer
{
	public function __construct($name, $userID, $urlParams)
	{
		$this->userID = $userID;
		$this->page = isset($params[$name . '_page']) ? max(1, (int) $this->params[$name . '_page']) : 1;
	}

	private function renderComment($comment, $index)
	{
		echo '<div class="sandboxComment">';
		$commentColor = $comment['from_admin'] ? 'commentBox2' : 'commentBox1';
		echo '<table class="sandboxTable2" width="100%" border="0">';
		echo '<tr><td width="73%"><div style="padding-bottom:7px;"><b>#'.$index.'</b> | ';
		if ($comment['set_connection_id'])
		{
			echo '<a href="/' . $comment['set_connection_id'] . '?search=topics">';
			echo $comment['set_title']. ' - '. $comment['set_num'].'</a><br>';
			echo '<div class="commentAnswer" style="color:#5e5e5e;"><div style="padding-top:14px;"></div>[You need to solve this problem to see the comment]</div></div>';
		}
		else
			echo '<i>Problem (id=' . $comment['tsumego_id']. 'doesn\'t have set connection.</i>';

		echo '<br></div>';
		echo '<div class="'.$commentColor.'">';
		echo $comment['user_name'].':<br>';
		if (TsumegoUtil::isSolvedStatus($comment['status']) || Auth::isAdmin())
			echo $comment['message'];
		else
			echo 'You can\'t seee comment of unsloved problem.';
		echo '</td><td class="sandboxTable2time" align="right">'.$comment['created'].'</td>';
		echo '</tr>';
		echo '<tr><td colspan="2"><div width="100%"><div align="center">';

		if ($comment['set_connection_id'])
		{
			new TsumegoButton($comment['tsumego_id'], $comment['set_connection_id'], $comment['set_num'], $comment['status'], false, false)->render();
			echo '<li id="naviElement0" class="set'.$comments[$j]['TsumegoComment']['user_tsumego'].'1" style="float:left;margin-top:14px;">
									<a id="tooltip-hover'.$j.'" class="tooltip" href="/tsumegos/play/'.$comments[$j]['TsumegoComment']['tsumego_id'].$sid.$QorA
				.'search=topics">'.$comments[$j]['TsumegoComment']['num']
				.'<span><div id="tooltipSvg'.$j.'"></div></span></a>

								</li>';
		echo '</div></div></td></tr>';
		echo '</table>';

		/*
		{
			echo '</td>
						<td class="sandboxTable2time" align="right">'.$comments[$j]['TsumegoComment']['created'].'</td>';
			echo '</tr>';
			echo '
						<tr>
							<td colspan="2">
								<div width="100%">
									<div align="center">
										<li id="naviElement0" class="set'.$comments[$j]['TsumegoComment']['user_tsumego'].'1" style="float:left;margin-top:14px;">
											<a href="/tsumegos/play/'.$comments[$j]['TsumegoComment']['tsumego_id'].$sid.$QorA.'search=topics">'.$comments[$j]['TsumegoComment']['num'].'</a>

										</li>
									</div>
									</div>
							</td>
						</tr>
					';
			echo '</table>';
		}
		echo '</div>';
		if($j<100) $display = " ";
		else $display = 'style="display:none;"';
		echo '<div id="space'.$j.'" '.$display.'">';
		echo '<br>';
		echo '</div>';*/
	}

	public function render()
	{
		$parameters = [];
		$parameters[] = $this->userID;

		$comments = Util::query("
SELECT
	tsumego_comment.message AS message,
	tsumego_comment.tsumego_id AS tsumego_id,
	tsumego_status.status AS status
	user.isAdmin AS from_admin,
	set_connection.id AS set_connection_id,
	CONCAT(`set`.title, ' ', `set`.title2) as set_title,
	set_connection.num as set_num

FROM
	tsumego_comment
	JOIN tsumego ON tsumego_comment.tsumego_id = tsumego.id
	JOIN user ON tsumego_comment.user_id = user.id
	LEFT JOIN tsumego_status ON tsumego_status.tsumego_id = tsumego.id AND AND tsumego_status.user_id = ?
    LEFT JOIN set_connection ON set_connection.tsumego_id = tsumego.id
    LEFT JOIN set ON set_connection.set_id = set.id
", $parameters);
		$pagination = ClassRegistry::init('Pagination');
		$pageIndex = 0; // TODO: index based
		foreach ($comments as $index => $comment)
			$this->renderComment($comment, $index);
	}
	public int $userID;
	public int $page;
}
