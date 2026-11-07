//The play page shows the next problem in place, without loading the page again: its route answers in JSON
//(AppController::renderPlay()) with the data of its script and the page's content, which replaces the old
//one, and the state, the board and the page around it are set up again (initPlayState() and
//createPlayBoard() of PlayBoard.js, setUpProblem() and setUpPageEnd() of PlayPage.js). The next problems
//are loaded ahead while the player thinks, without recording a visit (X-Play-Prefetch), and the page
//records it when it shows one. Back and Forward show the problems again in place.
//The level mode (with the class assignments) and the mistake training go in place, from a problem to one
//of the same kind of address; the rating and the time mode load their pages, as their next problem is
//decided when it is asked for. Whatever fails loads the page instead.
var PlayNavigation = (function()
{
	// the kinds of addresses shown in place: a problem of a collection, of the training (its first due one
	// without a number), of a class assignment (its first one not solved without a number)
	const KINDS = [/^\/\d+$/, /^\/mistake-training(\/play\/\d+)?$/, /^\/classes\/play\/\d+(\/\d+)?$/];
	// how many problems along the Next links are loaded ahead
	const AHEAD = 3;
	// address => promise of {data, path, loadedAt}, the problems loaded ahead
	const loaded = new Map();
	// counts the times what was loaded ahead was dropped, a load of an older time adds nothing
	let generation = 0;
	let shownPath = null;
	let navigating = false;

	function kindOf(path)
	{
		return KINDS.findIndex(kind => kind.test(path));
	}

	// the path of an address of this site shown in place from the shown problem, null for any other
	function inPlacePath(url)
	{
		if (!url || shownPath === null)
			return null;
		let address;
		try { address = new URL(url, location.href); }
		catch { return null; }
		if (address.origin !== location.origin || address.search || address.hash)
			return null;
		let kind = kindOf(address.pathname);
		return kind >= 0 && kind === kindOf(shownPath) ? address.pathname : null;
	}

	function inPlaceMode()
	{
		return mode == playPage.page.modes.level || mode == playPage.page.modes.mistakeTraining;
	}

	function load(path, prefetch)
	{
		let headers = {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'};
		if (prefetch)
			headers['X-Play-Prefetch'] = '1';
		return fetch(path, {headers: headers, credentials: 'same-origin'}).then(response =>
		{
			let finalPath = new URL(response.url).pathname;
			// a link that stopped holding is sent elsewhere, an error or a page that isn't a problem loads
			if (!response.ok || !(response.headers.get('Content-Type') || '').includes('application/json') || kindOf(finalPath) !== kindOf(path))
				throw new Error('No problem at ' + path);
			return response.json().then(data => ({data: data, path: finalPath, loadedAt: performance.now()}));
		});
	}

	function prefetch(path, ahead)
	{
		if (!path || loaded.has(path))
			return;
		let entry = load(path, true);
		let loadedIn = generation;
		loaded.set(path, entry);
		entry.then(({data}) =>
		{
			if (ahead > 1 && loadedIn === generation)
				prefetch(inPlacePath(data.problem.nextLink), ahead - 1);
		}, () =>
		{
			if (loaded.get(path) === entry)
				loaded.delete(path);
		});
	}

	// the problems the player may go to next: along Next, and the one before
	function prefetchAround()
	{
		if (!inPlaceMode())
			return;
		prefetch(inPlacePath(nextButtonLink), AHEAD);
		prefetch(inPlacePath(previousButtonLink), 1);
	}

	// the texture of the board is linked in the head, a golden problem has its own
	function showTexture(texture)
	{
		let link = document.querySelector('link[href^="/besogo/css/board-"]');
		if (!texture)
			return;
		let href = '/besogo/css/board-' + texture + '.css';
		if (link)
		{
			if (link.getAttribute('href') !== href)
				link.setAttribute('href', href);
			return;
		}
		link = document.createElement('link');
		link.rel = 'stylesheet';
		link.href = href;
		document.head.append(link);
	}

	function show({data, path, loadedAt}, push)
	{
		let content = document.getElementById('content');
		// the sprint went on running since the problem was loaded
		let xp = data.problem.xpStatus;
		if (xp && xp.sprintRemainingSeconds > 0)
			xp.sprintRemainingSeconds = Math.max(0, xp.sprintRemainingSeconds - Math.floor((performance.now() - loadedAt) / 1000));
		// what is left of the shown problem
		hideCoordPopup();
		window.unmountApps?.(content);
		besogo.destroy();
		if (timeModeTimer)
			timeModeTimer.stop();

		playPage.page = data.page;
		playPage.problem = data.problem;
		document.title = data.title;
		showTexture(data.problem.board?.texture);
		if (push)
			history.pushState({playPath: path}, '', path);
		shownPath = path;
		let template = document.createElement('template');
		template.innerHTML = data.content;
		// the page's scripts are running already, playPage is set above
		template.content.querySelectorAll('script').forEach(script => script.remove());
		content.replaceChildren(template.content);
		window.scrollTo(0, 0);

		initPlayState();
		createPlayBoard();
		setUpProblem();
		setUpPageEnd();
		window.mountApps?.(content);
		window.localizeTimeElements?.(content);
		if (data.visit)
		{
			let body = new FormData();
			body.append('markAsVisited', data.visit.markAsVisited ? '1' : '0');
			fetch('/api/v1/problems/' + data.visit.setConnectionID + '/visit', {method: 'POST', body: body, headers: {'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin'});
		}
		// what was loaded ahead along the old links stays for the new ones
		prefetchAround();
	}

	// Goes to the address, in place when it can; push: a new entry of the history, as a link adds
	function open(url, {push = true} = {})
	{
		let path = inPlacePath(url);
		if (!path || !inPlaceMode())
		{
			window.location.href = url;
			return;
		}
		if (navigating)
			return;
		navigating = true;
		let started = performance.now();
		// what wasn't loaded ahead (or was dropped for a result) is loaded now, as the page would be
		(loaded.get(path) || load(path, false))
			.then(entry =>
			{
				loaded.delete(path);
				show(entry, push);
				PlayTiming.inPlace(started);
			})
			.catch(error =>
			{
				console.error('The problem is loaded with its page:', error);
				window.location.href = url;
			})
			.finally(() => navigating = false);
	}

	// A result of the shown problem was sent: what was loaded ahead has the state before it (the problem's
	// button, the hearts), so it is dropped, and a Next before the result is recorded loads the problem
	// then, without waiting for the result, as the page would be. Once it is recorded, it is loaded ahead again.
	function resultSent()
	{
		generation++;
		loaded.clear();
	}

	function resultRecorded()
	{
		resultSent();
		prefetchAround();
	}

	// the page opened with a problem
	function start()
	{
		if (kindOf(location.pathname) < 0 || location.search || location.hash)
			return;
		shownPath = location.pathname;
		history.replaceState({playPath: shownPath}, '');
		prefetchAround();
	}

	document.addEventListener('click', event =>
	{
		if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)
			return;
		let link = event.target.closest?.('a[href]');
		if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download'))
			return;
		if (!inPlacePath(link.href) || !inPlaceMode())
			return;
		event.preventDefault();
		open(link.href);
	});

	addEventListener('popstate', event =>
	{
		if (shownPath === null || location.pathname === shownPath)
			return;
		if (event.state && event.state.playPath && inPlacePath(event.state.playPath))
			open(event.state.playPath, {push: false});
		else
			window.location.reload();
	});

	return {open: open, start: start, resultSent: resultSent, resultRecorded: resultRecorded};
})();
