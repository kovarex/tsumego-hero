<?php
/**
 * @var View $this
 * @var array $ux
 */

?>
<table>
<?php

for($i = 0; $i < count($ux); $i++)
		echo '<tr><td>' . $ux[$i]['User']['id'] . '</td><td>' . h($ux[$i]['User']['name']) . '</td><td>' . $ux[$i]['User']['solved'] . '</td><td><time datetime="' . Util::toIso8601($ux[$i]['User']['created']) . '" data-format="date">' . $ux[$i]['User']['created'] . '</time></td><td>' . $ux[$i]['User']['out'] . '</td></tr>';
//echo '<pre>';print_r($ux);echo '</pre>';
?>
</table>


?>