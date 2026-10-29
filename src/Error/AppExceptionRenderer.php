<?php

namespace App\Error;

use App\Utility\Auth;
use Cake\Core\Configure;
use Cake\Error\Renderer\WebExceptionRenderer;
use Cake\Http\Exception\HttpException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

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
		$response = $this->controller->getResponse()->withStatus($code);
		$showsDetails = self::showsDetails();
		if ($request->is('ajax') || str_contains($request->getHeaderLine('Accept'), 'application/json'))
		{
			//The message of an HttpException is the site's answer to the user. Any other error is a
			//fault inside, and its message can tell about the code or the database.
			$message = $showsDetails || $this->error instanceof HttpException ? $this->error->getMessage() : $response->getReasonPhrase();
			return $response->withType('json')->withStringBody((string) json_encode(['error' => $message]));
		}

		$this->controller->setResponse($response);
		//the page shows the exception, its file and its trace when it has the error
		$this->controller->set(['url' => $request->getRequestTarget(), 'error' => $showsDetails ? $this->error : null]);
		return $this->_outputMessage('error');
	}

	//The inside of an error is for the developers (debug) and for the admins, who need the traces on
	//the live site. The viewer is known once the beforeFilter of ErrorController, or of the request
	//that failed, has read the cookie. When that failed too, as with the database down, nobody is
	//signed in and only debug shows them.
	private static function showsDetails(): bool
	{
		if (Configure::read('debug'))
			return true;
		try
		{
			return Auth::isAdmin();
		}
		catch (Throwable)
		{
			return false;
		}
	}
}
