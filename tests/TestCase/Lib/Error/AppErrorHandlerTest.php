<?php

use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\ServerRequest as CakeRequest;
use Cake\Routing\Router;
use App\Error\AppExceptionRenderer;
use App\Lib\Error\UnprocessableEntityException;

//AppExceptionRenderer renders JSON error bodies for JSON/AJAX requests and the HTML error page for regular browser requests.
class AppErrorHandlerTest extends AppTestCase
{
	//Renders the given exception through AppExceptionRenderer with the supplied
	//request headers and returns the resulting response and its body.
	private function renderError($exception, array $server = [])
	{
		$this->loadRoutes();
		$request = new CakeRequest(['url' => '/tsumego-comments/add', 'environment' => $server + ['REQUEST_METHOD' => 'POST']]);
		Router::setRequest($request);
		$response = new AppExceptionRenderer($exception, $request)->render();
		return [$response, (string) $response->getBody()];
	}

	public function testJsonRequestRendersJsonErrorBody()
	{
		[$response, $output] = $this->renderError(new ForbiddenException('You are not authorized'), [
			'HTTP_ACCEPT' => 'application/json',
		]);

		$this->assertSame(403, $response->getStatusCode());
		$this->assertSame('application/json', $response->getType());
		$this->assertSame('{"error":"You are not authorized"}', (string) $response->getBody());
		$this->assertSame('{"error":"You are not authorized"}', $output);
	}

	public function testAjaxRequestRendersJsonErrorBody()
	{
		[$response, $output] = $this->renderError(new NotFoundException('Comment not found'), [
			'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
		]);

		$this->assertSame(404, $response->getStatusCode());
		$this->assertSame('{"error":"Comment not found"}', (string) $response->getBody());
		$this->assertSame('{"error":"Comment not found"}', $output);
	}

	public function testUnprocessableEntityRenders422Json()
	{
		[$response, $output] = $this->renderError(new UnprocessableEntityException('Failed to add comment'), [
			'HTTP_ACCEPT' => 'application/json',
		]);

		$this->assertSame(422, $response->getStatusCode());
		$this->assertSame('{"error":"Failed to add comment"}', (string) $response->getBody());
		$this->assertSame('{"error":"Failed to add comment"}', $output);
	}

	public function testHtmlRequestRendersHtmlErrorPage()
	{
		[$response, $output] = $this->renderError(new ForbiddenException('You are not authorized'));

		$this->assertSame(403, $response->getStatusCode());
		$this->assertNotSame('application/json', $response->getType());
		$this->assertStringContainsString('Forbidden', $output);
		$this->assertStringNotContainsString('{"error"', $output);
	}
}
