<?php

class SetEditRenderer
{
	public static function renderAddProblemForm($setId, $highestOrder)
	{
		echo '<h2 class="set-edit__heading">Add Problem</h2>';
		echo '<form id="TsumegoViewForm" method="post" action="/sets/createAndAddTsumego/' . $setId . '" enctype="multipart/form-data">';
		echo '<label for="order">Order:</label>';
		echo '<input type="text" name="order" id="order" value="' . ($highestOrder + 1) . '" placeholder="Order" />';
		echo '<input type="hidden" id="set_id" value="' . $setId . '">';
		echo '<div style="margin-top: 10px;">';
		echo '<label>SGF (choose one, or none for onsite edit):</label><br>';
		echo '<input type="file" name="adminUpload" accept=".sgf" style="margin-bottom: 10px;"><br>';
		echo '<label style="font-weight: normal;">or paste SGF:</label><br>';
		echo '<input id="sgf" type="textarea" placeholder="(;GM[1]FF[4]...)" rows="3">';
		echo '<input type="submit" value="Add" />';
		echo '</div>';
		echo '</form>';
	}
}
