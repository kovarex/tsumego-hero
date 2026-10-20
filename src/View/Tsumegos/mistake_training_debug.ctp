<?php

use App\Utility\Feature;
use App\Utility\MistakeTraining;

/**
 * Mistake Training - admin toolbox: the flag and the ladder. Served only in debug
 * mode, and only to admins.
 *
 * @var int $totalInTraining
 * @var int $importCandidates
 * @var string $flagState
 * @var string $notice
 * @var int[] $ladder
 */
$stats = [
	['Flag', $flagState],
	['In training', (string) $totalInTraining],
	['Not imported', (string) $importCandidates],
];
// The waits of the ladder after each number of lapses, until the floor stops them
// shrinking: the last row holds for every count from there on.
$waitsByLapses = [];
for ($lapses = 0; ; $lapses++)
{
	$waits = array_map(fn(int $rung) => MistakeTraining::wait($rung, $lapses), array_keys($ladder));
	if ($lapses > 0 && $waits === end($waitsByLapses))
		break;
	$waitsByLapses[$lapses] = $waits;
}
?>
<div class="info-box">
	<h2 style="margin-bottom: var(--space-4);">Mistake Training toolbox</h2>
	<div style="display: flex; gap: var(--space-5); flex-wrap: wrap; margin-bottom: var(--space-3);">
		<?php foreach ($stats as [$label, $value]): ?>
		<div>
			<div class="hint" style="font-size: .8em; text-transform: uppercase; letter-spacing: .06em;"><?php echo $label; ?></div>
			<div style="font-size: 1.8em; font-weight: bold; line-height: 1.1;"><?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?></div>
		</div>
		<?php endforeach; ?>
	</div>
	<div style="display: flex; gap: var(--space-4); flex-wrap: wrap; margin-bottom: var(--space-3);">
		<?php foreach ($ladder as $rung => $days): ?>
		<div>
			<div class="hint" style="font-size: .8em; text-transform: uppercase; letter-spacing: .06em;">Rung <?php echo $rung; ?></div>
			<div style="font-size: 1.1em; font-weight: bold; line-height: 1.1;">
				<?php echo $days; ?> <?php echo $days == 1 ? 'day' : 'days'; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<p class="hint">
		The wait until the next review at each rung. A clean solve climbs one rung, a mistake goes back to 0 and keeps a
		due problem due until it is solved.
	</p>
	<p class="hint">
		Every mistake on a problem in the training is also a lapse, at most one a day while the problem is due, and the
		lapses shorten its waits for good:
	</p>
	<table style="margin-bottom: var(--space-3);">
		<tr><th>Lapses</th><th>Waits in days, rung 0 first</th></tr>
		<?php foreach ($waitsByLapses as $lapses => $waits): ?>
		<tr>
			<td><?php echo $lapses; ?><?php echo $lapses === array_key_last($waitsByLapses) ? ' or more' : ''; ?></td>
			<td><?php echo implode(', ', $waits); ?></td>
		</tr>
		<?php endforeach; ?>
	</table>
	<p class="hint">
		<a href="<?php echo MistakeTraining::$OVERVIEW_LINK; ?>">Back to the training</a>
	</p>
	<?php if (!empty($notice)): ?>
	<p class="hint" style="margin-bottom: var(--space-3);"><b><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></b></p>
	<?php endif; ?>

	<h3>Flag</h3>
	<p class="hint">Off hides the homepage card, the training page and its changelog entry. This page stays reachable in any state.</p>
	<form method="post" action="<?php echo MistakeTraining::$DEBUG_LINK; ?>">
		<input type="hidden" name="command" value="flag">
		<?php foreach ([Feature::OFF, Feature::ADMINS, Feature::EVERYONE] as $state): ?>
		<button type="submit" name="state" value="<?php echo $state; ?>" class="btn btn--small" style="margin-right: var(--space-2);">
			Turn <?php echo $state; ?><?php echo $flagState === $state ? ' (now)' : ''; ?>
		</button>
		<?php endforeach; ?>
	</form>

</div>
