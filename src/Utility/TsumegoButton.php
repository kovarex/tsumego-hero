<?php

class TsumegoButton
{
	public function __construct(int $tsumegoID, int $setConnectionID, int $order, ?string $status, ?float $rating = 0)
	{
		$this->tsumegoID = $tsumegoID;
		$this->setConnectionID = $setConnectionID;
		$this->order = $order;
		$this->status = $status;
		$this->rating = $rating;
	}

	public static function createFromSetConnection($setConnection): TsumegoButton
	{
		$tsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => [
			'tsumego_id' => $setConnection['tsumego_id'],
			'user_id' => Auth::getUserID()]]);
		return new TsumegoButton(
			$setConnection['tsumego_id'],
			$setConnection['id'],
			$setConnection['num'],
			$tsumegoStatus ? $tsumegoStatus['TsumegoStatus']['status'] : null);
	}

	public function render()
	{
		$num = '<div class="setViewButtons1"' . ($this->isCurrentlyOpened ? ' id="currentNavigationButton"' : '') . '>' . $this->order . '</div>';

		// Calculate accuracy (performance) as percentage
		if (empty($this->performance))
		{
			$persormanceS = 0;
			$persormanceF = 0;
		}
		else
		{
			$persormanceS = substr_count($this->performance, '1');
			$persormanceF = substr_count($this->performance, 'F');
		}
		if ($persormanceS == 0 && $persormanceF == 0)
			$num2 = '-';
		else
			$num2 = $persormanceS . '/' . $persormanceF;
		$num2 = '<div class="setViewButtons2">' . $num2 . '</div>';

		// Calculate time
		if ($this->seconds == 0 || $this->seconds == '')
			$num3 = '-';
		else
			$num3 = $this->seconds . 's';
		$num3 = '<div class="setViewButtons3">' . $num3 . '</div>';

		echo '<li class="status' . ($this->status ?: 'N') . ($this->isCurrentlyOpened ? ' statusCurrent' : '') . '">';
		echo '<a class="tooltip" href="/' . $this->setConnectionID . '" onmouseover="' . $this->generateTooltip() . '">' . $num . $num2 . $num3 . '<span class="tooltip-box"></span></a>';
		echo '</li>';
	}

	private function generateTooltip(): string
	{
		$sgf = ClassRegistry::init('Sgf')->find('first', ['limit' => 1, 'order' => 'id DESC', 'conditions' => ['tsumego_id' => $this->tsumegoID]]);
		if (!$sgf)
			return '';
		$result = '';
		$result .= 'if (this.querySelector(\'svg\')) return;';
		$sgf = SgfParser::process($sgf['Sgf']['sgf']);
		$result .= 'black = \'' . implode("", array_map(fn($stone) => BoardPosition::toLetters($stone), $sgf->blackStones)) . '\';';
		$result .= 'white = \'' . implode("", array_map(fn($stone) => BoardPosition::toLetters($stone), $sgf->whiteStones)) . '\';';
		$result .= 'createPreviewBoard(this, black, white,' . $sgf->info[0] . ', ' . $sgf->info[1] . ', ' . $sgf->size . ');' . PHP_EOL;
		return $result;
	}

	public int $tsumegoID;
	public int $setConnectionID;
	public int $order;
	public ?string $status;
	public ?float $rating;
	public float $seconds = 0;
	public string $performance;
	public bool $isCurrentlyOpened = false;
}
