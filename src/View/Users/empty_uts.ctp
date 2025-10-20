
<?php
	echo $ut.' ';
	if($ut>0){
?>
<script type="text/JavaScript">
	setTimeout(function () {window.location.href = "/users/empty_uts";}, 1000);
</script>
<?php }else{ ?>
<script type="text/JavaScript">
	setTimeout(function () {window.location.href = "/users/purgelist";}, 1000);
</script>
<?php } ?>