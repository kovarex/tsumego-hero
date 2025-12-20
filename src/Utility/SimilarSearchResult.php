<?php

class SimilarSearchResult
{
	public $difference;
	public string $title;
	public TsumegoButton $tsumegoButton;

	public static function compare(self $a, self $b): int
	{
		return $a->difference <=> $b->difference;
	}
}
