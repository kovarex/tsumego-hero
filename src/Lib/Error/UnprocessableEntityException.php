<?php

/**
 * Represents an HTTP 422 error.
 */
class UnprocessableEntityException extends HttpException
{
	public function __construct($message = null, $code = 422)
	{
		if (empty($message))
			$message = 'Unprocessable Entity';
		parent::__construct($message, $code);
	}
}
