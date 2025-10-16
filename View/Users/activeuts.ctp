<?php

	echo count($u);
	echo '<pre>';
	print_r($u);
	echo '</pre>';
	$c = 0;
	for($i=0; $i<count($u); $i++){
		$c+=$u[$i];
	}
	
	echo $c;
?>