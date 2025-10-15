<br>
<font size="5">
<table>
<?php 
	$c = $t['Tsumego']['solved'] + $t['Tsumego']['failed'];
	$p = $t['Tsumego']['solved']/$c;
	$p*=100;
	$p = round($p,2);
?>
<tr><td>Tsumego id:</td><td><?php echo '<a href="/tsumegos/play/'.$t['Tsumego']['id'].'" target="_blank">'.$t['Tsumego']['id'].'</a>' ?></td></tr>
<tr><td>Difficulty:</td><td><?php echo $t['Tsumego']['difficulty'] ?></td></tr>
<tr><td>Entries:</td><td><?php echo $c; ?></td></tr>
<tr><td>Rate:</td><td><?php echo $t['Tsumego']['solved'].'/'.$c ?></td></tr>
<tr><td>Percent:</td><td><?php echo $p.'%' ?></td></tr>
</table>
</font>
<?php
	echo 'count: '.count($ts);
	echo '<br>';
	echo 'from: '.$from;
	echo '<br>';
	echo 'to: '.$to;
	
	$params += 10;
	echo '<pre>';print_r($ur);echo '</pre>';
	
	/*
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
<script type="text/JavaScript">
<?php if($params<30000){ ?>
	setTimeout(function () {window.location.href = "/users/set_tsumego_scores?t=<?php echo $params ?>";}, 1000);
<?php } ?>	
</script>