<?php

namespace App\Utility;

use CakeEmail;

//The emails the site sends, built and sent with CakeEmail of Cake 2 for now. At the cutover only
//this class moves to the Mailer of Cake 5; the controllers get it from AppController::_getEmailer().
class Mailer
{
	//$buildOnly: the message is built and not sent, as on the test site
	public function __construct(bool $buildOnly = false)
	{
		$this->email = $buildOnly ? new CakeEmail(['transport' => 'Debug']) : new CakeEmail();
	}

	/** @param string|array<string, string> $from An address, or address => name */
	public function from(string|array $from): void
	{
		$this->email->from($from);
	}

	public function to(string $to): void
	{
		$this->email->to($to);
	}

	public function subject(string $subject): void
	{
		$this->email->subject($subject);
	}

	public function send(string $body): void
	{
		$this->email->send($body);
	}

	private CakeEmail $email;
}
