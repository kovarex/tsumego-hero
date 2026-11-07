<?php

use App\Utility\CommentsRenderer;

/**
 * @var \App\View\AppView $this
 * @var CommentsRenderer $allComments
 * @var CommentsRenderer $yourComments
 */

?>
	<table class="co-table">
	<tr>
	<td class="co-table__half">
		<p class="title4">Comments</p>
		<div class="new1 co-table__all">
			<div class="legacy-center"><?php $allComments->render(); ?></div>
		</div>
	</td>
	<td class="co-table__half">
		<p class="title4">Your Comments</p>
		<div> <?php $yourComments->render(); ?> </div>
	</td>
	</tr>
	</table>