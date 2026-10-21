import ReactMarkdown, { type Components } from 'react-markdown';
import { useEffect, useMemo } from 'react';
import type { ChangelogEntry } from './changelogTypes';
import { countNew, getLastSeen, markSeen, syncMenuNewBadge } from './changelogCount';

// Inject BEM classes into markdown elements so we avoid element selectors
// (per the CSS architecture: element selectors only for td/th/tr/li).
const markdownComponents: Components = {
	a: ({ node: _node, ...props }) => <a className="changelog__link" {...props} />,
	p: ({ node: _node, ...props }) => <p className="changelog__paragraph" {...props} />
};

// Display order within a day: what a player can do now comes before what they
// already use and behaves differently, and maintenance comes last. Affects the
// rendering order only, never the dates or the timestamps behind them.
const CATEGORY_ORDER = ['Added', 'Changed', 'Performance', 'Removed', 'Fixed'];

function categoryRank(category: string): number
{
	const rank = CATEGORY_ORDER.indexOf(category);
	return rank === -1 ? CATEGORY_ORDER.length : rank;
}

// Entries grouped by day, each day ordered by category (stable, so entries of the
// same category keep the order they shipped in).
function groupByDay(entries: ChangelogEntry[]): { date: string; entries: ChangelogEntry[] }[]
{
	const days = new Map<string, ChangelogEntry[]>();
	for (const e of entries)
	{
		const list = days.get(e.date);
		if (list)
			list.push(e);
		else
			days.set(e.date, [e]);
	}

	return Array.from(days.entries()).map(([date, dayEntries]) => ({
		date,
		entries: dayEntries.slice().sort((a, b) => categoryRank(a.category) - categoryRank(b.category))
	}));
}

function Feed({ entries }: { entries: ChangelogEntry[] })
{
	return (
		<>
			{groupByDay(entries).map(({ date, entries: dayEntries }) => (
				<section key={date}>
					<h2 className="changelog__date">{date}</h2>
					<ul className="changelog__list">
						{dayEntries.map((e, index) => (
							<li key={`${e.file}-${index}`}>
								<span className={`changelog__category changelog__category--${e.category.toLowerCase()}`}>{e.category}</span>
								<span className="changelog__text">
									<ReactMarkdown components={markdownComponents}>{e.text}</ReactMarkdown>
								</span>
							</li>
						))}
					</ul>
				</section>
			))}
		</>
	);
}

/**
 * Renders the changelog feed from the mount element's data-props. New entries
 * since the player's last visit are grouped at the top; the marker advances on
 * render.
 */
export function Changelog({ entries }: { entries: ChangelogEntry[] })
{
	// Snapshot the marker at mount; it's advanced in the effect below.
	const lastSeen = useMemo(() => getLastSeen(), []);

	useEffect(() =>
	{
		if (!entries || entries.length === 0)
			return;

		markSeen(entries[0].ts);
		syncMenuNewBadge();
	}, [entries]);

	if (!entries || entries.length === 0)
		return <p className="changelog__empty">No changes yet.</p>;

	// Entries are sorted newest-first, so the new ones are a leading slice.
	const newCount = countNew(entries, lastSeen);
	const newEntries = entries.slice(0, newCount);
	const oldEntries = entries.slice(newCount);

	return (
		<div className="changelog">
			{newCount > 0 && (
				<div className="changelog__new-section">
					<Feed entries={newEntries} />
					<hr className="changelog__new-end" />
					<p className="changelog__new-count">{newCount} new since your last visit.</p>
				</div>
			)}
			<Feed entries={oldEntries} />
		</div>
	);
}
