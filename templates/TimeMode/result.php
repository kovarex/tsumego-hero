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
	<div class="legacy-center">
	<h2>Time Mode Results</h2>
	<br>
	<div class="legacy-center">
		<a class="btn btn--pill" href="/timeMode/overview">Select</a>
		<a class="btn btn--pill is-selected" href="#" aria-current="page">Results</a>
	</div>
	<br><br>
	<?php if ($unlock): ?>
		<label>
		<input type="checkbox" class="toast__toggle" id="alertCheckbox" autocomplete="off">
		<div class="toast" id="time-rank-unlock-alert">
			<div class="toast__banner legacy-center">Unlocked<span class="toast__close">x</span></div>
			<span class="toast__body">
		<a class="toast__link" href="/timeMode/overview"><img id="hpIcon1" src="/img/rankButton<?php echo $unlock['rank']; ?>.png">
		  You unlocked the <?php echo $unlock['rank']; ?> <?php echo $unlock['category']; ?> rank.
		</a><br>
		<br class="clear1"/></span>
		</div>
		</label>
	<?php endif; ?>
	<div class="timeModeTable">
	<?php foreach ($sessionsToShow as $categoryToShow): ?>
		<?php foreach ($categoryToShow as $rankToShow):
			$isOpen = isset($rankToShow['current']);
			// the row of the rank is the best run, or the run just finished when it has no other
			$headerSession = $rankToShow['best'] ?? $rankToShow['current'];
		?>
		<div class="time-result__rank-block">
				<div class="tScoreTitle <?php echo $headerSession['status'] == 'passed' ? 'tScoreTitle1' : 'tScoreTitle2'; ?>" id="title<?php echo $headerSession['id']; ?>">
					<div class="timeModeTable2">
							<span class="time-result__category"><?php echo $headerSession['category']; ?></span>
							<span class="time-result__rank"><?php echo $headerSession['rank']; ?></span>
							<span class="time-result__status"><b><?php echo $headerSession['status']; ?></b></span>
							<span class="time-result__points"><?php echo $headerSession['points']; ?> points</span>
							<span class="timeModeTable2td"><time datetime="<?php echo Util::toIso8601($headerSession['created']); ?>" data-format="datetime"><?php echo $headerSession['created']; ?></time></span>
							<span class="time-result__arrow timeModeTable2td"><img class="rankArrow" src="<?php echo $isOpen ? $rankArrowOpened : $rankArrowClosed; ?>" alt=""></span>
					</div>
				</div>
				<div class="timeModeTable3" style="display: <?php echo $isOpen ? '' : 'none'; ?>;" id="results_<?php echo $headerSession['category']; ?>_<?php echo $headerSession['rank']; ?>">
					<table class="scoreTable time-result__runs">
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
							$outcome = $run['status'] == 'passed' ? 'time-result--passed' : 'time-result--failed';
						?>
						<tr<?php echo in_array($run['id'], $shownSessionIDs) ? ' class="time-result__shown"' : ''; ?>>
							<td class="time-result__order">#<?php echo count($history) - $index; ?></td>
							<td class="time-result__problem"><a href="/timeMode/result/<?php echo $run['id']; ?>?history=1"><time datetime="<?php echo Util::toIso8601($run['created']); ?>" data-format="datetime"><?php echo $run['created']; ?></time></a></td>
							<td class="time-result__outcome <?php echo $outcome; ?>"><?php echo $run['status']; ?></td>
							<td class="time-result__score <?php echo $outcome; ?>"><?php echo $run['solvedCount'] . '/' . $run['attemptCount']; ?></td>
							<td><?php echo $run['points']; ?> points</td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</table>
				</div>
		</div>
		<?php endforeach; ?>
	<?php endforeach; ?>
	</div>
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
