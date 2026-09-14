<?php

namespace App\Utility;

use Exception;
use Throwable;

class RatingParseException extends Exception
{
	public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}
