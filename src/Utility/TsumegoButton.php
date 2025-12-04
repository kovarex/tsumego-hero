<?php

class TsumegoButton
{
	public function __construct(int $tsumegoID, int $setConnectionID, int $order, string $status, bool $passEnabled, bool $alternativeResponse)
	{
		$this->tsumegoID = $tsumegoID;
		$this->setConnectionID = $setConnectionID;
		$this->order = $order;
		$this->status = $status;
		$this->passEnabled = $passEnabled;
		$this->alternativeResponse = $alternativeResponse;
	}

	public function render(int $index)
	{
		$num = '<div class="setViewButtons1"' . ($this->isCurrentlyOpened ? ' id="currentNavigationButton"' : '') . '>' . $this->order . '</div>';
		/*
		$persormanceS = substr_count($ts[$i]['Tsumego']['performance'], '1');
		$persormanceF = substr_count($ts[$i]['Tsumego']['performance'], 'F');
		if($persormanceS==0 && $persormanceF==0) $num2 = '-';
		else $num2 = $persormanceS.'/'.$persormanceF;
		$num2 = '<div class="setViewButtons2">'.$num2.'</div>';
		if($ts[$i]['Tsumego']['seconds']=='') $num3 = '-';
		else $num3 = $ts[$i]['Tsumego']['seconds'].'s';
		$num3 = '<div class="setViewButtons3">'.$num3.'</div>';*/

		echo '<li class="status' . $this->status . ($this->isCurrentlyOpened ? ' statusCurrent' : '') . '">';
		echo '<a class="tooltip" href="/' . $this->setConnectionID . '" onmouseover="' . $this->generateTooltip() . '">' . $num . '<span class="tooltip-box"></span></a>';
		echo '</li>';
	}

	private function generateTooltip(): string
	{
		$sgf = ClassRegistry::init('Sgf')->find('first', ['limit' => 1, 'order' => 'id DESC', 'conditions' => ['tsumego_id' => $this->tsumegoID]]);
		if (!$sgf)
			return '';
		$result = '';
		$result .= 'if (this.querySelector(\'svg\')) return;';
		$result .= 'tooltipSgf = [];' . PHP_EOL;
		$sgf = SgfParser::process($sgf['Sgf']['sgf']);
		for($y = 0; $y < count($sgf->board); $y++)
		{
			$result .= 'tooltipSgf[' . $y . '] = [];' . PHP_EOL;
			for ($x = 0; $x < count($sgf->board[$y]); $x++)
				$result .= 'tooltipSgf[' . $y . '].push(\'' . $sgf->board[$x][$y] . '\');' . PHP_EOL;
		}
		$result .= 'createPreviewBoard(this, tooltipSgf,' . $sgf->info[0] . ', ' . $sgf->info[1] . ', ' . $sgf->size . ');' . PHP_EOL;
		return $result;
	}

	public int $tsumegoID;
	public int $setConnectionID;
	public int $order;
	public string $status;
	public bool $passEnabled; // used for set view statistics
	public bool $alternativeResponse ; // used for set view statistics
	public float $seconds = 0;
	public string $performance;
	public bool $isCurrentlyOpened = false;
}
