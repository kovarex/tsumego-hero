<?php

namespace App\Utility;

class AccountWidget
{
	//How the bar of the account starts, before the script takes it over (templates/element/account_widget.php)
	/** @return array{fillClass: string, ratio: float, text: string, textClass: string, switchText: string, modeSelectorClass: string} */
	public static function bar(TimeMode $timeMode): array
	{
		$user = Auth::getUser();
		return match (self::whatToShow())
		{
			'time' => [
				'fillClass' => 'xp-bar-fill-c3',
				'ratio' => Util::getRatio($timeMode->currentPosition() - 1, $timeMode->overallCount),
				'text' => $timeMode->getTitle(),
				'textClass' => '',
				'switchText' => 'Level bar',
				'modeSelectorClass' => 'modeSelectorInLevelBar'],
			'rating' => [
				'fillClass' => 'xp-bar-fill-c2',
				'ratio' => self::rankProgress($user['rating']),
				'text' => Rating::getReadableRankFromRating($user['rating']),
				'textClass' => 'account-bar-rating',
				'switchText' => 'Level Bar',
				'modeSelectorClass' => 'modeSelectorInRatingBar'],
			default => [ //the level
				'fillClass' => 'xp-bar-fill-c1',
				'ratio' => $user['xp'] / Level::getXPForNext($user['level']),
				'text' => 'Level ' . $user['level'],
				'textClass' => 'account-bar-level',
				'switchText' => 'Rating Bar',
				'modeSelectorClass' => 'modeSelectorInLevelBar'],
		};
	}

	//How far the rating is from the start of its rank to the next one
	private static function rankProgress(float $rating): float
	{
		$rank = Rating::getRankFromRating($rating);
		$rankStart = Rating::getRankMinimalRating($rank);
		return ($rating - $rankStart) / (Rating::getRankMinimalRating($rank + 1) - $rankStart);
	}

	//The number of unread messages, hidden while there are none. The messages page updates
	//every badge on the page (data-unread-messages) once it marks a conversation read.
	public static function unreadMessagesBadge(int $unreadMessageCount): string
	{
		return '<span class="badge badge--alert messages-unread-count" data-unread-messages title="Unread messages"' . ($unreadMessageCount ? '' : ' hidden') . '>'
			. $unreadMessageCount . '</span>';
	}

	//What the bar shows: the run in time mode, otherwise the rank or the level, as the player switched it
	private static function whatToShow(): string
	{
		if (Auth::isInTimeMode())
			return 'time';
		if (Util::cookie('showInAccountWidget') == 'rating')
			return 'rating';
		return 'level';
	}

	public static function renderJS(TimeMode $timeMode): void
	{
		echo "var accountWidget =";
		if (Auth::isLoggedIn())
			echo " new AccountWidget(
				{
					rating: " . Auth::getUser()['rating'] . ",
					xp: " . Auth::getUser()['xp'] . ",
					level: " . Auth::getUser()['level'] . ",
					show: '" . AccountWidget::whatToShow() . "',
					timeMode: " . $timeMode->exportTimeModeInfo() . ",
					levelSections: " . json_encode(Level::getSections()) . "});
				accountWidget.replayUnshownResult();";
		else
			echo "null;";
	}
}
