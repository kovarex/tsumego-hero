import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { publishDist } from './publish-dist.mjs';

function writeBuild(dir, files, manifest)
{
	for (const [file, content] of Object.entries(files))
	{
		mkdirSync(dirname(join(dir, file)), { recursive: true });
		writeFileSync(join(dir, file), content);
	}
	mkdirSync(join(dir, '.vite'), { recursive: true });
	writeFileSync(join(dir, '.vite/manifest.json'), JSON.stringify(manifest));
}

function build(dir, hash)
{
	writeBuild(dir, {
		[`app-${hash}.js`]: `app ${hash}`,
		[`legacy-app-${hash}.js`]: `legacy app ${hash}`,
		[`legacy-besogo-${hash}.js`]: `legacy besogo ${hash}`,
	}, {
		'app/index.tsx': { file: `app-${hash}.js`, isEntry: true },
		'virtual:legacy-app': { file: `legacy-app-${hash}.js`, isEntry: true },
		'virtual:legacy-besogo': { file: `legacy-besogo-${hash}.js`, isEntry: true },
	});
}

function servedFiles(dir)
{
	return readdirSync(dir).filter(file => file !== '.vite').sort();
}

test('a publish replaces the manifest and keeps the previous build until the next one', () =>
{
	const root = mkdtempSync(join(tmpdir(), 'publish-dist-'));
	const served = join(root, 'dist');
	const staging = join(root, 'dist-next');

	build(staging, 'aaa');
	publishDist(staging, served);
	assert.equal(existsSync(staging), false);
	assert.equal(JSON.parse(readFileSync(join(served, '.vite/manifest.json'), 'utf-8'))['virtual:legacy-app'].file, 'legacy-app-aaa.js');

	build(staging, 'bbb');
	publishDist(staging, served);
	assert.equal(JSON.parse(readFileSync(join(served, '.vite/manifest.json'), 'utf-8'))['virtual:legacy-app'].file, 'legacy-app-bbb.js');
	// A page rendered with the old manifest moments ago still finds its scripts.
	assert.deepEqual(servedFiles(served), [
		'app-aaa.js', 'app-bbb.js',
		'legacy-app-aaa.js', 'legacy-app-bbb.js',
		'legacy-besogo-aaa.js', 'legacy-besogo-bbb.js',
	]);

	build(staging, 'ccc');
	const { removed } = publishDist(staging, served);
	assert.deepEqual(removed, ['app-aaa.js', 'legacy-app-aaa.js', 'legacy-besogo-aaa.js']);
	assert.equal(readFileSync(join(served, 'legacy-app-ccc.js'), 'utf-8'), 'legacy app ccc');
});

test('files left by a build that was not published are only removed once a publish has a list', () =>
{
	const root = mkdtempSync(join(tmpdir(), 'publish-dist-'));
	const served = join(root, 'dist');
	const staging = join(root, 'dist-next');
	writeBuild(served, { 'legacy-app-old.js': 'built in place' }, {});

	build(staging, 'aaa');
	assert.deepEqual(publishDist(staging, served).removed, []);
	assert.ok(existsSync(join(served, 'legacy-app-old.js')));

	build(staging, 'bbb');
	assert.deepEqual(publishDist(staging, served).removed, ['legacy-app-old.js']);
});

test('an unfinished build is not published', () =>
{
	const root = mkdtempSync(join(tmpdir(), 'publish-dist-'));
	const served = join(root, 'dist');
	const staging = join(root, 'dist-next');
	build(served, 'aaa');

	// What the build looks like before its legacy bundles are written.
	writeBuild(staging, { 'app-bbb.js': 'app bbb' }, { 'app/index.tsx': { file: 'app-bbb.js', isEntry: true } });
	assert.throws(() => publishDist(staging, served), /virtual:legacy-app/);
	assert.equal(JSON.parse(readFileSync(join(served, '.vite/manifest.json'), 'utf-8'))['virtual:legacy-app'].file, 'legacy-app-aaa.js');
	assert.equal(existsSync(join(served, 'app-bbb.js')), false);
});

test('a manifest naming a file the build does not have is not published', () =>
{
	const root = mkdtempSync(join(tmpdir(), 'publish-dist-'));
	const staging = join(root, 'dist-next');
	build(staging, 'aaa');
	writeFileSync(join(staging, '.vite/manifest.json'), JSON.stringify({
		'virtual:legacy-app': { file: 'legacy-app-aaa.js' },
		'virtual:legacy-besogo': { file: 'legacy-besogo-missing.js' },
	}));
	assert.throws(() => publishDist(staging, join(root, 'dist')), /legacy-besogo-missing\.js/);
});
