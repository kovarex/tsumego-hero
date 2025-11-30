<?php

class Decoder
{
	public static function decodeSeconds($previousTsumego): ?float
	{
		$secondsCheck = Util::clearRequiredNumericCookie('secondsCheck');

		if ($secondsCheck % 79 != 0)
		{
			Auth::addSuspicion();
			return null;
		}
		$secondsCheck /= 79;
		if ($secondsCheck % $previousTsumego['Tsumego']['id'] != 0)
		{
			Auth::addSuspicion();
			return null;
		}
		return ($secondsCheck / $previousTsumego['Tsumego']['id']) / 100;
	}

	public static function decodeSuccess($previousTsumegoID): bool
	{
		$solvedCheck = Util::clearCookie('solvedCheck');
		if (empty($solvedCheck))
			return false;

		$decryptedSolvedCheck = explode('-', Util::decrypt($solvedCheck));
		if (count($decryptedSolvedCheck) != 2)
		{
			Auth::addSuspicion();
			return false;
		}
		if ($decryptedSolvedCheck[0] != $previousTsumegoID)
		{
			Auth::addSuspicion();
			return false;
		}
		return true;
	}
}
