<?php

//The secrets of the site, in the file and the form they had on Cake 2: the forum's single sign-on
//(webroot/forums/ext/tsumego/customsso/auth/provider/jwtauth.php) reads Security.salt out of the text
//of this file to check the auth_token cookie, so the line stays a literal Configure::write().
//config/bootstrap.php loads it after app.php and before core.local.php, which may override it.
Configure::write('Security.salt', 'DYhG93b0qyJfliouholkuzglR2G0FgaC9mi');
Configure::write('Security.cipherSeed', '76859309657453555596749683645');
