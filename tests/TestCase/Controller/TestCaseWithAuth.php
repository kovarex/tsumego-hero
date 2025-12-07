<?php

use DOM\HTMLDocument as DOMDocument;
use Facebook\WebDriver\WebDriverBy;

class TestCaseWithAuth extends ControllerTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		// Clear auth state
		Auth::logout();
		unset($_COOKIE['hackedLoggedInUserID']);
	}

	public function login($username)
	{
		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $username]]);
		$this->assertNotNull($user);
		$_COOKIE['hackedLoggedInUserID'] = $user['User']['id'];
		Auth::init();
	}

	public function logout()
	{
		Auth::logout();
		unset($_COOKIE['hackedLoggedInUserID']);
	}

	public function setLoggedIn(bool $loggedIn)
	{
		if ($loggedIn)
			$this->login('kovarex');
		else
			$this->logout();
	}

	public function testLogin()
	{
		$this->assertFalse(Auth::isLoggedIn());
		$this->login('kovarex');
		$this->assertTrue(Auth::isLoggedIn());
	}

	public function getStringDom()
	{
		$dom = DOMDocument::createFromString($this->view, LIBXML_HTML_NOIMPLIED);
		return $dom;
	}

	protected function checkNavigationButtonsBeforeAndAfterSolving($browser, int $count, $context, $indexFunction, $orderFunction, int $currentIndex, string $currentStatus): void
	{
		$this->checkPlayNavigationButtons($browser, $count, $context, $indexFunction, $orderFunction, $currentIndex, $currentStatus);
		$browser->playWithResult('S'); // mark the problem solved
		$this->checkPlayNavigationButtons($browser, $count, $context, $indexFunction, $orderFunction, $currentIndex, 'S');
	}

	protected function checkPlayNavigationButtons($browser, int $count, $context, $indexFunction, $orderFunction, int $currentIndex, string $currentStatus): void
	{
		$navigationButtons = $browser->driver->findElements(WebDriverBy::cssSelector('div.tsumegoNavi2 li'));

		$this->assertCount($count, $navigationButtons);
		foreach ($navigationButtons as $key => $button)
			$this->checkNavigationButton($button, $context, $indexFunction($key), $orderFunction($key), $indexFunction($currentIndex), $currentStatus);
	}

	protected function checkNavigationButton($button, $context, int $index, int $order, ?int $currentIndex = null, ?string $currentStatus = null): void
	{
		$this->assertSame($button->getText(), strval($order));
		if (is_null($currentIndex) || $index != $currentIndex)
		{
			$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $context->otherTsumegos[$index]['id']]]);
			$statusValue = $status ? $status['TsumegoStatus']['status'] : 'N';
		}
		else
			$statusValue = $currentStatus;

		$this->assertSame(explode(" ", $button->getAttribute('class'))[0], 'status' . $statusValue);
		$link = $button->findElement(WebDriverBy::tagName('a'));
		$this->assertTextStartsWith('/' . $context->otherTsumegos[$index]['set-connections'][0]['id'], $link->getAttribute('href'));
	}
}
