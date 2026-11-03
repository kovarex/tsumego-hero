<?php

use App\Mailer\Transport\DkimMailTransport;
use App\Utility\Mailer;
use Cake\Mailer\Mailer as CakeMailer;

//The transport that keeps what it would hand to mail()
class CapturingDkimMailTransport extends DkimMailTransport
{
	protected function sendMail(string $to, string $subject, string $message, string $headers, string $params): void
	{
		$this->sent = ['to' => $to, 'subject' => $subject, 'body' => $message, 'headers' => $headers];
	}

	/** @var array<string, string> */
	public array $sent = [];
}

//The site's mail carries a DKIM signature of tsumego.com that a receiver can verify against the
//public key in DNS, and goes out unsigned, as before, where no key is configured.
class DkimMailTransportTest extends AppTestCase
{
	public function testTheSignatureVerifiesAgainstThePublicKey(): void
	{
		[$privateKey, $publicKey] = $this->keyPair();
		$sent = $this->send(['domain' => 'tsumego.com', 'selector' => 'nfsn', 'privateKey' => $privateKey]);

		$tags = $this->signatureTags($sent);
		$this->assertSame('1', $tags['v']);
		$this->assertSame('rsa-sha256', $tags['a']);
		$this->assertSame('relaxed/relaxed', $tags['c']);
		$this->assertSame('tsumego.com', $tags['d']);
		$this->assertSame('nfsn', $tags['s']);
		$this->assertSame('from:to:subject:date:message-id:mime-version:content-type:content-transfer-encoding', $tags['h']);
		$this->assertSame(base64_encode(hash('sha256', $this->canonicalBody($sent['body']), true)), $tags['bh']);
		$this->assertSame(1, $this->verify($sent, $publicKey));
	}

	public function testAChangedSubjectBreaksTheSignature(): void
	{
		[$privateKey, $publicKey] = $this->keyPair();
		$sent = $this->send(['domain' => 'tsumego.com', 'selector' => 'nfsn', 'privateKey' => $privateKey]);

		$sent['subject'] = 'Something else';
		$this->assertSame(0, $this->verify($sent, $publicKey));
	}

	public function testTheKeyCanBeAFile(): void
	{
		[$privateKey, $publicKey] = $this->keyPair();
		$path = tempnam(sys_get_temp_dir(), 'dkim');
		file_put_contents($path, $privateKey);
		try
		{
			$sent = $this->send(['domain' => 'tsumego.com', 'selector' => 'nfsn', 'privateKey' => $path]);
		}
		finally
		{
			unlink($path);
		}
		$this->assertSame(1, $this->verify($sent, $publicKey));
	}

	public function testWithoutAKeyTheMailGoesOutUnsigned(): void
	{
		$sent = $this->send(null);

		$this->assertStringNotContainsString('DKIM-Signature', $sent['headers']);
		$this->assertStringContainsString('From: Tsumego Hero <no-reply@tsumego.com>', $sent['headers']);
		$this->assertSame('player@t-online.de', $sent['to']);
	}

	public function testTheBodyIsQuotedPrintableAndSevenBit(): void
	{
		$sent = $this->send(null);

		$this->assertStringContainsString('Content-Transfer-Encoding: quoted-printable', $sent['headers']);
		$this->assertSame(1, preg_match('/^[\x00-\x7F]*$/', $sent['body']));
		$this->assertStringContainsString('Gr=C3=BC=C3=9Fe', $sent['body']);
	}

	/**
	 * @param array<string, string>|null $dkim
	 * @return array<string, string>
	 */
	private function send(?array $dkim): array
	{
		$transport = new CapturingDkimMailTransport($dkim === null ? [] : ['dkim' => $dkim]);
		$mailer = new CakeMailer('default');
		$mailer->setTransport($transport);
		$mailer->setFrom(Mailer::SITE_SENDER);
		$mailer->setTo('player@t-online.de');
		$mailer->setSubject('Password reset for Tsumego Hero – Grüße');
		$mailer->deliver("Hello,  \n\nGrüße from Tsumego Hero.\n\tA line with a tab \nhttps://tsumego.com/users/newpassword/abc\n\n\n");
		return $transport->sent;
	}

	/** @return array{string, string} */
	private function keyPair(): array
	{
		$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
		openssl_pkey_export($key, $privateKey);
		return [$privateKey, openssl_pkey_get_details($key)['key']];
	}

	//The header lines as the receiver sees them: mail() writes To and Subject after the headers it gets
	/**
	 * @param array<string, string> $sent
	 * @return list<string>
	 */
	private function receivedHeaders(array $sent): array
	{
		$unfolded = preg_replace("/\r\n(?=[ \t])/", "\x01", $sent['headers'] . "\r\nTo: " . $sent['to'] . "\r\nSubject: " . $sent['subject']);
		return array_map(fn($line) => str_replace("\x01", "\r\n", $line), explode("\r\n", $unfolded));
	}

	/**
	 * @param array<string, string> $sent
	 * @return array<string, string>
	 */
	private function signatureTags(array $sent): array
	{
		$signature = current(array_filter($this->receivedHeaders($sent), fn($line) => str_starts_with($line, 'DKIM-Signature:')));
		$this->assertIsString($signature);
		$tags = [];
		foreach (explode(';', substr($signature, strlen('DKIM-Signature:'))) as $tag)
			if (trim($tag) !== '')
			{
				[$name, $value] = explode('=', $tag, 2);
				$tags[trim($name)] = preg_replace('/\s+/', '', $value);
			}
		return $tags;
	}

	//The verifier's side of RFC 6376 (relaxed/relaxed), written apart from the transport
	/** @param array<string, string> $sent */
	private function verify(array $sent, string $publicKey): int
	{
		$tags = $this->signatureTags($sent);
		$headers = $this->receivedHeaders($sent);
		$data = '';
		foreach (explode(':', $tags['h']) as $name)
			foreach (array_reverse($headers) as $line)
				if (strcasecmp(trim(explode(':', $line, 2)[0]), $name) === 0)
				{
					$data .= $this->canonicalHeader($line) . "\r\n";
					break;
				}
		$signature = current(array_filter($headers, fn($line) => str_starts_with($line, 'DKIM-Signature:')));
		$data .= $this->canonicalHeader(preg_replace('/b=[^;]*$/', 'b=', $signature));
		return openssl_verify($data, base64_decode($tags['b']), $publicKey, OPENSSL_ALGO_SHA256);
	}

	private function canonicalHeader(string $line): string
	{
		[$name, $value] = explode(':', $line, 2);
		return strtolower(rtrim($name)) . ':' . trim(preg_replace('/\s+/', ' ', str_replace("\r\n", '', $value)));
	}

	private function canonicalBody(string $body): string
	{
		$canonical = '';
		foreach (explode("\n", str_replace("\r\n", "\n", $body)) as $line)
			$canonical .= rtrim(preg_replace('/[ \t]+/', ' ', $line)) . "\r\n";
		return preg_replace("/(\r\n)+$/", "\r\n", $canonical);
	}
}
