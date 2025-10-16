<?php
echo count($trs).'<br>';
if($activeToday) echo '!';
else echo 'x';
echo '<pre>';print_r($trs);echo '</pre>';
/*
echo '<table>';
for($i=0; $i<count($ux); $i++){
	echo '<tr><td>'.$ux[$i]['User']['id'].'</td><td>'.$ux[$i]['User']['name'].'</td><td>'.$ux[$i]['User']['solved'].'</td><td>'.$ux[$i]['User']['created'].'</td></tr>';
}
echo '</table>';
*/
?>