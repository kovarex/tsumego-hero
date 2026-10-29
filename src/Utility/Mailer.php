<?php

namespace App\Utility;

use Cake\Mailer\Mailer as CakeMailer;
use Cake\Mailer\Transport\DebugTransport;

//The emails the site sends, built and sent with the Mailer of Cake 5 (the 'default' profile).
//The controllers get it from AppController::_getEmailer().
class Mailer
{
	//$buildOnly: the message is built and not sent, as on the test site
	public function __construct(bool $buildOnly = false)
	{
		$this->email = new CakeMailer('default');
		if ($buildOnly)
			$this->email->setTransport(new DebugTransport());
	}

	/** @param string|array<string, string> $from An address, or address => name */
	public function from(string|array $from): void
	{
		$this->email->setFrom($from);
	}

	public function to(string $to): void
	{
		$this->email->setTo($to);
	}

	public function subject(string $subject): void
	{
		$this->email->setSubject($subject);
	}

	public function send(string $body): void
	{
		$this->email->deliver($body);
	}

	private CakeMailer $email;
}
