//The flame of the daily streak in the menu (templates/element/daily_streak.php). It redraws from what
//the server says about the streak (App\Utility\DailyStreak::toArray(), in the result of a solve), and
//hands the server the browser's time zone when it differs from the stored one.
class DailyStreak
{
	static element()
	{
		return document.getElementById('daily-streak');
	}

	//streak: App\Utility\DailyStreak::view(), as templates/element/daily_streak.php draws it
	static update(streak)
	{
		let element = DailyStreak.element();
		if (!element || !streak)
			return;
		let days = count => count + ' ' + (count == 1 ? 'day' : 'days');
		element.classList.toggle('daily-streak--done', !!streak.done_today);
		element.classList.toggle('daily-streak--golden', !!streak.golden);
		element.querySelectorAll('[data-streak-active-length]').forEach(node => node.textContent = streak.golden ? streak.golden_length : streak.length);
		element.querySelectorAll('[data-streak-days]').forEach(node => node.textContent = days(streak[node.dataset.streakDays]));
		element.querySelectorAll('[data-streak-count]').forEach(node => node.textContent = streak[node.dataset.streakCount]);
		let title = element.querySelector('[data-streak-title]');
		if (title)
			title.textContent = streak.golden ? title.dataset.goldenText : title.dataset.normalText;
		let today = element.querySelector('[data-streak-today]');
		if (!today)
			return;
		if (streak.done_today)
			today.textContent = today.dataset.doneText;
		else if (streak.golden)
			today.textContent = today.dataset.goldenText.replace(/\{(\w+)\}/g, (match, field) => streak[field]);
		else
			today.textContent = today.dataset.todoText;
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
