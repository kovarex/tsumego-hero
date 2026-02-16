<?php

App::uses('ExceptionRenderer', 'Error');

class AppErrorHandler extends ExceptionRenderer
{
	private function renderError($error)
	{
		$code = $error->getCode();
		// Plain Exception has code 0, MissingController/Action have non-HTTP codes
		if ($code < 400 || $code >= 600)
			$code = 500;
		$this->controller->response->statusCode($code);
		$this->controller->set([
			'url' => $this->controller->request->here,
			'error' => $error
		]);
		$this->_outputMessage('error');
	}

	public function error400($error)
	{
		$this->renderError($error);
	}

	public function error404($error)
	{
		$this->renderError($error);
	}

	public function error500($error)
	{
		$this->renderError($error);
	}

	public function notFound($error)
	{
		$this->renderError($error);
	}

	public function missingController($error)
	{
		$this->renderError($error);
	}

	public function missingAction($error)
	{
		$this->renderError($error);
	}

	public function badRequest($error)
	{
		$this->renderError($error);
	}

	public function forbidden($error)
	{
		$this->renderError($error);
	}

	public function methodNotAllowed($error)
	{
		$this->renderError($error);
	}

	public function internalError($error)
	{
		$this->renderError($error);
	}
}
