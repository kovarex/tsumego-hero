<?php

/**
 * The verb an action declares is the only verb it answers to.
 */
class HttpVerbTest extends IntegrationTestCase
{
	public function testAPageRefusesPost(): void
	{
		$this->post('/changelog');

		$this->assertResponseCode(405);
		$this->assertHeader('Allow', 'GET');
	}

	public function testASubmitRefusesGet(): void
	{
		$this->get('/users/avatarPreference');

		$this->assertResponseCode(405);
		$this->assertHeader('Allow', 'POST');
	}

	/**
	 * A HEAD request asks for the headers of a page, so a page answers it like GET.
	 */
	public function testAPageAnswersHead(): void
	{
		$this->head('/changelog');

		$this->assertResponseOk();
		$this->assertResponseContains("What's new");
	}
}
