<?php

// this is hack until nicer solution in newer cake is possible to be used
class TestEmailer
{
	public function __construct()
	{
		self::$lastEmail = [];
	}

	public function from($from)
	{
		self::$lastEmail['from'] = $from;
	}

	public function to($to)
	{
		self::$lastEmail['to'] = $to;
	}

	public function subject($subject)
	{
		self::$lastEmail['subject'] = $subject;
	}

	public function send($body)
	{
		// A mailbox that is not reachable, the way mail() reports it
		if (self::$unreachable)
			throw new SocketException('Could not send email: unknown');

		self::$lastEmail['body'] = $body;
	}
	public static $lastEmail = null;
	public static bool $unreachable = false;
};
