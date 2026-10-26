<?php

/**
 * The verb an action declares is the only verb it answers to.
 */
class HttpVerbTest extends ControllerTestCase
{
	public function testAPageRefusesPost(): void
	{
		$this->expectException(MethodNotAllowedException::class);

		$this->testAction('/changelog', ['method' => 'post']);
	}

	public function testASubmitRefusesGet(): void
	{
		try
		{
			$this->testAction('/users/avatarPreference', ['method' => 'get']);
			$this->fail('a submit answered a GET');
		}
		catch (MethodNotAllowedException $e)
		{
			// The header tells a client which verb would have worked
			$this->assertSame(['Allow' => 'POST'], $e->responseHeader());
		}
	}

	/**
	 * A HEAD request asks for the headers of a page, so a page answers it like GET.
	 */
	public function testAPageAnswersHead(): void
	{
		$page = $this->testAction('/changelog', ['method' => 'head', 'return' => 'contents']);

		$this->assertStringContainsString("What's new", $page);
	}
}
