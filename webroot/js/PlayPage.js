//The play page's script: the hearts, the clock, the modes and the panels around the board. The board
//and the state of the page it reads are set up by PlayBoard.js, which runs as soon as the board's
//<div> is parsed; this runs at the end of the page (Tsumegos/play.php). Both are classic scripts,
//so their top-level functions stay global for the onclick attributes and the other files.

function updateCurrentNavigationButton(status)
{
	current = document.getElementById("currentNavigationButton");
	// The navigation buttons don't exist in all of the modes
	if (!current)
		return;
	current.parentElement.parentElement.className = 'problem-nav__item problem-nav__item--' + status + ' problem-nav__item--current';
}

// The mistake training button of the open problem as the server stored its result:
// the colour, the rung badge and the tooltip that explains them
function updateMistakeTrainingButton(button, xp)
{
	let current = document.getElementById("currentNavigationButton");
	if (!current)
		return;
	updateCurrentNavigationButton(button.status);
	let link = current.parentElement;
	let badge = link.querySelector(".problem-nav__rung");
	if (badge)
		badge.outerHTML = button.badge;
	let label = link.querySelector(".tooltip-label");
	label.className = "tooltip-label status" + button.status;
	label.textContent = button.label;
	// the description is the server's HTML, with the day of the next review as a <time>
	let description = link.querySelector(".tooltip-desc");
	description.innerHTML = button.description;
	window.localizeTimeElements?.(description);

	// A solve shows only "Correct!" in the row of the messages; the XP it earned and what it did to the problem (its
	// next review, or its graduation) are in the popup of the rank (and the title of that XP) and in the tooltip of
	// the problem's button
	let reward = document.getElementById("xpDisplay");
	let rewardText = document.getElementById("xpDisplayText");
	if (button.status === 'S' && problemSolved)
	{
		let earned = xp ? (xp.review || 0) + (xp.graduation || 0) + (xp.clear || 0) : 0;
		if (rewardText)
		{
			rewardText.className = "xpDisplay solvedXpDisplay";
			rewardText.textContent = earned > 0 ? "+" + Number(earned) + " XP" : "";
		}
		if (reward)
		{
			let outcome = document.createElement("div");
			outcome.innerHTML = button.description;
			window.localizeTimeElements?.(outcome);
			reward.title = outcome.textContent.trim() + (xp && xp.clear > 0 ? " All clear today: +" + xp.clear + " XP." : "");
			//and as a line of the popup of the rank, which a phone opens with a tap
			let details = document.getElementById("playStakeDetails");
			if (details)
			{
				details.querySelector(".play-stake__outcome")?.remove();
				let line = document.createElement("div");
				line.className = "play-stake__outcome";
				line.textContent = reward.title;
				details.appendChild(line);
			}
		}

		// The last problem of the day is solved, so Next leads to the end of the training
		if (!nextButtonLink)
		{
			nextButtonLink = playPage.page.mistakeTrainingQueueLink;
			let next = document.getElementById("besogo-next-button-inactive");
			if (next)
			{
				next.id = "besogo-next-button";
				next.href = nextButtonLink;
			}
		}
	}
}

//The rank of the problem opens its rating and the reward of a solve in a popup under it; a click or a tap
//elsewhere, or Escape, closes it. A click on the board that closes it only closes it: it would otherwise
//play a stone the player didn't mean to play
(function() {
	let rank = document.getElementById("status2");
	let stake = document.getElementById("xpDisplayDiv");
	if (!rank || !stake)
		return;
	let open = shown => {
		stake.classList.toggle("play-stake--open", shown);
		rank.setAttribute("aria-expanded", shown ? "true" : "false");
	};
	rank.addEventListener("click", () => open(!stake.classList.contains("play-stake--open")));
	rank.addEventListener("keydown", event => {
		if (event.key === "Enter" || event.key === " ") {
			event.preventDefault();
			open(!stake.classList.contains("play-stake--open"));
		}
	});
	// in the capturing phase, so it comes before the board's own click on the point
	document.addEventListener("click", event => {
		if (!stake.classList.contains("play-stake--open") || stake.contains(event.target))
			return;
		open(false);
		if (event.target.closest(".besogo-board")) {
			event.preventDefault();
			event.stopPropagation();
		}
	}, true);
	document.addEventListener("keydown", event => {
		if (event.key === "Escape")
			open(false);
	});
})();

//A link with data-toggle shows and hides the element it names, its arrow points down while it is open
document.querySelectorAll("[data-toggle]").forEach(toggle => toggle.addEventListener("click", event => {
	event.preventDefault();
	let open = toggle.dataset.open !== "1";
	toggle.dataset.open = open ? "1" : "";
	Display[open ? "fadeIn" : "fadeOut"](document.getElementById(toggle.dataset.toggle), 250);
	toggle.querySelectorAll(".toggle-arrow").forEach(arrow => arrow.setAttribute("src", open ? "/img/greyArrow2.png" : "/img/greyArrow1.png"));
	//The comments name points by their coordinates, so the board shows them while the comments are open
	if (toggle.id === "commentsToggle" && typeof besogo !== "undefined" && besogo.editor)
		besogo.editor.setCoordStyle(open ? "western" : "none");
	//The comments button that tells of comments or open issues opens them too (CommentSection listens)
	let summary = document.getElementById("moreCommentSummary");
	if (open && toggle.id === "commentsToggle" && summary && getComputedStyle(summary).visibility === "visible")
		window.dispatchEvent(new Event("comments-open"));
}));

//The filters of the problems as tiles: a tile drops its filter, clear drops them all
(function() {
	const filters = [
		{tiles: activeTopicTiles, color: "green", cookie: "filtered_sets"},
		{tiles: activeDifficultyTiles, color: "purple", cookie: "filtered_ranks"},
		{tiles: activeTagTiles, color: "brown", cookie: "filtered_tags"}];
	const container = document.querySelector(".active-tiles-container");
	if (!container)
		return;
	container.replaceChildren();
	for (const filter of filters)
		filter.tiles.forEach((name, index) => {
			let tile = document.createElement("div");
			tile.className = "dropdown__tile dropdown__tile--context dropdown__tile--" + filter.color;
			tile.textContent = name;
			tile.addEventListener("click", () => {
				filter.tiles.splice(index, 1);
				setCookie(filter.cookie, filter.tiles.length == 0 ? "clear" : filter.tiles.join("@"));
				window.location.reload();
			});
			container.append(tile);
		});
	if (filters.some(filter => filter.tiles.length > 0))
	{
		let clear = document.createElement("a");
		clear.className = "dropdown__tile dropdown__tile--muted";
		clear.id = "unselect-active-tiles";
		clear.href = "";
		clear.textContent = "clear";
		clear.addEventListener("click", event => {
			event.preventDefault();
			filters.forEach(filter => setCookie(filter.cookie, "clear"));
			window.location.reload();
		});
		let clearfix = document.createElement("div");
		clearfix.className = "clearfix";
		container.append(clear, clearfix);
	}
})();

function elapsedSeconds()
{
	return (performance.now() - clockStart) / 1000;
}

function startClock()
{
	clockStart = performance.now();
	if (timeModeTimer)
		timeModeTimer.start();
}

// The server adds up the seconds of the tries until the problem is solved, so each result sends
// only the time since the previous one. A try after a reset on the page would count the earlier
// tries again otherwise (forum#149).
function takeSecondsOfTry()
{
	let seconds = Math.max(elapsedSeconds() - bonusSeconds, timeToFirstClick || 0, 0.01);
	clockStart = performance.now();
	bonusSeconds = 0;
	timeToFirstClick = null;
	return seconds;
}
if (mode == playPage.page.modes.rating)
	var ratingModeDifficultySelector = new RatingModeDifficultySelector();

//The explanation of a semeai or a multiple choice is closed by its box
document.getElementById("alertCheckbox")?.addEventListener("change", () => Display.fadeOut("#multipleChoiceAlerts", 500));

if (mode == playPage.page.modes.rating)
{
	Display.hide(".problem-nav");
	Display.hide(".selectable-text");
	Display.hide("#commentSpace");
}
else if (mode == playPage.page.modes.time)
	document.querySelectorAll("#account-bar-user > a").forEach(link => link.style.color = "#ca6658");

//The parts of the page after its script, as the page is still being read when it runs
function setUpPageEnd()
{
	if (lockedUntilTomorrow && mode != playPage.page.modes.time)
	{
		document.getElementById("status").innerHTML = '<b class="message--locked">This problem is locked until ' + heartResetTime + '</b>';
		tryAgainTomorrow = true;
		document.getElementById("status").style.color = "var(--feedback-error)";
		document.querySelectorAll(".besogo-board").forEach(board => board.classList.add("besogo-board-red-glow"));
		var resetBtn = document.getElementById("besogo-reset-button");
		if (resetBtn) { resetBtn.id = "besogo-reset-button-inactive"; resetBtn.blur(); }
	}
	if (playPage.problem.solved)
		reviewEnabled = true;

	document.querySelectorAll(".besogo-board").forEach(board => board.addEventListener("click", () => {
		if (locked && nextButtonLink)
			window.location = nextButtonLink;
	}));

	let solutionRequest = true;
	if (playPage.problem.solved || playPage.problem.isSandbox)
	{
		displaySettings();
		solutionRequest = false;
	}
	if(authorProblem)
		displaySettings();
	if (playPage.page.canRequestSolution && !playPage.problem.requestSolution)
	{
		if(solutionRequest)
			displaySolutionRequest();
		document.getElementById("showx99")?.addEventListener("click", () => {
			window.location.href = "/tsumegos/play/" + tsumegoID + "?requestSolution=" + playPage.page.userID;
		});
	}

	document.getElementById("customAlertCheckbox")?.addEventListener("change", () => Display.fadeOut("#customAlerts", 500));
	document.getElementById("potionAlertCheckbox")?.addEventListener("change", () => Display.fadeOut("#potionAlerts", 500));
	document.getElementById("findSimilarProblems")?.addEventListener("click", () => {
		document.querySelectorAll(".loader-container").forEach(loader => loader.style.display = "flex");
		window.location.href = "/tsumegos/duplicatesearch/" + playPage.problem.setConnectionID;
	});
}
if (document.readyState === "loading")
	document.addEventListener("DOMContentLoaded", setUpPageEnd);
else
	setTimeout(setUpPageEnd);

function displaySolutionRequest(){
	let request = document.getElementById("showx99");
	if (!authorProblem && request)
		request.style.display = "inline-block";
}

function displaySettings(){
	let request = document.getElementById("showx99");
	if (request)
		request.style.display = "none";
	let open = document.getElementById("openSgfLink");
	if (open)
		open.style.display = "inline-block";
}

function rejuvenation(){
	postAjax('/hero/rejuvenation').then(response => {
		if (!response.ok)
			return;
		currentHealth = maxHealth;
		redrawHearts();
		disableRejuvenation();
		enableIntuition();
		tryAgainTomorrow = false;
		locked = false;
		toggleBoardLock(false);
		document.getElementById("status").innerHTML = '';
		document.querySelector(".play-message")?.classList.remove("play-message--failed");
		document.querySelectorAll(".besogo-board").forEach(board => board.classList.remove("besogo-board-red-glow"));
		var resetBtn = document.getElementById("besogo-reset-button-inactive");
		if (resetBtn) { resetBtn.id = "besogo-reset-button"; resetBtn.onclick = function() { window.location.reload(); }; }
	});
}

document.addEventListener("keydown", function(event){
	var keycode = (event.keyCode ? event.keyCode : event.which);
	if(mode!=2){
		if(document.getElementById("editToggle")?.dataset.open !== "1" && !besogo.editor.getReviewMode()){
			// Don't navigate if focus is on a textarea or text input
			var activeElement = document.activeElement;
			var isTextInput = activeElement && (activeElement.tagName === 'TEXTAREA' || (activeElement.tagName === 'INPUT' && activeElement.type === 'text'));
			if (!isTextInput) {
				// an empty link is a training edge with nowhere to go
				if(keycode == '37' && previousButtonLink) {
					window.location.href = previousButtonLink;
				}else if(keycode == '39' && nextButtonLink){
					window.location.href =  nextButtonLink;
				}
			}
		}
	}
});

//The floating board that shows a line of moves when a coordinate is hovered
var coordPopupBoard = null;
var coordPopupDiv = null;
var coordPopupTimeout = null;

function createCoordPopup() {
	if (coordPopupDiv) return;
	coordPopupDiv = document.createElement("div");
	coordPopupDiv.id = "coord-popup-board";
	coordPopupDiv.style.cssText = "position:fixed;z-index:10000;display:none;background:#d4a76a;border:3px solid #8b5a2b;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,0.3);padding:8px;pointer-events:none;";
	document.body.appendChild(coordPopupDiv);
}

// Maps an internal board coordinate to its position on the board SVG, as boardDisplay.js svgPos() places it
function boardPixel(x) {
	var cell = besogo.BOARD_CELL_SIZE;
	return besogo.boardMargin(besogo.editor.getCoordStyle()) + cell / 2 + (x - 1) * cell;
}

// Returns the coordinate labels for the path from the root to `node`.
function pathLabels(node) {
	var size = besogo.scaleParameters.boardCoordSize || 19;
	var labels = besogo.coord.western(size, size);
	var out = [];
	while (node) {
		if (node.move) out.unshift(labels.x[node.move.x] + labels.y[node.move.y]);
		node = node.parent;
	}
	return out;
}

// Draws one numbered realistic stone onto a cloned board SVG, ringed in blue
// when it is the hovered move.
function drawNumberedStone(svg, x, y, isBlack, moveNumber, hovered) {
	var px = boardPixel(x);
	var py = boardPixel(y);
	svg.appendChild(besogo.realStone(px, py, isBlack ? -1 : 1, 0, besogo.editor.BLACK_STONES || "", besogo.editor.WHITE_STONES || ""));
	var text = besogo.svgEl("text", {
		x: px, y: py, dy: ".35ex", "text-anchor": "middle",
		"font-size": 22, "font-weight": "bold",
		fill: isBlack ? "white" : "black",
		"font-family": "Helvetica,Arial,sans-serif"
	});
	text.appendChild(document.createTextNode(String(moveNumber)));
	svg.appendChild(text);
	if (hovered)
		svg.appendChild(besogo.svgCircle(px, py, "blue", 44, 4));
}

// Draws the comment sequence moves as written, through to the hovered one.
// This shows the commenters line even when it is not a path in the game tree.
// An explicit b/w marker is honored, otherwise the colors alternate from the
// puzzle first-move colour.
function overlaySequenceOnBoard(svg, coords, colors, hoverIndex, firstMove, startMoveNumber) {
	var nextIsBlack = firstMove !== 1;
	for (var i = 0; i <= hoverIndex && i < coords.length; i++) {
		var xy = besogo.editor.coordLabelToXY(coords[i]);
		if (!xy) continue;
		var isBlack = colors && colors[i] ? colors[i] === "b" : nextIsBlack;
		nextIsBlack = !isBlack;
		drawNumberedStone(svg, xy.x, xy.y, isBlack, startMoveNumber + i + 1, i === hoverIndex);
	}
}

// Draws the commenter moves using their resolved tree nodes, with the real game
// move numbers.
function overlayNodesOnBoard(svg, nodes, hoverIndex) {
	for (var i = 0; i <= hoverIndex && i < nodes.length; i++) {
		var node = nodes[i];
		if (node && node.move)
			drawNumberedStone(svg, node.move.x, node.move.y, node.move.color === -1, node.moveNumber, i === hoverIndex);
	}
}

function showCoordPopup(coord, event, position, sequence) {
	createCoordPopup();
	var mainBoard = document.querySelector(".besogo-board svg");
	if (!mainBoard) return;

	// The preview board is the main board cloned at the previewed node: the
	// comment anchor, or for a sequence (such as R19-P19-Q19) the node right
	// before the hovered move. The current node is restored afterwards.
	var isSequence = sequence && sequence.coords && sequence.coords.length > 0;
	var restoreNode = null;
	var previewNode = null;
	// The sequence resolved along the game tree, null when it is not a tree line
	var hover = null;

	if (isSequence) {
		restoreNode = besogo.editor.getCurrent();
		var startNode = position ? besogo.editor.findNodeForPosition(position) : null;
		// Tree-first: reconstruct the line from the game tree, filling in the
		// opponents moves the commenter omitted. Fall back to drawing the moves as
		// written when the sequence does not match a tree line (e.g. a proposed
		// new line).
		var resolved = besogo.editor.resolveSequenceHover(sequence.coords, sequence.index, startNode, sequence.colors);
		if (resolved.found && resolved.beforeNode)
			hover = resolved;
		previewNode = hover ? hover.beforeNode : (startNode || besogo.editor.getRoot());
		besogo.editor.setCurrent(previewNode);
	} else {
		var anchoredNode = position ? besogo.editor.findNodeForPosition(position) : null;
		if (anchoredNode) {
			restoreNode = besogo.editor.getCurrent();
			previewNode = anchoredNode;
			besogo.editor.setCurrent(anchoredNode);
		}
		besogo.editor.displayHoverCoord(coord);
	}

	// Clear any pending hide
	if (coordPopupTimeout) {
		clearTimeout(coordPopupTimeout);
		coordPopupTimeout = null;
}

	// Show popup near cursor (clone the board at the previewed position)
	var clone = mainBoard.cloneNode(true);
	clone.style.width = "400px";
	clone.style.height = "400px";
	coordPopupDiv.innerHTML = "";
	// Show what is being previewed. In tree mode this is the reconstructed
	// game line (with the opponents moves filled in), otherwise the commenter
	// moves as written, through to the hovered move.
	var caption = coord;
	if (isSequence) {
		var line = hover ? pathLabels(previewNode) : sequence.coords.slice(0, sequence.index + 1);
		if (hover && hover.hoverNode.move) {
			var hoverLabel = besogo.editor.coordXYToLabel(hover.hoverNode.move.x, hover.hoverNode.move.y);
			if (hoverLabel) line.push(hoverLabel);
		}
		if (line.length > 0) caption = line.join(" > ");
	}
	var label = document.createElement("div");
	label.id = "coord-popup-label";
	label.style.cssText = "font:600 14px/1.4 Helvetica,Arial,sans-serif;color:#3a2410;text-align:center;margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:400px;";
	label.textContent = caption;
	coordPopupDiv.appendChild(label);
	// Draw the commenter moves as numbered stones so the turn numbers always
	// show: the resolved tree nodes in tree mode, otherwise the moves as written.
	if (isSequence) {
		if (hover && hover.nodes.length > 0)
			overlayNodesOnBoard(clone, hover.nodes, sequence.index);
		else
			overlaySequenceOnBoard(clone, sequence.coords, sequence.colors, sequence.index,
				besogo.editor.getRoot().firstMove || -1, previewNode.moveNumber || 0);
	} else {
		// Single coordinate: ring the point on the popup board so it matches
		// the sequence look.
		var singleXY = besogo.editor.coordLabelToXY(coord);
		if (singleXY) clone.appendChild(besogo.svgCircle(boardPixel(singleXY.x), boardPixel(singleXY.y), "blue", 44, 4));
	}
	coordPopupDiv.appendChild(clone);

	// Restore the original position after capturing the preview
	if (restoreNode) {
		besogo.editor.setCurrent(restoreNode);
		besogo.editor.displayHoverCoord(-1);
	}

	// Position near cursor
	var x = event.clientX + 15;
	var y = event.clientY - 200;
	if (x + 430 > window.innerWidth) x = event.clientX - 430;
	if (y < 10) y = 10;
	if (y + 430 > window.innerHeight) y = window.innerHeight - 430;

	coordPopupDiv.style.left = x + "px";
	coordPopupDiv.style.top = y + "px";
	coordPopupDiv.style.display = "block";
}

function hideCoordPopup() {
	if (coordPopupDiv) coordPopupDiv.style.display = "none";
	besogo.editor.displayHoverCoord(-1);
}

function addTimeForMovePlayed(multiplier = 1)
{
	bonusSeconds += playPage.page.secondsAddedPerMovePlayed * multiplier;
}

// The outcome values come from the App\Utility\PlayOutcome enum
var playOutcome = {
	solvedClean: playPage.page.playOutcome.solvedClean,
	solvedAfterMistake: playPage.page.playOutcome.solvedAfterMistake,
	failed: playPage.page.playOutcome.failed,
	timedOut: playPage.page.playOutcome.timedOut,
};

function isSolved(outcome)
{
	return outcome === playOutcome.solvedClean || outcome === playOutcome.solvedAfterMistake;
}

// xpStatus is not rendered in mistake training, the widget has nothing to animate there; in time
// mode it has none either, but the widget counts the run's problems right and wrong
function accountWidgetShowsResults()
{
	return typeof accountWidget !== 'undefined' && accountWidget && (typeof xpStatus !== 'undefined' || accountWidget.show == 'time');
}

function submitResult(outcome)
{
	showResultResponse(outcome, sendResult(outcome));
}

//The moves from the root to the one on the board, as points of the stored SGF, however the board is
//turned: two letters each, tt for a pass
function playedLineInSgf()
{
	let moves = [];
	for (let node = besogo.editor.getCurrent(); node; node = node.parent)
		if (node.move)
		{
			if (node.move.x === 0 && node.move.y === 0)
				moves.unshift("tt");
			else
			{
				let point = besogo.editor.viewPointToSgf({x: node.move.x, y: node.move.y});
				moves.unshift(String.fromCharCode(96 + point.x) + String.fromCharCode(96 + point.y));
			}
		}
	return moves.join("");
}

// Null for a guest, whose results aren't recorded
function sendResult(outcome)
{
	if (besogoNoLogin)
		return null;
	// a replay for an assignment changes nothing the account bar shows
	if (accountWidgetShowsResults() && !assignmentReplay)
		accountWidget.rememberResult(isSolved(outcome));
	let data = {
		tsumego_id: tsumegoID,
		// the part of the set the problem is played from decides the XP reduction of its resets
		set_connection_id: playPage.problem.setConnectionID,
		seconds: takeSecondsOfTry(),
		outcome: outcome,
		// another tab may have switched the stored mode since this page was opened
		mode: mode,
	};
	// the challenge page shows how a problem of the run was failed (App\Utility\ChallengeFailedLine)
	if (mode == playPage.page.modes.time && playPage.page.challengeRun && !isSolved(outcome))
	{
		data.challenge_failed_line = playedLineInSgf();
		data.sgf_id = playPage.problem.sgfID;
	}

	let response = fetch('/tsumegos/result', {
		method: 'POST',
		headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
		body: JSON.stringify(data),
		// the request outlives the page, so leaving right after a lost line still counts it
		keepalive: true,
	}).then(response => response.json());
	window._submitResultPromise = response.catch(err => console.error('submitResult failed:', err));
	return response;
}

// Shows the player what the result changed: the account bar, the XP, a potion and achievements
function showResultResponse(outcome, response)
{
	if (!response)
		return;
	if (accountWidgetShowsResults())
		accountWidget.animate(isSolved(outcome));
	if (!isSolved(outcome) && typeof xpStatus !== 'undefined' && xpStatus)
		xpStatus.addFail();
	window._submitResultPromise = response.then(result => {
		if (typeof accountWidget !== 'undefined' && accountWidget)
		{
			accountWidget.rating = result.new_rating;
			accountWidget.xp = result.new_xp;
			accountWidget.level = result.new_level;
			try { accountWidget.setup(); } catch(e) { console.error('accountWidget.setup failed:', e); }
		}
		if (typeof DailyStreak !== 'undefined')
			DailyStreak.update(result.daily_streak);
		if (result.xp_gained && typeof xpStatus !== 'undefined' && xpStatus)
		{
			xpStatus.set('solved', true);
			xpStatus.update();
		}
		if (result.potion_triggered)
		{
			currentHealth = maxHealth;
			redrawHearts();
			Display.fadeIn("#potionAlerts", 500);
		}
		if (result.achievement_updates && result.achievement_updates.length
			&& typeof showAchievementPopup === 'function')
				result.achievement_updates.forEach(showAchievementPopup);
		// The training row says what today did with the problem, which this result changed
		if (result.mistake_training_button)
			updateMistakeTrainingButton(result.mistake_training_button, result.mistake_training_xp);
	}).catch(err => {
		console.error('submitResult failed:', err);
	});
}

// Called by the board the moment the line can no longer end correct. The fail is sent
// right away, so leaving or refreshing before the line is played out doesn't escape it,
// but the player is shown nothing (hearts, account bar) until the line is over, as
// that would give the refutation away.
function lineLost()
{
	if (failAlreadyReported || problemSolved)
		return;
	failAlreadyReported = true;
	hadMisplay = true;
	unrevealedFail = { response: sendResult(playOutcome.failed) };
}

// Shows the fail sent when the line was lost, once the line is over
function revealFail()
{
	currentHealth--;
	showResultResponse(playOutcome.failed, unrevealedFail.response);
	unrevealedFail = null;
}

function displayResult(result)
{
	let success = result == 'S';
	document.getElementById("status").style.color = playGreenColor;
	if (timeModeTimer)
		timeModeTimer.stop();

	if (success)
	{
		if (!problemSolved)
		{
			problemSolved = true;
			window.dispatchEvent(new Event('tag-editor-solved'));
			if (typeof xpStatus !== "undefined" && xpStatus)
				xpStatus.set('solved', true);
			submitResult(hadMisplay ? playOutcome.solvedAfterMistake : playOutcome.solvedClean);
		}
		else if (assignmentReplay)
		{
			sendResult(hadMisplay ? playOutcome.solvedAfterMistake : playOutcome.solvedClean);
			assignmentReplay = false;
		}
		updateCurrentNavigationButton('S');
		document.getElementById("status").innerHTML = "<h2>Correct!</h2>";
		document.querySelector(".play-message")?.classList.add("play-message--solved");
		let glow = light ? "0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(0, 0, 0, 0.2)" : "0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(80, 255, 0, 0.2)";
		document.querySelectorAll(".besogo-board").forEach(board => board.style.boxShadow = glow);
		enableReview();
		besogo.editor.setControlButtonLock(false);
		Display.show("#commentSpace");
		let summary = document.getElementById("moreCommentSummary");
		if (summary)
			summary.style.visibility = "visible";
		let commentsToggle = document.getElementById("commentsToggle");
		if (commentsToggle)
		{
			commentsToggle.classList.remove("play-actions__button--disabled");
			commentsToggle.removeAttribute("aria-disabled");
			commentsToggle.title = "Comments";
		}

		if (mode == playPage.page.modes.time)
			setCountdownColor(playGreenColor);
		noXP = true;
		toggleBoardLock(true);
		displaySettings();
	}
	else //incorrect
	{
		if (unrevealedFail)
			revealFail();
		else if (!failAlreadyReported && !problemSolved)
		{
			failAlreadyReported = true;
			hadMisplay = true;
			currentHealth--;
			submitResult(playOutcome.failed);
		}
		else if (assignmentReplay)
			hadMisplay = true;
		// Don't lock board - let user keep trying
		if (mode != 2)
		{
			document.getElementById("status").style.color = "var(--feedback-error)";
			document.getElementById("status").innerHTML = "<h2>Incorrect!</h2>";
			markFailed();
			document.querySelectorAll(".besogo-board").forEach(board => board.classList.add("besogo-board-red-glow"));
			if (mode==3)
			{
				setCountdownColor("var(--feedback-error)");
				toggleBoardLock(true);
				enableReview();
			}
			if (mode == playPage.page.modes.mistakeTraining)
			{
				// The board locks, but the problem stays in today's training until it is solved
				toggleBoardLock(true);
				document.getElementById("status").innerHTML = "<h2>Incorrect!</h2>";
				markFailed();
			}
			if (!noXP)
			{
				if(!freePlayMode && mode == 1)
					redrawHearts();
				freePlayMode = true;
				if (mode == 1)
				{
					if (currentHealth < 0)
					{
						updateCurrentNavigationButton('F');
						document.getElementById("status").innerHTML = '<b class="message--locked">This problem is locked until ' + heartResetTime + '</b>';
						tryAgainTomorrow = true;
						toggleBoardLock(true);
						var resetBtn = document.getElementById("besogo-reset-button");
						if (resetBtn) { resetBtn.id = "besogo-reset-button-inactive"; resetBtn.blur(); }
					}
				}
				// leaving for the plain problem page would drop the player out of the training
				if (goldenTsumego && mode != playPage.page.modes.mistakeTraining)
				{
					// the page is drawn again from the stored result, so it waits for the request of the result
					let reload = () => window.location.href = '/' + playPage.problem.setConnectionID;
					(window._submitResultPromise || Promise.resolve()).finally(reload);
					return;
				}
			}
		}
		else
		{//mode 2 incorrect
			document.getElementById("status").style.color = "var(--feedback-error)";
			document.getElementById("status").innerHTML = "<h2>Incorrect!</h2>";
			markFailed();
			document.querySelectorAll(".besogo-board").forEach(board => board.classList.add("besogo-board-red-glow"));
			if (!noXP)
				freePlayMode = true;
		}
	}
}


//Only the banner of the message box: the potion and the achievement boxes keep their own
function displayMessage(message='text', topic='Message', color='red')
{
	let text = document.getElementById("customText");
	if (text)
		text.innerHTML = message;
	Display.fadeIn("#customAlerts", 500);
	document.querySelectorAll("#customAlerts .toast__banner").forEach(banner => {
		banner.classList.toggle("toast__banner--error", color === 'red');
		banner.classList.toggle("toast__banner--success", color !== 'red');
		banner.innerHTML = topic + '<span class="toast__close">x</span>';
	});
}

//After a fail the message row turns red, and Reset gets a red outline (play.css) and the keyboard focus, so
//Enter or Space tries again
function markFailed()
{
	document.querySelector(".play-message")?.classList.add("play-message--failed");
	document.getElementById("besogo-reset-button")?.focus({ preventScroll: true, focusVisible: true });
}

//Review shows the solution, so it opens once the problem is answered: solved, and in time mode failed or out of time too
function enableReview()
{
	besogo.editor.setReviewEnabled(true);
	let review = document.getElementById("besogo-review-button-inactive");
	if (review)
		review.id = "besogo-review-button";
}

//The clock of the time mode, in the colour of the result
function setCountdownColor(color)
{
	let countdown = document.getElementById("time-mode-countdown");
	if (countdown)
		countdown.style.color = color;
}

function resetParameters(isAtStart)
{
	tStatus = playPage.problem.status;
	if(tStatus=="S" || tStatus=="C")
		heartLoss = false;
	else
		heartLoss = true;
	if(isAtStart)
		heartLoss = false;
	if (noXP || freePlayMode || locked || authorProblem)
		heartLoss = false;
	if (failAlreadyReported)
		heartLoss = false;

	freePlayMode = false;
	if (heartLoss)
	{
		hadMisplay = true;
		currentHealth--;
		redrawHearts();
		submitResult(playOutcome.failed);
	}
	else if (unrevealedFail)
	{
		// the line is given up before it was played out
		revealFail();
		redrawHearts();
	}
	failAlreadyReported = false;
}

//The board is created by PlayBoard.js; it gets the page's callbacks, now that they are all defined
(function() {
if (tryAgainTomorrow) { var resetBtn = document.getElementById("besogo-reset-button"); if (resetBtn) { resetBtn.id = "besogo-reset-button-inactive"; resetBtn.blur(); } }
besogo.editor.setAutoPlay(true);
besogo.editor.registerAddTimeForMovePlayed(addTimeForMovePlayed);
besogo.editor.registerDisplayResult(displayResult);
besogo.editor.registerLineLost(lineLost);
besogo.editor.addListener(function (changes)
{
	// the clock starts with the first move the player really plays
	if (timeToFirstClick !== null || !changes || !changes.navChange)
		return;
	let current = besogo.editor.getCurrent();
	if (current.move && current.move.color === besogo.editor.getRoot().firstMove)
		timeToFirstClick = elapsedSeconds();
});

startClock();
var showComment = function(commentText)
	{
		let comment = document.getElementById("theComment");
		if (!comment)
			return;
		comment.style.display = commentText.length == 0 ? "none" : "block";
		comment.textContent = besogo.boardInverted ? besogo.swapColorWords(commentText) : commentText;
	};
besogo.editor.registerShowComment(showComment);
showComment(besogo.editor.getCurrent().comment || '');
})();
if (mode == 2)
{
	let overlay = document.getElementById("targetLockOverlay");
	if (overlay)
		overlay.style.top = '235px';
}
