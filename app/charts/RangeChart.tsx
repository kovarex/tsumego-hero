import { useState } from 'react';
import type { ReactNode } from 'react';
import { LineChart } from './LineChart';

//A range of the chart, as ChartRanges::build() gives it
export interface ChartRange
{
	//what one point stands for
	period: 'day' | 'week' | 'month';
	//[the first day of the period, the value]
	points: [string, number][];
	//the day of the last point when it is still going on, today so far
	partial: string | null;
}

interface Props
{
	//null when the page names the chart above it
	title: string | null;
	//the name of the value in the tooltip
	label: string;
	color: string;
	//every range by name, shortest first, all printed with the page
	ranges: Record<string, ChartRange>;
	//the range shown first
	initial: string;
	//the value axis in go ranks, for a rating
	rankAxis: boolean;
	//false fits the value axis to the values
	startAtZero: boolean;
	//before the range buttons, as the toggle of the site growth chart
	lead?: ReactNode;
}

//A line over a range picked by the buttons 1M · 6M · 1Y · All above the chart. Every range comes with the page, so a
//button just switches.
export function RangeChart({ title, label, color, ranges, initial, rankAxis, startAtZero, lead }: Props)
{
	const [range, setRange] = useState(initial in ranges ? initial : Object.keys(ranges)[0]);
	const shown = ranges[range];
	const header = (
		<div className="range-chart__header">
			{lead}
			<div className="range-chart__ranges" role="group" aria-label={title ? `Range of the ${title.toLowerCase()} chart` : 'Range of the chart'}>
				{Object.keys(ranges).map(oneRange => (
					<button key={oneRange} type="button"
						className={'range-chart__range' + (oneRange === range ? ' range-chart__range--active' : '')}
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
			title={title}
			header={header}
			series={[{ label, color, axis: 'left', fill: false, points: shown.points }]}
			period={shown.period}
			height={360}
			startAtZero={startAtZero}
			rankAxis={rankAxis}
			partialDate={shown.partial ?? undefined}
		/>
	);
}
