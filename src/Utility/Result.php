<?php

class Result {

	static function success($message, $redirect = null) {
		$result = new Result();
		$result->isSuccess = true;
		$result->$message = $message;
		$result->redirect = $redirect;
		return $result;
	}

	static function fail($message, $redirect = null) {
		$result = new Result();
		$result->isSuccess = false;
		$result->$message = $message;
		$result->redirect = $redirect;
		return $result;
	}


	public bool $isSuccess;
	public string $message;
	public string $redirect;

	static int $RESULT_UNDEFINED = 0;
	static int $RESULT_SUCCESS = 1;
	static int $RESULT_FAIL = 2;
}
