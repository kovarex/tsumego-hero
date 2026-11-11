import { useState } from 'react';
import { get } from '../shared/api';
import { pageItems } from '../shared/pageItems';
import { BarChart } from './BarChart';

//A page of the level chart, a calendar quarter, as UserProfile::levelDays() gives it
export interface LevelDaysPage
{
	//page 1 is the current quarter
	page: number;
	pages: number;
	//the first and the last day of the quarter
	from: string;
	until: string;
	days: { day: string; Solves: number; Fails: number }[];
}

interface Props
{
	//the player whose chart it is
	playerId: number;
	//the newest page, printed with the page
	page: LevelDaysPage;
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

//The quarter of a page as its months, "Oct – Dec 2026"
export function quarterLabel(from: string, until: string): string
{
	return `${MONTHS[Number(from.slice(5, 7)) - 1]} – ${MONTHS[Number(until.slice(5, 7)) - 1]} ${until.slice(0, 4)}`;
}

const SERIES = [
	{ label: 'Solves' as const, color: '#74d14c' },
	{ label: 'Fails' as const, color: '#d63a49' },
];

//The problems of the player in level mode, a bar a day with attempts, a calendar quarter a page: the current one
//with the page, and a pager under the title loads the older ones.
export function LevelChart({ playerId, page: initial }: Props)
{
	const [shown, setShown] = useState(initial);
	const [loading, setLoading] = useState(false);
	const [failed, setFailed] = useState(false);

	const load = async (page: number) =>
	{
		setLoading(true);
		setFailed(false);
		try
		{
			setShown(await get<LevelDaysPage>(`/users/levelDays/${playerId}?page=${page}`));
		}
		catch
		{
			setFailed(true);
		}
		finally
		{
			setLoading(false);
		}
	};

	const header = (
		<div className="level-chart__header">
			<span className="level-chart__range">{quarterLabel(shown.from, shown.until)}</span>
			{shown.pages > 1 && (
				<nav className="pagination level-chart__pager" aria-label="Pages of the level chart">
					<button type="button" className="pagination__link level-chart__previous" aria-label="Newer quarter"
						disabled={loading || shown.page === 1} onClick={() => load(shown.page - 1)}>&lt;</button>
					{pageItems(shown.page, shown.pages).map((item, index) => item === '…'
						? <span key={'gap' + index} className="pagination__ellipsis">…</span>
						: <button key={item} type="button" disabled={loading}
							className={'pagination__link level-chart__page' + (item === shown.page ? ' pagination__link--active' : '')}
							aria-current={item === shown.page ? 'page' : undefined} onClick={() => item !== shown.page && load(item)}>{item}</button>)}
					<button type="button" className="pagination__link level-chart__next" aria-label="Older quarter"
						disabled={loading || shown.page === shown.pages} onClick={() => load(shown.page + 1)}>&gt;</button>
				</nav>
			)}
			{failed && <span className="level-chart__error" role="alert">Could not load this quarter.</span>}
		</div>
	);

	if (shown.days.length === 0)
		return (
			<div className="chart">
				<div className="chart__title">Problems in level mode</div>
				{shown.pages > 1 && header}
				<p className="level-chart__empty">{shown.pages > 1 ? 'No problems in level mode this quarter.' : 'No problems in level mode yet.'}</p>
			</div>
		);
	return (
		<BarChart
			key={shown.page}
			title="Problems in level mode"
			header={header}
			categories={shown.days.map(day => day.day)}
			series={SERIES.map(oneSeries => ({ ...oneSeries, values: shown.days.map(day => day[oneSeries.label]) }))}
		/>
	);
}
