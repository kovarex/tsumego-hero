import dayjs from 'dayjs';

/**
 * The one date format of the site, ISO 8601 style (largest unit first), the same for every browser locale.
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
const FORMATS = {
	datetime: 'YYYY-MM-DD HH:mm',
	date: 'YYYY-MM-DD',
	time: 'HH:mm',
	'month-day': 'MMM D'
};

export type DateFormat = keyof typeof FORMATS;

/** The text to show for the date, or the input itself when it is not a date. */
export function formatDate(value: string, format: DateFormat = 'datetime'): string
{
	const date = dayjs(value);
	return date.isValid() ? date.format(FORMATS[format]) : value;
}

function isDateFormat(format: string): format is DateFormat
{
	return Object.prototype.hasOwnProperty.call(FORMATS, format);
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
