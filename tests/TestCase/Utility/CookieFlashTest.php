<?php

App::uses('CakeTestCase', 'TestSuite');
App::uses('CookieFlash', 'Utility');

/**
 * Tests for CookieFlash utility class.
 *
 * CookieFlash provides stateless flash messages using cookies.
 */
class CookieFlashTest extends CakeTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		unset($_COOKIE['flash_message']);
		CookieFlash::clearCache();
	}

	public function tearDown(): void
	{
		parent::tearDown();
		unset($_COOKIE['flash_message']);
		CookieFlash::clearCache();
	}

	public function testSetStoresMessage(): void
	{
		CookieFlash::set('Test message');

		// The message should be stored in the cookie
		$this->assertNotEmpty($_COOKIE['flash_message']);
	}

	public function testGetReturnsMessageAndClearsIt(): void
	{
		CookieFlash::set('Test message');

		$message = CookieFlash::get();

		$this->assertEquals('Test message', $message);
	}

	public function testGetReturnsNullWhenNoMessage(): void
	{
		$message = CookieFlash::get();

		$this->assertNull($message);
	}

	public function testGetClearsMessageAfterReading(): void
	{
		CookieFlash::set('Test message');

		// First read returns message
		$message1 = CookieFlash::get();
		$this->assertEquals('Test message', $message1);

		// Need to clear cache to simulate new request
		CookieFlash::clearCache();

		// Second read returns null (cookie was cleared)
		$message2 = CookieFlash::get();
		$this->assertNull($message2);
	}

	public function testHasReturnsTrueWhenMessageExists(): void
	{
		CookieFlash::set('Test message');

		$this->assertTrue(CookieFlash::has());
	}

	public function testHasReturnsFalseWhenNoMessage(): void
	{
		$this->assertFalse(CookieFlash::has());
	}

	public function testSetWithType(): void
	{
		CookieFlash::set('Error occurred', 'error');

		// Get type BEFORE calling get() which clears the message
		$type = CookieFlash::getType();
		$message = CookieFlash::get();

		$this->assertEquals('Error occurred', $message);
		$this->assertEquals('error', $type);
	}

	public function testDefaultType(): void
	{
		CookieFlash::set('Default message');

		$type = CookieFlash::getType();

		$this->assertEquals('info', $type);
	}
}
