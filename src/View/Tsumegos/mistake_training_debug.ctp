<?php

use App\Utility\Feature;
use App\Utility\MistakeTraining;

/**
 * Mistake Training - admin toolbox for trying the training without waiting.
 * Served only in debug mode, and only to admins.
 *
 * @var array $poolRows
 * @var int $totalInTraining
 * @var int $importCandidates
 * @var string $flagState
 * @var string $notice
 * @var int[] $ladder
 */
$reviewResults = [
	MistakeTraining::REVIEW_SOLVED => 'solved',
	MistakeTraining::REVIEW_MISSED => 'missed, still due',
	MistakeTraining::REVIEW_GRADUATED => 'graduated',
];
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
		<a href="<?php echo MistakeTraining::$QUEUE_LINK; ?>">Back to the training</a>
	</p>
	<p class="hint">
		Paste the address of a problem, or just the number from it. The internal tsumego id is nowhere on
		the site, so the number a person can see is the one to use, and any set that holds the problem works.
	</p>
	<?php if (!empty($notice)): ?>
	<p class="hint" style="margin-bottom: var(--space-3);"><b><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></b></p>
	<?php endif; ?>

	<h3>Flag</h3>
	<p class="hint">Off hides the menu entry, the training page and its changelog entry. This page stays reachable in any state.</p>
	<form method="post" action="<?php echo MistakeTraining::$DEBUG_LINK; ?>">
		<input type="hidden" name="command" value="flag">
		<?php foreach ([Feature::OFF, Feature::ADMINS, Feature::EVERYONE] as $state): ?>
		<button type="submit" name="state" value="<?php echo $state; ?>" class="btn btn--small" style="margin-right: var(--space-2);">
			Turn <?php echo $state; ?><?php echo $flagState === $state ? ' (now)' : ''; ?>
		</button>
		<?php endforeach; ?>
	</form>

	<h3 style="margin-top: var(--space-4);">Let time pass</h3>
	<p class="hint">Acts as if whole days passed: every review below comes that many days closer, and what was solved today belongs to an earlier day. Rungs stay, so this is a shortcut for time passing, not for how well a problem is known.</p>
	<?php foreach ([[1, 'Next day'], [7, 'A week later'], [30, 'A month later']] as [$days, $label]): ?>
	<form method="post" action="<?php echo MistakeTraining::$DEBUG_LINK; ?>" style="display: inline-block; margin-right: var(--space-2);">
		<input type="hidden" name="command" value="shift">
		<input type="hidden" name="days" value="<?php echo $days; ?>">
		<button type="submit" class="btn btn--small"><?php echo $label; ?></button>
	</form>
	<?php endforeach; ?>

	<h3 style="margin-top: var(--space-4);">Add a problem</h3>
	<p class="hint">Arrives due now at the bottom rung, with no lapses. A problem already in training keeps its rung and its lapses.</p>
	<form method="post" action="<?php echo MistakeTraining::$DEBUG_LINK; ?>">
		<input type="hidden" name="command" value="add">
		<label>Problem <input type="text" name="problem" placeholder="https://tsumego.com/29358" style="width: 18em;"></label>
		<button type="submit" class="btn btn--small">Add as due now</button>
	</form>

	<h3 style="margin-top: var(--space-4);">Jump to a rung</h3>
	<p class="hint">Puts the problem on that rung and makes it due now, so a review months away can be played without waiting for it. Its lapses stay.</p>
	<form method="post" action="<?php echo MistakeTraining::$DEBUG_LINK; ?>">
		<input type="hidden" name="command" value="rung">
		<label>Problem <input type="text" name="problem" placeholder="https://tsumego.com/29358" style="width: 18em;"></label>
		<label>Rung <input type="number" name="rung" value="0" min="0" max="<?php echo count($ladder) - 1; ?>" style="width: 5em;"></label>
		<button type="submit" class="btn btn--small">Set and make due</button>
	</form>

	<h3 style="margin-top: var(--space-4);">Import past mistakes</h3>
	<p class="hint">The newest solved-with-mistakes attempts of this account, skipping what is already in training, all due at once.</p>
	<form method="post" action="<?php echo MistakeTraining::$DEBUG_LINK; ?>">
		<input type="hidden" name="command" value="import">
		<label>How many <input type="number" name="limit" value="<?php echo MistakeTraining::IMPORT_LIMIT; ?>" min="1" style="width: 6em;"></label>
		<button type="submit" class="btn btn--small">Import</button>
	</form>

	<h3 style="margin-top: var(--space-4);">Empty the training</h3>
	<p class="hint">Removes my rows only. The import offer comes back, because its problems count as unimported again.</p>
	<form method="post" action="<?php echo MistakeTraining::$DEBUG_LINK; ?>">
		<input type="hidden" name="command" value="reset">
		<button type="submit" class="btn btn--small btn--danger">Remove every problem</button>
	</form>

	<h3 style="margin-top: var(--space-4);">My pool</h3>
	<p class="hint">The problems a player would see in the training. The number opens the problem now, as if today were its review day; <b>graduate</b> takes it through the last rung and out.</p>
	<?php if (empty($poolRows)): ?>
	<p class="hint">Nothing in training.</p>
	<?php else: ?>
	<table>
		<tr><th>Problem</th><th>Rung</th><th>Lapses</th><th>Next review</th><th>Last review</th><th>Simulate</th></tr>
		<?php foreach ($poolRows as $row): ?>
		<?php $graduated = $row['next_due'] === null; ?>
		<tr>
			<td>
				<?php if ($graduated): ?>
				<?php echo (int) $row['set_connection_id'] ?: (int) $row['tsumego_id']; ?>
				<?php elseif (!empty($row['set_connection_id'])): ?>
				<a href="<?php echo MistakeTraining::$DEBUG_LINK; ?>/play/<?php echo (int) $row['set_connection_id']; ?>"
					title="Make it due now and open it in the training"><?php echo (int) $row['set_connection_id']; ?></a>
				<?php else: ?>
				<?php echo (int) $row['tsumego_id']; ?> (no set)
				<?php endif; ?>
			</td>
			<td><?php echo (int) $row['rung']; ?></td>
			<td><?php echo (int) $row['lapses']; ?></td>
			<td><?php echo $graduated ? 'graduated' : htmlspecialchars((string) $row['next_due'], ENT_QUOTES, 'UTF-8'); ?></td>
			<td><?php echo htmlspecialchars(trim($row['last_review_day'] . ' ' . ($reviewResults[(string) $row['last_review_result']] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
			<td>
				<?php if ($graduated): ?>
				<form method="post" action="<?php echo MistakeTraining::$DEBUG_LINK; ?>">
					<input type="hidden" name="command" value="fail">
					<input type="hidden" name="tsumego_id" value="<?php echo (int) $row['tsumego_id']; ?>">
					<button type="submit" class="btn btn--small">bring back</button>
				</form>
				<?php else: ?>
				<?php foreach ([['solve', 'clean solve'], ['fail', 'mistake'], ['graduate', 'graduate']] as [$command, $label]): ?>
				<form method="post" action="<?php echo MistakeTraining::$DEBUG_LINK; ?>" style="display: inline-block; margin-right: var(--space-1);">
					<input type="hidden" name="command" value="<?php echo $command; ?>">
					<input type="hidden" name="tsumego_id" value="<?php echo (int) $row['tsumego_id']; ?>">
					<button type="submit" class="btn btn--small"><?php echo $label; ?></button>
				</form>
				<?php endforeach; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
	<?php endif; ?>
</div>
