<?php

/**
 * One run on the results: its outcome and every problem of it.
 *
 * @var \App\View\AppView $this
 * @var array $session A session as TimeModeController::exportSessionToShow() makes it
 * @var bool $isCurrent The run just finished, or opened from the history, rather than the best one
 */

$color = $session['status'] == 'passed' ? 'var(--color-green)' : 'var(--feedback-error)';
?>
<tr>
	<td colspan="5">
		<h4 style="color:<?php echo $color; ?>;"><?php echo ($isCurrent ? 'Result' : 'Best') . ': ' . $session['status'] . '(' . $session['solvedCount'] . '/' . count($session['attempts']) . ')'; ?><?php if ($isCurrent): ?>- <?php echo $session['points']; ?> points<?php endif; ?></h4>
	</td>
</tr>
<?php foreach ($session['attempts'] as $attempt):
	$color = $attempt['status'] == 'solved' ? 'var(--color-green)' : 'var(--feedback-error)';
?>
<tr>
	<td width="9%">#<?php echo $attempt['order']; ?></td>
	<td width="46%"><a href="/tsumegos/play/<?php echo $attempt['tsumego_id']; ?>"><?php echo $attempt['set'] . ' - ' . $attempt['set_order']; ?></a></td>
	<td width="7%" style="color:<?php echo $color; ?>;"><?php echo $attempt['status']; ?></td>
	<td width="8%" style="color:<?php echo $color; ?>;"><?php echo $attempt['seconds']; ?></td>
	<td><?php echo $attempt['points']; ?> points</td>
</tr>
<?php endforeach; ?>
