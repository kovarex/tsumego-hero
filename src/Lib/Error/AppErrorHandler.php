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
		// 422 is not in CakePHP 2's default status code map
		if ($code === 422)
			$this->controller->response->httpCodes([422 => 'Unprocessable Entity']);
		$this->controller->response->statusCode($code);

		// JSON API consumers (React frontend) expect {"error": ...} bodies,
		// regular browser requests get the HTML error page.
		$wantsJson = $this->controller->request->is('ajax')
			|| strpos((string) CakeRequest::header('Accept'), 'application/json') !== false;

		if ($wantsJson)
		{
			$this->controller->response->type('json');
			$this->controller->response->body(json_encode(['error' => $error->getMessage()]));
			$this->controller->response->send();
			return;
		}

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
