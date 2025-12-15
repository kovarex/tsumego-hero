<div>
	<table>
	<tr><td colspan="2"><h2>Master tsumego</h2></td></tr>
	<tr>
		<td>Occurances:</td>
		<td>
		<?php
			foreach ($masterTsumegoButtons as $masterTsumegoButton)
				$masterTsumegoButton->render();
		?>
		</td>
	</tr>
	<tr><td colspan="2"><h2>Slave tsumego</h2></td></tr>
	<tr>
		<td>Occurances:</td>
		<td>
		<?php
			foreach ($slaveTsumegoButtons as $slaveTsumegoButton)
				$slaveTsumegoButton->render();
		?>
		</td>
	</tr>
	</table>

	<div>
		All these things will be merged:<br><br>
		Set connection ids will be preserved (the links will still work)<br>
		Player statuses (when both have status, the better will be selected)<br>
		Tsumego attempts<br>
		Comments<br>
		Favorites<br>
		Tags<br>
		Time mode attempts<br>
		Issues<br>
	</div>

	<form action="/tsumegos/performMerge" method="post">
		<input type="hidden" name="master-tsumego-id" value="<?php echo $masterTsumegoID; ?>">
		<input type="hidden" name="slave-tsumego-id" value="<?php echo $slaveTsumegoID; ?>">
		<input type="submit" value="PERFORM!" id="submit">
	</form>
</div>
