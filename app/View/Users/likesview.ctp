<div align="center">
<h2>Likes/Dislikes</h2>
</div>
<div align="right"><a href="/sets/beta">back</a></div>

<table class="contentOnTop" width="100%">
<tr>
<td>
<table>
<tr><td>Set</td><td><img src="/img/t-up.png" width="25px"></td><td><img src="/img/t-down.png" width="25px"></td></tr>
<?php
	for($i=0; $i<count($as); $i++){
		for($j=0; $j<count($repSets); $j++){
			if($repSets[$j]['set_id']==$as[$i]){
				echo '<tr>';
				echo '<td><a href="/users/i/'.$repSets[$j]['set_id'].'">'.$repSets[$j]['set_name'].'</a></td>';
				echo '<td align="center">'.$repSets[$j]['pos'].'</td>';
				echo '<td align="center">'.$repSets[$j]['neg'].'</td>';
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
		echo '<td><a href="/users/i2/'.$all[$i]['Reputation']['user_id'].'">'.$all[$i]['Reputation']['user'].'</a></td>';
		echo '<td><a target="_blank" href="/tsumegos/play/'.$all[$i]['Reputation']['tsumego_id'].'">'.$all[$i]['Reputation']['name'].' '.$all[$i]['Reputation']['num'].'</a></td>';
		if($all[$i]['Reputation']['value']=='like') echo '<td><img src="/img/t-up.png" width="25px"></td>';
		else echo '<td><img src="/img/t-down.png" width="25px"></td>';
		echo '<td>'.$all[$i]['Reputation']['created'].'</td>';
		echo '<tr>';
	}
?>
</table>
</td>
</tr>
</table>