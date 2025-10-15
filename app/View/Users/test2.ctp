	<?php
		echo '<table border="0">';
		for($i=0; $i<count($ts); $i++){
			if(true){
				echo '<tr>';
				echo '<td>'.($i+1).'</td>';
				echo '<td><a target="_blank" href="/users/tsumego_rating/'.$ts[$i]['Tsumego']['id'].'" > '.$ts[$i]['Tsumego']['id'].'</a></td>';
				echo '<td>'.$ts[$i]['Tsumego']['rd'].'</td>';
				echo '</tr>';
			}
		}
		echo '<table>';
	?>