//The page of a challenge (templates/Challenges/view.php): its table of players, which keeps itself up
//to date while the challenge is in progress, the board previews of its problem buttons and the lines
//played on its failed problems.
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

	// A failed or timed out problem shows the moves played until then, numbered, on its board, when its
	// cell is pointed at, tapped or focused; the board is drawn the first time and put under the cell,
	// or above it when it doesn't fit below, and follows the cell when the page scrolls. The listeners
	// are on the section, so they keep working on the cells of a table the refresh below puts in.
	var shownLineCell = null;
	function placeFailedLine(cell)
	{
		var box = cell.querySelector('.challenges__line-board');
		var rect = cell.getBoundingClientRect();
		var above = rect.top - 4 - box.offsetHeight;
		box.style.top = (rect.bottom + 4 + box.offsetHeight > window.innerHeight && above >= 0 ? above : rect.bottom + 4) + 'px';
		box.style.left = Math.max(4, Math.min(rect.left, window.innerWidth - box.offsetWidth - 4)) + 'px';
	}
	function showFailedLine(event)
	{
		var cell = event.target.closest('.challenges__cell--line');
		if (!cell)
			return;
		var box = cell.querySelector('.challenges__line-board');
		if (!box.querySelector('svg'))
		{
			var data = JSON.parse(cell.dataset.failedLine);
			var svg = createBoard(cell, data.black, data.white, data.xMax, data.yMax, data.boardSize, '', {moves: data.line, firstColor: data.firstColor});
			// half again the size of a button's preview, so the numbers can be read
			var width = parseFloat(svg.style.width), height = parseFloat(svg.style.height);
			svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
			svg.style.width = (width * 1.5) + 'px';
			svg.style.height = (height * 1.5) + 'px';
		}
		shownLineCell = cell;
		placeFailedLine(cell);
	}
	standings.addEventListener('mouseover', showFailedLine);
	standings.addEventListener('focusin', showFailedLine);
	standings.addEventListener('click', showFailedLine);
	// the page, or the table sideways
	document.addEventListener('scroll', function()
	{
		if (shownLineCell && shownLineCell.isConnected)
			placeFailedLine(shownLineCell);
	}, {capture: true, passive: true});

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
