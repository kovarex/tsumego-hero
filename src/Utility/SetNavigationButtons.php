<?php

namespace App\Utility;

use App\Model\Entity\SetConnection;

//The row of problem buttons the play page shows: the first and the last problem, and the
//neighbours of the current one
class SetNavigationButtons
{
	public static function around(TsumegoButtons $tsumegoButtons, SetConnection $currentSetConnection): TsumegoButtons
	{
		$navigationButtons = self::collectFromSetConnections($tsumegoButtons, $currentSetConnection);
		$navigationButtons->loadPreviews();
		return $navigationButtons;
	}

	private static function collectFromSetConnections(TsumegoButtons $tsumegoButtons, SetConnection $currentSetConnection): TsumegoButtons
	{
		$result = TsumegoButtons::deriveFrom($tsumegoButtons);
		$currentIndex = array_find_key((array) $tsumegoButtons, function ($tsumegoButton) use ($currentSetConnection) {
			return $tsumegoButton->setConnectionID === $currentSetConnection->id;
		});

		// mark the problem we are going to visit as already visited, except in the mistake
		// training, whose colours say what today did and opening a problem does nothing.
		// A problem in the trash, which only the admins open, has no button.
		if ($currentIndex !== null && $tsumegoButtons[$currentIndex]->status == 'N' && $tsumegoButtons[$currentIndex]->rung === null)
			$tsumegoButtons[$currentIndex]->status = 'V';

		if (count($tsumegoButtons) <= self::$NEIGHBOUR_COUNT_TO_SHOW_ON_EACH_SIDE * 2 + 3)
		{
			foreach ($tsumegoButtons as $tsumegoButton)
				$result [] = $tsumegoButton;
			return $result;
		}
		$startIndex = max(1, $currentIndex - self::$NEIGHBOUR_COUNT_TO_SHOW_ON_EACH_SIDE);
		$endIndex = min($startIndex + self::$NEIGHBOUR_COUNT_TO_SHOW_ON_EACH_SIDE * 2, count($tsumegoButtons) - 2);
		$startIndex = max(1, $endIndex - self::$NEIGHBOUR_COUNT_TO_SHOW_ON_EACH_SIDE * 2);
		$result [] = $tsumegoButtons[0];
		for ($i = $startIndex; $i <= $endIndex; $i++)
			$result [] = $tsumegoButtons[$i];
		$result [] = $tsumegoButtons[count($tsumegoButtons) - 1];
		return $result;
	}

	private static int $NEIGHBOUR_COUNT_TO_SHOW_ON_EACH_SIDE = 5;
}
