import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, renameSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

// Moves a finished Vite build (built into a staging directory) into the served webroot/dist,
// so the site never sees a half-written one.
//
// Building straight into webroot/dist first empties it and writes the legacy (plain script)
// bundles last, so a page rendered in between got no legacy-app script at all, and the React
// part failed on the globals it expects (makeIdValidName on any problem with a visible tag).
//
// Here the new files are copied in next to the old ones first, and the manifest, which is what
// decides which files the pages ask for, is replaced last in one rename. Files of the previous
// build stay, so a page rendered just before the deploy can still load its scripts; anything
// older is removed.
//
// Usage: node scripts/publish-dist.mjs <built dir> <served dir>

const MANIFEST = '.vite/manifest.json';
const FILE_LIST = '.vite/published-files.json';
const REQUIRED_ENTRIES = ['virtual:legacy-app', 'virtual:legacy-besogo'];

/** Relative paths (with forward slashes) of every file under dir, except the .vite bookkeeping. */
function listFiles(dir)
{
	const files = [];
	const walk = current =>
	{
		for (const entry of readdirSync(current, { withFileTypes: true }))
		{
			const path = join(current, entry.name);
			if (entry.isDirectory())
				walk(path);
			else
				files.push(relative(dir, path).split(sep).join('/'));
		}
	};
	walk(dir);
	return files.filter(file => !file.startsWith('.vite/')).sort();
}

/** Writes through a temporary file and a rename, so a reader sees either the old or the new content. */
function replaceFile(path, write)
{
	mkdirSync(dirname(path), { recursive: true });
	const temporary = `${path}.publishing-${process.pid}`;
	write(temporary);
	renameSync(temporary, path);
}

export function publishDist(builtDir, servedDir)
{
	const manifestPath = join(builtDir, MANIFEST);
	if (!existsSync(manifestPath))
		throw new Error(`${manifestPath} is missing, the build did not finish.`);
	const manifest = JSON.parse(readFileSync(manifestPath, 'utf-8'));
	for (const entry of REQUIRED_ENTRIES)
		if (!manifest[entry])
			throw new Error(`${manifestPath} has no ${entry}, the build did not finish.`);

	const files = listFiles(builtDir);
	for (const { file } of Object.values(manifest))
		if (!files.includes(file))
			throw new Error(`${manifestPath} names ${file}, which is not in ${builtDir}.`);

	const fileListPath = join(servedDir, FILE_LIST);
	const previousFiles = existsSync(fileListPath) ? JSON.parse(readFileSync(fileListPath, 'utf-8')) : null;

	for (const file of files)
		replaceFile(join(servedDir, file), temporary => copyFileSync(join(builtDir, file), temporary));
	replaceFile(fileListPath, temporary => writeFileSync(temporary, JSON.stringify(files, null, 2) + '\n'));
	replaceFile(join(servedDir, MANIFEST), temporary => copyFileSync(manifestPath, temporary));

	// Without a list from the previous publish nothing is known to be unused yet; the next
	// publish cleans up after this one.
	const removed = [];
	if (previousFiles !== null)
	{
		const kept = new Set([...files, ...previousFiles]);
		for (const file of listFiles(servedDir))
			if (!kept.has(file))
			{
				rmSync(join(servedDir, file));
				removed.push(file);
			}
	}

	rmSync(builtDir, { recursive: true, force: true });
	return { published: files, removed };
}

if (process.argv[1] === fileURLToPath(import.meta.url))
{
	const [builtDir, servedDir] = process.argv.slice(2);
	if (!builtDir || !servedDir)
	{
		console.error('Usage: node scripts/publish-dist.mjs <built dir> <served dir>');
		process.exit(1);
	}
	const { published, removed } = publishDist(builtDir, servedDir);
	console.log(`Published ${published.length} files to ${servedDir}, removed ${removed.length} old ones.`);
}
