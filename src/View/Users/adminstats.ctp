<?php
	if(!Auth::isLoggedIn() || !Auth::isAdmin())
		echo '<script type="text/javascript">window.location.href = "/";</script>';

	echo '<div class="homeRight" style="width:40%">';
		echo '<h3>Admin Activity (' . $activityTotal . ')</h3>';
		echo $this->Pagination->render($activityPage, $activityPagesTotal, 'activity_page');
		echo '<table border="0" class="statsTable" style="border-collapse:collapse;">';
		$iCounter = 1;
		for ($i = 0; $i < count($adminActivities['tsumego_id']); $i++)
		{
			// Format date without seconds
			$timestamp = strtotime($adminActivities['created'][$i]);
			$dateFormatted = date('Y-m-d H:i', $timestamp);

			// Build formatted message from type and old_value/new_value
			$contentMessage = $adminActivities['type'][$i]; // Start with type name
			if (!empty($adminActivities['old_value'][$i]) && !empty($adminActivities['new_value'][$i]))
			{
				// Show old → new for edits
				$contentMessage .= ': ' . h($adminActivities['old_value'][$i]) . ' → ' . h($adminActivities['new_value'][$i]);
			}
			elseif (!empty($adminActivities['new_value'][$i]))
			{
				if ($adminActivities['new_value'][$i] === '1')
					$contentMessage .= ' → enabled';
				elseif ($adminActivities['new_value'][$i] === '0')
					$contentMessage .= ' → disabled';
				else
					$contentMessage .= '[Empty]  → ' . $adminActivities['new_value'][$i];
			}

			echo '<tr style="border-bottom:1px solid #e0e0e0;">
				<td>'.($iCounter).'</td>
				<td>
					<a href="/tsumegos/play/'.$adminActivities['tsumego_id'][$i].'?search=topics">'.$adminActivities['tsumego'][$i].'</a>
					<div style="color:#666; margin-top:5px;">'.$contentMessage.'</div>
				</td>
				<td>
					<div>'.$dateFormatted.'</div>
					<div style="font-size:0.9em; color:#666; margin-top:2px;">'.$adminActivities['name'][$i].'</div>
				</td>
			</tr>';
			$iCounter++;
		}
		echo '</table>';
		echo $this->Pagination->render($activityPage, $activityPagesTotal, 'activity_page');

	echo '</div>';

	echo '<div class="homeLeft" style="text-align:left;border-right:1px solid #a0a0a0;width:60%">';
		$sgfProposalsRenderer->render();
		if($tagNames!=null){
			echo '<h3 style="margin:15px 0;">Tag Names (' . $tagNamesTotal . ')</h3>';
			echo $this->Pagination->render($tagNamesPage, $tagNamesPagesTotal, 'tagnames_page');
			echo '<table border="0" class="tagnames-adminpanel">';
			for($i=0; $i<count($tagNames); $i++){
				echo '<tr>';
					echo '<td>'.$tagNames[$i]['Tag']['user'].' created a new tag: <a href="/tag_names/view/'.$tagNames[$i]['Tag']['id'].'">'
					.$tagNames[$i]['Tag']['name'].'</a></td>';
					echo '<td>';
					if(Auth::getUserID() != $tags[$i]['TagConnection']['user_id']){
						echo '<a class="new-button-default2" id="tagname-accept'.$i.'">Accept</a>
						<a class="new-button-default2 tag-submit-button" id="tagname-submit'.$i.'" href="/users/adminstats?accept=true&tag_id='
						.$tagNames[$i]['Tag']['id'].'&hash='.md5(Auth::getUserID()).'">Submit (1)</a>
						<a class="new-button-default2" id="tagname-reject'.$i.'">Reject</a>';
					}
					echo '</td>';
				echo '</tr>';
			}
			echo '</table>';
			echo $this->Pagination->render($tagNamesPage, $tagNamesPagesTotal, 'tagnames_page');
			echo '<hr>';
		}
		if($requestDeletion!=null){
			echo '<table border="0">';
			for($i=0; $i<count($requestDeletion); $i++){
				echo '<tr>';
				echo '<td>'.$requestDeletion[$i]['User']['name'].' has requested account deletion.</td>';
				echo '<td><a class="new-button-default2" href="/users/adminstats?delete='.($requestDeletion[$i]['User']['id']*1111)
				.'&hash='.md5($requestDeletion[$i]['User']['name']).'">Delete Account</a></td>';
				echo '</tr>';
			}
			echo '</table><hr>';
		}
		$tagConnectionProposalsRenderer->render();

	echo '</div>';
	echo '<div style="clear:both;"></div>';
?>

<script>
	var tooltipSgfs = window.tooltipSgfs || [];
	let tagList = "null";
	let tagNameList = "null";
	let proposalList = "null";
	let submitCount = 0;

	<?php if($refreshView) echo 'window.location.href = "/sets/view/'.$set['Set']['id'].'";'; ?>

	<?php
		for($h=0; $h<count($tagNames); $h++){
			echo '$("#tagname-accept'.$h.'").click(function() {
				$("#tagname-submit'.$h.'").show();
				$("#tagname-accept'.$h.'").hide();
				$("#tagname-reject'.$h.'").hide();
				tagNameList = tagNameList + "-" + "a'.$tagNames[$h]['Tag']['id'].'";
				setCookie("tagNameList", tagNameList);
				submitCount++;
				$(".tag-submit-button").html("Submit ("+submitCount+")");
			});';
			echo '$("#tagname-reject'.$h.'").click(function() {
				$("#tagname-submit'.$h.'").show();
				$("#tagname-accept'.$h.'").hide();
				$("#tagname-reject'.$h.'").hide();
				tagNameList = tagNameList + "-" + "r'.$tagNames[$h]['Tag']['id'].'";
				setCookie("tagNameList", tagNameList);
				submitCount++;
				$(".tag-submit-button").html("Submit ("+submitCount+")");
			});';
		}
	?>
</script>
