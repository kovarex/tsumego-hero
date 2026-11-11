import { useState } from 'react';
import { get } from '../shared/api';
import { LineChart } from './LineChart';

//A range of the rating chart, as UserProfile::ratingHistory() gives it
export interface RatingHistory
{
	range: string;
	//what one point stands for, the highest rating of it
	period: 'day' | 'week' | 'month';
	//[the first day of the period, the rating]
	points: [string, number | null][];
}

interface Props
{
	//the player whose chart it is
	playerId: number;
	//the names of the ranges, shortest first
	ranges: string[];
	//the range printed with the page
	history: RatingHistory;
}

//The rating of the player over a range picked by the buttons above the chart, 6M with the page; another range is
//loaded on its first click and kept.
export function RatingChart({ playerId, ranges, history }: Props)
{
	const [loaded, setLoaded] = useState<Record<string, RatingHistory>>({ [history.range]: history });
	const [range, setRange] = useState(history.range);
	const [loading, setLoading] = useState<string | null>(null);
	const [failed, setFailed] = useState(false);

	const pick = async (picked: string) =>
	{
		setFailed(false);
		if (loaded[picked])
		{
			setRange(picked);
			return;
		}
		setLoading(picked);
		try
		{
			const fetched = await get<RatingHistory>(`/users/ratingHistory/${playerId}?range=${encodeURIComponent(picked)}`);
			setLoaded(previous => ({ ...previous, [picked]: fetched }));
			setRange(picked);
		}
		catch
		{
			setFailed(true);
		}
		finally
		{
			setLoading(null);
		}
	};

	const shown = loaded[range];
	const header = (
		<div className="rating-chart__header">
			<div className="rating-chart__ranges" role="group" aria-label="Range of the rating chart">
				{ranges.map(oneRange => (
					<button key={oneRange} type="button"
						className={'rating-chart__range' + (oneRange === range ? ' rating-chart__range--active' : '')}
						aria-pressed={oneRange === range} disabled={loading !== null} onClick={() => pick(oneRange)}>
						{oneRange}
					</button>
				))}
			</div>
			{failed && <span className="rating-chart__error" role="alert">Could not load this range.</span>}
		</div>
	);
	return (
		<LineChart
			key={range}
			title="Overall rating"
			header={header}
			series={[{ label: 'Rating', color: '#74d14c', axis: 'left', fill: false, points: shown.points }]}
			period={shown.period}
			height={360}
			startAtZero={false}
			rankAxis
		/>
	);
}
