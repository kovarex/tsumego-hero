<?php

namespace App\Utility;

class SetEditRenderer
{
	public static function renderAddProblemForm(int $setId, int $highestOrder): void
	{
		echo '<h2 class="set-edit__heading">Add Problem</h2>';
		echo '<form id="TsumegoViewForm" method="post" action="/sets/createAndAddTsumego/' . $setId . '" enctype="multipart/form-data">';
		echo '<div class="form-field"><label class="form-field__label" for="order">Order:</label>';
		echo '<input type="text" name="order" id="order" value="' . ($highestOrder + 1) . '" placeholder="Order" /></div>';
		echo '<input type="hidden" id="set_id" value="' . $setId . '">';
		echo '<div class="form-field"><label class="form-field__label" for="add-problem-file">SGF (choose one, or none for onsite edit):</label>';
		echo '<input type="file" id="add-problem-file" name="adminUpload" accept=".sgf"></div>';
		echo '<div class="form-field"><label class="form-field__label" for="sgf">or paste SGF:</label>';
		echo '<input id="sgf" class="form-field__control" type="textarea" placeholder="(;GM[1]FF[4]...)" rows="3"></div>';
		echo '<input type="submit" class="btn btn--sm btn--primary" value="Add" />';
		echo '</form>';
	}
}
