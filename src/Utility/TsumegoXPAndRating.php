<?php

namespace App\Utility;

use App\Model\Entity\Tsumego;
use App\Model\Table\ProgressDeletionTable;
use App\Model\Table\TsumegoAttemptTable;

class TsumegoXPAndRating
{
	public function __construct(Tsumego $tsumego, string $status, ?int $setConnectionID = null)
	{
		if ($status == 'G')
			$this->goldenTsumego = true;
		elseif (TsumegoUtil::isRecentlySolved($status))
			$this->solved = true;
		elseif ($status == 'W')
			$this->resolving = true;
		$this->tsumegoRating = $tsumego->rating;
		// the resets of the part of the set the problem is played from
		$this->progressDeletionCount = Auth::isLoggedIn() ? ProgressDeletionTable::countRecentForProblem(Auth::getUserID(), $tsumego->id, $setConnectionID) : 0;
		$this->failsInARow = Auth::isLoggedIn() ? TsumegoAttemptTable::failsInARow(TsumegoAttemptTable::findLast((int) Auth::getUserID(), $tsumego->id)) : 0;
	}

	//The stake of the problem, at the right of its title: the rank of the problem and, in a popup the rank opens
	//(webroot/js/PlayPage.js), its rating with the XP and the rating a solve brings or a fail costs (filled by
	//webroot/js/XPStatus.js); the countdown of the time mode shows beside the rank
	public function render(): void
	{
		echo '<div id="xpDisplayDiv" class="play-stake">';
		if (Auth::isInTimeMode())
			echo '<div id="time-mode-countdown">10.0</div><div id="plus2">+2</div>';
		echo '<div id="status2" class="play-stake__rank" role="button" tabindex="0" aria-expanded="false" aria-controls="playStakeDetails" title="The rating and the reward of the problem">
		<span class="status-rating">
		' . Rating::getReadableRankFromRating($this->tsumegoRating) . ' <span class="status-rating__number">(' . $this->tsumegoRating . ')</span>
		</span>
	</div>
	<div id="playStakeDetails" class="play-stake__details">
		<div class="play-stake__rating">Problem rating: ' . round($this->tsumegoRating) . '</div>
		<div id="xpDisplay">
			<span id="xpDisplayText"></span>
			<span id="ratingHeader"></span>
			<div class="eloTooltip"><span id="ratingGainShort"></span><span class="eloTooltiptext" id="ratingGainLong"></span></div>
			<span id="ratingSeparator"></span><div class="eloTooltip"><span id="ratingLossShort"></span><span class="eloTooltiptext" id="ratingLossLong"></span></div>
		</div>
	</div>
</div>';
	}

	//The one place of the messages of the problem, right above the board: Correct!, Incorrect!, the lock
	//of a problem failed today
	public function renderStatus(): void
	{
		echo '<div id="status" class="play-message__status" role="status" aria-live="polite"></div>';
	}

	//What the page's XPStatus (webroot/js/XPStatus.js) needs to show the reward of a solve, null when
	//the mode gives no XP and no rating or no one is signed in
	/** @return array<string, mixed>|null */
	public function clientData(): ?array
	{
		if (!Auth::isLoggedIn())
			return null;
		if (!Auth::XPisGainedInCurrentMode() && !Auth::ratingisGainedInCurrentMode())
			return null;
		return [
			'solved' => $this->solved,
			'sprintRemainingSeconds' => HeroPowers::getSprintRemainingSeconds(),
			'sprintMultiplier' => Constants::$SPRINT_MULTIPLIER,
			'goldenTsumego' => $this->goldenTsumego,
			'goldenTsumegoMultiplier' => Constants::$GOLDEN_TSUMEGO_XP_MULTIPLIER,
			'resolving' => $this->resolving,
			'resolvingMultiplier' => Constants::$RESOLVING_MULTIPLIER,
			'userRating' => Auth::getUser()['rating'],
			'tsumegoRating' => $this->tsumegoRating,
			'progressDeletionCount' => $this->progressDeletionCount,
			'failsInARow' => $this->failsInARow,
			'maximumRatedFailsInARow' => Constants::$MAXIMUM_RATED_FAILS_IN_A_ROW];
	}

	// changes here must be reflected in the same method in util.js
	public static function getProgressDeletionMultiplier(int $progressDeletionCount): float
	{
		if ($progressDeletionCount == 0)
			return 1;
		if ($progressDeletionCount == 1)
			return 0.5;
		if ($progressDeletionCount == 2)
			return 0.2;
		if ($progressDeletionCount == 3)
			return 0.1;
		return 0.01;
	}

	public bool $solved = false;
	public bool $goldenTsumego = false;
	public bool $resolving = false;

	public float $tsumegoRating;
	public float $progressDeletionCount;
	// the fails of the player on the problem since it was last solved
	public int $failsInARow;
}
