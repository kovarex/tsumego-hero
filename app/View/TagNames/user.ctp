
<div align="center">
	<p class="title">
		<br>
		Tags and proposals by <?php echo $_SESSION['loggedInUser']['User']['name'] ?>
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
				if($list[$i]['type'] == 'proposal'){
					echo '<tr>';
						echo '<td class="timeTableLeft versionColor" align="left">'
						.$list[$i]['user'].' made a proposal for <a href="/tsumegos/play/'.$list[$i]['tsumego_id'].'">'
						.$list[$i]['tsumego'].'</a></td>';
						echo '<td class="timeTableMiddle versionColor" align="left">'.$list[$i]['status'].'</td>';
						echo '<td class="timeTableRight versionColor" align="left">'.$list[$i]['created'].'</td>';
					echo '</tr>';
				}else if($list[$i]['type'] == 'tag'){
					echo '<tr>';
						echo '<td class="timeTableLeft versionColor" align="left">'
						.$list[$i]['user'].' added the tag <i>'.$list[$i]['tag']
						.'</i> for <a href="/tsumegos/play/'.$list[$i]['tsumego_id'].'">'.$list[$i]['tsumego']
						.'</a></td>';
						echo '<td class="timeTableMiddle versionColor" align="left">'.$list[$i]['status'].'</td>';
						echo '<td class="timeTableRight versionColor" align="left">'.$list[$i]['created'].'</td>';
					echo '</tr>';
				}else if($list[$i]['type'] == 'tag name'){
					echo '<tr>';
						echo '<td class="timeTableLeft versionColor" align="left">'
						.$list[$i]['user'].' created a new tag: <i>'.$list[$i]['tag'].'</i></td>';
						echo '<td class="timeTableMiddle versionColor" align="left">'.$list[$i]['status'].'</td>';
						echo '<td class="timeTableRight versionColor" align="left">'.$list[$i]['created'].'</td>';
					echo '</tr>';
				}
			}
		?>

	</tbody>
	</table>
</div>