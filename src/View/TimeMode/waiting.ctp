<?php

/**
 * @var View $this
 */

?>
<div align="center">
	<h2>Time Mode</h2>
	<br>
	<p>Finishing your session...</p>
	<p>This only takes a moment.</p>
</div>
<script type="text/javascript">
	// The result of the last problem can still be on its way, so the page keeps
	// asking for it. When it does not arrive, waiting for it is pointless and the
	// problems left behind have to be resolved and scored without it.
	var waitingSince = sessionStorage.getItem('timeModeWaitStarted');
	if (!waitingSince)
	{
		waitingSince = Date.now();
		sessionStorage.setItem('timeModeWaitStarted', waitingSince);
	}
	if (Date.now() - waitingSince > <?php echo TimeModeUtil::$WAITING_FOR_RESULT_SECONDS; ?> * 1000)
	{
		sessionStorage.removeItem('timeModeWaitStarted');
		window.location.replace('/timeMode/play');
	}
	else
		setTimeout(function () { window.location.reload(); }, 1000);
</script>
