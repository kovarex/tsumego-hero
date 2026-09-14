<?php

namespace App\Lib\Error;

use HttpException;

/**
 * Represents an HTTP 409 error.
 */
class ConflictException extends HttpException
{
	public function __construct(?string $message = null, int $code = 409)
	{
		if (empty($message))
			$message = 'Conflict';
		parent::__construct($message, $code);
	}
}
