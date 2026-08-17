<?php

App::uses('AppErrorHandler', 'Lib/Error');
App::uses('ForbiddenException', 'Routing/Error');
App::uses('NotFoundException', 'Routing/Error');
App::uses('UnprocessableEntityException', 'Lib/Error');

/**
 * AppErrorHandler renders JSON error bodies for JSON/AJAX requests
 * and the HTML error page for regular browser requests.
 */
class AppErrorHandlerTest extends CakeTestCase
{
	/**
	 * Renders the given exception through AppErrorHandler with the supplied
	 * request headers and returns the resulting response.
	 */
	private function renderError($exception, array $server = [])
	{
		$restoreServer = $_SERVER;
		$_SERVER = array_merge($_SERVER, $server);

		$request = new CakeRequest('/tsumego-comments/add');
		Router::setRequestInfo($request);
		try
		{
			ob_start();
			$handler = new AppErrorHandler($exception);
			$handler->render();
			$output = ob_get_clean();
		}
		finally
		{
			Router::popRequest();
			$_SERVER = $restoreServer;
		}

		return [$handler->controller->response, $output];
	}

	public function testJsonRequestRendersJsonErrorBody()
	{
		[$response, $output] = $this->renderError(new ForbiddenException('You are not authorized'), [
			'HTTP_ACCEPT' => 'application/json',
		]);

		$this->assertSame(403, $response->statusCode());
		$this->assertSame('application/json', $response->type());
		$this->assertSame('{"error":"You are not authorized"}', $response->body());
		$this->assertSame('{"error":"You are not authorized"}', $output);
	}

	public function testAjaxRequestRendersJsonErrorBody()
	{
		[$response, $output] = $this->renderError(new NotFoundException('Comment not found'), [
			'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
		]);

		$this->assertSame(404, $response->statusCode());
		$this->assertSame('{"error":"Comment not found"}', $response->body());
		$this->assertSame('{"error":"Comment not found"}', $output);
	}

	public function testUnprocessableEntityRenders422Json()
	{
		[$response, $output] = $this->renderError(new UnprocessableEntityException('Failed to add comment'), [
			'HTTP_ACCEPT' => 'application/json',
		]);

		$this->assertSame(422, $response->statusCode());
		$this->assertSame('{"error":"Failed to add comment"}', $response->body());
		$this->assertSame('{"error":"Failed to add comment"}', $output);
	}

	public function testHtmlRequestRendersHtmlErrorPage()
	{
		[$response, $output] = $this->renderError(new ForbiddenException('You are not authorized'));

		$this->assertSame(403, $response->statusCode());
		$this->assertNotSame('application/json', $response->type());
		$this->assertStringContainsString('Forbidden', $output);
		$this->assertStringNotContainsString('{"error"', $output);
	}
}
