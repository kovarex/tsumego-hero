<?php

class SgfResultBoard
{
	public function __construct(SgfResult $input)
	{
		$this->input = $input;
		$this->fillStones($this->input->blackStones, self::BLACK);
		$this->fillStones($this->input->whiteStones, self::WHITE);
	}

	private function fillStones($stones, int $color): void
	{
		foreach ($stones as $stone)
			$this->data[$stone] = $color;
	}

	public function get(int $packed): int
	{
		return $this->data[$packed] ?: self::EMPTY;
	}

	public function getMirrored(): SgfResultBoard
	{
		return new SgfResultBoard($this->input->getMirrored());
	}

	public function getColorSwitched(): SgfResultBoard
	{
		return new SgfResultBoard($this->input->getColorSwitched());
	}

	public function getShifted(int $shift): SgfResultBoard
	{
		return new SgfResultBoard($this->input->getShifted($shift));
	}

	public function getLowest(): int
	{
		return $this->input->getLowest();
	}

	public array $data = [];
	public $input;

    public const int EMPTY = 0;
    public const int BLACK = 1;
    public const int WHITE = 2;
}
