<?php

namespace Deployer;

require 'vendor/deployer/deployer/recipe/common.php';
require 'vendor/deployer/deployer/recipe/deploy/vendors.php';

// ===== Project Configuration =====

set('application', 'tsumego-hero');
set('keep_releases', 3);
set('git_tty', false);     // Disable TTY - required for non-interactive deploys
set('default_timeout', 600);  // Shared hosting builds can be slow

// Composer options — verbose for debugging, no-progress for CI log readability.
// No dev dependencies on production.
// WARNING: Do NOT add --optimize-autoloader. It generates a classmap that skips
// CakePHP 2.x function files (basics.php etc.), causing "undefined function" fatals.
set('composer_options', '--verbose --prefer-dist --no-progress --no-interaction --no-dev');

// Shared files: config files that persist across releases.
// These must be created in {{deploy_path}}/shared/ before the first deploy.
set('shared_files', [
	'config/database.php',
	'config/email.php',
	'config/core.local.php',
	'webroot/forums/config.php',  // phpBB config (gitignored, has DB credentials)
]);

// Shared directories: persist across releases.
set('shared_dirs', [
	'tmp',             // CakePHP cache, sessions, logs — shared to preserve login sessions across deploys
	'logs',
	'webroot/files',
	'webroot/forums/files',
	'webroot/forums/cache',
	'webroot/forums/store',
	'webroot/forums/images/avatars/upload',
]);

// Directories to chmod 777 on each deploy.
// webroot/dist is handled after upload (it does not exist yet during deploy:writable).
set('writable_dirs', [
	'tmp',
]);

set('writable_mode', 'chmod');
set('writable_chmod_mode', '0777');

// Strip dev/test/tooling files from releases to save disk space on shared hosting.
// IMPORTANT: Keep composer.json and composer.lock — the server needs them for composer install.
set('clear_paths', [
	'tests',
	'node_modules',
	'.github',
	'phpstan.neon',
	'phpstan-baseline.neon',
	'phpunit.xml.dist',
	'phpcs.xml',
	'vite.config.js',
	'tsconfig.json',
	'tsconfig.node.json',
	'pnpm-workspace.yaml',
	'eslint.config.js',
	'package.json',
	'pnpm-lock.yaml',
]);

// ===== Hosts =====

// host('test')  // test.tsumego.com — uncomment when ready to deploy
// 	->set('hostname', 'ssh.nyc1.nearlyfreespeech.net')
// 	->set('remote_user', 'sorcererontherocks_tsumego-hero')
// 	->set('deploy_path', '/home/public')
// 	->set('public_webroot', '/home/public/webroot')
// 	->set('repository', 'git@github.com:kovarex/tsumego-hero.git')
// 	->set('branch', 'master')
// 	->set('bin/php', 'php8.4')  // NFSN: use minor symlink, NOT full path (patch dirs may vanish)
// 	->set('ssh_multiplexing', false);  // multiplexing may fail on some clients

// host('production')  // tsumego.com — fill in server details before enabling
// 	->set('hostname', 'TODO')
// 	->set('remote_user', 'TODO')
// 	->set('deploy_path', 'TODO')
// 	->set('public_webroot', 'TODO')
// 	->set('repository', 'git@github.com:kovarex/tsumego-hero.git')
// 	->set('branch', 'master')
// 	->set('bin/php', 'php8.4')  // NFSN: use minor symlink, NOT full path (patch dirs may vanish)
// 	->set('ssh_multiplexing', false);

// Local Docker environment — mirrors NFSN production layout (PHP 8.4, MariaDB 10.11)
host('local')
	->set('hostname', '127.0.0.1')
	->set('port', 8022)
	->set('remote_user', 'root')
	->set('identity_file', '.local/docker/deploy_local_key')
	->set('deploy_path', '/var/www/deploy')
	->set('public_webroot', '/var/www/deploy/webroot')  // outer symlink, mirrors NFSN /home/public/webroot
	->set('repository', 'file:///var/www/html')  // local Docker: clone from mounted project source (no GitHub key needed)
	->set('ssh_multiplexing', false);  // multiplexing causes getsockname errors on Windows

// ===== Tasks =====

// When deploying from GitHub Actions, DEPLOY_SHA is set to the exact commit being deployed.
// This ensures test and production always run the same code, even if new commits were pushed
// while a production deploy was pending approval.
//
// Deployer's built-in 'revision' option tells deploy:update_code to archive that exact commit
// from the cached bare mirror (.dep/repo). Much faster than re-cloning every time.
// Falls back to branch-based clone when DEPLOY_SHA is not set (local / docker-test).
if ($deploySha = getenv('DEPLOY_SHA'))
	set('revision', $deploySha);

// Run Phinx database migrations
task('deploy:migrate', function () {
	cd('{{release_path}}');
	run('vendor/bin/phinx migrate', timeout: 900); // Init migration on empty DB can take 5-10 min
});

// Upload pre-built frontend assets (Vite/React) to the server.
// Build (pnpm build) must run BEFORE deploying — either manually (local dev)
// or as a CI step (GitHub Actions). The server never touches Node.js.
task('deploy:build_frontend', function () {
	// Upload pre-built assets to the release
	// NOTE: upload() places source directory INTO destination, so we target webroot/
	// to get webroot/dist/ (not webroot/dist/dist/).
	upload(__DIR__ . '/webroot/dist', '{{release_path}}/webroot');

	// Ensure web server can serve the built files
	run('chmod -R 777 {{release_path}}/webroot/dist');
})->desc('Upload pre-built frontend assets');

// Clear CakePHP cache after deploy to prevent stale views/models/config
task('deploy:clear_cache', function () {
	run('rm -rf {{release_path}}/tmp/cache/*');
	// Recreate cache subdirectories required by CakePHP's File cache engine.
	// Without these, Cache::config() in core.php hangs because it cannot write
	// to the cache path.
	run('mkdir -p {{release_path}}/tmp/cache/models {{release_path}}/tmp/cache/persistent {{release_path}}/tmp/cache/views');
	run('chmod -R 777 {{release_path}}/tmp/cache');
})->desc('Clear CakePHP cache and recreate required subdirectories');

// Update the /home/public/webroot symlink to point to the current release's webroot.
// Runs automatically after every deploy and rollback.
task('deploy:update_webroot_symlink', function () {
	$publicWebroot = get('public_webroot', null);
	if ($publicWebroot === null)
		return; // docker-test doesn't need this

	// Fail with a clear message if webroot is still a real directory (pre-migration state).
	$isRealDir = run("[ -d '$publicWebroot' ] && [ ! -L '$publicWebroot' ] && echo yes || echo no");
	if (trim($isRealDir) === 'yes')
		throw new \RuntimeException("$publicWebroot is a real directory, not a symlink. Run the one-time webroot migration before deploying.");

	// Create or update the symlink using the stable /current/ path, not a release number.
	// Rollback updates /current, so the webroot follows automatically.
	run("ln -sfn {{current_path}}/webroot $publicWebroot");
});
after('deploy:symlink', 'deploy:update_webroot_symlink');
after('rollback', 'deploy:update_webroot_symlink');

// Initialize shared directory structure (run once before first deploy)
// Creates required TMP subdirs and db config skeleton
task('setup:shared', function () {
	$sharedPath = get('deploy_path') . '/shared';

	// Create TMP subdirectories required by CakePHP
	run("mkdir -p $sharedPath/tmp/cache/models $sharedPath/tmp/cache/persistent $sharedPath/tmp/cache/views $sharedPath/tmp/logs $sharedPath/tmp/sessions");
	run("chmod -R 777 $sharedPath/tmp");

	// Create config directory
	run("mkdir -p $sharedPath/config");

	info("Shared directory initialized.");
	info("Before deploying, copy these files to $sharedPath/config/:");
	info("  - database.php  (database credentials)");
	info("  - core.local.php  (debug=0, security salt, etc.)");
	info("  - email.php  (SMTP credentials, optional)");
});

// ===== Deploy flow =====
// Deployer's built-in 'deploy' runs: deploy:prepare → deploy:publish
// deploy:prepare = [info, setup, lock, release, update_code, env, shared, writable]
// deploy:publish = [symlink, unlock, cleanup, success]
//
// Upload pre-built frontend assets right after code lands.
after('deploy:update_code', 'deploy:build_frontend');

// Server-side steps: Composer → Phinx migrations → clear CakePHP cache
after('deploy:writable', 'deploy:vendors');
after('deploy:vendors', 'deploy:migrate');
after('deploy:migrate', 'deploy:clear_cache');

// On deploy failure, release the lock so next deploy can proceed
after('deploy:failed', 'deploy:unlock');
