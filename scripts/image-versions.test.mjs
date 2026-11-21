import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdirSync, mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { runInNewContext } from 'node:vm';
import { imageVersions, imageVersionsScript, versionCssImages } from './image-versions.mjs';

function webroot(files)
{
	const root = mkdtempSync(join(tmpdir(), 'image-versions-'));
	for (const [file, content] of Object.entries(files))
	{
		mkdirSync(dirname(join(root, file)), { recursive: true });
		writeFileSync(join(root, file), content);
	}
	return root;
}

test('the version of an image changes with its content and only then', () =>
{
	const root = webroot({ 'img/hp1.png': 'sprint', 'besogo/img/black0.png': 'stone' });
	const before = imageVersions(root);
	assert.match(before['img/hp1.png'], /^[0-9a-f]{8}$/);

	writeFileSync(join(root, 'img/hp1.png'), 'sprint, drawn again');
	const after = imageVersions(root);
	assert.notEqual(after['img/hp1.png'], before['img/hp1.png']);
	assert.equal(after['besogo/img/black0.png'], before['besogo/img/black0.png']);
});

test('a new .webp beside an image, which the server sends in its place, changes its version', () =>
{
	const root = webroot({ 'img/hp1.png': 'sprint' });
	const before = imageVersions(root)['img/hp1.png'];
	writeFileSync(join(root, 'img/hp1.webp'), 'sprint as webp');
	assert.notEqual(imageVersions(root)['img/hp1.png'], before);
});

test('the images the site does not version itself are left out', () =>
{
	const root = webroot({
		'img/hp1.png': 'sprint',
		'img/sets/1/0123456789abcdef.webp': 'a cover, named by its content',
		'forums/images/smile.gif': 'the forum\'s own',
		'dist/legacy-app-12345678.js': 'a bundle',
		'js/util.js': 'not an image',
	});
	assert.deepEqual(Object.keys(imageVersions(root)), ['img/hp1.png']);
});

test('the CSS gets the versions of the images it names', () =>
{
	const versions = { 'img/a.png': '11111111', 'img/b.png': '22222222' };
	assert.equal(
		versionCssImages('x { background: url("/img/a.png"); } y { background: url(/img/b.png) } z { background: url(/img/unknown.png) }', versions),
		'x { background: url("/img/a.png?v=11111111"); } y { background: url(/img/b.png?v=22222222) } z { background: url(/img/unknown.png) }');
});

test('imageUrl() of the script gives an image its version, and leaves an unknown one as it is', () =>
{
	const context = {};
	runInNewContext(imageVersionsScript({ 'img/hp1.png': '11111111', 'img/hp1.webp': '22222222', 'img/new_startpage/q.png': '33333333', 'besogo/img/black0.png': '44444444' }), context);
	assert.equal(context.imageUrl('/img/hp1.png'), '/img/hp1.png?v=11111111');
	assert.equal(context.imageUrl('/besogo/img/black0.png'), '/besogo/img/black0.png?v=44444444');
	assert.equal(context.imageUrl('/img/unknown.png'), '/img/unknown.png');
	//Only the images the scripts name, so the script every page loads stays small
	assert.deepEqual(Object.keys(context.imageVersions), ['img/hp1.png', 'besogo/img/black0.png']);
});
