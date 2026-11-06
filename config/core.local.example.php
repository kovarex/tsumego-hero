<?php

/**
 * Local configuration overrides
 * This file is not tracked in git
 */

// Enable debug mode locally
Configure::write('debug', 2);

// Disable cache for development
Configure::write('Cache.disable', true);
define ('CRON_SECRET', 'example');

// The node that runs scripts/sgfCorrectMoves.mjs (App\Utility\SgfAnalysis), when the web server's PATH has none
// Configure::write('SgfAnalysis.node', '/usr/local/bin/node');
