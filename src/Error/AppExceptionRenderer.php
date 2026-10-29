<?php

namespace App\Error;

use Cake\Error\Renderer\WebExceptionRenderer;
use Cake\Http\Exception\HttpException;
use Psr\Http\Message\ResponseInterface;

//The error answers of the site: {"error": ...} for the JSON requests of the React pages, otherwise
//templates/Error/error.php in the site's layout, whatever the status. AppErrorHandler of Cake 2.
class AppExceptionRenderer extends WebExceptionRenderer
{
	public function render(): ResponseInterface
	{
		$code = $this->getHttpCode($this->error);
		if ($code < 400 || $code >= 600)
			$code = 500;
		$request = $this->controller->getRequest();
		//the headers of the exception, like Allow of a 405
		if ($this->error instanceof HttpException)
			foreach ($this->error->getHeaders() as $name => $value)
				$this->controller->setResponse($this->controller->getResponse()->withHeader($name, $value));
		if ($request->is('ajax') || str_contains($request->getHeaderLine('Accept'), 'application/json'))
			return $this->controller->getResponse()
				->withStatus($code)
				->withType('json')
				->withStringBody((string) json_encode(['error' => $this->error->getMessage()]));

		$this->controller->setResponse($this->controller->getResponse()->withStatus($code));
		$this->controller->set(['url' => $request->getRequestTarget(), 'error' => $this->error]);
		return $this->_outputMessage('error');
	}
}
