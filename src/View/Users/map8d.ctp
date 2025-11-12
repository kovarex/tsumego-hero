	<?php
		echo '<table border="0">';
		$counter = 1;
		for($i=0; $i<count($ts); $i++){
			if(!$ts[$i]['Tsumego']['deleted']){
				echo '<tr>';
				echo '<td>'.($counter).'</td>';
				echo '<td><a target="_blank" href="/users/tsumego_rating/'.$ts[$i]['Tsumego']['id'].'" > '.$ts[$i]['Tsumego']['id'].'</a></td>';
				echo '<td>'.$ts[$i]['Tsumego']['rating'].'</td>';
				echo '<td>'.$ts[$i]['Tsumego']['rank'].'</td>';
				echo '<td>'.$ts[$i]['Tsumego']['shift'].'</td>';
				echo '<td>'.$ts[$i]['Tsumego']['rank2'].'</td>';
				echo '</tr>';
				$counter++;
			}
		}
		echo '<table>';
	?>
