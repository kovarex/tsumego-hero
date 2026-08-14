<?php

/**
 * @var View $this
 * @var SimilarSearchResult $result
 * @var int $sourceMoveCount
 * @var string $sourceSetName
 * @var TsumegoButton $sourceTsumegoButton
 */

?>
<div style="text-align:center;">
	<p class="title">
		Similar problem search:
	<p>Search took: <?php echo round($result->elapsed, 1); ?> seconds</p>
</div>
<table>
	<thead><th>Difference</th><th>Preview</th><th>Moves</th><th>Merge</th></th><th>Problem</th></thead>
	<tr>
		<td><b>Source</b></td>
		<td id="previewMaster"><span></span></td>
		<td style="text-align:right"><?php echo $sourceMoveCount ?></td>
		<td></td>
		<td>
			<div style="display:flex;align-items: center">
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
			echo '<td style="text-align:right">' . $item->moveCount . '</td>';
			echo '<td>';
			if (Auth::isAdmin())
			{
				echo '  <form action="/tsumegos/mergeFinalForm" method="post">';
				echo '    <input type="hidden" name="master-id" id="master-id" value="' . $sourceTsumegoButton->setConnectionID . '">';
				echo '    <input type="hidden" name="slave-id" id="slave-id" value="' . $item->tsumegoButton->setConnectionID . '">';
				echo '    <input type="submit" value="Start merge" id="submit">';
				echo '  </form>';
			}
			else
				echo '(Only admins)';
			echo '</td>';
			echo '<td><div style="display:flex;align-items: center">';
			$item->tsumegoButton->render();
			echo '&nbsp;&nbsp;';
			echo h($item->title) . '</div></td>';
			echo '</tr>' . PHP_EOL;
		}
		if (empty($result->items))
			echo 'No problems found.';
echo '</table>';
echo '<script>';
echo 'document.querySelectorAll("a[data-sgf-preview]").forEach(function(a) {';
echo '  var td = a.closest("tr").querySelector("td[id^=preview]");';
echo '  if (!td) return;';
echo '  var data = JSON.parse(a.dataset.sgfPreview);';
echo '  createBoard(td, data.black, data.white, data.xMax, data.yMax, data.boardSize, data.diff || "");';
echo '});';
echo '</script>';
