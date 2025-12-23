<?php

/**
 * Local configuration overrides for CI environment
 */

// Disable debug mode for production-like environment
Configure::write('debug', 2);

// Disable cache
Configure::write('Cache.disable', true);

// Dummy cron secret for tests
define('CRON_SECRET', 'ci-test-secret');