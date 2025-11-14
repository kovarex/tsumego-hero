<?php

class TsumegoXpAndRating {
	public function __construct(array $tsumego, string $status) {
		$this->xp = TsumegoUtil::getXpValue($tsumego);
		if (TsumegoUtil::isStatusAllowingInspection($status)) {
			$this->comment = "Solved";
			$this->color = '#0cbb0c';
			$this->currentMultiplier = 0;
		} elseif ($status == 'G') {
			$this->comment = "Golden";
			$this->color = '#0cbb0c'; //b5910b
			$this->currentMultiplier = Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER;
		} elseif ($status == 'W' || $status == 'X') {
			$this->currentMultiplier = 1 / 2;
		}
		$this->tsumegoRating = $tsumego['rating'];
	}

	public function render() {
		echo '<div align="center" id="xpDisplayDiv">
		<table class="xpDisplayTable" border="0" width="70%">
			<tr>
			<td style="width:33%;">';
		if (!Auth::isInTimeMode()) {
			echo '<div id="xpDisplay" align="center">
					<font size="4" color="' . $this->color . '">' . $this->getXpPart() . $this->getRatingPart() . '</font>
				</div>';
		} else {
			echo '<div id="xpDisplay" align="center"></div>
				<div id="time-mode-countdown">10.0</div><div id="plus2">+2</div>';
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

	private function getXpPart(): string {
		$result = $this->xp . ' XP';
		if ($this->comment) {
			$result = $this->comment . '(' . $result . ')';
		}
		$result .= $this->getReadableMultiplier();
		return $result;
	}

	private function getRatingPart(): string {
		if (is_null($this->ratingChangeWhenSolved)) {
			return '';
		}
		return '<font size="4">'
			 . '<div class="eloTooltip">+' . round($this->ratingChangeWhenSolved) . '<span class="eloTooltiptext">+' . round($this->ratingChangeWhenSolved, 2) . '</span></div>'
			. '/'
			. '<div class="eloTooltip">' . round($this->ratingChangeWhenFailed) . ' <span class="eloTooltiptext">' . round($this->ratingChangeWhenFailed, 2) . '</span></div></font>';
	}

	private function getReadableMultiplier(): string {
		if ($this->currentMultiplier == 1 || $this->currentMultiplier == 0) {
			return '';
		}
		if ($this->currentMultiplier < 1) {
			return ' (1/' . round(1 / $this->currentMultiplier) . ')';
		}
		return ' (X ' . $this->currentMultiplier . ')';
	}

	public int $xp;
	public float $tsumegoRating;
	public ?float $ratingChangeWhenSolved = null;
	public ?float $ratingChangeWhenFailed = null;
	public float $currentMultiplier = 1;
	public string $color = 'black';
	public ?string $comment = null;
	public string $sandboxComment = '';
}
