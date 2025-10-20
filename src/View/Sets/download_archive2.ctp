<script src ="/FileSaver.min.js"></script>

<?php
	echo 'Downloading collection '.($text).' of '.count($s).'.';
?>


<script>
	setTimeout(function(){
	   window.location.href = "/sets/download_archive";
	}, 100);
</script>
