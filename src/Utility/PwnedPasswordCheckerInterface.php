<?php

namespace App\Utility;

//Asks a public breach list whether a password is already known to be leaked.
interface PwnedPasswordCheckerInterface
{
	//$password: The password as the player typed it
	//Returns whether the password appears in a public breach
	public function isBreached(string $password): bool;
}
