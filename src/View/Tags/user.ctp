
<div align="center">
	<p class="title">
		<br>
		Tags and proposals by <?php echo h(Auth::getUser()['name']) ?>
		<br><br> 
	</p>
	<table class="highscoreTable" border="0">
		<tbody>
		<tr>
			<th align="left">Action</th>
			<th align="left">Status</th>
			<th align="left">Timestamp</th>
		</tr>
		<?php
			for($i=0; $i<count($list); $i++){
				$statusColor = $list[$i]['status'] === 'accepted' ? '#047804' : '#ce3a47';
				if($list[$i]['type'] == 'proposal'){
					echo '<tr>';
						echo '<td class="timeTableLeft versionColor" align="left">'
						.h($list[$i]['user']).' made a proposal for <a href="/tsumegos/play/'.$list[$i]['tsumego_id'].'">'
						.h($list[$i]['tsumego']).'</a></td>';
						echo '<td class="timeTableMiddle versionColor" align="left"><b style="color:'.$statusColor.'">'.h($list[$i]['status']).'</b></td>';
						echo '<td class="timeTableRight versionColor" align="left">'.h($list[$i]['created']).'</td>';
					echo '</tr>';
				}else if($list[$i]['type'] == 'tag'){
					echo '<tr>';
						echo '<td class="timeTableLeft versionColor" align="left">'
						.h($list[$i]['user']).' added the tag <i>'.h($list[$i]['tag'])
						.'</i> for <a href="/tsumegos/play/'.$list[$i]['tsumego_id'].'">'.h($list[$i]['tsumego'])
						.'</a></td>';
						echo '<td class="timeTableMiddle versionColor" align="left"><b style="color:'.$statusColor.'">'.h($list[$i]['status']).'</b></td>';
						echo '<td class="timeTableRight versionColor" align="left">'.h($list[$i]['created']).'</td>';
					echo '</tr>';
				}else if($list[$i]['type'] == 'tag name'){
					echo '<tr>';
						echo '<td class="timeTableLeft versionColor" align="left">'
						.h($list[$i]['user']).' created a new tag: <i>'.h($list[$i]['tag']).'</i></td>';
						echo '<td class="timeTableMiddle versionColor" align="left"><b style="color:'.$statusColor.'">'.h($list[$i]['status']).'</b></td>';
						echo '<td class="timeTableRight versionColor" align="left">'.h($list[$i]['created']).'</td>';
					echo '</tr>';
				}
			}
		?>

	</tbody>
	</table>
</div>