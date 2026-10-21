<?php

class AppErrorHandler extends ExceptionRenderer
{
	private function renderError(Throwable $error): void
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

	public function error400(mixed $error): void
	{
		$this->renderError($error);
	}

	public function error404(Throwable $error): void
	{
		$this->renderError($error);
	}

	public function error500(mixed $error): void
	{
		$this->renderError($error);
	}

	public function notFound(Throwable $error): void
	{
		$this->renderError($error);
	}

	public function missingController(Throwable $error): void
	{
		$this->renderError($error);
	}

	public function missingAction(Throwable $error): void
	{
		$this->renderError($error);
	}

	public function badRequest(Throwable $error): void
	{
		$this->renderError($error);
	}

	public function forbidden(Throwable $error): void
	{
		$this->renderError($error);
	}

	public function methodNotAllowed(Throwable $error): void
	{
		$this->renderError($error);
	}

	public function internalError(Throwable $error): void
	{
		$this->renderError($error);
	}
}
