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

	public array $data = [];
	public $input;
}
