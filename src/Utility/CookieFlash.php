<?php

namespace App\Utility;

//Cookie-based flash messages for stateless operation.
//Replaces session-based flash messages with cookie storage.
//Messages are stored as JSON in a cookie and cleared after reading.
//Usage:
//  CookieFlash::set('Your message', 'success')  // Set flash message
//  CookieFlash::get()                           // Get and clear message
//  CookieFlash::getType()                       // Get message type (info/success/error)
//  CookieFlash::has()                           // Check if message exists
class CookieFlash
{
	private const COOKIE_NAME = 'flash_message';

	//Cached flash data
	private static ?array $cache = null;

	//Set a flash message.
	//$message: The message to display
	//$type: Message type: 'info', 'success', 'error', 'warning'
	public static function set(string $message, string $type = 'info'): void
	{
		$data = [
			'message' => $message,
			'type' => $type,
		];

		$json = json_encode($data);
		Util::setCookie(self::COOKIE_NAME, $json, ['expires' => time() + 60, 'secure' => false, 'samesite' => null]);
		self::$cache = $data;
	}

	//Get and clear the flash message.
	//Returns the message, or null if none exists
	public static function get(): ?string
	{
		$data = self::load();
		if (!$data)
			return null;

		// Clear the cookie after reading
		self::clear();

		return $data['message'];
	}

	//Get the message type without clearing.
	//Returns the type ('info', 'success', 'error', 'warning')
	public static function getType(): string
	{
		$data = self::load();
		return $data['type'] ?? 'info';
	}

	//Check if a flash message exists.
	//Returns true if a message exists
	public static function has(): bool
	{
		return self::load() !== null;
	}

	//Clear the flash message.
	public static function clear(): void
	{
		Util::clearCookie(self::COOKIE_NAME, ['secure' => false, 'samesite' => null]);
		self::$cache = null;
	}

	//Clear internal cache (for testing).
	public static function clearCache(): void
	{
		self::$cache = null;
	}

	//Load flash data from cookie.
	//Returns the flash data array or null
	private static function load(): ?array
	{
		if (self::$cache !== null)
			return self::$cache;

		$json = Util::cookie(self::COOKIE_NAME);
		if (empty($json))
			return null;

		$decoded = json_decode($json, true);
		if (!is_array($decoded) || !isset($decoded['message']))
			return null;

		self::$cache = $decoded;
		return self::$cache;
	}

	//Render flash message as HTML (for use in views).
	//Automatically gets type before clearing, then renders the message.
	//Returns HTML output or empty string
	public static function render(): string
	{
		if (!self::has())
			return '';

		$type = self::getType();  // Get type BEFORE get() clears it
		$message = self::get();

		$cssClass = match ($type)
		{
			'error' => 'alert-error',
			'success' => 'alert-success',
			'warning' => 'alert-warning',
			default => 'alert-info',
		};

		return '<div class="alert ' . $cssClass . '" role="alert">' . htmlspecialchars($message) . '</div>';
	}
}
