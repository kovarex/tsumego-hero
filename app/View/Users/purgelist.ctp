<h1>Purge</h1>
<table>
<?php
echo '<table>';
echo '<tr><td>#</td><td>'.$s.'/'.$uCount.'</td></tr>';
echo '<tr><td>id</td><td>'.$u['User']['id'].'</td></tr>';
echo '</table>';
for($i=0; $i<count($ux); $i++){
	echo '<tr>
	<td>'.$ux[$i]['User']['id'].'</td>
	<td>'.$ux[$i]['User']['name'].'</td>
	<td>'.$ux[$i]['User']['solved'].'</td>
	<td>'.$ux[$i]['User']['created'].'</td>
	<td>'.$ux[$i]['User']['d1'].'</td>
	<td>'.$ux[$i]['User']['d2'].'</td></tr>';
}
//echo '<pre>';print_r($ux);echo '</pre>';
?>
</table>
<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
<script type="text/JavaScript">
	<?php 
	if($stop=='f'){
		echo '
			setTimeout(function () {
			   window.location.href = "/users/purgelist"; 
			}, 100);
		';
	}
	if($stop=='t'){
		/*
		echo '
			setTimeout(function () {
			   window.location.href = "/users/countlist"; 
			}, 100);
		';
		*/
		echo '
			setTimeout(function () {
			   window.location.href = "/users/set_full_tsumego_scores?t=0";
			}, 100);
		';
	}
		?>
</script>
