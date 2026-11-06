//How long ago a moment was, in words: "just now", "5 minutes ago", "an hour ago", "yesterday", "3 days ago",
//"a week ago", "2 months ago". Up to the hours it goes by the time elapsed; from yesterday on by the days of the
//calendar in the local timezone, so what happened at 23:00 is "yesterday" at 1:00 the next morning.
const MINUTE = 60 * 1000;
const HOUR = 60 * MINUTE;

function ago(count: number, unit: string, one: string): string
{
	return count === 1 ? one + ' ago' : count + ' ' + unit + 's ago';
}

//The days between the local calendar days of the two moments.
function calendarDaysBetween(then: Date, now: Date): number
{
	const thenDay = new Date(then.getFullYear(), then.getMonth(), then.getDate());
	const nowDay = new Date(now.getFullYear(), now.getMonth(), now.getDate());
	//Math.round absorbs the hour a daylight saving change adds to or takes from a day
	return Math.round((nowDay.getTime() - thenDay.getTime()) / (24 * HOUR));
}

export function relativeTime(then: Date, now: Date): string
{
	const elapsed = now.getTime() - then.getTime();
	if (elapsed < MINUTE)
		return 'just now';
	if (elapsed < HOUR)
		return ago(Math.floor(elapsed / MINUTE), 'minute', 'a minute');
	const days = calendarDaysBetween(then, now);
	if (days === 0)
		return ago(Math.floor(elapsed / HOUR), 'hour', 'an hour');
	if (days === 1)
		return 'yesterday';
	if (days < 7)
		return days + ' days ago';
	if (days < 30)
		return ago(Math.floor(days / 7), 'week', 'a week');
	if (days < 365)
		return ago(Math.floor(days / 30), 'month', 'a month');
	return ago(Math.floor(days / 365), 'year', 'a year');
}
