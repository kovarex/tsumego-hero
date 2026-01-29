<?php

App::uses('AppException', 'Utility');
App::uses('ExceptionRenderer', 'Error');

class AppErrorHandler extends ExceptionRenderer
{
	private function renderError($code, $title, $error)
	{
		$this->controller->response->statusCode($code);
		$this->controller->set('title_for_layout', $title);
		$this->controller->set([
			'url' => $this->controller->request->here,
			'error' => $error
		]);
		$this->_outputMessage('error');
	}

	public function error404($error)
	{
		$this->renderError(404, 'Page Not Found', $error);
	}

	public function notFound($error)
	{
		return $this->error404($error);
	}

	public function missingController($error)
	{
		return $this->error404($error);
	}

	public function missingAction($error)
	{
		return $this->error404($error);
	}

	public function error500($error)
	{
		$this->renderError(500, 'Internal Server Error', $error);
	}
}
