import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { chmodSync, copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const REPO = join(dirname(fileURLToPath(import.meta.url)), '..');
const FLAG = 'tmp/maintenance.flag';

//A project the real deploy.sh runs in, with the commands it calls (git, npx, composer, phinx, pkill) as
//stubs that note whether the maintenance flag is there when they run, and that fail when asked to
function project()
{
	const root = mkdtempSync(join(tmpdir(), 'deploy-maintenance-'));
	for (const dir of ['scripts', 'config', 'bin', 'vendor/bin', 'webroot/forums/cache', 'webroot/forums/store', 'webroot/forums/files', 'webroot/forums/images/avatars/upload'])
		mkdirSync(join(root, dir), { recursive: true });
	copyFileSync(join(REPO, 'deploy.sh'), join(root, 'deploy.sh'));
	copyFileSync(join(REPO, 'scripts/ensure-secrets.sh'), join(root, 'scripts/ensure-secrets.sh'));
	// a server that has its configuration and secrets, so the deploy asks for nothing
	writeFileSync(join(root, 'config/database.php'), "<?php\n");
	writeFileSync(join(root, 'webroot/forums/config.php'), "<?php\n");
	writeFileSync(join(root, 'config/jwt_secret.php'), `<?php\nreturn '${'a'.repeat(64)}';\n`);
	writeFileSync(join(root, 'config/core.local.php'), `<?php\nConfigure::write('Security.salt', '${'b'.repeat(64)}');\n`);
	const stub = `#!/bin/bash
name=$(basename "$0")
flag=no; [ -f "$PROJECT/${FLAG}" ] && flag=yes
echo "$name $1 flag=$flag reexec=\${DEPLOY_REEXEC:-}" >> "$PROJECT/calls.log"
[ "$FAIL" = "$name $1" ] && exit 1
exit 0
`;
	for (const file of ['bin/git', 'bin/npx', 'bin/composer', 'bin/pkill', 'vendor/bin/phinx'])
	{
		writeFileSync(join(root, file), stub);
		chmodSync(join(root, file), 0o755);
	}
	return root;
}

function deploy(root, fail = '')
{
	const result = spawnSync('bash', ['deploy.sh'], {
		cwd: root,
		encoding: 'utf-8',
		env: { PATH: `${join(root, 'bin')}:/usr/bin:/bin`, HOME: root, PROJECT: root, FAIL: fail },
	});
	const calls = existsSync(join(root, 'calls.log')) ? readFileSync(join(root, 'calls.log'), 'utf-8').trim().split('\n') : [];
	return { status: result.status, stdout: result.stdout, stderr: result.stderr, calls };
}

test('the maintenance page is on from before the pull, through the re-exec, to the end of the deploy', () => {
	const root = project();

	const { status, stdout, stderr, calls } = deploy(root);

	assert.equal(status, 0, stderr);
	assert.equal(calls[0], 'git pull flag=yes reexec=');
	// the steps after the pull run in the re-executed script, the flag still there
	assert.ok(calls.length > 3);
	for (const call of calls.slice(1))
		assert.match(call, / flag=yes reexec=1$/, call);
	assert.ok(!existsSync(join(root, FLAG)), 'the flag is gone after the deploy');
	assert.match(stdout, /=== Maintenance page on ===/);
	assert.match(stdout, /=== Maintenance page off ===/);
	assert.match(stdout, /=== Deploy complete ===/);
});

test('a deploy that fails takes the maintenance page away and says it failed', () => {
	const root = project();

	const { status, stderr, calls } = deploy(root, 'composer install');

	assert.notEqual(status, 0);
	assert.ok(calls.includes('composer install flag=yes reexec=1'));
	assert.ok(!existsSync(join(root, FLAG)), 'the flag is gone after the failed deploy');
	assert.match(stderr, /=== Deploy FAILED \(exit 1\): the maintenance page is off again, check the site ===/);
});

test('a pull that fails takes the maintenance page away before anything else runs', () => {
	const root = project();

	const { status, stderr, calls } = deploy(root, 'git pull');

	assert.notEqual(status, 0);
	assert.deepEqual(calls, ['git pull flag=yes reexec=']);
	assert.ok(!existsSync(join(root, FLAG)));
	assert.match(stderr, /Deploy FAILED/);
});

//Apache decides alone, from the same flag the deploys set: the site and the forum, whose rewrite rules
//replace those of webroot/, both answer with the 503
test('the site, the forum and both deploys use the same flag', () => {
	const read = (file) => readFileSync(join(REPO, file), 'utf-8');
	assert.match(read('deploy.sh'), /MAINTENANCE_FLAG="\$ROOT_DIR\/tmp\/maintenance\.flag"/);
	const hook = read('scripts/deploy-test-hook.cgi');
	assert.match(hook, /MAINTENANCE_FLAG=\$REPO_DIR\/tmp\/maintenance\.flag/);
	assert.ok(hook.indexOf('date > "$MAINTENANCE_FLAG"') < hook.indexOf('git checkout'));
	assert.match(hook, /trap '[^']*rm -f "\$MAINTENANCE_FLAG"[^']*' EXIT/);
	for (const htaccess of ['webroot/.htaccess', 'webroot/forums/.htaccess'])
	{
		const rules = read(htaccess);
		assert.match(rules, /RewriteCond %\{DOCUMENT_ROOT\}\/tmp\/maintenance\.flag -f \[OR\]\n\s*RewriteCond %\{DOCUMENT_ROOT\}\/\.\.\/tmp\/maintenance\.flag -f\n/, htaccess);
		assert.match(rules, /RewriteRule \^ - \[R=503,L\]/, htaccess);
	}
	assert.match(read('webroot/.htaccess'), /ErrorDocument 503 \/maintenance\.html/);
	assert.match(read('webroot/maintenance.html'), /Upgrade in progress/);
});

test('the deploy stops the push hub after the migrations, with the maintenance page still on', () => {
	const root = project();

	const { status, stderr, calls } = deploy(root);

	assert.equal(status, 0, stderr);
	assert.ok(calls.includes('pkill -TERM flag=yes reexec=1'), calls.join('\n'));
	assert.ok(calls.includes('phinx migrate flag=yes reexec=1'));
	assert.ok(calls.indexOf('pkill -TERM flag=yes reexec=1') > calls.indexOf('phinx migrate flag=yes reexec=1'), 'after the migrations');
	assert.equal(readFileSync(join(root, 'deploy.sh'), 'utf-8').match(/^pkill .*$/m)[0],
		"pkill -TERM -f 'scripts/live-hub.mjs' >/dev/null 2>&1 || true");
});

test('a deploy where no hub runs goes on as usual', () => {
	const root = project();

	// pkill's exit status when it found nothing to signal
	const { status, stdout, stderr } = deploy(root, 'pkill -TERM');

	assert.equal(status, 0, stderr);
	assert.match(stdout, /=== Deploy complete ===/);
});
