<?php

namespace App\Mailer\Transport;

use Cake\Log\Log;
use Cake\Mailer\Transport\MailTransport;

//PHP's mail(), like Cake's Mail transport, with a DKIM-Signature (RFC 6376, rsa-sha256, relaxed/relaxed)
//of the sender's domain. NFSN's server sends the mail under its own envelope domain, so only the
//signature lets DMARC align with the From domain, which strict receivers (T-Online, ...) require.
//The server's config/core.local.php turns it on, the key stays outside the repository:
//Configure::write('EmailTransport.default.dkim', ['domain' => 'tsumego.com', 'selector' => 'nfsn', 'privateKey' => '/home/protected/dkim/nfsn.pem']);
//The key goes to /home/protected, which the site's PHP can read and the web never serves; a key in
//NFSN's /home/private is out of the web server's reach and can't be read.
//privateKey is a path to the PEM file or the PEM itself. Without it (local, CI) the mail goes out unsigned.
class DkimMailTransport extends MailTransport
{
	public const SIGNED_HEADERS = ['from', 'to', 'subject', 'date', 'message-id', 'mime-version', 'content-type', 'content-transfer-encoding'];

	protected function _mail(string $to, string $subject, string $message, string $headers = '', string $params = ''): void
	{
		$signature = $this->signature($to, $subject, $message, $headers);
		$this->sendMail($to, $subject, $message, $signature === null ? $headers : $signature . "\r\n" . $headers, $params);
	}

	//The hand-over to mail(), which a test replaces to read what would be sent
	protected function sendMail(string $to, string $subject, string $message, string $headers, string $params): void
	{
		parent::_mail($to, $subject, $message, $headers, $params);
	}

	//The DKIM-Signature header line of the mail as mail() gets it (it writes the To and Subject lines itself),
	//null when no key is configured or the key can't be read
	public function signature(string $to, string $subject, string $body, string $headers): ?string
	{
		$dkim = $this->getConfig('dkim');
		if (empty($dkim['domain']) || empty($dkim['selector']) || empty($dkim['privateKey']))
			return null;
		$privateKey = (string) $dkim['privateKey'];
		$key = openssl_pkey_get_private(str_starts_with(trim($privateKey), '-----BEGIN') ? $privateKey : 'file://' . $privateKey);
		if ($key === false)
		{
			Log::error('DKIM: the private key can\'t be read, the mail goes out unsigned');
			return null;
		}

		$present = [];
		foreach (array_merge(self::headerLines($headers), ['To: ' . $to, 'Subject: ' . $subject]) as $line)
		{
			[$name, $value] = explode(':', $line, 2) + [1 => ''];
			if (in_array(strtolower(trim($name)), self::SIGNED_HEADERS, true))
				$present[strtolower(trim($name))] = $name . ':' . $value;
		}
		$signed = array_values(array_filter(self::SIGNED_HEADERS, fn($name) => isset($present[$name])));

		$signature = 'DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed; d=' . $dkim['domain'] . '; s=' . $dkim['selector'] . ";\r\n"
			. "\tt=" . time() . '; h=' . implode(':', $signed) . ";\r\n"
			. "\tbh=" . base64_encode(hash('sha256', self::relaxedBody($body), true)) . ";\r\n"
			. "\tb=";
		$signedData = '';
		foreach ($signed as $name)
			$signedData .= self::relaxedHeader($present[$name]) . "\r\n";
		$signedData .= self::relaxedHeader($signature);
		if (!openssl_sign($signedData, $binarySignature, $key, OPENSSL_ALGO_SHA256))
		{
			Log::error('DKIM: signing failed, the mail goes out unsigned');
			return null;
		}
		return $signature . base64_encode($binarySignature);
	}

	//The header lines, each folded line joined to its header
	/** @return list<string> */
	public static function headerLines(string $headers): array
	{
		$unfolded = preg_replace('/\r?\n(?=[ \t])/', '', $headers);
		return array_values(array_filter(preg_split('/\r?\n/', $unfolded), fn($line) => $line !== ''));
	}

	//RFC 6376 3.4.2: lowercase name, unfolded value, whitespace runs as one space, none around the colon
	public static function relaxedHeader(string $header): string
	{
		[$name, $value] = explode(':', $header, 2) + [1 => ''];
		$value = preg_replace('/[ \t]+/', ' ', preg_replace('/\r?\n(?=[ \t])/', '', $value));
		return strtolower(trim($name)) . ':' . trim($value);
	}

	//RFC 6376 3.4.4: CRLF lines, no trailing whitespace, whitespace runs as one space, no empty lines at the end
	public static function relaxedBody(string $body): string
	{
		$lines = preg_split('/\r?\n/', $body);
		$lines = array_map(fn($line) => rtrim(preg_replace('/[ \t]+/', ' ', $line), ' '), $lines);
		while ($lines !== [] && end($lines) === '')
			array_pop($lines);
		return $lines === [] ? '' : implode("\r\n", $lines) . "\r\n";
	}
}
