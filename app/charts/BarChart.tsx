import { useState } from 'react';
import { formatDate } from '../shared/dates';
import { formatChartValue, toggled, valueTicks } from './chartScale';
import { ChartLegend, ChartTooltip } from './ChartParts';

export interface BarChartSeries
{
	label: string;
	color: string;
	//one value for each category
	values: number[];
}

interface Props
{
	title: string;
	//the label of each bar, a date as YYYY-MM-DD
	categories: string[];
	series: BarChartSeries[];
}

const MAX_TICKS = 6;

//Horizontal bars, one for each category, with the series stacked in them, the total at the end of the bar and the
//values of a bar under the mouse. Plain HTML in a grid of two columns, the labels and the bars; the widths of the bars
//are percentages of the axis.
export function BarChart({ title, categories, series }: Props)
{
	const [shown, setShown] = useState<boolean[]>(() => series.map(() => true));
	const [hoverIndex, setHoverIndex] = useState<number | null>(null);

	const totals = categories.map((_, index) => series.reduce((sum, oneSeries, seriesIndex) => sum + (shown[seriesIndex] ? oneSeries.values[index] : 0), 0));
	const ticks = valueTicks(0, Math.max(0, ...totals), MAX_TICKS);
	const scale = ticks[ticks.length - 1];
	const percent = (value: number) => value / scale * 100 + '%';

	return (
		<div className="chart">
			<div className="chart__title">{title}</div>
			<div className="bar-chart" onMouseLeave={() => setHoverIndex(null)}>
				<div className="bar-chart__grid" style={{ gridRow: `1 / ${categories.length + 1}` }}>
					{ticks.map(tick => <span key={tick} className="bar-chart__grid-line" style={{ left: percent(tick) }} />)}
				</div>
				{categories.map((category, index) => (
					<div key={category} className="bar-chart__row" onMouseEnter={() => setHoverIndex(index)}>
						<span className="bar-chart__category" style={{ gridRow: index + 1 }}>{formatDate(category, 'date')}</span>
						<span className="bar-chart__bar" style={{ gridRow: index + 1 }}>
							{series.map((oneSeries, seriesIndex) => shown[seriesIndex] && oneSeries.values[index] > 0 && (
								<span key={oneSeries.label} className="bar-chart__segment" data-label={oneSeries.label}
									style={{ width: percent(oneSeries.values[index]), background: oneSeries.color }}>
									{oneSeries.values[index]}
								</span>
							))}
							<span className="bar-chart__total">{formatChartValue(totals[index])}</span>
							{hoverIndex === index && (
								<ChartTooltip
									title={formatDate(category, 'date')}
									values={series.flatMap((oneSeries, seriesIndex) => shown[seriesIndex] ? [{ series: oneSeries, value: oneSeries.values[index] }] : [])}
									style={{
										left: `calc((100% - var(--bar-chart-total-space)) * ${totals[index] / scale})`,
										top: '100%',
										transform: totals[index] / scale > 0.5 ? 'translateX(-100%)' : undefined
									}}
								/>
							)}
						</span>
					</div>
				))}
				<div className="bar-chart__axis" style={{ gridRow: categories.length + 1 }}>
					{ticks.map(tick => <span key={tick} className="bar-chart__tick" style={{ left: percent(tick) }}>{formatChartValue(tick)}</span>)}
				</div>
			</div>
			<ChartLegend series={series} shown={shown} onToggle={index => setShown(previous => toggled(previous, index))} />
		</div>
	);
}
