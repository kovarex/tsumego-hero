<div>
	<h2>Master tsumego</h2>
	<?php
		foreach ($masterTsumegoButtons as $masterTsumegoButton)
			$masterTsumegoButton->render();
	?>
	<h2>Slave tsumego</h2>
	<?php
		foreach ($slaveTsumegoButtons as $slaveTsumegoButton)
			$slaveTsumegoButton->render();
	?>
	<form action="/tsumegos/performMerge">
		<input type="hidden" id="masterTsumegoID" value="<?php echo $masterTsumegoID; ?>">
		<input type="hidden" id="slaveTsumegoID" value="<?php echo $slaveTsumegoID; ?>">
		<input type="submit" value="PERFORM!" id="submit">
	</form>
</div>
