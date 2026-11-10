<?php

/**
 * Local configuration overrides
 * This file is not tracked in git
 */

// Enable debug mode locally
Configure::write('debug', 2);

// Disable cache for development
Configure::write('Cache.disable', true);

// The salt of the HMACs of this server (signed login redirects, the demotion form of a Google admin):
// a random value of its own, made on the server with
//	php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
// The site doesn't start without one of 32 characters or more. The secret of the session cookie is
// separate, in config/jwt_secret.php (see config/jwt_secret.example.php). Empty here, so a copy left as
// it is fails loudly.
Configure::write('Security.salt', '');

// The node that runs scripts/sgfCorrectMoves.mjs (App\Utility\SgfAnalysis), when the web server's PATH has none
// Configure::write('SgfAnalysis.node', '/usr/local/bin/node');
