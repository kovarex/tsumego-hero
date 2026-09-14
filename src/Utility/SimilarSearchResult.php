<?php

namespace App\Utility;

class SimilarSearchResult
{
	public string $title = '';
	public array $items = [];
	public ?float $elapsed = null; // how long the search took in seconds
}
