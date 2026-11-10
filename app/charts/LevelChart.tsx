import { useState } from 'react';
import { get } from '../shared/api';
import { formatDate } from '../shared/dates';
import { BarChart } from './BarChart';

//A window of the days of the level chart, as UserProfile::levelDays() gives it
export interface LevelDaysWindow
{
	from: string;
	until: string;
	days: { day: string; Solves: number; Fails: number }[];
	//the until of the window before, null when the player has no attempt before this one
	older: string | null;
	//the until of the window after, null on the latest
	newer: string | null;
}

interface Props
{
	//the player whose chart it is
	playerId: number;
	//the latest window, printed with the page
	window: LevelDaysWindow;
}

const SERIES = [
	{ label: 'Solves' as const, color: '#74d14c' },
	{ label: 'Fails' as const, color: '#d63a49' },
];

//The problems of the player in level mode, a bar a day, LEVEL_DAYS_WINDOW days at a time: the latest with the page,
//and Older and Newer load the window before or after it.
export function LevelChart({ playerId, window: initial }: Props)
{
	const [page, setPage] = useState(initial);
	const [loading, setLoading] = useState(false);
	const [failed, setFailed] = useState(false);

	const load = async (until: string) =>
	{
		setLoading(true);
		setFailed(false);
		try
		{
			setPage(await get<LevelDaysWindow>(`/users/level_days/${playerId}?until=${until}`));
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
		<div className="level-chart__pages">
			<button type="button" className="btn btn--small level-chart__older" disabled={loading || page.older === null}
				onClick={() => page.older && load(page.older)}>‹ Older</button>
			<span className="level-chart__range">{formatDate(page.from, 'date')} – {formatDate(page.until, 'date')}</span>
			<button type="button" className="btn btn--small level-chart__newer" disabled={loading || page.newer === null}
				onClick={() => page.newer && load(page.newer)}>Newer ›</button>
			{failed && <span className="level-chart__error" role="alert">Could not load these days.</span>}
		</div>
	);

	if (page.days.length === 0)
		return (
			<div className="chart">
				<div className="chart__title">Problems in level mode</div>
				{header}
				<p className="level-chart__empty">No problems in these days.</p>
			</div>
		);
	return (
		<BarChart
			key={page.until}
			title="Problems in level mode"
			header={header}
			categories={page.days.map(day => day.day)}
			series={SERIES.map(oneSeries => ({ ...oneSeries, values: page.days.map(day => day[oneSeries.label]) }))}
		/>
	);
}
