<?php

namespace App\Utility;

use TsumegoStatus;

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

	public function render(): void
	{
		$num = '<div class="problem-nav__number"' . ($this->isCurrentlyOpened ? ' id="currentNavigationButton"' : '') . '>' . $this->order . '</div>';

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
		$num2 = '<div class="problem-nav__ratio">' . $num2 . '</div>';

		// Calculate time
		if ($this->seconds == 0 || $this->seconds == '')
			$num3 = '-';
		else
			$num3 = $this->seconds . 's';
		$num3 = '<div class="problem-nav__time">' . $num3 . '</div>';

		$sgfAttr = '';
		if ($this->sgf !== '')
		{
			$preview = self::sgfToPreviewData($this->sgf);
			if ($preview)
			{
				if ($this->diff)
					$preview['diff'] = $this->diff;
				$sgfAttr = ' data-sgf-preview=\'' . json_encode($preview) . '\'';
			}
		}

		// In the mistake training the ratio and the time are always empty, so the button
		// shows the rung of the review ladder under the number instead.
		$details = $this->rung === null ? $num2 . $num3 : $this->renderRungBadge();

		echo '<li class="problem-nav__item problem-nav__item--' . ($this->status ?: 'N') . ($this->isCurrentlyOpened ? ' problem-nav__item--current' : '') . '">';
		$status = $this->status ?: 'N';
		$label = $this->label ?? TsumegoStatus::label($status);
		$description = $this->description ?? TsumegoStatus::description($status);
		echo '<a class="tooltip' . ($this->rung === null ? '' : ' problem-nav__rung-link') . '" href="' . $this->linkPrefix . $this->setConnectionID . '"'
			. ' data-tsumego-id="' . $this->tsumegoID . '"'
			. $sgfAttr . '>'
			. $num . $details
			. '<span class="tooltip-box">'
			. '<div class="tooltip-label status' . $status . '">' . h($label) . '</div>'
			. '<div class="tooltip-desc">' . h($description) . '</div>'
			. '</span></a>';
		echo '</li>';
	}

	/**
	 * The badge of a training button: a colour of its own for every rung, from a
	 * fresh mistake to the last review before graduation, with the wait of the rung,
	 * so the row tells a new mistake from a late review at a glance. The play page
	 * swaps it in once a result moves the rung.
	 */
	public function renderRungBadge(): string
	{
		if ($this->graduated)
			return '<div class="problem-nav__rung problem-nav__rung--graduated">&#9733;</div>';
		return '<div class="problem-nav__rung problem-nav__rung--' . $this->rung . '">'
			. MistakeTraining::ladder()[$this->rung] . 'd</div>';
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
	public string $linkPrefix = '/';
	public int $order;
	public string $status;
	public ?float $rating;
	public float $seconds = 0;
	public string $performance;
	public bool $isCurrentlyOpened = false;
	public string $sgf;
	public ?string $diff = null;
	public ?int $setID = null;
	public ?string $setTitle = null;

	/**
	 * Rung of the mistake training ladder, shown as a badge under the number. Null
	 * outside the training, where the button shows its usual lines.
	 */
	public ?int $rung = null;

	/** Graduated from the training today, which has a badge of its own. */
	public bool $graduated = false;

	/**
	 * Tooltip texts in place of the ones of the status, for a row whose colours mean
	 * something other than the tsumego status.
	 */
	public ?string $label = null;
	public ?string $description = null;
}
