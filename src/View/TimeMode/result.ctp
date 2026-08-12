<?php

/**
 * @var View $this
 * @var array $categories
 * @var array $ranks
 * @var array $bestByKey
 * @var array|null $finishedSession
 * @var array $attemptsBySession
 * @var array $unlock
 */

?>
	<div align="center">
	<h2>Time Mode Results</h2>
	<br>
	<div align="center">
		<a class="new-button" href="/timeMode/overview">Select</a>
		<a class="new-button-inactive" href="#">Results</a>
	</div>
	<br><br>
	<?php if($unlock) { ?>
		<label>
		<input type="checkbox" class="alertCheckbox1" id="alertCheckbox" autocomplete="off">
		<div class="alertBox alertInfo" id="time-rank-unlock-alert">
			<div class="alertBanner" align="center">Unlocked<span class="alertClose">x</span></div>
			<span class="alertText">
		<a style="color:black;text-decoration:none;" href="/timeMode/overview"><img id="hpIcon1" src="/img/rankButton<?php echo $unlock['rank']; ?>.png">
		  You unlocked the <?php echo $unlock['rank'];?> <?php echo $unlock['category']; ?> rank.
		</a><br>
		<br class="clear1"/></span>
		</div>
		</label><?php
	} ?>
	<table class="timeModeTable" border="0">
	<?php
		$finishedId = $finishedSession ? (int) $finishedSession['TimeModeSession']['id'] : 0;
		foreach ($categories as $cat):
			$catId = $cat['TimeModeCategory']['id'];
			$catName = $cat['TimeModeCategory']['name'];
			foreach ($ranks as $rank):
				$rankId = $rank['TimeModeRank']['id'];
				$rankName = $rank['TimeModeRank']['name'];
				$key = $catId . '-' . $rankId;

				$isCurrent = $finishedSession
					&& $finishedSession['TimeModeSession']['time_mode_category_id'] == $catId
					&& $finishedSession['TimeModeSession']['time_mode_rank_id'] == $rankId;

				$best = $bestByKey[$key] ?? null;
				if ($best && $isCurrent && (int) $best['id'] === $finishedId)
					$best = null; // best is same session as current, don't show twice

				if (!$best && !$isCurrent)
					continue;

				$header = $best ?? $finishedSession['TimeModeSession'];
				$headerStatus = (int) $header['time_mode_session_status_id'] === TimeModeUtil::$SESSION_STATUS_SOLVED ? 'passed' : 'failed';
				$boxHighlight = $headerStatus === 'passed' ? 'tScoreTitle1' : 'tScoreTitle2';
				$arrowSrc = $isCurrent ? '/img/greyArrow2.png' : '/img/greyArrow1.png';
				$displayStyle = $isCurrent ? '' : 'none';
		?>
		<tr>
			<td>
				<div class="tScoreTitle <?php echo $boxHighlight; ?>" id="title<?php echo $header['id']; ?>" onclick="toggleRelatedContent(this);">
					<table class="timeModeTable2" width="100%" border="0">
						<tr>
							<td width="9%"><?php echo h($catName); ?></td>
							<td width="46%"><?php echo h($rankName); ?></td>
							<td width="15%"><b><?php echo $headerStatus; ?></b></td>
							<td width="13%"><?php echo (int) $header['points']; ?> points</td>
							<td class="timeModeTable2td"><?php echo (new DateTime($header['created']))->format('H:i d.m.Y'); ?></td>
							<td width="3%" class="timeModeTable2td"><img class="rankArrow" src="<?php echo $arrowSrc; ?>"></td>
						</tr>
					</table>
				</div>
				<div class="timeModeTable3" width="100%" style="display: <?php echo $displayStyle; ?>;" id="results_<?php echo $catName; ?>_<?php echo $rankName; ?>">
					<table width="100%" class="scoreTable" border="0">
						<?php if ($best):
							$bestAttempts = $attemptsBySession[(int) $best['id']] ?? [];
							$bestSolved = count(array_filter($bestAttempts, fn($a) => (int) $a['time_mode_attempt_status_id'] === TimeModeUtil::$ATTEMPT_RESULT_SOLVED));
							$bestStatus = (int) $best['time_mode_session_status_id'] === TimeModeUtil::$SESSION_STATUS_SOLVED ? 'passed' : 'failed';
							$bestColor = $bestStatus === 'passed' ? 'green' : '#e03c4b';
						?>
						<tr>
							<td colspan="5">
								<h4 style="color:<?php echo $bestColor; ?>;">Best: <?php echo $bestStatus; ?>(<?php echo $bestSolved; ?>/<?php echo TimeModeUtil::$PROBLEM_COUNT; ?>)</h4>
							</td>
						</tr>
						<?php foreach ($bestAttempts as $a):
							$astatus = TimeModeUtil::attemptStatusName((int) $a['time_mode_attempt_status_id']);
							$acolor = $astatus === 'solved' ? 'green' : '#e03c4b';
							$secs = (float) $a['seconds'];
							$min = floor($secs / 60);
							$secs -= $min * 60;
							$label = $a['set_title'] . ($a['set_title2'] ? ' ' . $a['set_title2'] : '');
						?>
						<tr>
							<td width="9%">#<?php echo $a['order']; ?></td>
							<td width="46%"><a class="tooltip" data-tsumego-id="<?php echo $a['tsumego_id']; ?>" href="/tsumegos/play/<?php echo $a['tsumego_id']; ?>"><?php echo h($label); ?> - <?php echo $a['set_num']; ?><span class="tooltip-box"></span></a></td>
							<td width="7%" style="color:<?php echo $acolor; ?>;"><?php echo $astatus; ?></td>
							<td width="8%" style="color:<?php echo $acolor; ?>;"><?php echo $min; ?> : <?php echo number_format($secs, 2); ?></td>
							<td><?php echo (int) $a['points']; ?> points</td>
						</tr>
						<?php endforeach; ?>
						<?php endif; ?>

						<?php if ($isCurrent):
							$currentAttempts = $attemptsBySession[$finishedId] ?? [];
							$currentSolved = count(array_filter($currentAttempts, fn($a) => (int) $a['time_mode_attempt_status_id'] === TimeModeUtil::$ATTEMPT_RESULT_SOLVED));
							$currentStatus = (int) $finishedSession['TimeModeSession']['time_mode_session_status_id'] === TimeModeUtil::$SESSION_STATUS_SOLVED ? 'passed' : 'failed';
							$currentColor = $currentStatus === 'passed' ? 'green' : '#e03c4b';
							$currentPoints = (int) $finishedSession['TimeModeSession']['points'];
						?>
						<tr>
							<td colspan="5">
								<h4 style="color:<?php echo $currentColor; ?>;">Result: <?php echo $currentStatus; ?>(<?php echo $currentSolved; ?>/<?php echo TimeModeUtil::$PROBLEM_COUNT; ?>)- <?php echo $currentPoints; ?> points</h4>
							</td>
						</tr>
						<?php foreach ($currentAttempts as $a):
							$astatus = TimeModeUtil::attemptStatusName((int) $a['time_mode_attempt_status_id']);
							$acolor = $astatus === 'solved' ? 'green' : '#e03c4b';
							$secs = (float) $a['seconds'];
							$min = floor($secs / 60);
							$secs -= $min * 60;
							$label = $a['set_title'] . ($a['set_title2'] ? ' ' . $a['set_title2'] : '');
						?>
						<tr>
							<td width="9%">#<?php echo $a['order']; ?></td>
							<td width="46%"><a class="tooltip" data-tsumego-id="<?php echo $a['tsumego_id']; ?>" href="/tsumegos/play/<?php echo $a['tsumego_id']; ?>"><?php echo h($label); ?> - <?php echo $a['set_num']; ?><span class="tooltip-box"></span></a></td>
							<td width="7%" style="color:<?php echo $acolor; ?>;"><?php echo $astatus; ?></td>
							<td width="8%" style="color:<?php echo $acolor; ?>;"><?php echo $min; ?> : <?php echo number_format($secs, 2); ?></td>
							<td><?php echo (int) $a['points']; ?> points</td>
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
	<script>
		function toggleRelatedContent(div) {
			let parentOfDiv = div.parentElement;
			let content = parentOfDiv.querySelector('.timeModeTable3');
			let rankArrow = div.querySelector('.rankArrow');
			if (content.style.display === 'none' || getComputedStyle(content).display === 'none') {
				content.style.display = '';
				rankArrow.setAttribute('src', '/img/greyArrow2.png');
			}
			else {
				content.style.display = 'none';
				rankArrow.setAttribute('src', '/img/greyArrow1.png');
			}
		}
		$(document).ready(function() {
			$("#account-bar-user2 a").css("color", "rgb(202, 102, 88)");
			$("#xp-bar-fill").attr("class", "xp-bar-fill-c3");
			$("#xp-bar-fill").removeClass("xp-bar-fill-c2");
			$("#xp-bar-fill").removeClass("xp-bar-fill-c1");
			$("#account-bar-user a").attr("class", "xp-text-fill-c3x");
			$("#modeSelector").hide();
			notMode3 = false;

			bartext = "<?php echo 'some rank to show'; ?>"; // TODO:
			barPercent = "<?php echo '100'; ?>%"; // TODO:

			$("#account-bar-xp").text(bartext);
			$("#account-bar-xp").html(bartext);
			$("#xp-bar-fill").css("width", barPercent);

			<?php if($unlock){ ?>
			$(".alertBox").fadeIn(500);
			<?php } ?>
		});

		$("#alertCheckbox").change(function() {
			$(".alertBox").fadeOut(500);
		});

	</script>
