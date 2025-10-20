
<?php
	echo count($t).' ';
	echo count($ut).' ';
	echo count($out).' ';
	echo count($ux);
	echo '<pre>';print_r($tr);echo '</pre>';
	//echo '<pre>';print_r($c);echo '</pre>';
	//echo '<script type="text/javascript">window.location.href = "/users/playerdb5";</script>';
	$date = date('Y-m-d', strtotime('yesterday'));
	$ids = array();
	for($i=0; $i<count($ut); $i++){
		$date2 = new DateTime($ut[$i]['TsumegoRatingAttempt']['created']);
		$date2 = $date2->format('Y-m-d');
		if($date===$date2){
			array_push($ids, $ut[$i]['TsumegoRatingAttempt']['tsumego_id']);
		}
	}
	$ids = array_count_values($ids);
	$highest = 0;
	$best = array();
	foreach ($ids as $key => $value){
		if($value>$highest) $highest=$value;
	}
	foreach ($ids as $key => $value){
		if($value==$highest){
			$x = array();
			$x[$key] = $value;
			array_push($best, $x);
		}
	}
	echo '<pre>'; print_r($best); echo '</pre>';
	
	//echo '<pre>'; print_r($ids); echo '</pre>';
	//echo '<pre>'; print_r($ut[count($ut)-1]); echo '</pre>';
	//echo '<pre>'; print_r($out[count($out)-1]); echo '</pre>';
	
	$ids2 = array();
	$out2 = array();
	for($i=0; $i<count($out); $i++){
		$date2 = new DateTime($out[$i]['TsumegoAttempt']['created']);
		$date2 = $date2->format('Y-m-d');
		if($date===$date2){
			array_push($ids2, $out[$i]['TsumegoAttempt']['tsumego_id']);
			array_push($out2, $out[$i]);
		}
	}
	echo count($out2).'<br>';
	$ids2 = array_count_values($ids2);
	//echo '<pre>'; print_r($ids2); echo '</pre>';
	$highest = 0;
	$best2 = array();
	foreach ($ids2 as $key => $value){
		if($value>$highest) $highest=$value;
	}
	$done = false;
	$found = 0;
	$decrement = 0;
	$best3 = array();
	$findNum = 20;
	while(!$done){
		foreach ($ids2 as $key => $value){
			if($value==$highest-$decrement){
				array_push($best2, $key);
				array_push($best3, $value);
				$found++;
			}
		}
		$decrement++;
		if($found<$findNum) $done = false;
		else $done = true;
	}
	echo '<pre>'; print_r($best2); echo '</pre>';
	echo '<pre>'; print_r($best3); echo '</pre>';
	$newBest = array();
	for($j=0; $j<$findNum; $j++) $newBest[$j] = array();
	for($i=0; $i<count($out2); $i++){
		for($j=0; $j<$findNum; $j++){
			if($out2[$i]['TsumegoAttempt']['tsumego_id']==$best2[$j]){
				$x = array();
				$x['tid'] = $out2[$i]['TsumegoAttempt']['tsumego_id'];
				for($k=0; $k<count($t); $k++){
					if($x['tid']==$t[$k]['Tsumego']['id']) $x['sid'] = $t[$k]['Tsumego']['set_id'];
				}
				$x['status'] = $out2[$i]['TsumegoAttempt']['solved'];
				$x['seconds'] = $out2[$i]['TsumegoAttempt']['seconds'];
				array_push($newBest[$j], $x);
			}
		}
	}
	for($i=0; $i<count($newBest); $i++){
		//echo $newBest[$i][0]['tid'].'<br>';
		$sum = 0;
		for($j=0; $j<count($newBest[$j]); $j++){
			//echo $newBest[$i][$j]['seconds'].'<br>';
			if($newBest[$i][$j]['seconds']!=null){
				if($newBest[$i][$j]['seconds']>300){
					$newBest[$i][$j]['seconds'] = 300;
				}
				$sum += $newBest[$i][$j]['seconds'];
			}
		}
		$sum = $sum * count($newBest[$i]);
		$newBest[$i]['sum'] = $sum;
		echo '<b>!'.$i.' ! '.$sum.'</b><br>';
	}
	$highest = 0;
	$hid = 0;
	for($i=0; $i<count($newBest); $i++){
		if($newBest[$i]['sum']>$highest && $newBest[$i][0]['sid']!=104 && $newBest[$i][0]['sid']!=105 && $newBest[$i][0]['sid']!=117){
			$yesterday = false;
			for($j=0; $j<count($s); $j++){
				if($newBest[$i][0]['tid']==$s[$j]['Schedule']['tsumego_id']) $yesterday = true;
			}
			if(!$yesterday){
				$highest = $newBest[$i]['sum'];
				$hid = $i;
			}
		}
	}
	echo '<b>Tsumego Of The Day (most popular): '.$newBest[$hid][0]['tid'].'</b><br>';
	echo 'Highest: '.$highest.'<br>';
	//echo '<pre>'; print_r($s); echo '</pre>';
	echo '<pre>'; print_r($newBest); echo '</pre>';
	
?>
<script type="text/JavaScript">
	//setTimeout(function () {window.location.href = "https://tsumego-hero.com/users/playerdb5";}, 1000);
</script>