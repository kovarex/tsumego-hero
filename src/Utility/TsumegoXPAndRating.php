<?php

class TsumegoXPAndRating {
	public function __construct(array $tsumego, string $status) {
		$this->baseXP = TsumegoUtil::getXpValue($tsumego);
		if ($status == 'G') {
			$this->goldenTsumego = true;
		} elseif ($status == 'S') {
			$this->solved = true;
		} elseif ($status == 'W') {
			$this->resolving = true;
		}
		$this->tsumegoRating = $tsumego['rating'];
	}

	public function render() {
		echo '<div align="center" id="xpDisplayDiv">
		<table class="xpDisplayTable" border="0" width="70%">
			<tr>
			<td style="width:33%;">';
		echo '<div id="xpDisplay"></div>';
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
		echo '
	let xpStatus = new XPStatus(
	{
		baseXP: ' . $this->baseXP . ',
		solved: ' . Util::boolString($this->solved) . ',
		sprintRemainingSeconds: ' . HeroPowers::getSprintRemainingSeconds() . ',
		sprintMultiplier: ' . Constants::$SPRINT_MULTIPLIER . ',
		goldenTsumego: ' . Util::boolString($this->goldenTsumego) . ',
		goldenTsumegoMultiplier: ' . Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER . ',
		resolving: ' . Util::boolString($this->resolving) . ',
		resolvingMultiplier: ' . Constants::$RESOLVING_MULTIPLIER . '
	});
	xpStatus.update();
';
	}

	/* will be also javascript based
	private function getRatingPart(): string {
		if (is_null($this->ratingChangeWhenSolved)) {
			return '';
		}
		return '<font size="4">'
			 . '<div class="eloTooltip">+' . round($this->ratingChangeWhenSolved) . '<span class="eloTooltiptext">+' . round($this->ratingChangeWhenSolved, 2) . '</span></div>'
			. '/'
			. '<div class="eloTooltip">' . round($this->ratingChangeWhenFailed) . ' <span class="eloTooltiptext">' . round($this->ratingChangeWhenFailed, 2) . '</span></div></font>';
	}*/

	public int $baseXP;
	public bool $solved = false;
	public bool $goldenTsumego = false;
	public bool $resolving = false;

	public float $tsumegoRating;
	public ?float $ratingChangeWhenSolved = null;
	public ?float $ratingChangeWhenFailed = null;
}
