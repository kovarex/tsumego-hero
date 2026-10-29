<?php

use App\Utility\Mailer;

//The mailer of a test (see AppController::_setTestEmailer()): it keeps the last email instead of sending it.
class TestEmailer extends Mailer
{
	public function __construct()
	{
		self::$lastEmail = [];
	}

	public function from(string|array $from): void
	{
		self::$lastEmail['from'] = $from;
	}

	public function to(string $to): void
	{
		self::$lastEmail['to'] = $to;
	}

	public function subject(string $subject): void
	{
		self::$lastEmail['subject'] = $subject;
	}

	public function send(string $body): void
	{
		// A mailbox that is not reachable, the way mail() reports it
		if (self::$unreachable)
			throw new SocketException('Could not send email: unknown');

		self::$lastEmail['body'] = $body;
	}
	public static $lastEmail = null;
	public static bool $unreachable = false;
};
