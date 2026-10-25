<?php
/**
 * @var View $this
 */
?>
<div>
	<form action="/tsumegos/mergeFinalForm" method="post">
		<div class="stack">
			<div class="form-field">
				<label class="form-field__label" for="master-id">Master id:</label>
				<input class="form-field__control" type="text" name="master-id" id="master-id">
			</div>
			<div class="form-field">
				<label class="form-field__label" for="slave-id">Slave id:</label>
				<input class="form-field__control" type="text" name="slave-id" id="slave-id">
			</div>
			<input type="submit" value="Proceed to step 2" id="submit">
		</div>
	</form>
</div>
