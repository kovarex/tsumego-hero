<?php

App::uses('AppException', 'Utility');

class AppErrorHandler {
	public function __construct($exception) {
		$this->exception = $exception;
	}

	public static function handleException(AppException $exception) {}

	public function render() {
		echo $this->exception->getMessage();
		if ($this->exception instanceof AppException) {
			return;
		}
		echo nl2br(htmlspecialchars($this->exception->getTraceAsString()));
	}

	public $exception;
}
