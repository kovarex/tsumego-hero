//The front end of the hosting closes a connection after about 5 seconds without a request, so the
//next page a player opens after thinking for longer waits for a new connection first: two more
//round trips, about 0.2 s for players in Europe. While the page is visible and the player is
//active, a tiny request every few seconds keeps the connection open for their next click.
//On window, so the tests (WebDriver sees only the properties of window) can reach it
window.KeepAlive = (function()
{
	const INTERVAL_MS = 4000;
	const ACTIVE_FOR_MS = 5 * 60 * 1000; // the player counts as away after this long without any input
	const PING_URL = '/keepalive.txt'; // an empty static file, answered without PHP

	let lastInput = Date.now();
	let timer = null;

	function ping()
	{
		if (document.hidden || Date.now() - lastInput > ACTIVE_FOR_MS)
		{
			stop();
			return;
		}
		// Sent with the cookies like any request of the page, so the browser uses the same connection
		fetch(PING_URL, { method: 'HEAD', cache: 'no-store' }).catch(function() {});
	}

	function start()
	{
		if (timer === null && !document.hidden)
			timer = setInterval(ping, INTERVAL_MS);
	}

	function stop()
	{
		if (timer === null)
			return;
		clearInterval(timer);
		timer = null;
	}

	function input()
	{
		lastInput = Date.now();
		start();
	}

	for (const event of ['pointerdown', 'pointermove', 'keydown', 'wheel', 'touchstart'])
		window.addEventListener(event, input, { passive: true });
	document.addEventListener('visibilitychange', function() {
		if (document.hidden)
			stop();
		else
			input();
	});
	start();

	return {
		isRunning: function() { return timer !== null; },
		// for the tests
		pretendLastInputWasMsAgo: function(ms) { lastInput = Date.now() - ms; },
	};
})();
