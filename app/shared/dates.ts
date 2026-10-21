import dayjs, { type Dayjs } from 'dayjs';

/**
 * The dates of the whole site are written here, in the format the player picked in the settings, ISO by default.
 *
 * The server prints dates as <time datetime="..." data-format="...">, see localizeTimeElements, and the React
 * components call formatDate directly.
 *
 * Convention:
 * - Timestamps (full ISO 8601, e.g. 2026-08-15T12:00:00+00:00) are instants: they are shown in the user's
 *   local timezone, where the day may cross over.
 * - Calendar dates (date-only YYYY-MM-DD) are timezone-free: dayjs reads them as a local day, so the day
 *   never changes across timezones.
 */
const DATE_FORMATS = ['datetime', 'date', 'time', 'month-day'] as const;

export type DateFormat = (typeof DATE_FORMATS)[number];

type Formatter = (date: Dayjs, format: DateFormat) => string;

function patterns(patternOf: Record<DateFormat, string>): Formatter
{
	return (date, format) => date.format(patternOf[format]);
}

const BROWSER_OPTIONS: Record<DateFormat, Intl.DateTimeFormatOptions> = {
	datetime: { dateStyle: 'short', timeStyle: 'short' },
	date: { dateStyle: 'short' },
	time: { timeStyle: 'short' },
	'month-day': { month: 'short', day: 'numeric' }
};

/** Indexed by User::DATE_FORMAT_*, the choice the layout puts on <html data-date-format>. */
const FORMATTERS: Formatter[] = [
	// ISO, 2026-10-21 14:05
	patterns({ datetime: 'YYYY-MM-DD HH:mm', date: 'YYYY-MM-DD', time: 'HH:mm', 'month-day': 'MMM D' }),
	// the Czech way, 21. 10. 2026 14:05
	patterns({ datetime: 'D. M. YYYY HH:mm', date: 'D. M. YYYY', time: 'HH:mm', 'month-day': 'D MMM' }),
	// 10/21/2026 2:05 PM
	patterns({ datetime: 'MM/DD/YYYY h:mm A', date: 'MM/DD/YYYY', time: 'h:mm A', 'month-day': 'MMM D' }),
	// whatever the language the browser is set to uses
	(date, format) => date.toDate().toLocaleString(undefined, BROWSER_OPTIONS[format])
];

/** The text to show for the date, or the input itself when it is not a date. */
export function formatDate(value: string, format: DateFormat = 'datetime'): string
{
	const date = dayjs(value);
	if (!date.isValid())
		return value;
	const formatter = FORMATTERS[Number(document.documentElement.dataset.dateFormat)] ?? FORMATTERS[0];
	return formatter(date, format);
}

function isDateFormat(format: string): format is DateFormat
{
	return (DATE_FORMATS as readonly string[]).includes(format);
}

/** Replaces the text of every <time datetime="..."> rendered by the server with its formatted date. */
export function localizeTimeElements(): void
{
	document.querySelectorAll<HTMLTimeElement>('time[datetime]').forEach(element =>
	{
		const format = element.getAttribute('data-format') ?? 'datetime';
		element.textContent = formatDate(element.dateTime, isDateFormat(format) ? format : 'datetime');
	});
}
