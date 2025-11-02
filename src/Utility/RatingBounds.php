<?php

class RatingBounds {
	public function getConditions(): array {
		$result = [];
		if ($this->min) {
			$result['rating >= '] = $this->min;
		}
		if ($this->max) {
			$result['rating < '] = $this->max;
		}
		return $result;
	}

	public ?float $min = null;
	public ?float $max = null;
}
