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
require __DIR__ . DS . 'define.php';
require __DIR__ . DS . 'functions.php';

Configure::config('default', new PhpConfig());
Configure::load('app', 'default', false);
//core.local.php of each server writes with the global Configure of Cake 2 (and the forum reads the
//salt out of its text), so it keeps working through an alias until the servers' files are rewritten.
class_alias(Configure::class, 'Configure');
if (file_exists(__DIR__ . DS . 'core.local.php'))
	require __DIR__ . DS . 'core.local.php';
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
\Cake\Utility\Security::setSalt(Configure::read('Security.salt'));
Router::fullBaseUrl((string) Configure::read('App.fullBaseUrl'));
