select rank
<br><br>
<table>
<?php
	
	echo '<br><br>';
	echo $hash;
	//echo '<pre>'; print_r($ts); echo '</pre>';
	$rank = 15;
	while($rank>0){
		echo '<tr><td><a href="/tsumegos/play/2597?hash='.$hash.'">'.$rank.'k</a></td></tr>';
		$rank--;
	}

	
?>
</table>