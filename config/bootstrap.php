<?php

use App\Utility\Util;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\Datasource\ConnectionManager;
use Cake\Error\ErrorTrap;
use Cake\Error\ExceptionTrap;
use Cake\Log\Log;
use Cake\Mailer\Mailer;
use Cake\Mailer\TransportFactory;
use Cake\Routing\Router;

require __DIR__ . DIRECTORY_SEPARATOR . 'paths.php';
require CORE_PATH . 'config' . DS . 'bootstrap.php';
require CAKE . 'Core' . DS . 'functions_global.php';
require CAKE . 'I18n' . DS . 'functions_global.php';
require __DIR__ . DS . 'define.php';
require __DIR__ . DS . 'functions.php';

Configure::config('default', new PhpConfig());
Configure::load('app', 'default', false);
//The core.local.php of each server writes with the global Configure of Cake 2, so it keeps working
//through an alias. It holds the salt of the server (Security.salt), which is not in git.
class_alias(Configure::class, 'Configure');
if (file_exists(__DIR__ . DS . 'core.local.php'))
	require __DIR__ . DS . 'core.local.php';
//The secret the auth_token cookie is signed with, in a file of its own outside git that the forum's
//single sign-on reads too (see config/jwt_secret.example.php). JwtAuth fails loudly without it.
if (file_exists(__DIR__ . DS . 'jwt_secret.php'))
	Configure::write('Security.jwtSecret', require __DIR__ . DS . 'jwt_secret.php');
//The database of each server is in its config/database.php of Cake 2, which deploy.sh, phinx.php
//and the deploy hook read too. Its default connection becomes the default datasource, with the
//switch to its test connection that its constructor makes on the test site in debug mode.
if (file_exists(__DIR__ . DS . 'database.php'))
{
	require_once __DIR__ . DS . 'database.php';
	$databaseConfig = new DATABASE_CONFIG();
	foreach (['default' => $databaseConfig->default, 'test' => $databaseConfig->test ?? null] as $name => $connection)
		if (is_array($connection))
			Configure::write('Datasources.' . $name, array_merge((array) Configure::read('Datasources.' . $name), array_filter([
				'host' => $connection['host'] ?? null,
				'port' => $connection['port'] ?? null,
				'username' => $connection['login'] ?? null,
				'password' => $connection['password'] ?? null,
				'database' => $connection['database'] ?? null,
				'encoding' => $connection['encoding'] ?? null,
			], fn($value) => $value !== null)));
	unset($databaseConfig, $name, $connection);
}
if (is_int(Configure::read('debug')))
	Configure::write('debug', Configure::read('debug') > 0);

if (Util::isInGithubCI())
	Configure::write('App.fullBaseUrl', 'https://host.docker.internal:8443');

// Fix Chrome POST losing REQUEST_URI behind Apache rewrite
if (empty($_SERVER['REQUEST_URI']) && !empty($_SERVER['REDIRECT_URL']))
	$_SERVER['REQUEST_URI'] = preg_replace('#^/webroot#', '', $_SERVER['REDIRECT_URL']);

date_default_timezone_set(Configure::read('App.defaultTimezone'));
mb_internal_encoding(Configure::read('App.encoding'));
ini_set('intl.default_locale', Configure::read('App.defaultLocale'));

(new ErrorTrap(Configure::read('Error')))->register();
(new ExceptionTrap(Configure::read('Error')))->register();

if (PHP_SAPI !== 'cli' && !Configure::read('App.fullBaseUrl'))
{
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	if (isset($_SERVER['HTTP_HOST']))
		Configure::write('App.fullBaseUrl', $scheme . '://' . $_SERVER['HTTP_HOST']);
}

//Cache.disable of core.local.php sits among the cache configurations of Cake 5
$cacheDisabled = (bool) Configure::consume('Cache.disable');
Cache::setConfig(Configure::consume('Cache'));
if ($cacheDisabled)
	Cache::disable();
ConnectionManager::setConfig(Configure::consume('Datasources'));
TransportFactory::setConfig(Configure::consume('EmailTransport'));
Mailer::setConfig(Configure::consume('Email'));
Log::setConfig(Configure::consume('Log'));
//The salt keys the HMACs of the site (the signed redirect of the login, the demotion form of a
//Google admin). It is only in each server's core.local.php, and the site doesn't start without one
//rather than run with a guessable value.
$salt = (string) Configure::read('Security.salt');
if (strlen($salt) < 32)
	throw new RuntimeException('Security.salt is not set (32 characters or more) in config/core.local.php, see config/core.local.example.php.');
\Cake\Utility\Security::setSalt($salt);
unset($salt);
Router::fullBaseUrl((string) Configure::read('App.fullBaseUrl'));
