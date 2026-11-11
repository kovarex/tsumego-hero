//The flame of the daily streak in the menu (templates/element/daily_streak.php). It redraws from what
//the server says about the streak (App\Utility\DailyStreak::view(), in the result of a solve): the dim
//flame fills as far as today got, with a little pulse when a solve moved it, and when that solve
//completed the day it lights up with a burst and the number ticks up. It also hands the server the
//browser's time zone when it differs from the stored one.
class DailyStreak
{
	static element()
	{
		return document.getElementById('daily-streak');
	}

	//How long the number under the flame waits in the burst before it ticks up (daily-streak-count in daily-streak.css)
	static TICK_DELAY_MS = 600;

	//streak: App\Utility\DailyStreak::view(), as templates/element/daily_streak.php draws it
	static update(streak)
	{
		let element = DailyStreak.element();
		if (!element || !streak)
			return;
		let days = count => count + ' ' + (count == 1 ? 'day' : 'days');
		let ignites = streak.done_today && !element.classList.contains('daily-streak--done');
		let share = Number(streak.progress) || 0;
		let moved = !ignites && !streak.done_today && share > (parseFloat(element.style.getPropertyValue('--streak-progress')) || 0);
		element.style.setProperty('--streak-progress', String(share));
		//The burst plays once, when the day gets done on this page; daily-streak.css has it, and drops it for reduced motion
		if (ignites)
			element.classList.add('daily-streak--ignite');
		else if (moved)
			DailyStreak.restartClass(element, 'daily-streak--grow');
		element.classList.toggle('daily-streak--done', !!streak.done_today);
		element.querySelectorAll('[data-streak-days]').forEach(node => node.textContent = days(streak[node.dataset.streakDays]));
		//In the burst the number under the flame ticks up at its height; with reduced motion at once
		let tickLater = ignites && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		element.querySelectorAll('[data-streak-count]').forEach(node =>
		{
			if (tickLater && node.classList.contains('daily-streak__length'))
				setTimeout(() => node.textContent = formatNumber(streak[node.dataset.streakCount]), DailyStreak.TICK_DELAY_MS);
			else
				node.textContent = formatNumber(streak[node.dataset.streakCount]);
		});
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

	//Plays the animation of $className again, also when the last one is still on
	static restartClass(element, className)
	{
		element.classList.remove(className);
		void element.offsetWidth;
		element.classList.add(className);
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

	//A tap opens the details (the flame takes the focus, and a touch screen keeps the hover on) and a second tap
	//closes them; a mouse opens them by hovering (daily-streak.css)
	static closeOnSecondTap()
	{
		let element = DailyStreak.element();
		let details = element?.querySelector('.daily-streak__details');
		if (!details)
			return;
		let wasOpen = false;
		element.addEventListener('pointerdown', event =>
			wasOpen = event.pointerType !== 'mouse' && getComputedStyle(details).display !== 'none');
		element.addEventListener('click', event =>
		{
			if (event.target.closest('.daily-streak__details'))
				return;
			element.classList.toggle('daily-streak--closed', wasOpen);
			if (wasOpen)
				document.activeElement?.blur();
		});
	}

	static start()
	{
		DailyStreak.syncTimeZone();
		DailyStreak.closeOnSecondTap();
	}
}

if (document.readyState === 'loading')
	document.addEventListener('DOMContentLoaded', () => DailyStreak.start());
else
	DailyStreak.start();
