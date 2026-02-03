<table>
<?php

for ($i = 0; $i < count($ux); $i++)
{
	echo '<tr><td>' . $ux[$i]['User']['id'] . '</td><td>' . h($ux[$i]['User']['display_name']) . '</td><td>' . $ux[$i]['User']['solved'] . '</td></tr>';
}
//echo '<pre>';print_r($ux);echo '</pre>';
?>
</table>


?>