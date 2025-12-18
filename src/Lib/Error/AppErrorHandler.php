<?php

App::uses('AppException', 'Utility');

class AppErrorHandler
{
	public $exception;

	public function __construct($exception)
	{
		$this->exception = $exception;
	}

	public function render()
	{
		if ($this->exception instanceof MissingControllerException)
		{
			header('HTTP/1.1 404 Page not found	');
			echo "404 Error - Page not found";
			exit;
		}

		// Build the exception message
		$message = $this->exception->getMessage() . "<br>\n";
		$message .= "#-1 " . $this->exception->getFile() . "(" . $this->exception->getLine() . ")<br>\n";

		if (!($this->exception instanceof AppException))
		{
			$message = "<h2>Exception</h2><br>\n" . $message;
			$message .= nl2br(htmlspecialchars($this->exception->getTraceAsString()));
		}

		// Output and terminate
		echo $message;
		exit;
	}
}
