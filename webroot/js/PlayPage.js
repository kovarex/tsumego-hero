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
	link.querySelector(".tooltip-desc").textContent = button.description;

	// A solve says under "Correct!" what it did to the problem: its next review, or its graduation
	let status = document.getElementById("status");
	status.querySelectorAll(".mistake-training-outcome").forEach(old => old.remove());
	let outcome;
	if (button.status === 'S' && problemSolved)
	{
		outcome = document.createElement("p");
		outcome.className = "mistake-training-outcome";
		let earned = xp ? xp.review + xp.graduation : 0;
		outcome.textContent = (earned > 0 ? "+" + earned + " XP \u00b7 " : "") + button.description;
		status.appendChild(outcome);
		if (xp && xp.clear > 0)
		{
			let clear = document.createElement("p");
			clear.className = "mistake-training-outcome mistake-training-outcome--clear";
			clear.textContent = "All clear today: +" + xp.clear + " XP";
			outcome.after(clear);
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
				next.title = "next problem";
			}
		}
	}
}

//A link with data-toggle shows and hides the element it names, its arrow points down while it is open
$("[data-toggle]").click(function(event){
	event.preventDefault();
	let open = this.dataset.open !== "1";
	this.dataset.open = open ? "1" : "";
	$("#" + this.dataset.toggle)[open ? "fadeIn" : "fadeOut"](250);
	$(this).find(".toggle-arrow").attr("src", open ? "/img/greyArrow2.png" : "/img/greyArrow1.png");
	//More that tells of comments or open issues opens them too (CommentSection listens)
	if (open && this.id === "editToggle" && $("#moreCommentSummary").is(":visible"))
		window.dispatchEvent(new Event("comments-open"));
});

//The filters of the problems as tiles: a tile drops its filter, clear drops them all
(function() {
	const filters = [
		{tiles: activeTopicTiles, color: "green", cookie: "filtered_sets"},
		{tiles: activeDifficultyTiles, color: "purple", cookie: "filtered_ranks"},
		{tiles: activeTagTiles, color: "brown", cookie: "filtered_tags"}];
	const container = $(".active-tiles-container").empty();
	for (const filter of filters)
		filter.tiles.forEach((name, index) => container.append($('<div class="dropdown__tile dropdown__tile--context">')
			.addClass("dropdown__tile--" + filter.color)
			.text(name)
			.click(() => {
				filter.tiles.splice(index, 1);
				setCookie(filter.cookie, filter.tiles.length == 0 ? "clear" : filter.tiles.join("@"));
				window.location.reload();
			})));
	if (filters.some(filter => filter.tiles.length > 0))
		container.append($('<a class="dropdown__tile dropdown__tile--muted" id="unselect-active-tiles" href="">clear</a>').click(event => {
			event.preventDefault();
			filters.forEach(filter => setCookie(filter.cookie, "clear"));
			window.location.reload();
		}), '<div class="clearfix"></div>');
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
$("#alertCheckbox").change(function(){
	$("#multipleChoiceAlerts").fadeOut(500);
});

if (mode == playPage.page.modes.rating)
{
	$(".problem-nav").hide();
	$(".selectable-text").hide();
	$("#commentSpace").hide();
}
else if (mode == playPage.page.modes.time)
	$("#account-bar-user > a").css({color:"#ca6658"});


$(document).ready(function(){
	if (lockedUntilTomorrow && mode != playPage.page.modes.time)
	{
		document.getElementById("status").innerHTML = '<b class="message--locked">This problem is locked until ' + heartResetTime + '</b>';
		tryAgainTomorrow = true;
		document.getElementById("status").style.color = "var(--feedback-error)";
		$(".besogo-board").addClass("besogo-board-red-glow");
		var resetBtn = document.getElementById("besogo-reset-button");
		if (resetBtn) { resetBtn.id = "besogo-reset-button-inactive"; }
	}
	if (playPage.problem.solved)
		reviewEnabled = true;

	$('.besogo-board').click(function(e){
		if(locked && nextButtonLink)
			window.location = nextButtonLink;
});

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
		$("#showx99").click(function(){
			window.location.href = "/tsumegos/play/" + tsumegoID + "?requestSolution=" + playPage.page.userID;
			});
	}

	$("#customAlertCheckbox").change(function(){
		$("#customAlerts").fadeOut(500);
	});
	$("#potionAlertCheckbox").change(function(){
		$("#potionAlerts").fadeOut(500);
	});
	$("#findSimilarProblems").click(function(){
		$('.loader-container').css({"display": "flex"});
		window.location.href = "/tsumegos/duplicatesearch/" + playPage.problem.setConnectionID;
	});
});

function displaySolutionRequest(){
	if(!authorProblem)
		$("#showx99").css("display", "inline-block");
}

function displaySettings(){
	$("#showx99").css("display", "none");
	$("#openSgfLink").css("display", "inline-block");
	$(".play-edit-toggle--after-solve").css("display", "inline");
}

function rejuvenation(){
	$.ajax({
			url: '/hero/rejuvenation',
			type: 'POST',
		success: function(response) {
				currentHealth = maxHealth;
				redrawHearts();
				disableRejuvenation();
				enableIntuition();
				tryAgainTomorrow = false;
				locked = false;
				toggleBoardLock(false);
				document.getElementById("status").innerHTML = '';
				$(".besogo-board").removeClass("besogo-board-red-glow");
				var resetBtn = document.getElementById("besogo-reset-button-inactive");
				if (resetBtn) { resetBtn.id = "besogo-reset-button"; resetBtn.onclick = function() { window.location.reload(); }; }
		}
	});
}

$(document).keydown(function(event){
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

// Maps an internal board coordinate to its pixel position on the board SVG.
// Matches boardDisplay.js svgPos (CELL_SIZE=88, COORD_MARGIN=75).
function boardPixel(x) {
	var coordStyle = besogo.editor.getCoordStyle();
	var cell = 88;
	var margin = coordStyle === "none" ? 0 : 75;
	return margin + cell / 2 + (x - 1) * cell;
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
			$("#potionAlerts").fadeIn(500);
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
		if(light)
			$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(0, 0, 0, 0.2)");
		else
			$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(80, 255, 0, 0.2)");
		besogo.editor.setReviewEnabled(true);
		besogo.editor.setControlButtonLock(false);
		$("#besogo-review-button-inactive").attr("id","besogo-review-button");
		$("#commentSpace").show();
		$("#moreCommentSummary").show();

		if (mode == playPage.page.modes.time)
			$("#time-mode-countdown").css("color", playGreenColor);
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
			$(".besogo-board").addClass("besogo-board-red-glow");
			if (mode==3)
			{
				$("#time-mode-countdown").css("color","var(--feedback-error)");
				toggleBoardLock(true);
			}
			if (mode == playPage.page.modes.mistakeTraining)
			{
				// The board locks, but the problem stays in today's training until it is solved
				toggleBoardLock(true);
				document.getElementById("status").innerHTML = '<h2>Incorrect!</h2><p class="mistake-training-note">Press Reset to try again.</p>';
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
						if (resetBtn) { resetBtn.id = "besogo-reset-button-inactive"; }
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
			$(".besogo-board").addClass("besogo-board-red-glow");
			if (!noXP)
				freePlayMode = true;
		}
	}
}


//Only the banner of the message box: the potion and the achievement boxes keep their own
function displayMessage(message='text', topic='Message', color='red')
{
	$('#customText').html(message);
	$("#customAlerts").fadeIn(500);
	$("#customAlerts .toast__banner")
		.toggleClass("toast__banner--error", color === 'red')
		.toggleClass("toast__banner--success", color !== 'red')
		.html(topic + '<span class="toast__close">x</span>');
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
if (tryAgainTomorrow) { var resetBtn = document.getElementById("besogo-reset-button"); if (resetBtn) { resetBtn.id = "besogo-reset-button-inactive"; } }
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
		$("#theComment").css("display", commentText.length == 0 ? "none" : "block");
		$("#theComment").text(besogo.boardInverted ? besogo.swapColorWords(commentText) : commentText);
	};
besogo.editor.registerShowComment(showComment);
showComment(besogo.editor.getCurrent().comment || '');
})();
if (mode == 2)
	$("#targetLockOverlay").css('top', '235px');
