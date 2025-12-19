<?php

class Query
{
	public function __construct($query)
	{
		$this->query = $query;
	}

	public function str()
	{
		$result = $this->prefix;
		$result .= 'SELECT ';
		$result .= implode(', ', $this->selects);
		$result .= ' ';
		$result .= $this->query;

		if (!empty($this->conditions))
			$result .= ' WHERE ' . implode(' AND ', array_map(fn($c) => stripos($c, ' OR ') !== false ? "($c)" : $c, $this->conditions));
		if (!empty($this->groupBy))
			$result .= ' GROUP BY ' . implode(', ', $this->groupBy);
		if (!empty($this->orderBy))
			$result .= ' ORDER BY ' . implode(', ', $this->orderBy);
		$result .= $this->suffix;
		return $result;
	}

	public $selects = [];
	public $query = '';
	public $conditions = [];
	public $orderBy = [];
	public $groupBy = [];
	public $prefix;
	public $suffix;
}
