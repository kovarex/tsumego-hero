//The flame of the daily streak in the menu (templates/element/daily_streak.php). It redraws from what
//the server says about the streak (App\Utility\DailyStreak::view(), in the result of a solve), lights
//up with a burst when that solve completed the day, and hands the server the browser's time zone when
//it differs from the stored one.
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
		//The burst plays once, when the day gets done on this page; daily-streak.css has it, and drops it for reduced motion
		if (streak.done_today && !element.classList.contains('daily-streak--done'))
			element.classList.add('daily-streak--ignite');
		element.classList.toggle('daily-streak--done', !!streak.done_today);
		element.querySelectorAll('[data-streak-days]').forEach(node => node.textContent = days(streak[node.dataset.streakDays]));
		element.querySelectorAll('[data-streak-count]').forEach(node => node.textContent = streak[node.dataset.streakCount]);
		let progress = element.querySelector('.daily-streak__progress');
		if (progress)
		{
			progress.max = streak.xp_required;
			progress.value = streak.done_today ? streak.xp_required : Math.min(streak.xp, streak.xp_required);
		}
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
