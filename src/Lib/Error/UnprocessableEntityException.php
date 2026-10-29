<?php

namespace App\Lib\Error;

use Cake\Http\Exception\HttpException;

//Represents an HTTP 422 error.
class UnprocessableEntityException extends HttpException
{
	public function __construct(?string $message = null, int $code = 422)
	{
		if (empty($message))
			$message = 'Unprocessable Entity';
		parent::__construct($message, $code);
	}
}
