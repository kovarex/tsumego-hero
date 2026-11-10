import { useState } from 'react';
import { get } from '../shared/api';
import { formatDate } from '../shared/dates';
import { pageItems } from '../shared/pageItems';
import { BarChart } from './BarChart';

//A page of the days of the level chart, as UserProfile::levelDays() gives it
export interface LevelDaysPage
{
	//page 1 is the newest
	page: number;
	pages: number;
	//the first and the last day of the page, null without attempts
	from: string | null;
	until: string | null;
	days: { day: string; Solves: number; Fails: number }[];
}

interface Props
{
	//the player whose chart it is
	playerId: number;
	//the newest page, printed with the page
	page: LevelDaysPage;
}

const SERIES = [
	{ label: 'Solves' as const, color: '#74d14c' },
	{ label: 'Fails' as const, color: '#d63a49' },
];

//The problems of the player in level mode, a bar a day with attempts, a page of days at a time: the newest with the
//page, and a pager under the title loads the others.
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
			{shown.from && shown.until && (
				<span className="level-chart__range">{formatDate(shown.from, 'date')} – {formatDate(shown.until, 'date')}</span>
			)}
			{shown.pages > 1 && (
				<nav className="pagination level-chart__pager" aria-label="Pages of the level chart">
					<button type="button" className="pagination__link level-chart__previous" aria-label="Newer days"
						disabled={loading || shown.page === 1} onClick={() => load(shown.page - 1)}>&lt;</button>
					{pageItems(shown.page, shown.pages).map((item, index) => item === '…'
						? <span key={'gap' + index} className="pagination__ellipsis">…</span>
						: <button key={item} type="button" disabled={loading}
							className={'pagination__link level-chart__page' + (item === shown.page ? ' pagination__link--active' : '')}
							aria-current={item === shown.page ? 'page' : undefined} onClick={() => item !== shown.page && load(item)}>{item}</button>)}
					<button type="button" className="pagination__link level-chart__next" aria-label="Older days"
						disabled={loading || shown.page === shown.pages} onClick={() => load(shown.page + 1)}>&gt;</button>
				</nav>
			)}
			{failed && <span className="level-chart__error" role="alert">Could not load these days.</span>}
		</div>
	);

	if (shown.days.length === 0)
		return (
			<div className="chart">
				<div className="chart__title">Problems in level mode</div>
				<p className="level-chart__empty">No problems in level mode yet.</p>
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
