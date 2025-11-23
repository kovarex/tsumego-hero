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

		echo '<li class="set' . $this->status . '1">';
		echo '<a id="tooltip-hover' . $index . '" class="tooltip" href="/' . $this->setConnectionID . '">' . $num . '<span><div id="tooltipSvg' . $index . '"></div></span></a>';
		echo '</li>';
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
