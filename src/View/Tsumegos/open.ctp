	<?php 
	if($s2!=null)
		$diff = '$diff';
	else
		$diff = '';
	?>
	<script>
		localStorage.setItem("sgfForBesogo", "<?php echo $s1['Sgf']['sgf']?>");
		<?php if($s2!=null){ ?>
			localStorage.setItem("diffForBesogo", "<?php echo $s2['Sgf']['sgf']?>");
		<?php } ?>
		window.location.href = "<?php echo '/editor/?onSite='.$_SERVER['HTTP_HOST'].'$'.($t['Tsumego']['id']*1337).$diff; ?>";
	</script>