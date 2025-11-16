<?php

class TsumegoXPAndRating {
	public function __construct(array $tsumego, string $status) {
		if ($status == 'G') {
			$this->goldenTsumego = true;
		} elseif ($status == 'S') {
			$this->solved = true;
		} elseif ($status == 'W') {
			$this->resolving = true;
		}
		$this->tsumegoRating = $tsumego['rating'];
		$this->progressDeletionCount = TsumegoUtil::getProgressDeletionCount($tsumego);
	}

	public function render() {
		echo '<div align="center" id="xpDisplayDiv">
		<table class="xpDisplayTable" border="0" width="70%">
			<tr>
			<td style="width:33%;">';
		echo '
	<div id="xpDisplay">
		<span id="xpDisplayText"></span>
		<span id="ratingHeader"></span>
		<div class="eloTooltip"><span id="ratingGainShort"></span><span class="eloTooltiptext" id="ratingGainLong"></span></div>
		<span id="ratingSeparator"></span><div class="eloTooltip"><span id="ratingLossShort"></span><span class="eloTooltiptext" id="ratingLossLong"></span></div>
	</div>';
		if (Auth::isInTimeMode()) {
			echo '<div id="time-mode-countdown">10.0</div><div id="plus2">+2</div>';
		}
		echo '</td>
			<td style="width:33%;">
				<div id="status" align="center" style="color:black;"></div>
			</td>
			<td style="width:33%;">
				<div id="status2" align="center" style="color:black;">
				<font size="4">
				' . Rating::getReadableRankFromRating($this->tsumegoRating) . ' <font color="grey">(' . $this->tsumegoRating . ')</font>
				</font>
				</div>
			</td>
			</tr>
		</table>
	</div>';
	}

	public function renderJavascript() {
		if (!Auth::isLoggedIn()) {
			return;
		}
		echo '
	let xpStatus = new XPStatus(
	{
		solved: ' . Util::boolString($this->solved) . ',
		sprintRemainingSeconds: ' . HeroPowers::getSprintRemainingSeconds() . ',
		sprintMultiplier: ' . Constants::$SPRINT_MULTIPLIER . ',
		goldenTsumego: ' . Util::boolString($this->goldenTsumego) . ',
		goldenTsumegoMultiplier: ' . Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER . ',
		resolving: ' . Util::boolString($this->resolving) . ',
		resolvingMultiplier: ' . Constants::$RESOLVING_MULTIPLIER . ',
		userRating: ' . Auth::getUser()['rating'] . ',
		tsumegoRating: ' . $this->tsumegoRating . ',
		progressDeletionCount: ' . $this->progressDeletionCount .'
	});
	xpStatus.update();
';
	}

	// changes here must be reflected in the same method in util.js
	public static function getProgressDeletionMultiplier($progressDeletionCount): float {
		if ($progressDeletionCount == 0) {
			return 1;
		}
		if ($progressDeletionCount == 1) {
			return 0.5;
		}
		if ($progressDeletionCount == 2) {
			return 0.2;
		}
		if ($progressDeletionCount == 3) {
			return 0.1;
		}
		return 0.01;
	}

	public bool $solved = false;
	public bool $goldenTsumego = false;
	public bool $resolving = false;

	public float $tsumegoRating;
	public float $progressDeletionCount;
}
