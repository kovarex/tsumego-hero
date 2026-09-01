<?php

/**
 * @var View $this
 * @var AdminActivityRenderer $adminActivityRenderer
 * @var bool $refreshView
 * @var array $requestDeletion
 * @var array $set
 * @var SGFProposalsRenderer $sgfProposalsRenderer
 * @var TagConnectionProposalsRenderer $tagConnectionProposalsRenderer
 * @var array $tagNames
 * @var TagProposalsRenderer $tagProposalsRenderer
 */

	echo '<div class="split split--sidebar">';
	echo '<div style="text-align:left;border-right:1px solid var(--surface-border-light);flex:0 0 calc(60% - 6px)">';
		$sgfProposalsRenderer->render();
		$tagProposalsRenderer->render();
		if($requestDeletion!=null){
			echo '<table border="0">';
			for($i=0; $i<count($requestDeletion); $i++){
				echo '<tr>';
				echo '<td>'.h($requestDeletion[$i]['User']['name']).' has requested account deletion.</td>';
				echo '<td><a class="btn btn--neutral" id="delete-user-'.($i+1).'" href="/users/adminstats?delete='.($requestDeletion[$i]['User']['id']*1111)
				.'&hash='.md5($requestDeletion[$i]['User']['name']).'" onclick="return confirm(\'Are you sure you want to delete this account?\');">Delete Account</a></td>';
				echo '</tr>';
			}
			echo '</table><hr>';
		}
		$tagConnectionProposalsRenderer->render();
	echo '</div>';

	echo '<div style="flex:0 0 calc(40% - 6px)">';
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
