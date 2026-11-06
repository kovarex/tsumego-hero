//The page of a challenge (templates/Challenges/view.php): its table of players, which keeps itself up
//to date while the challenge is in progress, and the board previews of its problem buttons.
(function()
{
	var standings = document.getElementById('challenge-standings');
	if (!standings)
		return;

	// The table scrolls sideways when it is wider than the white box, which would cut off the board
	// previews of its problem buttons, so a preview is put under its button on the screen instead.
	standings.addEventListener('mouseover', function(event)
	{
		var link = event.target.closest('.challenges__problem a[data-tsumego-id]');
		if (!link)
			return;
		var box = link.querySelector('.tooltip-box');
		var rect = link.getBoundingClientRect();
		box.style.top = (rect.bottom + 4) + 'px';
		box.style.left = (rect.left - 1) + 'px';
	});

	// While the challenge is in progress the table is asked for again every few seconds, as long as
	// the tab is visible; the finish brings the places, the medals and the rest of the page, so it reloads.
	var REFRESH_SECONDS = 10;
	if (standings.dataset.finished === '1')
		return;
	var timer = null;
	var asking = false;

	function refresh()
	{
		timer = null;
		if (asking)
			return;
		asking = true;
		fetch(standings.dataset.standingsUrl, {headers: {Accept: 'application/json'}, credentials: 'same-origin'})
			.then(function(response)
			{
				asking = false;
				if (!response.ok)
					throw new Error('HTTP ' + response.status);
				return response.json();
			})
			.then(function(data)
			{
				if (data.finished)
				{
					location.reload();
					return;
				}
				standings.innerHTML = data.html;
				if (typeof window.renderPreviews === 'function')
					window.renderPreviews();
				schedule();
			})
			// a request that fails is tried again on the next round
			.catch(function()
			{
				asking = false;
				schedule();
			});
	}

	function schedule()
	{
		if (timer === null && document.visibilityState === 'visible')
			timer = setTimeout(refresh, REFRESH_SECONDS * 1000);
	}

	document.addEventListener('visibilitychange', function()
	{
		if (document.visibilityState === 'visible')
			refresh();
		else if (timer !== null)
		{
			clearTimeout(timer);
			timer = null;
		}
	});
	schedule();
})();
