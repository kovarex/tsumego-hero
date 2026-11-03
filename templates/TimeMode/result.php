<?php

use App\Utility\Util;
use App\Utility\ViteManifest;

/**
 * @var \App\View\AppView $this
 * @var array $sessionsToShow By category and rank: the 'current' run, the 'best' one and the 'history' of the runs
 * @var array|null $unlock
 * @var string $rankArrowClosed
 * @var string $rankArrowOpened
 */

?>
	<div align="center">
	<h2>Time Mode Results</h2>
	<br>
	<div align="center">
		<a class="btn btn--primary" href="/timeMode/overview">Select</a>
		<a class="btn btn--inactive" href="#">Results</a>
	</div>
	<br><br>
	<?php if ($unlock): ?>
		<label>
		<input type="checkbox" class="toast__toggle" id="alertCheckbox" autocomplete="off">
		<div class="toast" id="time-rank-unlock-alert">
			<div class="toast__banner" align="center">Unlocked<span class="toast__close">x</span></div>
			<span class="toast__body">
		<a style="color:var(--color-black);text-decoration:none;" href="/timeMode/overview"><img id="hpIcon1" src="/img/rankButton<?php echo $unlock['rank']; ?>.png">
		  You unlocked the <?php echo $unlock['rank']; ?> <?php echo $unlock['category']; ?> rank.
		</a><br>
		<br class="clear1"/></span>
		</div>
		</label>
	<?php endif; ?>
	<table class="timeModeTable" border="0">
	<?php foreach ($sessionsToShow as $categoryToShow): ?>
		<?php foreach ($categoryToShow as $rankToShow):
			$isOpen = isset($rankToShow['current']);
			// the row of the rank is the best run, or the run just finished when it has no other
			$headerSession = $rankToShow['best'] ?? $rankToShow['current'];
		?>
		<tr>
			<td>
				<div class="tScoreTitle <?php echo $headerSession['status'] == 'passed' ? 'tScoreTitle1' : 'tScoreTitle2'; ?>" id="title<?php echo $headerSession['id']; ?>">
					<table class="timeModeTable2" width="100%" border="0">
						<tr>
							<td width="9%"><?php echo $headerSession['category']; ?></td>
							<td width="46%"><?php echo $headerSession['rank']; ?></td>
							<td width="15%"><b><?php echo $headerSession['status']; ?></b></td>
							<td width="13%"><?php echo $headerSession['points']; ?> points</td>
							<td class="timeModeTable2td"><time datetime="<?php echo Util::toIso8601($headerSession['created']); ?>" data-format="datetime"><?php echo $headerSession['created']; ?></time></td>
							<td width="3%" class="timeModeTable2td"><img class="rankArrow" src="<?php echo $isOpen ? $rankArrowOpened : $rankArrowClosed; ?>"></td>
						</tr>
					</table>
				</div>
				<div class="timeModeTable3" width="100%" style="display: <?php echo $isOpen ? '' : 'none'; ?>;" id="results_<?php echo $headerSession['category']; ?>_<?php echo $headerSession['rank']; ?>">
					<table width="100%" class="scoreTable" border="0">
					<?php
					if (isset($rankToShow['best']))
						echo $this->element('TimeMode/result_session', ['session' => $rankToShow['best'], 'isCurrent' => false]);
					if (isset($rankToShow['current']))
						echo $this->element('TimeMode/result_session', ['session' => $rankToShow['current'], 'isCurrent' => true]);
					?>
					<?php if (isset($rankToShow['history'])):
						$history = $rankToShow['history'];
						$shownSessionIDs = array_column(array_filter([$rankToShow['best'] ?? null, $rankToShow['current'] ?? null]), 'id');
					?>
						<tr>
							<td colspan="5"><h4>History: <?php echo count($history); ?> runs</h4></td>
						</tr>
						<?php foreach ($history as $index => $run):
							$color = $run['status'] == 'passed' ? 'var(--color-green)' : 'var(--feedback-error)';
						?>
						<tr<?php echo in_array($run['id'], $shownSessionIDs) ? ' style="font-weight:bold;"' : ''; ?>>
							<td width="9%">#<?php echo count($history) - $index; ?></td>
							<td width="46%"><a href="/timeMode/result/<?php echo $run['id']; ?>?history=1"><time datetime="<?php echo Util::toIso8601($run['created']); ?>" data-format="datetime"><?php echo $run['created']; ?></time></a></td>
							<td width="7%" style="color:<?php echo $color; ?>;"><?php echo $run['status']; ?></td>
							<td width="8%" style="color:<?php echo $color; ?>;"><?php echo $run['solvedCount'] . '/' . $run['attemptCount']; ?></td>
							<td><?php echo $run['points']; ?> points</td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</table>
				</div>
			</td>
		</tr>
		<?php endforeach; ?>
	<?php endforeach; ?>
	</table>
	<br>
	</div>
	<?php echo ViteManifest::legacyScript('time-mode'); ?>
	<script>
		initTimeModeResult(<?php echo json_encode([
			'rankArrowOpened' => $rankArrowOpened,
			'rankArrowClosed' => $rankArrowClosed,
			'unlock' => (bool) $unlock,
		], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
	</script>
