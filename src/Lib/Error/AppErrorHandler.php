<?php

App::uses('AppException', 'Utility');

class AppErrorHandler {
	public $exception;

	public function __construct($exception) {
		$this->exception = $exception;
	}

	public function render() {
		// Build the exception message
		$message = $this->exception->getMessage() . "<br>\n";
		$message .= "#-1 " . $this->exception->getFile() . "(" . $this->exception->getLine() . ")<br>\n";

		if (!($this->exception instanceof AppException)) {
			$message = "<h2>Exception</h2><br>\n" . $message;
			$message .= nl2br(htmlspecialchars($this->exception->getTraceAsString()));
		}

		// Output and terminate
		echo $message;
		exit;
	}
}
