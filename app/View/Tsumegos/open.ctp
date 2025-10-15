	<?php 
	if($_SERVER["HTTP_HOST"]=='localhost') 
		$rootPath = '/tsumego-hero';
	else
		$rootPath = '';
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
		window.location.href = "<?php echo $rootPath.'/app/webroot/editor/?onSite='.$_SERVER['HTTP_HOST'].'$'.($t['Tsumego']['id']*1337).$diff; ?>";
	</script>