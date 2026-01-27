<?php

class SetEditRenderer
{
	public static function renderAddProblemForm($set, $tsumegoButtons)
	{
		echo '<h1>Add Problem</h1>';
		echo '<form id="TsumegoViewForm" method="post" action="/sets/addTsumego/' . $set['Set']['id'] . '" enctype="multipart/form-data">';
		echo '<label for="order">Order:</label>';
		echo '<input type="text" name="order" id="order" value="' . ($tsumegoButtons->highestTsumegoOrder + 1) . '" placeholder="Order" />';
		echo '<input type="hidden" id="set_id" value="' . $set['Set']['id'] . '">';
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
