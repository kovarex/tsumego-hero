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
	<form action="/tsumegos/performMerge" method="post">
		<input type="hidden" name="master-tsumego-id" value="<?php echo $masterTsumegoID; ?>">
		<input type="hidden" name="slave-tsumego-id" value="<?php echo $slaveTsumegoID; ?>">
		<input type="submit" value="PERFORM!" id="submit">
	</form>
</div>
