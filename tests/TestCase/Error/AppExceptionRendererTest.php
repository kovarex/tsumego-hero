<?php

use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use App\Error\AppExceptionRenderer;
use Cake\Http\Exception\UnprocessableContentException;
use Cake\Core\Configure;

//AppExceptionRenderer renders JSON error bodies for JSON/AJAX requests and the HTML error page for regular browser requests.
class AppExceptionRendererTest extends AppTestCase
{
	//Renders the given exception through AppExceptionRenderer with the supplied
	//request headers and returns the resulting response and its body.
	private function renderError($exception, array $server = [], string $url = '/tsumego-comments/add')
	{
		$this->loadRoutes();
		$request = new ServerRequest(['url' => $url, 'environment' => $server + ['REQUEST_METHOD' => 'POST']]);
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

	public function testUnprocessableContentRenders422Json()
	{
		[$response, $output] = $this->renderError(new UnprocessableContentException('Failed to add comment'), [
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

	//What the error page shows of the exception itself: its class, message, file and stack trace.
	private function assertShowsTheInsideOfTheError(string $output, bool $shows): void
	{
		foreach (['Debug Information', 'NotFoundException', 'There is no such page', basename(__FILE__), 'Stack Trace'] as $detail)
			if ($shows)
				$this->assertStringContainsString($detail, $output);
			else
				$this->assertStringNotContainsString($detail, $output);
	}

	public function testA404WithoutDebugShowsNothingOfTheErrorItself()
	{
		Configure::write('debug', false);
		[$response, $output] = $this->renderError(new NotFoundException('There is no such page'), ['REQUEST_METHOD' => 'GET'], '/sets/nowhere');

		$this->assertSame(404, $response->getStatusCode());
		$this->assertStringContainsString('Page Not Found', $output);
		$this->assertStringContainsString('/sets/nowhere', $output);
		$this->assertShowsTheInsideOfTheError($output, false);
	}

	public function testA404WithDebugShowsTheErrorItself()
	{
		Configure::write('debug', true);
		[$response, $output] = $this->renderError(new NotFoundException('There is no such page'), ['REQUEST_METHOD' => 'GET'], '/sets/nowhere');

		$this->assertSame(404, $response->getStatusCode());
		$this->assertShowsTheInsideOfTheError($output, true);
	}

	//The admins need the traces on the live site, where debug is off.
	public function testAnAdminSeesTheErrorItselfWithoutDebug()
	{
		Configure::write('debug', false);
		new ContextPreparator(['user' => ['admin' => true]]);
		[, $output] = $this->renderError(new NotFoundException('There is no such page'), ['REQUEST_METHOD' => 'GET'], '/sets/nowhere');

		$this->assertShowsTheInsideOfTheError($output, true);
	}

	public function testAPlayerSeesNothingOfTheErrorItselfWithoutDebug()
	{
		Configure::write('debug', false);
		new ContextPreparator(['user' => ['name' => 'player']]);
		[, $output] = $this->renderError(new NotFoundException('There is no such page'), ['REQUEST_METHOD' => 'GET'], '/sets/nowhere');

		$this->assertShowsTheInsideOfTheError($output, false);
	}

	//A fault inside, like a failed query, answers JSON with its status only: its message can name
	//tables, columns or files. With debug on, the message is there to read.
	public function testAJsonAnswerToAnInternalErrorKeepsItsMessageToDebug()
	{
		$fault = new RuntimeException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'secret' in 'user'");

		Configure::write('debug', false);
		[$response, $output] = $this->renderError($fault, ['HTTP_ACCEPT' => 'application/json']);
		$this->assertSame(500, $response->getStatusCode());
		$this->assertSame('{"error":"Internal Server Error"}', $output);

		Configure::write('debug', true);
		[, $output] = $this->renderError($fault, ['HTTP_ACCEPT' => 'application/json']);
		$this->assertStringContainsString('Unknown column', $output);
	}

	//The message of an HttpException is the site's answer, so it stays without debug.
	public function testAJsonAnswerKeepsTheMessageOfAnHttpExceptionWithoutDebug()
	{
		Configure::write('debug', false);
		[$response, $output] = $this->renderError(new UnprocessableContentException('Failed to add comment'), ['HTTP_ACCEPT' => 'application/json']);

		$this->assertSame(422, $response->getStatusCode());
		$this->assertSame('{"error":"Failed to add comment"}', $output);
	}
}
