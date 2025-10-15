<table>
<?php

for($i=0; $i<count($ux); $i++){
	echo '<tr>
		<td>'.$ux[$i]['User']['id'].'</td>
		<td>'.$ux[$i]['User']['name'].'</td>
		<td>'.$ux[$i]['User']['solved'].'</td>
		<td>'.$ux[$i]['User']['d1'].'</td>
		<td>'.$ux[$i]['User']['d2'].'</td>
	</tr>';
}/*
for($i=0; $i<count($ux); $i++){
	echo '<tr><td>'.$ux[$i]['TsumegoAttempt']['id'].'</td><td>'.$ux[$i]['TsumegoAttempt']['created'].'</td></tr>';
}
*/
//echo '<pre>';print_r($ux);echo '</pre>';
?>
</table>


?>