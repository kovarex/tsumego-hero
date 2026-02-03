<?php

App::uses('GoogleTokenVerifierInterface', 'Utility');

/**
 * Real Google token verifier using tokeninfo endpoint.
 */
class GoogleTokenVerifier implements GoogleTokenVerifierInterface
{
	private string $clientId;

	public function __construct(string $clientId)
	{
		$this->clientId = $clientId;
	}

	/**
	 * Verify a Google ID token using the tokeninfo endpoint.
	 *
	 * @param string $idToken The ID token from Google Sign-In
	 * @return array|null The decoded payload on success, null on failure
	 */
	public function verify(string $idToken): ?array
	{
		$tokenInfoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
		$response = @file_get_contents($tokenInfoUrl);

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
