<?php

App::uses('GoogleTokenVerifierInterface', 'Utility');

/**
 * Fake Google token verifier for testing.
 *
 * Allows tests to simulate Google Sign-In without making network calls.
 * Pre-configure tokens and their payloads before testing.
 *
 * Usage:
 *   $verifier = new FakeGoogleTokenVerifier();
 *   $verifier->addToken('test-token-123', [
 *       'sub' => '123456789012345678901',
 *       'email' => 'test@gmail.com',
 *       'name' => 'Test User',
 *       'picture' => 'https://lh3.googleusercontent.com/a/test123',
 *       'email_verified' => true,
 *   ]);
 *
 *   // Now the verifier will return this payload for 'test-token-123'
 *   $payload = $verifier->verify('test-token-123');
 */
class FakeGoogleTokenVerifier implements GoogleTokenVerifierInterface
{
	/**
	 * @var array<string, array> Map of token -> payload
	 */
	private array $tokens = [];

	/**
	 * @var array<string> List of tokens that were verified
	 */
	private array $verifiedTokens = [];

	/**
	 * Add a valid token with its payload.
	 *
	 * @param string $token The token string
	 * @param array $payload The payload to return for this token
	 */
	public function addToken(string $token, array $payload): void
	{
		$this->tokens[$token] = $payload;
	}

	/**
	 * Verify a Google ID token.
	 *
	 * @param string $idToken The ID token to verify
	 * @return array|null The payload if token is registered, null otherwise
	 */
	public function verify(string $idToken): ?array
	{
		$this->verifiedTokens[] = $idToken;

		if (!isset($this->tokens[$idToken]))
			return null;

		return $this->tokens[$idToken];
	}

	/**
	 * Get all tokens that were verified (for test assertions).
	 *
	 * @return array<string>
	 */
	public function getVerifiedTokens(): array
	{
		return $this->verifiedTokens;
	}

	/**
	 * Check if a specific token was verified.
	 *
	 * @param string $token The token to check
	 * @return bool
	 */
	public function wasVerified(string $token): bool
	{
		return in_array($token, $this->verifiedTokens, true);
	}

	/**
	 * Reset state for testing.
	 */
	public function reset(): void
	{
		$this->tokens = [];
		$this->verifiedTokens = [];
	}
}
