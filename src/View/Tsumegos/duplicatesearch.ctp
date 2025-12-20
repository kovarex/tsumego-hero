<div style="text-align:center;">
	<p class="title">
		<br>
		<div style="display:flex;">
			Similar problem search for <?php echo $title; ?>
			<?php $sourceTsumegoButton->render(); ?>
		</div>
	</p>
</div>
<table>
	<thead><th>Difference</th><th>Problem</th></thead>
	<?php
		foreach ($result->items as $item)
		{
			echo '<tr>';
			echo '<td>' . $item->difference . '</td>';
			echo '<td><div style="display:flex;">';
			$item->tsumegoButton->render();
			echo $item->title . '</div></td>';
			echo '</tr>' . PHP_EOL;
		}
		if (empty($result))
			echo 'No problems found.';
	?>
</table>
