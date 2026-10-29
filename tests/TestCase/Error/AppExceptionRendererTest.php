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

	//An error raised in a call that got a secret, as a login gets the password.
	private function errorOfACallWith(string $secret): NotFoundException
	{
		return new NotFoundException('There is no such page');
	}

	//The stack trace of the error page, as text.
	private static function traceOf(string $output): string
	{
		preg_match('#Stack Trace</strong>\s*<pre[^>]*>(.*?)</pre>#s', $output, $match);
		return html_entity_decode($match[1] ?? '');
	}

	//Every player sees what went wrong, so they can report it, but the trace names the types of
	//the arguments, never their values: those can be passwords, tokens, e-mails or SQL.
	public function testAPlayerSeesTheTraceWithTheTypesOfTheArgumentsOnly()
	{
		Configure::write('debug', false);
		ini_set('zend.exception_ignore_args', '0');
		new ContextPreparator(['user' => ['name' => 'player']]);
		[$response, $output] = $this->renderError($this->errorOfACallWith('hunter2-the-password'), ['REQUEST_METHOD' => 'GET'], '/sets/nowhere');

		$this->assertSame(404, $response->getStatusCode());
		foreach (['Debug Information', 'NotFoundException', 'There is no such page', 'Stack Trace'] as $detail)
			$this->assertStringContainsString($detail, $output);
		$trace = self::traceOf($output);
		$this->assertStringContainsString('AppExceptionRendererTest->errorOfACallWith(string)', $trace);
		$this->assertStringNotContainsString('hunter2-the-password', $output);
		//no string value at all: they were printed in quotes
		$this->assertStringNotContainsString("'", $trace);
	}

	//The paths start at the root of the site, as in the repository: shorter to report, and they
	//don't tell how the server is laid out.
	public function testTheErrorPageShowsThePathsFromTheRootOfTheSite()
	{
		Configure::write('debug', false);
		[, $output] = $this->renderError(new NotFoundException('There is no such page'), ['REQUEST_METHOD' => 'GET'], '/sets/nowhere');

		$this->assertStringContainsString('tests/TestCase/Error/AppExceptionRendererTest.php', $output);
		$this->assertStringNotContainsString(ROOT . DS, $output);
	}

	//The JSON answer keeps the real message for everyone, as the page does.
	public function testAJsonAnswerKeepsTheMessageOfAnyErrorWithoutDebug()
	{
		Configure::write('debug', false);
		[$response, $output] = $this->renderError(new RuntimeException('The quote could not be generated'), ['HTTP_ACCEPT' => 'application/json']);

		$this->assertSame(500, $response->getStatusCode());
		$this->assertSame('{"error":"The quote could not be generated"}', $output);
	}
}
