<?php

use App\Utility\ChartRenderer;

/**
 * One of the two columns of statistics under the profile, each with a switch of what it shows.
 *
 * @var \App\View\AppView $this
 * @var string $side Left or Right
 * @var array $dailyResults
 * @var array $timeGraph
 */
?>
		<td width="50%">
			<div id="userShowLevel<?php echo $side; ?>">
				<?php echo ChartRenderer::bar('Problems in level mode', array_reverse($dailyResults), 'day', [
					['label' => 'Solves', 'color' => '#74d14c'],
					['label' => 'Fails', 'color' => '#d63a49']]); ?>
			</div>
			<div id="userShowRating<?php echo $side; ?>">
				<?php echo ChartRenderer::ratingLine('Overall rating', $dailyResults); ?>
			</div>
			<div id="userShowTime<?php echo $side; ?>">
				<?php echo ChartRenderer::bar('Time mode runs', $timeGraph, 'category', [
					['label' => 'Passes', 'color' => '#c8723d'],
					['label' => 'Fails', 'color' => '#888888']]); ?>
			</div>
		</td>
