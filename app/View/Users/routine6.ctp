<?php
	//echo '<pre>'; print_r($u); echo '</pre>'; 
	for($i=0; $i<count($u); $i++){
		echo $u[$i]['User']['id'].'<br>';
		echo $u[$i]['User']['name'].'<br>';
		echo 's'.$u[$i]['User']['solved'].'<br>';
	}
?>