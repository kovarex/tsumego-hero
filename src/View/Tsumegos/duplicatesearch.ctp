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
				echo $sourceSetName;
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
			echo $item->title . '</div></td>';
			echo '</tr>' . PHP_EOL;
		}
		if (empty($result))
			echo 'No problems found.';
echo '</table>';
echo '<script>';
echo $sourceTsumegoButton->createBoard('document.getElementById(\'previewMaster\')', 'createBoard');
foreach ($result->items as $item)
	echo $item->tsumegoButton->createBoard('document.getElementById(\'preview' . $item->tsumegoButton->setConnectionID . '\')', 'createBoard', $item->diff);
echo '</script>';
