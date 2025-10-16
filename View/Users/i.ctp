

	<div align="center">
	<h2>Likes/Dislikes of <?php echo $s['Set']['title'].' '.$s['Set']['title2']; ?></h2>
	</div>
	<div align="right"><a href="/users/likesview">back</a></div>
	<br>
	<?php
	echo $likes.' likes, '.$dislikes.' dislikes<br>';
	?>
	<br>
	<table class="contentOnTop" width="100%">
	<tr>
	<td>
	<table >
	<?php
		for($j=0; $j<count($a); $j++){
			$thumb = '<img src="/img/t-down.png" width="25px">';
			if($a[$j]['Reputation']['value']==1) $thumb = '<img src="/img/t-up.png" width="25px">';
			echo '<tr>';
			echo '<td>'.$a[$j]['Reputation']['user'].'</td>';
			echo '<td>'.$a[$j]['Reputation']['tsumego'].'</td>';
			echo '<td>'.$thumb.'</td>';
			echo '<td>'.$a[$j]['Reputation']['created'].'</td>';
			echo '</tr>';
		}
		//echo '<pre>'; print_r($as); echo '</pre>'; 
		//echo '<pre>'; print_r($repSets); echo '</pre>'; 
	?> 
	</table>

	<?php
	
	//echo '<pre>'; print_r($a); echo '</pre>'; 