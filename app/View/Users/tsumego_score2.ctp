	
	
	<br>
	<?php
	$p = array();
	for($i=0; $i<=100; $i++){
		$p[$i] = 0;
	}
	//echo '<pre>';print_r($p);echo '</pre>';
	echo count($ts).'<br>';
	
	
	$r = array();
	$r['15k'] = 0;
	$r['14k'] = 0;
	$r['13k'] = 0;
	$r['12k'] = 0;
	$r['11k'] = 0;
	$r['10k'] = 0;
	$r['9k'] = 0;
	$r['8k'] = 0;
	$r['7k'] = 0;
	$r['6k'] = 0;
	$r['5k'] = 0;
	$r['4k'] = 0;
	$r['3k'] = 0;
	$r['2k'] = 0;
	$r['1k'] = 0;
	$r['1d'] = 0;
	$r['2d'] = 0;
	$r['3d'] = 0;
	$r['4d'] = 0;
	$r['5d'] = 0;
	
	for($i=0; $i<count($ts); $i++){
		$p[$ts[$i]['Tsumego']['userWin']]++;
		if($ts[$i]['Tsumego']['userWin']>=0 && $ts[$i]['Tsumego']['userWin']<=17) $r['5d']++;
		elseif($ts[$i]['Tsumego']['userWin']<=23) $r['4d']++;
		elseif($ts[$i]['Tsumego']['userWin']<=26) $r['3d']++;
		elseif($ts[$i]['Tsumego']['userWin']<=29) $r['2d']++;
		elseif($ts[$i]['Tsumego']['userWin']<=32) $r['1d']++;
		elseif($ts[$i]['Tsumego']['userWin']<=35) $r['1k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=38) $r['2k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=42) $r['3k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=46) $r['4k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=50) $r['5k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=55) $r['6k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=60) $r['7k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=65) $r['8k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=70) $r['9k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=75) $r['10k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=80) $r['11k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=85) $r['12k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=90) $r['13k']++;
		elseif($ts[$i]['Tsumego']['userWin']<=95) $r['14k']++;
		else $r['15k']++;
	}
	echo '<pre>';print_r($r);echo '</pre>';
	echo '<pre>';print_r($p);echo '</pre>';
	
	echo '<br>';
	echo '<table border="1">';
	for($i=0; $i<count($ts); $i++){
		echo '<tr>';
		echo '<td><a target="_blank" href="/tsumegos/play/'.$ts[$i]['Tsumego']['id'].'">'.$ts[$i]['Tsumego']['id'].'</a></td>';
		echo '<td>'.$ts[$i]['Tsumego']['difficulty'].'</td>';
		echo '<td>'.$ts[$i]['Tsumego']['userWin'].'</td>';
		echo '<td>'.$ts[$i]['Tsumego']['userLoss'].'</td>';
		echo '</tr>';
	}
	echo '<table>';

	
	?>


<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
