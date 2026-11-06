<?php

//The secret the auth_token cookie (the session of a signed in player) is signed and checked with.
//Copy this file to config/jwt_secret.php on each server, which git ignores, and put a random value
//of its own in it, made on that server:
//	php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
//The site (config/bootstrap.php → JwtAuth) and the forum's single sign-on
//(webroot/forums/ext/tsumego/customsso/auth/provider/jwtauth.php) both read it, and neither starts
//signing anyone in without it. Changing it signs everyone out once.
//Empty here, so a copy left as it is fails loudly rather than sign with a value anyone can read.
return '';
