<script src ="/js/previewBoard.js"></script>
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
		if($approveSgfs!=null){
			echo '<h3 style="margin:15px 0;">SGF Proposals (' . $proposalsTotal . ')</h3>';
			echo $this->Pagination->render($proposalsPage, $proposalsPagesTotal, 'proposals_page');
			echo '<table border="0">';
			for($i=0; $i<count($approveSgfs); $i++){
				echo '<tr>';
					echo '<td class="adminpanel-table-text">'.$approveSgfs[$i]['Sgf']['user'].' made a proposal for <a class="adminpanel-link" href="/tsumegos/play/'
					.$approveSgfs[$i]['Sgf']['tsumego_id'].'?search=topics">'.$approveSgfs[$i]['Sgf']['tsumego'].'</a>:</td>';
					echo '<td>
					<a href="/tsumegos/open/'.$approveSgfs[$i]['Sgf']['tsumego_id'].'/'.$latestVersionTsumegos[$i]['Sgf']['id'].'">current</a> |
					<a href="/tsumegos/open/'.$approveSgfs[$i]['Sgf']['tsumego_id'].'/'.$approveSgfs[$i]['Sgf']['id'].'">proposal</a> |
					<a href="/tsumegos/open/'.$approveSgfs[$i]['Sgf']['tsumego_id'].'/'.$approveSgfs[$i]['Sgf']['id'].'/'.$latestVersionTsumegos[$i]['Sgf']['id'].'">diff</a>
					</td>';
					if($sgfTsumegos[$i]['Tsumego']['status']=='')
						$sgfTsumegos[$i]['Tsumego']['status'] = 'N';
					echo '<td><li class="set'.$sgfTsumegos[$i]['Tsumego']['status'].'1">
						<a id="tooltip-hover999'.$i.'" class="tooltip" href="/tsumegos/play/'.$sgfTsumegos[$i]['Tsumego']['id'].'?search=topics">'.$sgfTsumegos[$i]['Tsumego']['num'].'
						<span><div id="tooltipSvg999'.$i.'"></div></span></a>
					</li></td>';
					echo '<td><a class="new-button-default2" id="proposal-accept'.$i.'">Accept</a>
					<a class="new-button-default2 tag-submit-button" id="proposal-submit'.$i.'" href="/users/adminstats?accept=true&tag_id='
					.$approveSgfs[$i]['Sgf']['id'].'&hash='.md5(Auth::getUserID()).'">Submit (1)</a>
					<a class="new-button-default2" id="proposal-reject'.$i.'">Reject</a></td>';
				echo '</tr>';
			}
			echo '</table>';
			echo $this->Pagination->render($proposalsPage, $proposalsPagesTotal, 'proposals_page');
			echo '<hr>';
		}
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
		if($tags!=null){
			echo '<h3 style="margin:15px 0;">New Tags (' . $tagsTotal . ')</h3>';
			echo $this->Pagination->render($tagsPage, $tagsPagesTotal, 'tags_page');
			echo '<table border="0">';
			for($i=0; $i<count($tags); $i++){
				echo '<tr>';
					echo '<td>'.$i.'</td><td class="adminpanel-table-text">'.$tags[$i]['TagConnection']['user'].' added a tag for <a class="adminpanel-link" href="/tsumegos/play/'
					.$tags[$i]['TagConnection']['tsumego_id'].'?search=topics">'.$tags[$i]['TagConnection']['tsumego'].'</a>: <a class="adminpanel-link" href="/tag_names/view/'
					.$tags[$i]['TagConnection']['tag_id'].'">'.$tags[$i]['TagConnection']['name'].'</a></td>';
					if($tagTsumegos[$i]['Tsumego']['status']=='')
						$tagTsumegos[$i]['Tsumego']['status'] = 'N';
					echo '<td><li class="set'.$tagTsumegos[$i]['Tsumego']['status'].'1">
						<a id="tooltip-hover'.$i.'" class="tooltip" href="/tsumegos/play/'.$tagTsumegos[$i]['Tsumego']['id'].'?search=topics">'.$tagTsumegos[$i]['Tsumego']['num'].'
						<span><div id="tooltipSvg'.$i.'"></div></span></a>
					</li></td>';
					echo '<td>';
					if(Auth::getUserID() != $tags[$i]['TagConnection']['user_id']){
						echo '<a class="new-button-default2" id="tag-accept'.$i.'">Accept</a>
						<a class="new-button-default2" id="tag-reject'.$i.'">Reject</a>
						<a class="new-button-default2 tag-submit-button" id="tag-submit'.$i.'" href="/users/adminstats?accept=true&tag_id='
						.$tags[$i]['TagConnection']['id'].'&hash='.md5(Auth::getUserID()).'">Submit</a>';
					}
					echo '</td>';
					echo '<td style="font-size:13px">'.$tags[$i]['TagConnection']['created'].'</td>';
				echo '</tr>';
			}
			echo '</table>';
			echo $this->Pagination->render($tagsPage, $tagsPagesTotal, 'tags_page');
			echo '<br><br><br><br><br>';
		}

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
		for($h=0; $h<count($tags); $h++){
			echo '$("#tag-accept'.$h.'").click(function() {
				$("#tag-submit'.$h.'").show();
				$("#tag-accept'.$h.'").hide();
				$("#tag-reject'.$h.'").hide();
				tagList = tagList + "-" + "a'.$tags[$h]['TagConnection']['id'].'";
				setCookie("tagList", tagList);
				submitCount++;
				$(".tag-submit-button").html("Submit ("+submitCount+")");
			});';
			echo '$("#tag-reject'.$h.'").click(function() {
				$("#tag-submit'.$h.'").show();
				$("#tag-accept'.$h.'").hide();
				$("#tag-reject'.$h.'").hide();
				tagList = tagList + "-" + "r'.$tags[$h]['TagConnection']['id'].'";
				setCookie("tagList", tagList);
				submitCount++;
				$(".tag-submit-button").html("Submit ("+submitCount+")");
			});';
		}
		for($h=0; $h<count($approveSgfs); $h++){
			echo '$("#proposal-accept'.$h.'").click(function() {
				$("#proposal-submit'.$h.'").show();
				$("#proposal-accept'.$h.'").hide();
				$("#proposal-reject'.$h.'").hide();
				proposalList = proposalList + "-" + "a'.$approveSgfs[$h]['Sgf']['id'].'";
				setCookie("proposalList", proposalList);
				submitCount++;
				$(".tag-submit-button").html("Submit ("+submitCount+")");
			});';
			echo '$("#proposal-reject'.$h.'").click(function() {
				$("#proposal-submit'.$h.'").show();
				$("#proposal-accept'.$h.'").hide();
				$("#proposal-reject'.$h.'").hide();
				proposalList = proposalList + "-" + "r'.$approveSgfs[$h]['Sgf']['id'].'";
				setCookie("proposalList", proposalList);
				submitCount++;
				$(".tag-submit-button").html("Submit ("+submitCount+")");
			});';
		}
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
