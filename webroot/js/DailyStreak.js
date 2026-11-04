//The flame of the daily streak in the menu (templates/element/daily_streak.php). It redraws from what
//the server says about the streak (App\Utility\DailyStreak::toArray(), in the result of a solve), and
//hands the server the browser's time zone when it differs from the stored one.
class DailyStreak
{
	static element()
	{
		return document.getElementById('daily-streak');
	}

	//streak: {length, best, freezes, done_today}
	static update(streak)
	{
		let element = DailyStreak.element();
		if (!element || !streak)
			return;
		let dayWord = days => days == 1 ? 'day' : 'days';
		element.classList.toggle('daily-streak--done', !!streak.done_today);
		element.querySelectorAll('[data-streak-length]').forEach(node => node.textContent = streak.length);
		element.querySelectorAll('[data-streak-length-word]').forEach(node => node.textContent = dayWord(streak.length));
		element.querySelectorAll('[data-streak-best]').forEach(node => node.textContent = streak.best);
		element.querySelectorAll('[data-streak-best-word]').forEach(node => node.textContent = dayWord(streak.best));
		element.querySelectorAll('[data-streak-freezes]').forEach(node => node.textContent = streak.freezes);
		let today = element.querySelector('[data-streak-today]');
		if (today)
			today.textContent = streak.done_today ? today.dataset.doneText : today.dataset.todoText;
	}

	static browserTimeZone()
	{
		try
		{
			return Intl.DateTimeFormat().resolvedOptions().timeZone || null;
		}
		catch (e)
		{
			return null;
		}
	}

	//Like the other settings, the zone goes in a cookie that the next request stores and drops
	//(AppController::saveSettingsFromCookies), so it costs no request of its own.
	static syncTimeZone()
	{
		let element = DailyStreak.element();
		let timeZone = DailyStreak.browserTimeZone();
		if (element && timeZone && timeZone !== element.dataset.timeZone)
			setCookie('time_zone', timeZone);
	}
}

if (document.readyState === 'loading')
	document.addEventListener('DOMContentLoaded', () => DailyStreak.syncTimeZone());
else
	DailyStreak.syncTimeZone();
