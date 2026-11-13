<?php

use App\Utility\Auth;
use App\Utility\SimilarSearchResult;
use App\Utility\TsumegoButton;
use App\Utility\ViteManifest;

/**
 * @var \App\View\AppView $this
 * @var SimilarSearchResult $result
 * @var int $sourceMoveCount
 * @var string $sourceSetName
 * @var TsumegoButton $sourceTsumegoButton
 */

?>
<div class="text-center">
	<p class="title">
		Similar problem search:
	<p>Search took: <?php echo round($result->elapsed, 1); ?> seconds</p>
</div>
<table class="data-table data-table--compact phone-scroll-x">
	<thead><tr><th>Difference</th><th>Preview</th><th class="data-table__num">Moves</th><th>Merge</th><th>Problem</th></tr></thead>
	<tr>
		<td><b>Source</b></td>
		<td id="previewMaster"><span></span></td>
		<td class="text-right"><?php echo $sourceMoveCount ?></td>
		<td></td>
		<td>
			<div class="duplicate-search__problem">
			<?php
				$sourceTsumegoButton->render();
				echo '&nbsp;&nbsp;';
				echo h($sourceSetName);
				?>
			</div>
		</td>
	</tr>
	<?php
		foreach ($result->items as $item)
		{
			echo '<tr>';
			echo '<td>' . $item->difference . '</td>';
			echo '<td id="preview' . $item->tsumegoButton->setConnectionID . '"><span></span></td>';
			echo '<td class="text-right">' . $item->moveCount . '</td>';
			echo '<td>';
			if (Auth::isAdmin())
			{
				echo '  <form action="/tsumegos/mergeFinalForm" method="post">';
				echo '    <input type="hidden" name="master-id" id="master-id" value="' . $sourceTsumegoButton->setConnectionID . '">';
				echo '    <input type="hidden" name="slave-id" id="slave-id" value="' . $item->tsumegoButton->setConnectionID . '">';
				echo '    <input type="submit" class="btn btn--sm btn--primary" value="Start merge" id="submit">';
				echo '  </form>';
			}
			else
				echo '(Only admins)';
			echo '</td>';
			echo '<td><div class="duplicate-search__problem">';
			$item->tsumegoButton->render();
			echo '&nbsp;&nbsp;';
			echo h($item->title) . '</div></td>';
			echo '</tr>' . PHP_EOL;
		}
		if (empty($result->items))
			echo 'No problems found.';
echo '</table>';
echo ViteManifest::legacyScript('problem-admin');
