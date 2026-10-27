import type { CSSProperties } from 'react';
import { formatChartValue } from './chartScale';

//The pieces the line and the bar chart share: the colour swatch, the legend and the tooltip.

export interface ChartSeriesLabel
{
	label: string;
	color: string;
}

export function ChartSwatch({ color }: { color: string })
{
	return (
		<svg className="chart__swatch" viewBox="0 0 12 12" aria-hidden="true">
			<rect width="12" height="12" fill={color} />
		</svg>
	);
}

//The names of the series; a click hides a series or shows it again.
export function ChartLegend({ series, shown, onToggle }: { series: ChartSeriesLabel[]; shown: boolean[]; onToggle: (index: number) => void })
{
	return (
		<div className="chart__legend">
			{series.map((oneSeries, index) => (
				<button
					key={oneSeries.label}
					type="button"
					className={'chart__legend-item' + (shown[index] ? '' : ' chart__legend-item--hidden')}
					aria-pressed={shown[index]}
					onClick={() => onToggle(index)}
				>
					<ChartSwatch color={oneSeries.color} />
					{oneSeries.label}
				</button>
			))}
		</div>
	);
}

export function ChartTooltip({ title, values, style }: { title: string; values: { series: ChartSeriesLabel; value: number }[]; style: CSSProperties })
{
	return (
		<div className="chart__tooltip" style={style}>
			<div className="chart__tooltip-title">{title}</div>
			{values.map(({ series, value }) => (
				<div key={series.label}>
					<ChartSwatch color={series.color} />
					{series.label}: {formatChartValue(value)}
				</div>
			))}
		</div>
	);
}
