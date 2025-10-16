<table>
<?php
//echo '<pre>'; print_r($u); echo '</pre>'; 
for($i=0; $i<count($u); $i++){
	echo '<tr>';
	echo '<td>'.$i.'</td>';
	echo '<td>'.$u[$i]['User']['id'].'</td>';
	echo '<td>'.$u[$i]['User']['name'].'</td>';
	
	if($u[$i]['User']['x']!=4) echo '<td>'.$u[$i]['User']['x'].'</td>';
	else echo '<td><font style="font-weight:800;" color="red">'.$u[$i]['User']['x'].'</font></td>';
	echo '</tr>';
}
?>
</table>