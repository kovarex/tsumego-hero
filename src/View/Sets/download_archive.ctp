

<?php
	
	if($text < count($s)){
		echo 'Downloading collection '.($text).' of '.count($s).'.';
	}else{
		echo 'Download finished.';
?>

<table class="highscoreTable" border="0">
	<tbody>
	<?php
		for($i=0; $i<count($s); $i++){
			echo '<tr>';
			echo '<td class="timeTableMiddle versionColor" align="left">id ';
			echo $s[$i]['Set']['id'];
			echo '</td>';
			echo '<td class="timeTableMiddle versionColor" align="left">';
			echo $s[$i]['Set']['title'];
			echo '</td>';
			echo '</tr>';
		}
	?>
	</tbody>
</table>
<?php
	}
?>

<script>
	<?php
		if($text<count($s)){
			echo 'setTimeout(function(){
				window.location.href = "/sets/download_archive2/'.$s[$text]['Set']['id'].'";
			}, 100);';
		}
	?>
</script>
