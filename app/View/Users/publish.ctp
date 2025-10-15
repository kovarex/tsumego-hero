<div align="center">
<p class="title">
					<br> Publish Schedule
				<br><br> 
				</p>
<div align="left"><a href="/sets/beta">back</a></div>
	<table class="highscoreTable" border="0">
	<tbody>
	<tr>
		<th width="60px">Date</th>
		<th width="220px" align="left">&nbsp;Name</th>
	</tr>
	
	<?php
		for($i=0; $i<count($p); $i++){
			echo '<tr><td class="timeTableLeft timeTableColor11" align="center">
				'.$p[$i]['Schedule']['date'].'
			</td>
			<td class="timeTableRight timeTableColor11" width="225px" align="left">
				<a href="/tsumegos/play/'.$p[$i]['Schedule']['tsumego_id'].'">'.$p[$i]['Schedule']['set'].$p[$i]['Schedule']['num'].'</a>
			</td></tr>';
		}
	?>
			
	</tbody>
	</table>
</div>
<br>