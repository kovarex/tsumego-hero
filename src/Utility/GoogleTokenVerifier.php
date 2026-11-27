<?php

namespace App\Utility;

//Real Google token verifier using tokeninfo endpoint.
class GoogleTokenVerifier implements GoogleTokenVerifierInterface
{
	private static ?GoogleTokenVerifierInterface $testInstance = null;

	//How long the sign-in waits for Google: without a limit a hanging answer kept the PHP process for
	//default_socket_timeout (60 seconds), a process the other requests of the site wait for
	private const int TIMEOUT_SECONDS = 10;

	private string $clientId;

	public function __construct(string $clientId)
	{
		$this->clientId = $clientId;
	}

	//Answer the token check with the given verifier instead of the network, in the test suite
	//only. The guard is the same one the password checker uses, so both seams can be dropped
	//for the same request.
	public static function useForTests(?GoogleTokenVerifierInterface $verifier): void
	{
		self::$testInstance = Util::isOnTestSite() ? $verifier : null;
	}

	public static function testInstance(): ?GoogleTokenVerifierInterface
	{
		return self::$testInstance;
	}

	//The verifier a caller should use: the one a test put in place, or one that asks
	//Google.
	public static function create(string $clientId): GoogleTokenVerifierInterface
	{
		return self::$testInstance ?? new self($clientId);
	}

	//Verify a Google ID token using the tokeninfo endpoint.
	//$idToken: The ID token from Google Sign-In
	//Returns the decoded payload on success, null on failure
	public function verify(string $idToken): ?array
	{
		$tokenInfoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
		$context = stream_context_create(['http' => ['timeout' => self::TIMEOUT_SECONDS]]);
		$response = RequestTiming::external(fn() => @file_get_contents($tokenInfoUrl, false, $context));

		if ($response === false)
			return null;

		$data = json_decode($response, true);
		if (!is_array($data))
			return null;

		// Verify the audience matches our client ID
		if (!isset($data['aud']) || $data['aud'] !== $this->clientId)
			return null;

		// Verify email is verified
		if (!isset($data['email_verified']) || $data['email_verified'] !== 'true')
			return null;

		return [
			'sub' => $data['sub'] ?? null,
			'email' => $data['email'] ?? null,
			'name' => $data['name'] ?? null,
			'picture' => $data['picture'] ?? null,
			'email_verified' => true,
		];
	}
}
