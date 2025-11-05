<?php

App::uses('AppException', 'Utility');

class AppErrorHandler {
	public function __construct($exception) {
		$this->exception = $exception;
	}

	public static function handleException(Exception $exception) {}

	public function render() {
		echo $this->exception->getMessage()."<br>\n";
		echo "#-1 ".$this->exception->getFile()."(".$this->exception->getLine().")<br>\n";
		if ($this->exception instanceof AppException) {
			return;
		}
		echo nl2br(htmlspecialchars($this->exception->getTraceAsString()));
	}

	public $exception;
}
