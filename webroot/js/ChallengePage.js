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
	// A cell of a problem is a link to it (the link fills the cell, so the middle button and a new tab work);
	// a click on the rest of a taller cell opens it too. On a touch screen, which has no pointing, the first
	// tap on a failed cell shows its moves and the next one opens the problem.
	var tappedLineCell = null;
	standings.addEventListener('click', function(event)
	{
		var cell = event.target.closest('.challenges__cell--link');
		if (!cell)
			return;
		if (cell.classList.contains('challenges__cell--line') && window.matchMedia('(hover: none)').matches && tappedLineCell !== cell)
		{
			tappedLineCell = cell;
			event.preventDefault();
			return;
		}
		if (!event.target.closest('a'))
			window.location.href = cell.querySelector('.challenges__cell-link').href;
	});
	standings.addEventListener('mouseover', showFailedLine);
	standings.addEventListener('focusin', showFailedLine);
	standings.addEventListener('click', showFailedLine);
	// the page, or the table sideways
	document.addEventListener('scroll', function()
	{
		if (shownLineCell && shownLineCell.isConnected)
			placeFailedLine(shownLineCell);
	}, {capture: true, passive: true});

	// While the challenge is in progress the page watches its change stamp (webroot/js/Live.js) and
	// fetches the table once it changed; the finish brings the places, the medals and the rest of the
	// page, so it reloads. What changes without anything written, a run whose time ran out, comes with
	// the full refresh every SAFETY_SECONDS.
	var SAFETY_SECONDS = 60;
	if (standings.dataset.finished === '1')
		return;
	var asking = false;
	var askAgain = false;

	function refresh()
	{
		if (asking)
		{
			askAgain = true;
			return;
		}
		asking = true;
		fetch(standings.dataset.standingsUrl, {headers: {Accept: 'application/json'}, credentials: 'same-origin'})
			.then(function(response)
			{
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
				shownLineCell = null;
				if (typeof window.renderPreviews === 'function')
					window.renderPreviews();
			})
			// a request that fails is made again on the next change or safety refresh
			.catch(function() {})
			.then(function()
			{
				asking = false;
				if (askAgain)
				{
					askAgain = false;
					refresh();
				}
			});
	}

	Live.watch(standings.dataset.liveChannel, refresh);
	setInterval(function()
	{
		if (document.visibilityState === 'visible')
			refresh();
	}, SAFETY_SECONDS * 1000);
})();
