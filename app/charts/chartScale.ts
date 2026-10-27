//The arithmetic of the charts: where the ticks of the axes go and how a value reads. Kept apart from the drawing, so that
//it reads on its own.

//A calendar date of the chart data, YYYY-MM-DD, as a month index and a position on the time axis.
export interface ChartDate
{
	//months since year 0, so that the ticks can step by whole months
	month: number;
	//milliseconds, UTC, the position on the axis
	time: number;
}

export function parseChartDate(date: string): ChartDate
{
	const [year, month, day] = date.split('-').map(Number);
	return { month: year * 12 + month - 1, time: Date.UTC(year, month - 1, day) };
}

//The start of a month index as a position on the time axis.
export function monthStart(month: number): number
{
	return Date.UTC(Math.floor(month / 12), month % 12, 1);
}

const MONTH_STEPS = [1, 2, 3, 4, 6, 12, 24, 36, 60, 120];

//The months the time axis labels: whole steps (a step of 12 and more starts at January), at most maxCount of them.
export function monthTicks(first: ChartDate, last: ChartDate, maxCount: number): number[]
{
	const step = MONTH_STEPS.find(candidate => Math.floor((last.month - first.month) / candidate) + 1 <= maxCount)
		?? MONTH_STEPS[MONTH_STEPS.length - 1];
	const ticks: number[] = [];
	for (let month = Math.ceil(first.month / step) * step; month <= last.month; month += step)
		if (monthStart(month) >= first.time)
			ticks.push(month);
	return ticks;
}

//Round values that cover min to max, at most about maxCount + 1 of them: steps of 1, 2, 2.5 or 5 times a power of ten,
//whole numbers only, as the charts count things.
export function valueTicks(min: number, max: number, maxCount: number): number[]
{
	const range = max - min > 0 ? max - min : Math.max(1, Math.abs(max));
	const rawStep = range / Math.max(1, maxCount);
	const magnitude = Math.pow(10, Math.floor(Math.log10(rawStep)));
	const step = [1, 2, 2.5, 5, 10]
		.map(factor => factor * magnitude)
		.find(candidate => candidate >= rawStep && Number.isInteger(candidate)) ?? Math.max(1, 10 * magnitude);
	const ticks = [Math.floor(min / step) * step];
	while (ticks[ticks.length - 1] < max || ticks.length < 2)
		ticks.push(ticks[ticks.length - 1] + step);
	return ticks;
}

//Values are shown as whole numbers with the separators of the browser's language.
export const formatChartValue = (value: number) => value.toLocaleString(undefined, { maximumFractionDigits: 0 });

//Which series of a chart are shown, after the legend toggled the one at index.
export function toggled(shown: boolean[], index: number): boolean[]
{
	return shown.map((value, shownIndex) => shownIndex === index ? !value : value);
}
