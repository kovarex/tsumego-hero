import { useLayoutEffect, useRef, useState } from 'react';
import type { MouseEvent } from 'react';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import { formatDate } from '../shared/dates';
import { formatChartValue, monthStart, monthTicks, parseChartDate, toggled, valueTicks } from './chartScale';
import type { ChartDate } from './chartScale';
import { ChartLegend, ChartTooltip } from './ChartParts';

dayjs.extend(utc);

export interface LineChartSeries
{
	label: string;
	color: string;
	//the left or the right value axis, each with its own scale
	axis: 'left' | 'right';
	//whether the area under the line is filled
	fill: boolean;
	//[date, value], the date as YYYY-MM-DD; null leaves a gap in the line
	points: [string, number | null][];
}

interface Props
{
	title: string | null;
	series: LineChartSeries[];
	height: number;
	//what one point stands for, it names the date under the mouse
	period: 'month' | 'day';
	//false fits the value axes to the values, for a rating that never gets near zero
	startAtZero: boolean;
}

interface Point
{
	date: string;
	at: ChartDate;
	value: number;
}

const MARGIN_TOP = 10;
const MARGIN_BOTTOM = 26;
const AXIS_GAP = 8;
const CHARACTER_WIDTH = 7;
const TICK_SPACING_X = 70;
const TICK_SPACING_Y = 45;
const AREA_OPACITY = 0.25;
const TOOLTIP_OFFSET = 12;

//The runs of points of one series without a gap, each drawn as one stretch of the line.
function stretchesOf(points: [string, number | null][]): Point[][]
{
	const stretches: Point[][] = [[]];
	for (const [date, value] of points)
		if (value === null)
			stretches.push([]);
		else
			stretches[stretches.length - 1].push({ date, at: parseChartDate(date), value });
	return stretches.filter(stretch => stretch.length > 0);
}

//A line chart over a time axis drawn as an SVG, with up to two value axes, a legend that hides a series on a click
//and the values of the date under the mouse.
export function LineChart({ title, series, height, period, startAtZero }: Props)
{
	const plotRef = useRef<HTMLDivElement>(null);
	const [width, setWidth] = useState(0);
	const [shown, setShown] = useState<boolean[]>(() => series.map(() => true));
	const [hoverDate, setHoverDate] = useState<string | null>(null);

	//The width follows the page; a chart in a hidden tab gets its width once the tab shows.
	useLayoutEffect(() =>
	{
		const plot = plotRef.current;
		if (!plot)
			return;
		setWidth(plot.clientWidth);
		const observer = new ResizeObserver(() => setWidth(plot.clientWidth));
		observer.observe(plot);
		return () => observer.disconnect();
	}, []);

	const stretches = series.map(oneSeries => stretchesOf(oneSeries.points));
	const dates = [...new Set(series.flatMap(oneSeries => oneSeries.points.map(([date]) => date)))]
		.map(date => ({ date, at: parseChartDate(date) }))
		.sort((a, b) => a.at.time - b.at.time);

	//An axis is drawn while a series on it is shown; its scale covers the values of the shown series only.
	const axisTicks = (axis: 'left' | 'right') =>
	{
		const onAxis = series.map((oneSeries, index) => shown[index] && oneSeries.axis === axis);
		if (!onAxis.includes(true))
			return null;
		const values = stretches.flatMap((seriesStretches, index) => onAxis[index] ? seriesStretches.flat().map(point => point.value) : []);
		const min = startAtZero || values.length === 0 ? 0 : Math.min(...values);
		return valueTicks(min, Math.max(min, ...values), Math.floor((height - MARGIN_TOP - MARGIN_BOTTOM) / TICK_SPACING_Y));
	};
	const leftTicks = axisTicks('left');
	const rightTicks = axisTicks('right');
	const axisWidth = (ticks: number[] | null) =>
		ticks ? Math.max(...ticks.map(tick => formatChartValue(tick).length)) * CHARACTER_WIDTH + AXIS_GAP : AXIS_GAP;
	const left = axisWidth(leftTicks);
	const right = width - axisWidth(rightTicks);
	const top = MARGIN_TOP;
	const bottom = height - MARGIN_BOTTOM;

	const first = dates.length > 0 ? dates[0].at : null;
	const last = dates.length > 0 ? dates[dates.length - 1].at : null;
	const x = (time: number) =>
		!first || !last || last.time === first.time ? (left + right) / 2 : left + (time - first.time) / (last.time - first.time) * (right - left);
	const y = (value: number, ticks: number[]) => bottom - (value - ticks[0]) / (ticks[ticks.length - 1] - ticks[0]) * (bottom - top);
	const ticksOf = (oneSeries: LineChartSeries) => (oneSeries.axis === 'left' ? leftTicks : rightTicks) ?? [0, 1];
	const gridTicks = leftTicks ?? rightTicks;

	const linePath = (stretch: Point[], ticks: number[]) =>
		stretch.map((point, index) => `${index === 0 ? 'M' : 'L'}${x(point.at.time).toFixed(1)},${y(point.value, ticks).toFixed(1)}`).join(' ');
	const areaPath = (stretch: Point[], ticks: number[]) =>
		`${linePath(stretch, ticks)} L${x(stretch[stretch.length - 1].at.time).toFixed(1)},${bottom} L${x(stretch[0].at.time).toFixed(1)},${bottom} Z`;

	const onMouseMove = (event: MouseEvent<SVGSVGElement>) =>
	{
		if (dates.length === 0)
			return;
		const mouseX = event.clientX - event.currentTarget.getBoundingClientRect().left;
		const distance = (date: { at: ChartDate }) => Math.abs(x(date.at.time) - mouseX);
		setHoverDate(dates.reduce((best, date) => distance(date) < distance(best) ? date : best).date);
	};

	const hovered = dates.find(date => date.date === hoverDate) ?? null;
	const hoveredValues = hovered === null ? [] : series.flatMap((oneSeries, index) =>
	{
		const point = shown[index] ? stretches[index].flat().find(point => point.date === hovered.date) : undefined;
		return point ? [{ series: oneSeries, value: point.value, y: y(point.value, ticksOf(oneSeries)) }] : [];
	});
	const hoveredX = hovered === null ? 0 : x(hovered.at.time);

	return (
		<div className="chart">
			{title && <div className="chart__title">{title}</div>}
			{series.length > 1 && <ChartLegend series={series} shown={shown} onToggle={index => setShown(previous => toggled(previous, index))} />}
			<div className="line-chart__plot" ref={plotRef} style={{ height }}>
				{width > 0 && (
					<svg width={width} height={height} role="img" aria-label={title ?? series.map(oneSeries => oneSeries.label).join(', ')}
						onMouseMove={onMouseMove} onMouseLeave={() => setHoverDate(null)}>
						{gridTicks && gridTicks.map(tick => (
							<line key={'grid' + tick} className="line-chart__grid" x1={left} x2={right} y1={y(tick, gridTicks)} y2={y(tick, gridTicks)} />
						))}
						{first && last && monthTicks(first, last, Math.max(2, Math.floor((right - left) / TICK_SPACING_X))).map(month => (
							<g key={'month' + month}>
								<line className="line-chart__grid" x1={x(monthStart(month))} x2={x(monthStart(month))} y1={top} y2={bottom} />
								<text className="line-chart__tick" x={x(monthStart(month))} y={bottom + 17} textAnchor="middle">
									{dayjs.utc(monthStart(month)).format(period === 'day' ? 'MMM YYYY' : 'MMM YY')}
								</text>
							</g>
						))}
						{leftTicks && leftTicks.map(tick => (
							<text key={'left' + tick} className="line-chart__tick" x={left - AXIS_GAP / 2} y={y(tick, leftTicks) + 4} textAnchor="end"
								style={{ fill: series.find(oneSeries => oneSeries.axis === 'left')?.color }}>
								{formatChartValue(tick)}
							</text>
						))}
						{rightTicks && rightTicks.map(tick => (
							<text key={'right' + tick} className="line-chart__tick" x={right + AXIS_GAP / 2} y={y(tick, rightTicks) + 4} textAnchor="start"
								style={{ fill: series.find(oneSeries => oneSeries.axis === 'right')?.color }}>
								{formatChartValue(tick)}
							</text>
						))}
						{series.map((oneSeries, index) => shown[index] && (
							<g key={oneSeries.label} className="line-chart__series" data-label={oneSeries.label}>
								{stretches[index].map((stretch, stretchIndex) => (
									<g key={stretchIndex}>
										{oneSeries.fill && <path fill={oneSeries.color} fillOpacity={AREA_OPACITY} d={areaPath(stretch, ticksOf(oneSeries))} />}
										<path className="line-chart__line" stroke={oneSeries.color} d={linePath(stretch, ticksOf(oneSeries))} />
										{stretch.length === 1 && (
											<circle fill={oneSeries.color} r={3} cx={x(stretch[0].at.time)} cy={y(stretch[0].value, ticksOf(oneSeries))} />
										)}
									</g>
								))}
							</g>
						))}
						{hoveredValues.map(hoveredValue => (
							<circle key={hoveredValue.series.label} fill={hoveredValue.series.color} r={5} cx={hoveredX} cy={hoveredValue.y} />
						))}
					</svg>
				)}
				{hovered && hoveredValues.length > 0 && (
					<ChartTooltip
						title={period === 'day' ? formatDate(hovered.date, 'date') : dayjs.utc(hovered.at.time).format('MMM YYYY')}
						values={hoveredValues}
						style={hoveredX > width / 2 ? { right: width - hoveredX + TOOLTIP_OFFSET } : { left: hoveredX + TOOLTIP_OFFSET }}
					/>
				)}
			</div>
		</div>
	);
}
