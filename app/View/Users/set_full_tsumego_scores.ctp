	<br>
	<?php echo $hightestT['Tsumego']['id']; ?>
	<br>

<table >
<tr>
<td>
	<table border="1" style="padding:8px;">
	<?php 
	for($i=0; $i<count($ts); $i++){
		$tRank = '15k';
		echo '<tr>';
		echo '<td><a href="/tsumegos/play/'.$ts[$i]['Tsumego']['id'].'" target="_blank">'.$ts[$i]['Tsumego']['id'].'</a></td>';
		echo '<td>'.$ts[$i]['Tsumego']['difficultyOld'].'</td>';
		echo '<td>'.$ts[$i]['Tsumego']['difficulty'].'</td>';
		echo '<td>'.$ts[$i]['Tsumego']['solved'].'-'.$ts[$i]['Tsumego']['failed'].'</td>';
		echo '<td>'.$ts[$i]['Tsumego']['userWin'].'</td>';
		echo '<td>'.$ts[$i]['Tsumego']['userLoss'].'</td>';
		$ts[$i]['Tsumego']['userWin'] = round($ts[$i]['Tsumego']['userWin']);
		if($ts[$i]['Tsumego']['userWin']>=0 && $ts[$i]['Tsumego']['userWin']<=17) $tRank='5d';
		elseif($ts[$i]['Tsumego']['userWin']<=23) $tRank='4d';
		elseif($ts[$i]['Tsumego']['userWin']<=26) $tRank='3d';
		elseif($ts[$i]['Tsumego']['userWin']<=29) $tRank='2d';
		elseif($ts[$i]['Tsumego']['userWin']<=32) $tRank='1d';
		elseif($ts[$i]['Tsumego']['userWin']<=35) $tRank='1k';
		elseif($ts[$i]['Tsumego']['userWin']<=38) $tRank='2k';
		elseif($ts[$i]['Tsumego']['userWin']<=42) $tRank='3k';
		elseif($ts[$i]['Tsumego']['userWin']<=46) $tRank='4k';
		elseif($ts[$i]['Tsumego']['userWin']<=50) $tRank='5k';
		elseif($ts[$i]['Tsumego']['userWin']<=55) $tRank='6k';
		elseif($ts[$i]['Tsumego']['userWin']<=60) $tRank='7k';
		elseif($ts[$i]['Tsumego']['userWin']<=65) $tRank='8k';
		elseif($ts[$i]['Tsumego']['userWin']<=70) $tRank='9k';
		elseif($ts[$i]['Tsumego']['userWin']<=75) $tRank='10k';
		elseif($ts[$i]['Tsumego']['userWin']<=80) $tRank='11k';
		elseif($ts[$i]['Tsumego']['userWin']<=85) $tRank='12k';
		elseif($ts[$i]['Tsumego']['userWin']<=90) $tRank='13k';
		elseif($ts[$i]['Tsumego']['userWin']<=95) $tRank='14k';
		else $tRank='15k';
		if($ts[$i]['Tsumego']['userWin']==0) echo '<td>0</td>';
		else echo '<td>'.$tRank.'</td>';
		echo '</tr>';
	} 
	?>
	</table>
</td>
<td>
	<table border="1" style="padding:8px;">
	<?php 
	for($i=0; $i<count($ts2); $i++){
		$tRank = '15k';
		echo '<tr>';
		echo '<td><a href="/tsumegos/play/'.$ts2[$i]['Tsumego']['id'].'" target="_blank">'.$ts2[$i]['Tsumego']['id'].'</a></td>';
		echo '<td>'.$ts2[$i]['Tsumego']['difficulty'].'</td>';
		echo '<td>'.$ts2[$i]['Tsumego']['solved'].'-'.$ts2[$i]['Tsumego']['failed'].'</td>';
		echo '<td>'.$ts2[$i]['Tsumego']['userWin'].'</td>';
		echo '<td>'.$ts2[$i]['Tsumego']['userLoss'].'</td>';
		$ts2[$i]['Tsumego']['userWin'] = round($ts2[$i]['Tsumego']['userWin']);
		if($ts2[$i]['Tsumego']['userWin']>=0 && $ts2[$i]['Tsumego']['userWin']<=17) $tRank='5d';
		elseif($ts2[$i]['Tsumego']['userWin']<=23) $tRank='4d';
		elseif($ts2[$i]['Tsumego']['userWin']<=26) $tRank='3d';
		elseif($ts2[$i]['Tsumego']['userWin']<=29) $tRank='2d';
		elseif($ts2[$i]['Tsumego']['userWin']<=32) $tRank='1d';
		elseif($ts2[$i]['Tsumego']['userWin']<=35) $tRank='1k';
		elseif($ts2[$i]['Tsumego']['userWin']<=38) $tRank='2k';
		elseif($ts2[$i]['Tsumego']['userWin']<=42) $tRank='3k';
		elseif($ts2[$i]['Tsumego']['userWin']<=46) $tRank='4k';
		elseif($ts2[$i]['Tsumego']['userWin']<=50) $tRank='5k';
		elseif($ts2[$i]['Tsumego']['userWin']<=55) $tRank='6k';
		elseif($ts2[$i]['Tsumego']['userWin']<=60) $tRank='7k';
		elseif($ts2[$i]['Tsumego']['userWin']<=65) $tRank='8k';
		elseif($ts2[$i]['Tsumego']['userWin']<=70) $tRank='9k';
		elseif($ts2[$i]['Tsumego']['userWin']<=75) $tRank='10k';
		elseif($ts2[$i]['Tsumego']['userWin']<=80) $tRank='11k';
		elseif($ts2[$i]['Tsumego']['userWin']<=85) $tRank='12k';
		elseif($ts2[$i]['Tsumego']['userWin']<=90) $tRank='13k';
		elseif($ts2[$i]['Tsumego']['userWin']<=95) $tRank='14k';
		else $tRank='15k';
		if($ts2[$i]['Tsumego']['userWin']==0) echo '<td>0</td>';
		else echo '<td>'.$tRank.'</td>';
		echo '</tr>';
	} 
	?>
	</table>
</td>
</tr>
</table>	
	<?php
		echo 'count: '.count($ts);
		echo '<br>';
		echo 'from: '.$from;
		echo '<br>';
		echo 'to: '.$to;
		
		$params += 10;
		//echo '<pre>';print_r($ur);echo '</pre>';

	?>

	<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
	<script type="text/JavaScript">
	<?php if($params<=$hightestT['Tsumego']['id']){ ?>
		setTimeout(function () {window.location.href = "/users/set_full_tsumego_scores?t=<?php echo $params ?>";}, 500);
	<?php }else{ ?>	
		setTimeout(function () {window.location.href = "/users/tsumego_score";}, 5000);
	<?php } ?>
	</script>