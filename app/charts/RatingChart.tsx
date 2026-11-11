import { useState } from 'react';
import { LineChart } from './LineChart';

//A range of the rating chart, as UserProfile::ratingRanges() gives it
export interface RatingRange
{
	//what one point stands for, the highest rating of it
	period: 'day' | 'week' | 'month';
	//[the first day of the period, the rating]
	points: [string, number][];
}

interface Props
{
	//the player whose chart it is
	playerId: number;
	//every range by name, shortest first, all printed with the page
	ranges: Record<string, RatingRange>;
	//the range shown first
	initial: string;
}

//The rating of the player over a range picked by the buttons above the chart, on an axis of ranks. Every range comes
//with the page, so a button just switches.
export function RatingChart({ ranges, initial }: Props)
{
	const [range, setRange] = useState(initial in ranges ? initial : Object.keys(ranges)[0]);
	const shown = ranges[range];
	const header = (
		<div className="rating-chart__header">
			<div className="rating-chart__ranges" role="group" aria-label="Range of the rating chart">
				{Object.keys(ranges).map(oneRange => (
					<button key={oneRange} type="button"
						className={'rating-chart__range' + (oneRange === range ? ' rating-chart__range--active' : '')}
						aria-pressed={oneRange === range} onClick={() => setRange(oneRange)}>
						{oneRange}
					</button>
				))}
			</div>
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
