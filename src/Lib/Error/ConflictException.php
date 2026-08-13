<?php

/**
 * Represents an HTTP 409 error.
 */
class ConflictException extends HttpException
{
	public function __construct($message = null, $code = 409)
	{
		if (empty($message))
			$message = 'Conflict';
		parent::__construct($message, $code);
	}
}
