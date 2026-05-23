<?php

namespace Deployer;

require 'vendor/deployer/deployer/recipe/common.php';

// ===== Project Configuration =====

set('application', 'tsumego-hero');
set('keep_releases', 3);
set('git_tty', false);     // Disable TTY - required for non-interactive deploys

// Shared files: config files that persist across releases.
// These must be created in {{deploy_path}}/shared/ before the first deploy.
set('shared_files', [
	'config/database.php',
	'config/core.local.php',
	'config/email.php',
	'webroot/forums/config.php',  // phpBB config (gitignored, has DB credentials)
]);

// Shared directories: persist across releases.
// NOTE: Do NOT share 'tmp' as a whole - asset_compress_build_time lives in tmp/
// and must stay per-release so its version matches that release's cache_css/cache_js files.
// If tmp were shared, rolling back would leave release 1's cache_css files
// but with release 2's build timestamp, causing 404s.
set('shared_dirs', [
	'tmp/sessions',    // user sessions persist across deploys
	'logs',
	'webroot/files',
	'webroot/forums/files',
	'webroot/forums/cache',
	'webroot/forums/store',
	'webroot/forums/images/avatars/upload',
]);

// Directories to chmod 777 on each deploy (built fresh, not shared)
set('writable_dirs', [
	'webroot/cache_css',
	'webroot/cache_js',
	'webroot/dist',
]);

set('writable_mode', 'chmod');
set('writable_chmod_mode', '0777');

// ===== Hosts =====

// host('test')  // test.tsumego.com — uncomment when ready to deploy
// 	->set('hostname', 'ssh.nyc1.nearlyfreespeech.net')
// 	->set('remote_user', 'sorcererontherocks_tsumego-hero')
// 	->set('deploy_path', '/home/public')
// 	->set('public_webroot', '/home/public/webroot')
// 	->set('repository', 'git@github.com:kovarex/tsumego-hero.git')
// 	->set('branch', 'master')
// 	->set('bin/php', '/usr/local/bin/php8.4')  // NFSN CLI default is php8.3; web server uses 8.4
// 	->set('ssh_multiplexing', false);  // multiplexing may fail on some clients

// host('production')  // tsumego.com — fill in server details before enabling
// 	->set('hostname', 'TODO')
// 	->set('remote_user', 'TODO')
// 	->set('deploy_path', 'TODO')
// 	->set('public_webroot', 'TODO')
// 	->set('repository', 'git@github.com:kovarex/tsumego-hero.git')
// 	->set('branch', 'master')
// 	->set('bin/php', '/usr/local/bin/php8.4')  // NFSN CLI default is php8.3; web server uses 8.4
// 	->set('ssh_multiplexing', false);

// Local Docker environment — mirrors NFSN production layout (PHP 8.4, MariaDB 10.11)
host('local')
	->set('hostname', '127.0.0.1')
	->set('port', 8022)
	->set('remote_user', 'root')
	->set('identity_file', '.local/docker/deploy_local_key')
	->set('deploy_path', '/var/www/deploy')
	->set('public_webroot', '/var/www/deploy/webroot')  // outer symlink, mirrors NFSN /home/public/webroot
	->set('repository', 'file:///var/www/html')
	->set('git_ssh_command', 'ssh -o StrictHostKeyChecking=no')  // skip host key check for local container
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

// Install PHP dependencies
// Uses {{bin/php}} for PHP binary — allows NFSN hosts to specify /usr/local/bin/php8.4
// since NFSN CLI default php is 8.3 but web server and project require 8.4.
task('deploy:composer', function () {
	cd('{{release_path}}');
	$phpBin = get('bin/php', 'php');
	$isDev = get('is_dev', false);
	if ($isDev)
		run("$phpBin $(which composer) install --prefer-dist --optimize-autoloader --no-interaction");
	else
		run("$phpBin $(which composer) install --prefer-dist --no-dev --optimize-autoloader --no-interaction");
});

// Run Phinx database migrations
task('deploy:migrate', function () {
	cd('{{release_path}}');
	run('vendor/bin/phinx migrate', timeout: 900); // Init migration on empty DB can take 5-10 min
});

// Build React/Vite frontend
task('deploy:build_frontend', function () {
	cd('{{release_path}}');
	run('CI=true npx pnpm@latest install --frozen-lockfile');
	run('CI=true npx pnpm@latest run build');
});

// Build and minify CSS/JS with AssetCompress
task('deploy:build_assets', function () {
	cd('{{release_path}}');
	// Ensure CakePHP TMP cache subdirs exist (not shared - per-release for correct versioning)
	run('mkdir -p tmp/cache/models tmp/cache/persistent tmp/cache/views tmp/cache/asset_compress tmp/logs');
	run('chmod -R 777 tmp');
	run('mkdir -p webroot/cache_js webroot/cache_css');
	run('chmod 777 webroot/cache_js webroot/cache_css');
	run('./bin/cake asset_compress clear');
	run('./bin/cake asset_compress build --force');
});

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
task('init', function () {
	$sharedPath = get('deploy_path') . '/shared';

	// Create TMP subdirectories required by CakePHP
	run("mkdir -p $sharedPath/tmp/cache/models $sharedPath/tmp/cache/persistent $sharedPath/tmp/cache/views $sharedPath/tmp/cache/asset_compress $sharedPath/tmp/logs $sharedPath/tmp/sessions");
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
// IMPORTANT: deploy:composer/migrate/build_assets must run AFTER deploy:shared
// because shared/tmp/ must be symlinked before CakePHP CLI can write to TMP.
// Hook after deploy:writable to ensure shared dirs are set up first.

after('deploy:writable', 'deploy:composer');
after('deploy:composer', 'deploy:migrate');
after('deploy:migrate', 'deploy:build_frontend');
after('deploy:build_frontend', 'deploy:build_assets');

// On deploy failure, release the lock so next deploy can proceed
after('deploy:failed', 'deploy:unlock');
