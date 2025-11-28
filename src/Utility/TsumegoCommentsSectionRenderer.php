<?php

// renders comments related to one issue or comments without an issue
class TsumegoCommentsSectionRenderer
{
	public function __construct(int $tsumegoID, ?int $issueID)
	{
		$commentsInput = ClassRegistry::init('TsumegoComment')->find('all', ['conditions' => (['tsumego_id' => $tsumegoID, 'tsumego_issue_id' => $issueID])]) ?: [];
		$this->comments = [];
		foreach ($commentsInput as $index => $commentInput)
		{
			$comment = $commentInput['TsumegoComment'];
			//$co[$i]['Comment']['message'] = htmlspecialchars($co[$i]['Comment']['message']);
			//$cou = ClassRegistry::init('User')->findById($co[$i]['Comment']['user_id']);
			//if ($cou == null)
			//	$cou['User']['name'] = '[deleted user]';
			//$co[$i]['Comment']['user'] = AppController::checkPicture($cou['User']);
			$array = TsumegosController::commentCoordinates($comment['message'], $index + 1, true);
			$comment['message'] = $array[0];
			//array_push($commentCoordinates, $array[1]);
			$this->comments []= $comment;
		}
	}

	public function empty(): bool
	{
		return empty($this->comments);
	}

	public function render()
	{
		foreach ($this->comments as $comment)
		{
			echo '<div id="msg2x">';
			$user = ClassRegistry::init('User')->findById($comment['user_id'])['User'];
			$commentColorCheck = $user['admin'];
			$commentColor = $commentColorCheck ? 'commentBox2' : 'commentBox1';

			$cpArray = null;
			if($comment['position']!=null)
			{
				$cpNew = null;
				if(strpos($comment['position'], '|') !== false)
				{
					$cpNew = explode("|", $comment['position']);
					$cp = explode('/', $cpNew[0]);
					$cpNew[1] = ',\''.$cpNew[1].'\'';
				}
				else
					$cp = explode('/', $comment['position']);
				$cpArray = $cp[0].','.$cp[1].','.$cp[2].','.$cp[3].','.$cp[4].','.$cp[5].','.$cp[6].','.$cp[7].',\''.$cp[8].'\'';
				if($cpNew!=null)
					$cpArray .= $cpNew[1];
				$cpArray = '<img src="/img/positionIcon1.png" class="positionIcon1" onclick="commentPosition('.$cpArray.');">';
			}
			$comment['message'] = '|'.$comment['message'];
			if (strpos($comment['message'], '[current position]') != 0)
				$comment['message'] = str_replace('[current position]', $cpArray, $comment['message']);
			$comment['message'] = substr($comment['message'], 1);

			echo '<div class="sandboxComment">';
			echo '<table class="sandboxTable2" width="100%" border="0"><tr><td>';
			echo '<div class="'.$commentColor.'">'.$user['name'].':<br>'.$comment['message'].' </div>';
			echo '</td><td align="right" class="sandboxTable2time">';

			echo new DateTime($comment['created'])->format('M. d. Y <br> H:i');
			/*
			if(Auth::getUserID() == $comment['user_id'])
				echo '<a class="deleteComment" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'"><br>Delete</a>';
			if(Auth::isAdmin()){
				if($showComment[$i]['Comment']['status']==0){
					//echo '<br><a class="deleteComment" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'&changeComment=2">Can\'t Resolve This</a>';
					echo '<br>';
					if(Auth::getUserID()!=$showComment[$i]['Comment']['user_id'] && $commentColorCheck){
						echo '<a class="deleteComment" style="text-decoration:none;" href="/tsumegos/play/'.$t['Tsumego']['id'].'?deleteComment='.$showComment[$i]['Comment']['id'].'&changeComment=3">
									<img class="thumbs-small" title="approve this comment" width="20px" src="/img/thumbs-small.png">
								</a>';
					}
					echo '&nbsp;<a id="adminComment'.$i.'" class="adminComment" href="">Answer</a>';

				}else{
					echo '<a id="adminComment'.$i.'" class="adminComment" href=""><br>Edit</a>';
				}
			}*/
			echo "</td></tr></table></div></div>\n";
		}
	}
}
