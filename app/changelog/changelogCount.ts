import type { ChangelogEntry } from './changelogTypes';

const LAST_SEEN_KEY = 'tsumego-changelog-last-seen';

// The newest timestamp a player has seen on the changelog. Stored locally so
// "new" is per player and stays accurate without a server round trip.
export function getLastSeen(): number
{
	try
	{
		return parseInt(localStorage.getItem(LAST_SEEN_KEY) || '0', 10) || 0;
	}
	catch
	{
		return 0;
	}
}

// Record that the player has seen everything up to `ts`.
export function markSeen(ts: number): void
{
	try
	{
		localStorage.setItem(LAST_SEEN_KEY, String(ts));
	}
	catch
	{
		// ignore
	}
}

// How many entries are newer than the marker. No marker means nothing to
// compare against, so there's nothing new yet.
export function countNew(entries: ChangelogEntry[], lastSeen: number): number
{
	return lastSeen === 0 ? 0 : entries.filter(e => e.ts > lastSeen).length;
}

// Update the count badge on the "What's new" nav link. The timestamps of the
// changelog entries are emitted inline by the layout (window.__CHANGELOG_TS),
// so the count is computed locally without a round trip. The badge only shows
// once a marker exists and there is something newer.
export function syncMenuNewBadge(): void
{
	const badge = document.querySelector<HTMLElement>('a[href="/changelog"] .nav__new-badge');
	if (!badge)
		return;

	const lastSeen = getLastSeen();
	if (lastSeen === 0)
	{
		badge.classList.remove('nav__new-badge--visible');
		return;
	}

	const timestamps: number[] = (window as unknown as { __CHANGELOG_TS?: number[] }).__CHANGELOG_TS ?? [];
	const count = timestamps.filter(ts => ts > lastSeen).length;
	badge.textContent = String(count);
	badge.classList.toggle('nav__new-badge--visible', count > 0);
}
