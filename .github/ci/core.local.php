<?php

//Local configuration overrides for CI environment

// Disable debug mode for production-like environment
Configure::write('debug', 2);

// Disable cache
Configure::write('Cache.disable', true);

// Dummy cron secret for tests
define('CRON_SECRET', 'ci-test-secret');

// The salt of the CI runs only; no server uses it
Configure::write('Security.salt', 'ci-only-salt-of-the-test-runs-not-used-by-any-server');

// The web server of the browser tests reports every PHP warning, notice and deprecation. Each one
// goes to tmp/webserver-errors.log in place of the page it would be printed into, and AppTestCase
// fails the test whose requests raised it.
if (PHP_SAPI === 'cli-server')
{
	Configure::write('Error.errorLevel', E_ALL);
	Cake\Event\EventManager::instance()->on('Error.beforeRender', function (Cake\Event\EventInterface $event): void {
		$error = $event->getData('error');
		$line = sprintf("%s: %s in %s:%d, request %s\n", $error->getLabel(), $error->getMessage(), $error->getFile(), $error->getLine(), $_SERVER['REQUEST_URI'] ?? '');
		file_put_contents(TMP . 'webserver-errors.log', $line, FILE_APPEND | LOCK_EX);
		$event->stopPropagation();
	});
}
