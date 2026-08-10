<?php

App::uses('TsumegoStatus', 'Model');

class TsumegoButton
{
	public function __construct(int $tsumegoID, int $setConnectionID, int $order, string $status, ?float $rating, string $sgf)
	{
		$this->tsumegoID = $tsumegoID;
		$this->setConnectionID = $setConnectionID;
		$this->order = $order;
		$this->status = $status;
		$this->rating = $rating;
		$this->sgf = $sgf;
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

		$sgfAttr = '';
		if ($this->sgf !== '')
		{
			$preview = self::sgfToPreviewData($this->sgf);
			if ($preview)
				$sgfAttr = ' data-sgf-preview=\'' . json_encode($preview) . '\'';
		}

		echo '<li class="status' . ($this->status ?: 'N') . ($this->isCurrentlyOpened ? ' statusCurrent' : '') . '">';
		$status = $this->status ?: 'N';
		$label = TsumegoStatus::label($status);
		$description = TsumegoStatus::description($status);
		echo '<a class="tooltip" href="/' . $this->setConnectionID . '"'
			. ' data-tsumego-id="' . $this->tsumegoID . '"'
			. $sgfAttr . '>'
			. $num . $num2 . $num3
			. '<span class="tooltip-box">'
			. '<div class="tooltip-label status' . $status . '">' . h($label) . '</div>'
			. '<div class="tooltip-desc">' . h($description) . '</div>'
			. '</span></a>';
		echo '</li>';
	}

	/**
	 * Parse SGF into the JSON format that previewBoard.js expects.
	 * Returns null if the SGF is empty or unparseable.
	 */
	public static function sgfToPreviewData(string $sgf): ?array
	{
		if ($sgf === '')
			return null;
		$parsed = SgfParser::process($sgf);
		return [
			'black' => implode('', array_map(fn($stone) => BoardPosition::toLetters($stone), $parsed->filterStonesPositions(SgfBoard::BLACK))),
			'white' => implode('', array_map(fn($stone) => BoardPosition::toLetters($stone), $parsed->filterStonesPositions(SgfBoard::WHITE))),
			'xMax' => $parsed->info[0],
			'yMax' => $parsed->info[1],
			'boardSize' => $parsed->size,
		];
	}

	public int $tsumegoID;
	public int $setConnectionID;
	public int $order;
	public string $status;
	public ?float $rating;
	public float $seconds = 0;
	public string $performance;
	public bool $isCurrentlyOpened = false;
	public string $sgf;
}
