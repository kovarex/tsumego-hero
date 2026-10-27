<?php

namespace App\Utility;

//What the author of an SGF version wrote about it (sgf.description), in the tables of versions and
//proposals. A long one is shortened to a line there, the whole text is in its tooltip.
class SgfDescription
{
	public const SHOWN_LENGTH = 80;

	public static function render(?string $description): string
	{
		if ($description === null || $description === '')
			return '';
		$line = preg_replace('/\s+/u', ' ', $description);
		if (mb_strlen($line) <= self::SHOWN_LENGTH)
			return h($line);
		return '<span title="' . h($description) . '">' . h(rtrim(mb_substr($line, 0, self::SHOWN_LENGTH))) . '…</span>';
	}
}
