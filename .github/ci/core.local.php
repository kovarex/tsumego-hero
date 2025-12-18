<?php

/**
 * Local configuration overrides for CI environment
 */

// Enable debug mode (required for JavaScript error tracking in Selenium tests)
Configure::write('debug', 2);

// Dummy cron secret for tests
define('CRON_SECRET', 'ci-test-secret');
