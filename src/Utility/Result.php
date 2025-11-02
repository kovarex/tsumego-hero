<?php

class Result {
	public static function success($message = null, $redirect = null) {
		$result = new Result();
		$result->isSuccess = true;
		$result->$message = $message;
		$result->redirect = $redirect;
		return $result;
	}

	public static function fail($message = null, $redirect = null) {
		$result = new Result();
		$result->isSuccess = false;
		$result->$message = $message;
		$result->redirect = $redirect;
		return $result;
	}


	public bool $isSuccess;
	public string $message;
	public string $redirect;

	public static int $RESULT_UNDEFINED = 0;
	public static int $RESULT_SUCCESS = 1;
	public static int $RESULT_FAIL = 2;
}
