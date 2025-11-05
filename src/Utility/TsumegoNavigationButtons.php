<?php

class TsumegoNavigationButtons {
	public function combine() {
		$result = [];
		if ($this->first) {
			array_push($result, $this->first);
		}
		array_push($result, ...$this->previous);
		array_push($result, $this->current);
		array_push($result, ...$this->next);
		if ($this->last) {
			array_push($result, $this->last);
		}
		return $result;
	}

	public $first;
	public $previous = [];
	public $current;
	public $next = [];
	public $last;
}
