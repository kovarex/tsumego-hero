import ReactMarkdown, { type Components } from 'react-markdown';
import type { ChangelogEntry } from './changelogTypes';

// Inject BEM classes into markdown elements so we avoid element selectors
// (per the CSS architecture: element selectors only for td/th/tr/li).
const markdownComponents: Components = {
	a: ({ node, ...props }) => <a className="changelog__link" {...props} />,
	p: ({ node, ...props }) => <p className="changelog__paragraph" {...props} />,
};

/**
 * Renders the changelog feed. Entries are provided by the server (from the
 * generated changelog/index.json) via the mount element's data-props.
 */
export function Changelog({ entries }: { entries: ChangelogEntry[] })
{
	if (!entries || entries.length === 0)
		return <p className="changelog__empty">No changes yet.</p>;

	// Group by day, then by category (newest-first order preserved within each).
	const CATEGORY_ORDER = ['Added', 'Fixed', 'Changed', 'Performance', 'Removed'];
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
		if (list) list.push(e);
		else cats.set(e.category, [e]);
	}

	return (
		<div className="changelog">
			{Array.from(days.entries()).map(([date, cats]) => (
				<section key={date}>
					<h2 className="changelog__date">{date}</h2>
					{Array.from(cats.entries())
						.sort((a, b) => CATEGORY_ORDER.indexOf(a[0]) - CATEGORY_ORDER.indexOf(b[0]))
						.map(([category, list]) => (
							<div className="changelog__group" key={category}>
								<h3 className="changelog__category">{category}</h3>
								<ul className="changelog__list">
									{list.map(e => (
										<li key={e.file}>
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
		</div>
	);
}
