<?php

class SetNavigationButtonsInput
{
	public function __construct($setFunction)
	{
		$this->setFunction = $setFunction;
	}

	public function execute(TsumegoButtons $tsumegoButtons, array $currentSetConnection): void
	{
		$navigationButtons = $this->collectFromSetConnections($tsumegoButtons, $currentSetConnection);
		($this->setFunction)('tsumegoButtons', $navigationButtons);
	}

	private function collectFromSetConnections(TsumegoButtons $tsumegoButtons, array $currentSetConnection): TsumegoButtons
	{
		$result = TsumegoButtons::deriveFrom($tsumegoButtons);
		$currentIndex = array_find_key((array) $tsumegoButtons, function ($tsumegoButton) use ($currentSetConnection) { return $tsumegoButton->setConnectionID === $currentSetConnection['SetConnection']['id']; });

		// mark the problem we are going to visit as already visited
		if ($tsumegoButtons[$currentIndex]->status == 'N')
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

	private static $NEIGHBOUR_COUNT_TO_SHOW_ON_EACH_SIDE = 5;
	private $setFunction;
}
