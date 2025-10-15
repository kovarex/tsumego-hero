<br>
<font size="5">
<table>
<tr><td>Tsumego:</td><td><?php echo '<a href="/tsumegos/play/'.$ur[0]['TsumegoAttempt']['tsumego_id'].'" target="_blank">'.$ur[0]['TsumegoAttempt']['tsumego_id'].'</a>' ?></td></tr>
<tr><td>Difficulty:</td><td><?php echo $t['Tsumego']['difficulty'] ?></td></tr>
<tr><td>Entries:</td><td><?php echo count($ur); ?></td></tr>
<tr><td>Rate:</td><td><?php echo $ratio['s'].'/'.$ratio['count'] ?></td></tr>
<tr><td>Percent:</td><td><?php echo $ratio['percent'].'%' ?></td></tr>
<tr><td>Percent:</td><td><?php echo $t['Tsumego']['userWin'].'%' ?></td></tr>
</table>
</font>

<?php
echo count($ur);
echo '<br>';
//echo '<pre>';print_r($ur);echo '</pre>';

$avg = array();
$avg[10] = 87.382505764796;
$avg[20] = 73.129444444444;
$avg[30] = 64.567987288136;
$avg[40] = 56.862570224719;
$avg[50] = 52.194473324213;
$avg[60] = 45.272835820896;
$avg[70] = 37.944619771863;
$avg[80] = 31.16525083612;
$avg[90] = 21.294197002141;



$distance = array();

for($i=0; $i<9; $i++){
	$xp = ($i+1)*10;
	$distance[$i] = $t['Tsumego']['userWin'] - $avg[$xp];
	if($distance[$i]<0) $distance[$i]*=-1;
}
echo '<pre>';print_r($distance);echo '</pre>';
echo '<pre>';print_r($avg);echo '</pre>';

$lowest = 100;
$pos = 0;
for($i=0; $i<count($distance); $i++){
	if($distance[$i]<$lowest){
		$pos = $i;
		$lowest = $distance[$i];
	}
}
echo $lowest.'<br>';
echo ($pos+1)*10;

/*

$avg[10] = 87.382505764796;
$avg[20] = 73.129444444444;
$avg[30] = 64.567987288136;
$avg[40] = 56.862570224719;
$avg[50] = 52.194473324213;
$avg[60] = 45.272835820896;
$avg[70] = 37.944619771863;
$avg[80] = 31.16525083612;
$avg[90] = 21.294197002141;

	10XP 83.166900684931
	20XP 73.953954887218 
	30XP 63.589457364341 
	40XP 57.215468431772
	50XP 52.61725 
	60XP 44.911859706362 
	70XP 36.617423822715 
	80XP 29.654845360825
	90XP 24.928333333333
*/

?>



















<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
