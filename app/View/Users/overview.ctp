<div align="center">
<h2>Likes/Dislikes</h2>
</div>
<br>
<table class="contentOnTop" width="100%">
<tr>
<td>
<table>
<tr><td>Set</td><td>like</td><td>dislike</td></tr>
<?php
	for($i=0; $i<count($as); $i++){
		for($j=0; $j<count($repSets); $j++){
			if($repSets[$j]['set_id']==$as[$i]){
				echo '<tr>';
				echo '<td>'.$repSets[$j]['set_name'].'</td>';
				echo '<td>'.$repSets[$j]['pos'].'</td>';
				echo '<td>'.$repSets[$j]['neg'].'</td>';
				echo '</tr>';
			}
		}
	}

	//echo '<pre>'; print_r($as); echo '</pre>'; 
	//echo '<pre>'; print_r($repSets); echo '</pre>'; 
?>
</table>
</td>
<td>
<table>
<?php
	echo '<b>'.count($all).' likes/dislikes in the database.</b><br><br>';
	for($i=0; $i<count($all); $i++){
		echo '<tr>';
		echo '<td>'.$all[$i]['Reputation']['user'].'</td>';
		echo '<td>'.$all[$i]['Reputation']['name'].' '.$all[$i]['Reputation']['num'].'</td>';
		echo '<td>'.$all[$i]['Reputation']['value'].'</td>';
		echo '<td>'.$all[$i]['Reputation']['created'].'</td>';
		echo '<tr>';
	}
?>
</table>
</td>
</tr>
</table>