import { readFileSync, writeFileSync, readdirSync } from 'node:fs';
import { join, isAbsolute } from 'node:path';
import { execSync } from 'node:child_process';

// Generates changelog/index.json from changelog/*.md fragments, ordered by
// "ship time": the commit that first ADDED each fragment. Not the date in the
// filename (that is the day it was written) and not the fragment's latest edit
// either, so a published entry keeps its place when its wording changes later.
//
// A fragment can optionally pin a `commit` in its front-matter (e.g. when the
// fragment is added later in a separate commit than the change it describes).
// In that case the ship timestamp is taken from that commit's date instead of
// the fragment's own commit, so backfilled entries keep their real ship date.
//
// Dates come from git and from nowhere else: the pinned commit in the fragment's
// front-matter, or the commit that added the file. A made-up date would move the
// entry in the feed and show it to every player as new, so a fragment git cannot
// date stops the run before index.json is written.
//
// A fragment may hold several entries (one per non-empty line). They all share
// the file's ship timestamp, so changes that shipped together can live in one file.

const ROOT = process.cwd();
const DIR = join(ROOT, 'changelog');
const OUT = join(DIR, 'index.json');

const CATEGORIES = ['Added', 'Fixed', 'Changed', 'Performance', 'Removed'];

// The flags a fragment may name: the cases of FeatureKey. An entry behind a flag the
// site does not know would never be shown, so a mistyped key stops the run instead.
const FLAGS = [...readFileSync(join(ROOT, 'src/Utility/FeatureKey.php'), 'utf8')
	.matchAll(/^\s*case\s+\w+\s*=\s*'([^']+)'\s*;/gm)].map(m => m[1]);
const unknownFlags = [];

// Every git call fails quietly: a checkout without a repository is not an error here
// but a missing date, and reportDates() is the one that reports it. stderr is dropped
// so git's own wording cannot pass for that report.
const GIT_QUIET = { encoding: 'utf8', cwd: ROOT, stdio: ['ignore', 'pipe', 'ignore'] };

// Where the dates came from, and what git can tell us about this checkout.
const sources = { pinned: [], added: [] };
const undated = [];

function gitProbe() {
	const read = (args) => {
		try {
			return execSync(`git ${args}`, GIT_QUIET).trim();
		} catch (e) {
			return null;
		}
	};

	const workTree = read('rev-parse --is-inside-work-tree');
	return {
		installed: read('--version') !== null,
		workTree: workTree === 'true',
		shallow: read('rev-parse --is-shallow-repository') === 'true'
	};
}

const PROBE = gitProbe();

// The fragments git knows about, which only decides how a missing date is worded: a
// fragment that was never committed is the normal state while a change is being
// written, a committed one without history means the checkout is incomplete.
function trackedFragments() {
	try {
		const out = execSync('git ls-files -- changelog', GIT_QUIET).trim();
		return new Set(out.split('\n').filter(Boolean).map(path => path.replace(/^changelog\//, '')));
	} catch (e) {
		return new Set();
	}
}

const tracked = trackedFragments();

// In a shallow clone git reports the oldest commit it has as having added every file
// it contains, so a fragment "added" by that commit did not get a date from history
// at all - it got the clone depth. Those fragments must never be published.
function shallowBoundaries() {
	if (!PROBE.shallow) return new Set();
	try {
		const path = execSync('git rev-parse --git-path shallow', GIT_QUIET).trim();
		const file = isAbsolute(path) ? path : join(ROOT, path);
		return new Set(readFileSync(file, 'utf8').split('\n').filter(Boolean));
	} catch (e) {
		return new Set();
	}
}

const boundaries = shallowBoundaries();

// --- Collect fragment files (skip README.md, index.json) ---
const files = readdirSync(DIR)
	.filter(f => f.endsWith('.md') && f !== 'README.md')
	.sort();

// --- One git log pass: map each changelog file -> the commit that added it ---
// `--diff-filter=A` keeps only additions, so an entry's date is decided by the
// commit that introduced its fragment and never by a later rewrite of the file.
// The commit id is kept as well, so a shallow boundary can be recognised.
function buildAddMap() {
	const map = {};
	try {
		const log = execSync(
			`git log --reverse --diff-filter=A --format='@@%H@@%ct@@' --name-only -- 'changelog'`,
			GIT_QUIET
		);
		let commit = null;
		let ts = 0;
		for (const line of log.split('\n')) {
			const m = line.match(/^@@([0-9a-f]+)@@(\d+)@@$/);
			if (m) {
				commit = m[1];
				ts = parseInt(m[2], 10);
				continue;
			}
			if (line.trim()) map[line.trim()] = { ts, commit };
		}
	} catch (e) {
			// No git history at all; reportDates() reports that, git itself stays quiet.
	}
	return map;
}

const addMap = buildAddMap();

// The ship date of a fragment, or the reason git cannot provide one.
function resolveShipStamp(file, pinned) {
	if (pinned) {
		const ts = commitShipStamp(pinned);
		return ts ? { ts, source: 'pinned' } : { problem: `its pinned commit ${pinned} is not in this repository` };
	}

	if (!PROBE.workTree)
		return { problem: 'this is not a git work tree' };

	const added = addMap[`changelog/${file}`];
	if (!added)
		return { problem: tracked.has(file) ? 'no commit in this history has added it' : 'it is not committed yet' };

	if (boundaries.has(added.commit))
		return { problem: `it was "added" by ${added.commit.slice(0, 7)}, which is only where this shallow clone ends` };

	return { ts: added.ts, source: 'added' };
}

// Resolve a front-matter `commit` ref to its committer timestamp (ship date),
// so a fragment added in a separate commit borrows the date of the commit it
// describes. Only accepts real commit-ish SHAs; anything else is ignored.
function commitShipStamp(commit) {
	if (!/^[0-9a-fA-F]{7,40}$/.test(commit)) return 0;
	try {
		const out = execSync(`git show -s --format=%ct ${commit}`, GIT_QUIET).trim();
		const ts = parseInt(out, 10);
		return Number.isFinite(ts) ? ts : 0;
	} catch (e) {
		return 0;
	}
}

// --- Parse a fragment: optional front-matter (commit), then one entry per line ---
function parseFragment(content) {
	let body = content.trim();
	let commit = null;
	let flag = null;
	const fm = body.match(/^---\n([\s\S]*?)\n---\n?/);
	if (fm) {
		for (const row of fm[1].split('\n')) {
			const m = row.match(/^commit:\s*(\S+)\s*$/i);
			if (m) commit = m[1];
			const f = row.match(/^flag:\s*(\S+)\s*$/i);
			if (f) flag = f[1];
		}
		body = body.slice(fm[0].length).trim();
	}
	// One entry per non-empty line, so a single file can describe several changes
	// that shipped together. All entries in a file share its ship timestamp.
	// A line without a known category keeps its prefix as text and is reported by
	// the caller, which is what a line wrapped by hand looks like.
	const entries = body
		.split('\n')
		.map((line, index) => ({ line: line.trim(), number: index + 1 }))
		.filter(({ line }) => line.length > 0)
		.map(({ line, number }) => {
			let category = 'Changed';
			let rest = line;
			let known = false;
			const m = line.match(/^([A-Za-z]+):\s*(.*)$/);
			if (m && CATEGORIES.includes(m[1])) {
				category = m[1];
				rest = m[2];
				known = true;
			}
			// The body stays markdown so the UI can render the links in it.
			return { category, text: rest.trim(), known, number };
		});
	return { entries, commit, flag };
}

function isoDate(ts) {
	return new Date(ts * 1000).toISOString().slice(0, 10);
}

// --- Build entries. "what's new" is keyed on the ship timestamp (ts), not a seq. ---
// A file can yield several entries; they all inherit the file's ship timestamp.
const entries = files.flatMap(file => {
	const parsed = parseFragment(readFileSync(join(DIR, file), 'utf8'));
	for (const entry of parsed.entries)
		if (!entry.known)
			console.warn(`${file}:${entry.number}: no known category prefix, published as "Changed": ${entry.line}`);

	if (parsed.flag && !FLAGS.includes(parsed.flag))
		unknownFlags.push({ file, flag: parsed.flag });

	const resolved = resolveShipStamp(file, parsed.commit);
	if (resolved.problem)
		undated.push({ file, problem: resolved.problem });
	else
		sources[resolved.source].push(file);

	const ts = resolved.ts ?? 0;
	return parsed.entries.map(e => ({
		ts,
		date: isoDate(ts),
		category: e.category,
		text: e.text,
		file,
		...(parsed.commit ? { commit: parsed.commit } : {}),
		...(parsed.flag ? { flag: parsed.flag } : {}),
	}));
});

// Newest first (ship time), then by filename.
entries.sort((a, b) => (b.ts - a.ts) || a.file.localeCompare(b.file));

// --- Report where the dates came from, and stop when git cannot provide one ---
function reportDates() {
	const fromGit = sources.pinned.length + sources.added.length;
	console.log(`dates: ${fromGit}/${files.length} from git (${sources.pinned.length} pinned, ${sources.added.length} from the adding commit)`);

	if (undated.length === 0)
		return;

	const env = [];
	if (!PROBE.installed) env.push('git is not installed');
	else if (!PROBE.workTree) env.push('this directory is not a git work tree');
	else if (PROBE.shallow) env.push('this is a shallow clone');

	console.error(`changelog: ERROR: ${undated.length} of ${files.length} fragments have no ship date in git`);
	for (const { file, problem } of undated.slice(0, 5))
		console.error(`changelog:   ${file} - ${problem}`);
	if (undated.length > 5)
		console.error(`changelog:   ... and ${undated.length - 5} more`);
	if (env.length)
		console.error(`changelog:   note: ${env.join(', ')}`);
	console.error('changelog:   ship dates only come from git; a made-up date would move an entry in the feed');
	console.error('changelog:   commit the fragment (or pin a commit in its front-matter) and run this again');
	process.exit(1);
}

if (unknownFlags.length) {
	for (const { file, flag } of unknownFlags)
		console.error(`changelog: ERROR: ${file} names the flag "${flag}", which is not a FeatureKey case (${FLAGS.join(', ') || 'none'})`);
	console.error('changelog:   fix the key, or remove the flag: line when its feature shipped for good');
	process.exit(1);
}

reportDates();

// --- Capture the deployed revision and embed it in the manifest ---
let revision = null;
try {
	const head = execSync(`git rev-parse HEAD`, GIT_QUIET).trim();
	if (head) revision = head;
} catch (e) {
		// git not available; revision stays null
}

writeFileSync(OUT, JSON.stringify({ revision, entries }, null, 2) + '\n');
console.log(`Wrote ${entries.length} entries (+ revision) to changelog/index.json`);
