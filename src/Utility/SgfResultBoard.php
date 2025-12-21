<?php

class SgfResultBoard
{
	public function __construct(SgfResult $input)
	{
		$this->input = $input;
		$this->createEmpty();
		$this->fillStones($this->input->blackStones, 'x');
		$this->fillStones($this->input->whiteStones, 'x');
	}

	private function createEmpty(): void
	{
		for ($i = 0; $i < $this->input->size; $i++)
		{
			$this->data[$i] = [];
			for ($j = 0; $j < $this->input->size; $j++)
				$this->data[$i][$j] = '-';
		}
	}

	private function fillStones($stones, $color): void
	{
		foreach ($stones as $stone)
			$this->set($stone, $color);
	}

	public function &set(BoardPosition $stone, $value): void
	{
		$this->data[$stone->x][$stone->y] = $value;
	}

	public function get(BoardPosition $stone): string
	{
		return $this->data[$stone->x][$stone->y];
	}

	public function getSafe(BoardPosition $stone): string
	{
		if ($stone->x >= $this->input->size || $stone->y >= $this->input->size)
			return '-';
		return $this->get($stone);
	}

	public function getMirrored(): SgfResultBoard
	{
		return new SgfResultBoard($this->input->getMirrored());
	}
	public function getColorSwitched(): SgfResultBoard
	{
		return new SgfResultBoard($this->input->getColorSwitched());
	}


	public function getShifted(BoardPosition $shift): SgfResultBoard
	{
		return new SgfResultBoard($this->input->getShifted($shift));
	}

	public function getLowest(): BoardPosition
	{
		return $this->input->getLowest();
	}

	public array $data = [];
	public $input;
}
