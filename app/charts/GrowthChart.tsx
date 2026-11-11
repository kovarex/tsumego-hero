import { useState } from 'react';
import { LineChart } from './LineChart';
import type { LineChartSeries } from './LineChart';
import { RangeChart } from './RangeChart';
import type { ChartRange } from './RangeChart';

type View = 'growth' | 'active';

const VIEWS: { view: View; label: string }[] = [
	{ view: 'growth', label: 'Growth' },
	{ view: 'active', label: 'Active players' },
];
//The view the visitor last picked, kept in their browser
const STORAGE_KEY = 'siteGrowthView';

interface Props
{
	//the problems and the users month by month, on two axes
	growth: { series: LineChartSeries[]; period: 'month' | 'week' | 'day'; height: number };
	//the players who solved a problem each day, with its ranges (ActivePlayers::ranges())
	active: { label: string; color: string; ranges: Record<string, ChartRange>; initial: string };
}

function storedView(): View
{
	try
	{
		return localStorage.getItem(STORAGE_KEY) === 'active' ? 'active' : 'growth';
	}
	catch
	{
		return 'growth';
	}
}

//The "Site Growth" chart of the home page: a toggle in its header switches between the growth of the site (the
//problems and the players) and the active players with their ranges, both printed with the page. It opens on the
//growth, or on the view the visitor last picked.
export function GrowthChart({ growth, active }: Props)
{
	const [view, setView] = useState<View>(storedView);
	const pick = (picked: View) =>
	{
		setView(picked);
		try
		{
			localStorage.setItem(STORAGE_KEY, picked);
		}
		catch
		{
			//a browser without storage just opens on the growth next time
		}
	};
	const toggle = (
		<div className="range-chart__ranges" role="group" aria-label="What the chart shows">
			{VIEWS.map(({ view: oneView, label }) => (
				<button key={oneView} type="button"
					className={'range-chart__range' + (oneView === view ? ' range-chart__range--active' : '')}
					aria-pressed={oneView === view} onClick={() => pick(oneView)}>
					{label}
				</button>
			))}
		</div>
	);
	if (view === 'active')
		return <RangeChart title={null} label={active.label} color={active.color} ranges={active.ranges} initial={active.initial} rankAxis={false} startAtZero lead={toggle} />;
	return (
		<LineChart
			title={null}
			header={<div className="range-chart__header">{toggle}</div>}
			series={growth.series}
			period={growth.period}
			height={growth.height}
			startAtZero
		/>
	);
}
