	
	<?php
		echo count($x1).'<br>';
		echo '<pre>'; print_r($x1); echo '</pre>';
	?>
	<br>
	<font size="5">

	</font>

	<?php
	//echo '<pre>';print_r($tsx);echo '</pre>';
	echo '<pre>'; print_r(array_count_values($tsx)); echo '</pre>';
	echo '<pre>'; print_r($avg); echo '</pre>';
	//echo '<pre>'; print_r($count); echo '</pre>';
	echo '<table border="1">';
	for($i=0; $i<count($ts); $i++){
		echo '<tr>';
		echo '<td>'.$ts[$i]['Tsumego']['userWin'].'</td>';
		echo '<td>'.$ts[$i]['Tsumego']['userLoss'].'</td>';
		echo '</tr>';
	}
	echo '<table>';



	?>


	<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
