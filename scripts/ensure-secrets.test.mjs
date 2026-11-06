import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdirSync, mkdtempSync, readFileSync, statSync, writeFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const SCRIPT = join(dirname(fileURLToPath(import.meta.url)), 'ensure-secrets.sh');

//The salt the test takes for the one that was committed to git; the script gets only its hash, as
//it has only the hash of the real one
const COMMITTED_SALT = 'the-salt-this-test-takes-for-the-committed-one';
const GOOD_SECRET = 'a'.repeat(64);
const GOOD_SALT = 'b'.repeat(64);

function project(files = {})
{
	const root = mkdtempSync(join(tmpdir(), 'ensure-secrets-'));
	mkdirSync(join(root, 'config'));
	for (const [file, content] of Object.entries(files))
		writeFileSync(join(root, 'config', file), content);
	return root;
}

function ensureSecrets(root)
{
	const result = spawnSync('bash', [SCRIPT, root], {
		encoding: 'utf-8',
		env: { ...process.env, ENSURE_SECRETS_COMPROMISED_SHA256: createHash('sha256').update(COMMITTED_SALT).digest('hex') },
	});
	return { status: result.status, lines: result.stdout.split('\n').filter(Boolean), stderr: result.stderr };
}

const read = (root, file) => readFileSync(join(root, 'config', file), 'utf-8');
const jwtSecret = (root) => read(root, 'jwt_secret.php').match(/^return '([^']*)';$/m)?.[1];
const salts = (root) => [...read(root, 'core.local.php').matchAll(/^Configure::write\('Security\.salt', '([^']*)'\);$/mg)].map(match => match[1]);

test('a server without the secrets gets both, random and unprinted, readable by no one else', () => {
	const root = project();

	const { status, lines } = ensureSecrets(root);

	assert.equal(status, 0);
	assert.deepEqual(lines, ['created config/jwt_secret.php', 'added Security.salt to config/core.local.php']);
	assert.match(read(root, 'jwt_secret.php'), /^<\?php\n/);
	assert.match(jwtSecret(root), /^[0-9a-f]{64}$/);
	assert.match(read(root, 'core.local.php'), /^<\?php\n/);
	assert.equal(salts(root).length, 1);
	assert.match(salts(root)[0], /^[0-9a-f]{64}$/);
	assert.notEqual(jwtSecret(root), salts(root)[0]);
	for (const file of ['jwt_secret.php', 'core.local.php'])
		assert.equal(statSync(join(root, 'config', file)).mode & 0o007, 0, `${file} is not readable by everyone`);
});

test('the secrets a server has are left as they are, and running again changes nothing', () => {
	const files = {
		'jwt_secret.php': `<?php\nreturn '${GOOD_SECRET}';\n`,
		'core.local.php': `<?php\ndefine('CRON_SECRET', 'x');\nConfigure::write('Security.salt', '${GOOD_SALT}');\n`,
	};
	const root = project(files);

	assert.deepEqual(ensureSecrets(root).lines, []);
	assert.deepEqual(ensureSecrets(root).lines, []);

	assert.equal(read(root, 'jwt_secret.php'), files['jwt_secret.php']);
	assert.equal(read(root, 'core.local.php'), files['core.local.php']);
});

test('a core.local.php without a salt gets one after what it has', () => {
	const root = project({ 'jwt_secret.php': `<?php\nreturn '${GOOD_SECRET}';\n`, 'core.local.php': `<?php\ndefine('CRON_SECRET', 'x');\n` });

	assert.deepEqual(ensureSecrets(root).lines, ['added Security.salt to config/core.local.php']);

	assert.match(read(root, 'core.local.php'), /^<\?php\ndefine\('CRON_SECRET', 'x'\);\n/);
	assert.match(salts(root)[0], /^[0-9a-f]{64}$/);
});

test('the salt that was committed to git is replaced, wherever it is', () => {
	const root = project({
		'jwt_secret.php': `<?php\nreturn '${COMMITTED_SALT}';\n`,
		'core.local.php': `<?php\ndefine('CRON_SECRET', 'x');\nConfigure::write('Security.salt', '${COMMITTED_SALT}');\n// the end\n`,
	});
	const modeBefore = statSync(join(root, 'config', 'core.local.php')).mode;

	const { status, lines } = ensureSecrets(root);

	assert.equal(status, 0);
	assert.deepEqual(lines, ['replaced the unusable secret of config/jwt_secret.php', 'replaced the unusable Security.salt of config/core.local.php']);
	assert.match(jwtSecret(root), /^[0-9a-f]{64}$/);
	assert.match(salts(root)[0], /^[0-9a-f]{64}$/);
	assert.doesNotMatch(read(root, 'jwt_secret.php') + read(root, 'core.local.php'), new RegExp(COMMITTED_SALT));
	assert.match(read(root, 'core.local.php'), /^<\?php\ndefine\('CRON_SECRET', 'x'\);\nConfigure::write[^\n]*\n\/\/ the end\n$/);
	assert.equal(statSync(join(root, 'config', 'core.local.php')).mode, modeBefore);
	assert.ok(!existsSync(join(root, 'config', `core.local.php.ensure-secrets.${process.pid}`)));
});

test('an example copied as it is, with an empty value, gets a real one', () => {
	const root = project({ 'jwt_secret.php': `<?php\nreturn '';\n`, 'core.local.php': `<?php\nConfigure::write('Security.salt', '');\n` });

	assert.deepEqual(ensureSecrets(root).lines, ['replaced the unusable secret of config/jwt_secret.php', 'replaced the unusable Security.salt of config/core.local.php']);

	assert.match(jwtSecret(root), /^[0-9a-f]{64}$/);
	assert.match(salts(root)[0], /^[0-9a-f]{64}$/);
});

test('a salt that is not a literal, read from the environment, is left as it is', () => {
	const coreLocal = `<?php\nConfigure::write('Security.salt', getenv('SALT'));\n`;
	const root = project({ 'jwt_secret.php': `<?php\nreturn '${GOOD_SECRET}';\n`, 'core.local.php': coreLocal });

	assert.deepEqual(ensureSecrets(root).lines, []);
	assert.equal(read(root, 'core.local.php'), coreLocal);
});

test('a core.local.php ending with ?> stops the deploy before anything changes, and says why', () => {
	const coreLocal = `<?php\ndefine('CRON_SECRET', 'x');\n?>\n`;
	const root = project({ 'jwt_secret.php': `<?php\nreturn '${GOOD_SECRET}';\n`, 'core.local.php': coreLocal });

	const { status, stderr } = ensureSecrets(root);

	assert.notEqual(status, 0);
	assert.match(stderr, /ends with \?>/);
	assert.equal(read(root, 'core.local.php'), coreLocal);
});

test('the deploy runs it before the pull, and the test site before the checkout', () => {
	const deploy = readFileSync(join(dirname(SCRIPT), '..', 'deploy.sh'), 'utf-8');
	assert.ok(deploy.indexOf('scripts/ensure-secrets.sh') < deploy.indexOf('git pull'));
	const hook = readFileSync(join(dirname(SCRIPT), 'deploy-test-hook.cgi'), 'utf-8');
	assert.ok(hook.indexOf('bash scripts/ensure-secrets.sh') < hook.indexOf('git checkout'));
});
