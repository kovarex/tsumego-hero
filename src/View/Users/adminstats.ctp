<?php

use App\Utility\AdminActivityRenderer;
use App\Utility\SGFProposalsRenderer;
use App\Utility\TagConnectionProposalsRenderer;
use App\Utility\TagProposalsRenderer;
use App\Utility\Util;

/**
 * @var View $this
 * @var AdminActivityRenderer $adminActivityRenderer
 * @var bool $refreshView
 * @var array $deletedUsers
 * @var array $set
 * @var SGFProposalsRenderer $sgfProposalsRenderer
 * @var TagConnectionProposalsRenderer $tagConnectionProposalsRenderer
 * @var array $tagNames
 * @var TagProposalsRenderer $tagProposalsRenderer
 */

	echo '<div class="split">';
	echo '<div style="text-align:left;flex:3 1 0;min-width:min-content">';

		$sgfProposalsRenderer->render();
		echo '<hr>';
		$tagProposalsRenderer->render();
		echo '<hr>';
		if ($deletedUsers) {
			echo '<table class="data-table data-table--compact">';
			echo '<caption>Deleted Accounts (' . count($deletedUsers) . ')</caption>';
			foreach ($deletedUsers as $deletedUser) {
				echo '<tr>';
				echo '<td><a href="/users/view/' . $deletedUser['User']['id'] . '">' . h($deletedUser['User']['deleted_name']) . '</a></td>';
				echo '<td><time datetime="' . Util::toIso8601($deletedUser['User']['deleted']) . '" data-format="datetime">' . $deletedUser['User']['deleted'] . '</time></td>';
				echo '<td><form method="post" action="/users/restore_account/' . $deletedUser['User']['id'] . '" onsubmit="return confirm(\'Restore this account?\');">'
					. '<button type="submit" class="btn btn--small" id="restore-user-' . $deletedUser['User']['id'] . '">Restore</button></form></td>';
				echo '</tr>';
			}
			echo '</table>';
			echo '<hr>';
		}
		$tagConnectionProposalsRenderer->render();
	echo '</div>';

	echo '<div style="flex:2 1 0;min-width:min-content">';

	$adminActivityRenderer->render();
	echo '</div>';
	echo '</div>';
?>

<script>
	var tooltipSgfs = window.tooltipSgfs || [];
	let tagList = "null";
	let proposalList = "null";
	let submitCount = 0;

	<?php if($refreshView) echo 'window.location.href = "/sets/view/'.$set['Set']['id'].'";'; ?>

	<?php
		for($h=0; $h<count($tagNames); $h++){
			echo '$("#tagname-accept'.$h.'").click(function() {
				$("#tagname-submit'.$h.'").show();
				$("#tagname-accept'.$h.'").hide();
				$("#tagname-reject'.$h.'").hide();
				submitCount++;
				$(".tag-submit-button").html("Submit ("+submitCount+")");
			});';
			echo '$("#tagname-reject'.$h.'").click(function() {
				$("#tagname-submit'.$h.'").show();
				$("#tagname-accept'.$h.'").hide();
				$("#tagname-reject'.$h.'").hide();
				submitCount++;
				$(".tag-submit-button").html("Submit ("+submitCount+")");
			});';
		}
	?>
</script>
