<table>
<?php
	//echo '<pre>';print_r($u);echo '</pre>';
	for($i=0; $i<count($u); $i++){
		if($u[$i]['User']['lastRefresh']==null) $u[$i]['User']['lastRefresh'] = 'null';
		echo '<tr>';
		echo '<td>'.$u[$i]['User']['id'].'</td>';
		echo '<td>'.$u[$i]['User']['name'].'</td>';
		echo '<td>'.$u[$i]['User']['lastRefresh'].'</td>';
		echo '<td>'.date('Y-m-d').'</td>';
		echo '</tr>';
	}
?>
</table>