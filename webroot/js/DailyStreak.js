//The flame of the daily streak in the menu (templates/element/daily_streak.php). It redraws from what
//the server says about the streak (App\Utility\DailyStreak::toArray(): the result of a solve, or
//the answer to the time zone), and tells the server the browser's time zone when it differs.
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

	//Sent only when the zone changed, so a page normally makes no request for it
	static syncTimeZone()
	{
		let element = DailyStreak.element();
		let timeZone = DailyStreak.browserTimeZone();
		if (!element || !timeZone || timeZone === element.dataset.timeZone)
			return;
		fetch('/daily-streak/time-zone', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
			body: 'time_zone=' + encodeURIComponent(timeZone)
		}).then(response => response.ok ? response.json() : null).then(streak =>
		{
			if (!streak)
				return;
			element.dataset.timeZone = timeZone;
			DailyStreak.update(streak);
		}).catch(error => console.error('DailyStreak.syncTimeZone failed:', error));
	}
}

if (document.readyState === 'loading')
	document.addEventListener('DOMContentLoaded', () => DailyStreak.syncTimeZone());
else
	DailyStreak.syncTimeZone();
