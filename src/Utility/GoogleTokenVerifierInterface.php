<?php

namespace App\Utility;

//Interface for Google ID token verification.
interface GoogleTokenVerifierInterface
{
	//Verify a Google ID token and return the payload.
	//$idToken: The ID token from Google Sign-In
	//Returns the decoded payload on success, null on failure
	//Expected payload keys: sub, email, name, picture, email_verified
	public function verify(string $idToken): ?array;
}
