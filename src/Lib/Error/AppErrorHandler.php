<?php

App::uses('AppException', 'Utility');

class AppErrorHandler {
	public function handleException(AppException $exception) {}

	public function render(AppException $exception) {
		echo $exception->getMessage();
	}
}
