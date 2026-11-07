<?php

/**
 * One run on the results: its outcome and every problem of it.
 *
 * @var \App\View\AppView $this
 * @var array $session A session as TimeModeController::exportSessionToShow() makes it
 * @var bool $isCurrent The run just finished, or opened from the history, rather than the best one
 */

$outcome = $session['status'] == 'passed' ? 'time-result--passed' : 'time-result--failed';
?>
<tr>
	<td colspan="5">
		<h4 class="<?php echo $outcome; ?>"><?php echo ($isCurrent ? 'Result' : 'Best') . ': ' . $session['status'] . '(' . $session['solvedCount'] . '/' . count($session['attempts']) . ')'; ?><?php if ($isCurrent): ?>- <?php echo $session['points']; ?> points<?php endif; ?></h4>
	</td>
</tr>
<?php foreach ($session['attempts'] as $attempt):
	$outcome = $attempt['status'] == 'solved' ? 'time-result--passed' : 'time-result--failed';
?>
<tr>
	<td class="time-result__order">#<?php echo $attempt['order']; ?></td>
	<td class="time-result__problem"><a href="/tsumegos/play/<?php echo $attempt['tsumego_id']; ?>"><?php echo $attempt['set'] . ' - ' . $attempt['set_order']; ?></a></td>
	<td class="time-result__outcome <?php echo $outcome; ?>"><?php echo $attempt['status']; ?></td>
	<td class="time-result__score <?php echo $outcome; ?>"><?php echo $attempt['seconds']; ?></td>
	<td><?php echo $attempt['points']; ?> points</td>
</tr>
<?php endforeach; ?>
