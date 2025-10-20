
<div align="center">
	<h2>Activities</h2>
	</div>
	<br>
	<br>
	<table class="contentOnTop" width="100%">
	<tr>
	<td>
	<table>
	<?php
		for($j=0; $j<count($u2['name']); $j++){
			echo '<tr>';
			echo '<td>'.$u2['name'][$j].'</td>';
			echo '<td>'.$u2['value'][$j].'</td>';
			echo '</tr>';
		} 
	?>
	</table>
	<table>
	<?php
		for($j=0; $j<count($comments); $j++){
			echo '<tr>';
			echo '<td>'.$comments[$j]['Comment']['user'].'</td>';
			echo '<td>'.$comments[$j]['Comment']['tsumego'].'</td>';
			echo '<td>'.$comments[$j]['Comment']['message'].'</td>';
			echo '<td>'.$comments[$j]['Comment']['status'].'</td>';
		
			echo '<td>'.$comments[$j]['Comment']['created2'].'</td>';
			echo '</tr>';
		}
		//echo '<pre>'; print_r($comments); echo '</pre>'; 
		//echo '<pre>'; print_r($repSets); echo '</pre>'; 
	?>
	</table>