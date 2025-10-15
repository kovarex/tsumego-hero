<table>
<?php
//echo '<pre>'; print_r($u); echo '</pre>'; 
for($i=0; $i<count($u); $i++){
	echo '<tr>';
	echo '<td>'.$i.'</td>';
	echo '<td>'.$u[$i]['User']['id'].'</td>';
	echo '<td>'.$u[$i]['User']['name'].'</td>';
	echo '<td>'.$u[$i]['User']['x'].'</td>';
	echo '</tr>';
}
?>
</table>

<?php
	echo $d;
?>

<br>
<table>
<?php
//echo '<pre>'; print_r($uts); echo '</pre>'; 

for($i=0; $i<count($idMap); $i++){
	echo '<tr>';
	echo '<td>'.$i.'</td>';
	echo '<td>'.$idMap[$i].'</td>';
	echo '<td>'.$status[$i].'</td>';
	echo '</tr>';
}
?>
</table>