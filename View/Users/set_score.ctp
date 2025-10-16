<br>
<?php
	//echo count($newTs3['id']).'<br>';
	//echo count($newTs3['id'][0]).'<br><br>';
	
	echo '<table border="1">';
	
	for($i=0; $i<count($newTs3['id']); $i++){
		$multiplier = '';
		$mRow = '';
		if($newTs3['multiplier'][$i][0]!=1) $mRow = '<th style="text-align:left">multiplied</th>';
		echo '<tr>';
		echo '<td>';
		echo '
		<div class="scoreTitle" id="title'.$i.'">
		<table width="100%"><tr>
		<td width="100%">'.$newTs3['set'][$i][0].'</td><td>'.$setPercent[$i].'%</td><td> ×'.$newTs3['multiplier'][$i][0].'</td><td> difficulty:<b>'.$setDifficulty[$i].'</b></td>
		<td><img id="arrow'.$i.'" src="/img/greyArrow1.png"></td>
		</tr></table>
		</div>';
		echo '<div id="content'.$i.'" >';
		echo '<table class="scoreTable" border="0">';
		echo '<tr>';
		echo '<th style="text-align:left">id</th><th style="text-align:left">title</th><th style="text-align:left">solved</th>
		<th style="text-align:left">old xp</th><th style="text-align:left">new xp</th>'.$mRow;
		echo '</tr>';
		for($j=0; $j<count($newTs3['id'][$i]); $j++){
			$bgcolor = '';
			$bgcolor2 = '';
			if($newTs3['xp'][$i][$j]>$newTs3['newxp'][$i][$j]){
				$bgcolor = 'style="background:#62db8a;"';
			}elseif($newTs3['newxp'][$i][$j]>$newTs3['xp'][$i][$j]){
				$bgcolor = 'style="background:#e06464;"';
			}
			
			echo '<tr>';
			echo '<td>';
			echo $newTs3['id'][$i][$j];
			echo '</td>';
			echo '<td>';
			echo '<a href="/tsumegos/play/'.$newTs3['id'][$i][$j].'" target="_blank">'.$newTs3['set'][$i][$j].' '.$newTs3['num'][$i][$j].'</a>';
			echo '</td>';
			//echo '<td>';
			//echo $newTs3['count'][$i][$j];
			//echo '</td>';
			echo '<td>';
			if($newTs3['count'][$i][$j]>=10) echo $newTs3['percent'][$i][$j].'%';
			else echo 'n.a.';
			echo '</td>';
			echo '<td >';
			echo $newTs3['xp'][$i][$j];
			echo '</td>';
			if($newTs3['count'][$i][$j]>=10){
			echo '<td '.$bgcolor.'>';
			echo '<b>'.$newTs3['newxp'][$i][$j].'</b>';
			echo '</td>';
			}else echo '<td>n.a.</td>';
			if($newTs3['multiplier'][$i][0]!=1){
			//if(true){
				echo '<td >';
				echo $newTs3['multiplied'][$i][$j];
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
		echo '</div>';
		echo '</td>';
		echo '</tr>';
	}
	
	
	echo '</table>';
	
	/*
	for($i=0; $i<count($newTs3['id']); $i++){
		for($j=0; $j<count($newTs3['id'][$i]); $j++){
			echo $newTs3['id'][$i][$j].'<br>';
			echo $newTs3['count'][$i][$j].'<br>';
			echo $newTs3['percent'][$i][$j].'<br>';
			echo $newTs3['set'][$i][$j].'<br>';
			echo $newTs3['num'][$i][$j].'<br>';
			echo $newTs3['xp'][$i][$j].'<br>';
			echo $newTs3['newxp'][$i][$j].'<br>.';
		}
	}
	
	echo '<table>';
	echo '<th>id</th><th>title</th><th>count</th><th>percent</th><th>xp</th><th>new xp</th>';
	$sum = 0;
	$c = 0;
	for($i=0; $i<count($ts['id']); $i++){
		if(true){
			$sum+= $ts['percent'][$i];
			$c++;
			echo '<tr>';
			echo '<td>';
			echo $ts['id'][$i];
			echo '</td>';
			echo '<td>';
			echo '<a href="https://gamma.tsumego-hero.com/tsumegos/play/'.$ts['id'][$i].'" target="_blank">'.$ts['set'][$i].' '.$ts['num'][$i].'</a>';
			echo '</td>';
			
			echo '<td>';
			echo $ts['count'][$i];
			echo '</td>';
			echo '<td>';
			echo $ts['percent'][$i].'%';
			echo '</td>';
			echo '<td>';
			echo $ts['xp'][$i];
			echo '</td>';
			echo '<td>';
			echo $ts['newxp'][$i];
			echo '</td>';
			echo '</tr>';
		}
	}
	echo '</table>';
	//echo $sum;
	echo '<br>';
	echo $c;
	echo '<br>';
	echo $sum/$c;
	*/
	//echo '<pre>';print_r($setCount);echo '</pre>';
	//echo '<pre>';print_r($setPercent);echo '</pre>';
	/*
	$avg = array();
	$avg[10] = 83.139212328767;
	$avg[20] = 73.936962406015;
	$avg[30] = 63.582480620155;
	$avg[40] = 57.21416496945;
	$avg[50] = 52.61221;
	$avg[60] = 44.959153094463;
	$avg[70] = 36.729862258953;
	$avg[80] = 31.031111111111;
	$avg[90] = 24.950833333333;
	*/
	
	
?>
<br><br>
<h3>Average solved % by xp value:</h3>
<table border="1">
<tr>
<td>10xp</td>
<td>83.139212328767%</td>
</tr>
<tr>
<td>20xp</td>
<td>73.936962406015%</td>
</tr>
<tr>
<td>30xp</td>
<td>63.582480620155%</td>
</tr>
<tr>
<td>40xp</td>
<td>57.21416496945%</td>
</tr>
<tr>
<td>50xp</td>
<td>52.61221%</td>
</tr>
<tr>
<td>60xp</td>
<td>44.959153094463%</td>
</tr>
<tr>
<td>70xp</td>
<td>36.729862258953%</td>
</tr>
<tr>
<td>80xp</td>
<td>31.031111111111%</td>
</tr>
<tr>
<td>90xp</td>
<td>24.950833333333%</td>
</tr>
</table>

<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>


<script type="text/JavaScript">
	<?php
	for($i=0; $i<count($newTs3['id']); $i++){
		echo '
		var triggered'.$i.' = false;
		$("#content'.$i.'").hide();
		$("#title'.$i.'").click(function(){
			if(!triggered'.$i.'){
				$("#content'.$i.'").fadeIn(250);
				document.getElementById("arrow'.$i.'").src = "/img/greyArrow2.png";
			}else{
				$("#content'.$i.'").fadeOut(250);
				document.getElementById("arrow'.$i.'").src = "/img/greyArrow1.png";
			}
			triggered'.$i.' = !triggered'.$i.';
		});
		';
		
	}
	?>


</script>







