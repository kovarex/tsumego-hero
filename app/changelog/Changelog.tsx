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

const CATEGORY_ORDER = ['Added', 'Fixed', 'Changed', 'Performance', 'Removed'];

// Group entries by day, then category (newest-first order preserved within each).
function groupByDay(entries: ChangelogEntry[]): { date: string; categories: [string, ChangelogEntry[]][] }[]
{
	const days = new Map<string, Map<string, ChangelogEntry[]>>();
	for (const e of entries)
	{
		let cats = days.get(e.date);
		if (!cats)
		{
			cats = new Map();
			days.set(e.date, cats);
		}
		const list = cats.get(e.category);
		if (list)
			list.push(e);
		else
			cats.set(e.category, [e]);
	}

	return Array.from(days.entries()).map(([date, cats]) => ({
		date,
		categories: Array.from(cats.entries())
			.sort((a, b) => CATEGORY_ORDER.indexOf(a[0]) - CATEGORY_ORDER.indexOf(b[0]))
	}));
}

function Feed({ entries }: { entries: ChangelogEntry[] })
{
	return (
		<>
			{groupByDay(entries).map(({ date, categories }) => (
				<section key={date}>
					<h2 className="changelog__date">{date}</h2>
					{categories.map(([category, list]) => (
						<div className="changelog__group" key={category}>
							<h3 className="changelog__category">{category}</h3>
							<ul className="changelog__list">
								{list.map((e, index) => (
									<li key={`${e.file}-${index}`}>
										<div className="changelog__text">
											<ReactMarkdown components={markdownComponents}>{e.text}</ReactMarkdown>
										</div>
									</li>
								))}
							</ul>
						</div>
					))}
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
