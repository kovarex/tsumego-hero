import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { execSync } from 'node:child_process';

// Generates changelog/index.json from changelog/*.md fragments, ordered by
// "ship time" (the last master commit that touched each fragment), NOT by the
// date in the filename. This keeps a fragment that was written long before it
// merged from sorting into the past.
//
// A fragment can optionally pin a `commit` in its front-matter (e.g. when the
// fragment is added later in a separate commit than the change it describes).
// In that case the ship timestamp is taken from that commit's date instead of
// the fragment's own commit, so backfilled entries keep their real ship date.

const ROOT = process.cwd();
const DIR = join(ROOT, 'changelog');
const OUT = join(DIR, 'index.json');

const CATEGORIES = ['Added', 'Fixed', 'Changed', 'Performance', 'Removed'];

// --- Collect fragment files (skip README.md, index.json) ---
const files = readdirSync(DIR)
	.filter(f => f.endsWith('.md') && f !== 'README.md')
	.sort();

// --- One git log pass: map each changelog file -> latest commit time (ship time) ---
// `--reverse` (oldest first) means the last occurrence of each file wins = latest touch.
function buildShipMap() {
	const map = {};
	try {
		const log = execSync(
			`git log --reverse --format='@@%ct@@' --name-only -- 'changelog'`,
			{ encoding: 'utf8', cwd: ROOT }
		);
		let ts = 0;
		for (const line of log.split('\n')) {
			const m = line.match(/^@@(\d+)@@$/);
			if (m) {
				ts = parseInt(m[1], 10);
				continue;
			}
			if (line.trim()) map[line.trim()] = ts;
		}
	} catch (e) {
		// No git history (e.g. fresh checkout) — fall back to file mtime below.
	}
	return map;
}

const shipMap = buildShipMap();

function shipStamp(file) {
	const rel = `changelog/${file}`;
	if (shipMap[rel]) return shipMap[rel];
	// Untracked files (e.g. freshly added, not yet committed) fall back to mtime.
	try {
		return Math.floor(statSync(join(DIR, file)).mtimeMs / 1000) || 0;
	} catch (e) {
		return 0;
	}
}

// Resolve a front-matter `commit` ref to its committer timestamp (ship date),
// so a fragment added in a separate commit borrows the date of the commit it
// describes. Only accepts real commit-ish SHAs; anything else is ignored.
function commitShipStamp(commit) {
	if (!/^[0-9a-fA-F]{7,40}$/.test(commit)) return 0;
	try {
		const out = execSync(`git show -s --format=%ct ${commit}`, { encoding: 'utf8', cwd: ROOT }).trim();
		const ts = parseInt(out, 10);
		return Number.isFinite(ts) ? ts : 0;
	} catch (e) {
		return 0;
	}
}

// --- Parse a fragment: optional front-matter (commit), then category + text ---
function parseFragment(content) {
	let body = content.trim();
	let commit = null;
	const fm = body.match(/^---\n([\s\S]*?)\n---\n?/);
	if (fm) {
		for (const row of fm[1].split('\n')) {
			const m = row.match(/^commit:\s*(\S+)\s*$/i);
			if (m) commit = m[1];
		}
		body = body.slice(fm[0].length).trim();
	}
	const line = body.split('\n')[0];
	let category = 'Changed';
	let rest = line;
	const m = line.match(/^([A-Za-z]+):\s*(.*)$/);
	if (m && CATEGORIES.includes(m[1])) {
		category = m[1];
		rest = m[2];
	}
	// Keep the body as markdown (links included) so the UI can render it directly;
	// no need to split out link/linkText. Category is kept for optional badge/filter.
	return { category, text: rest.trim(), commit };
}

function isoDate(ts) {
	return (ts ? new Date(ts * 1000) : new Date()).toISOString().slice(0, 10);
}

// --- Build entries. "what's new" is keyed on the ship timestamp (ts), not a seq. ---
const entries = files.map(file => {
	const parsed = parseFragment(readFileSync(join(DIR, file), 'utf8'));
	const referenced = parsed.commit && commitShipStamp(parsed.commit);
	const ts = referenced || shipStamp(file);
	return {
		ts,
		date: isoDate(ts),
		category: parsed.category,
		text: parsed.text,
		file,
		...(parsed.commit ? { commit: parsed.commit } : {}),
	};
});

// Newest first (ship time), then by filename.
entries.sort((a, b) => (b.ts - a.ts) || a.file.localeCompare(b.file));

// --- Capture the deployed revision and embed it in the manifest ---
let revision = null;
try {
	const head = execSync(`git rev-parse HEAD`, { encoding: 'utf8', cwd: ROOT }).trim();
	if (head) revision = head;
} catch (e) {
	// git not available — revision stays null
}

writeFileSync(OUT, JSON.stringify({ revision, entries }, null, 2) + '\n');
console.log(`Wrote ${entries.length} entries (+ revision) to changelog/index.json`);
