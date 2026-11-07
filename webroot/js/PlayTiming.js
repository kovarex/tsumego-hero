//The timings the players' browsers measure on the play page, sent to RumController in one beacon when
//the page is left or hidden: how long a problem took until its board took moves, and how long the
//request recording a result took. Each sample has the server's part of it from its Server-Timing header.
var PlayTiming = (function()
{
	const samples = [];
	// set when a problem page is left, so the next one knows it came from a problem (Next, the problem
	// buttons, the arrow keys), whatever the address of either
	const LEFT_PROBLEM_KEY = 'playTimingLeftProblem';

	function add(metric, milliseconds, serverMilliseconds)
	{
		samples.push({
			metric: metric,
			ms: Math.round(milliseconds),
			server_ms: serverMilliseconds == null ? null : Math.round(serverMilliseconds),
			mode: typeof mode === 'number' ? mode : null,
		});
	}

	// the whole of the request on the server ("app" of ServerTimingMiddleware), null without the header
	function serverMilliseconds(entry)
	{
		const app = (entry.serverTiming || []).find(timing => timing.name === 'app');
		return app ? app.duration : null;
	}

	function takeCameFromProblem()
	{
		try
		{
			const left = Number(sessionStorage.getItem(LEFT_PROBLEM_KEY));
			sessionStorage.removeItem(LEFT_PROBLEM_KEY);
			return left > 0 && Date.now() - left < 60000;
		}
		catch
		{
			return false;
		}
	}
	const cameFromProblem = takeCameFromProblem();

	function flush()
	{
		if (samples.length && navigator.sendBeacon)
			navigator.sendBeacon('/rum/record', new Blob([JSON.stringify({samples: samples.splice(0)})], {type: 'application/json'}));
	}
	addEventListener('pagehide', () =>
	{
		try { sessionStorage.setItem(LEFT_PROBLEM_KEY, String(Date.now())); } catch { /* no storage, no next */ }
		flush();
	});
	document.addEventListener('visibilitychange', () => document.visibilityState === 'hidden' && flush());

	return {
		// The board takes moves: measured after the next paint, from the start of the navigation, which
		// includes leaving the previous page. A reload, Back and a tab opened in the background measure
		// something else, so they are left out.
		boardReady()
		{
			const navigation = performance.getEntriesByType('navigation')[0];
			if (!navigation || navigation.type !== 'navigate' || document.visibilityState !== 'visible')
				return;
			requestAnimationFrame(() => setTimeout(() =>
			{
				if (document.visibilityState === 'visible')
					add(cameFromProblem ? 'problem_next' : 'problem_open', performance.now(), serverMilliseconds(navigation));
			}));
		},

		// The next problem shown in place (PlayNavigation.js), from the click until it was painted
		inPlace(started)
		{
			requestAnimationFrame(() => setTimeout(() => add('problem_next_in_place', performance.now() - started, null)));
		},

		// The request recording a result, from its sending until its answer was read, with the
		// Server-Timing header of the answer
		result(milliseconds, serverTiming)
		{
			const app = /(?:^|,)\s*app;dur=([\d.]+)/.exec(serverTiming || '');
			add('result_post', milliseconds, app ? Number(app[1]) : null);
		},
	};
})();
