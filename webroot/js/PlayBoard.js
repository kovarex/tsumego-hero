//The board of the play page, created as soon as Tsumegos/play.php has its <div id="target">, so the
//first paint of the page already has it, with the hearts and the hero powers above it; without them,
//the page showed for a moment with its buttons under the title, which then jumped down. The state of
//the page the board reads is set up here too. The rest of the page's code, which needs the whole page,
//is PlayPage.js at its end. Both are classic scripts, so their top-level names are shared.
var hadMisplay = false;
var tryAgainTomorrow = false;
var currentHealth = playPage.page.currentHealth;
var maxHealth = playPage.page.maxHealth;
var fullHeart = playPage.page.fullHeart;
var emptyHeart = playPage.page.emptyHeart;
var heartResetTime = new Date(new Date().setUTCHours(24,0,0,0)).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
var countDownDate = new Date();
var revelationUseCount = playPage.page.revelationUseCount;
var clockStart = 0;
var bonusSeconds = 0;
var timeToFirstClick = null;
var freePlayMode = false;
var reviewEnabled = false;
// The effective mode the server plays by, so the board cannot disagree with it.
var mode = playPage.page.mode;
var authorProblem = playPage.problem.authorProblem;
var tsumegoID = playPage.problem.tsumegoID;
var playGreenColor = playPage.page.light == 'dark' ? '#0cbb0c' : 'green';
var tStatus = playPage.problem.status;
var failAlreadyReported = false;
// A problem solved before, played again for a class assignment: the solve is sent for the
// assignment, though it changes nothing else
var assignmentReplay = playPage.problem.assignmentReplay;
// The fail sent when the line was lost, which the player isn't shown until the line is over
var unrevealedFail = null;
window._submitResultPromise = null;

var previousButtonLink = playPage.problem.previousLink;
var nextButtonLink = playPage.problem.nextLink;
// A training try ends at the first mistake, Reset opens the problem again for the next one
var resetButtonLink = playPage.problem.resetLink;
var noSkipNextButtonLink = playPage.problem.noSkipNextLink;
var timeModeTimer = (mode == playPage.page.modes.time ? new TimeModeTimer(playPage.page.timeModeSecondsToSolve) : null);
var author = playPage.problem.author;
var userSets = playPage.problem.userSets;
var besogoNoLogin = playPage.page.userID === null;
if (playPage.page.userID !== null)
	var besogoUserId = playPage.page.userID;
var sprintSeconds = playPage.page.sprintSeconds;
var problemSolved = playPage.problem.solved;
var playerRatingCalculationModifier = playPage.page.playerRatingCalculationModifier;
//The question of the problem, set by setUpQuestion(); webroot/js/multipleChoice*.js read them
let multipleChoiceLibertiesB = 0;
let multipleChoiceLibertiesW = 0;
let multipleChoiceLibertyCount = 0;
let multipleChoiceSemeaiType = 0;
let multipleChoiceInsideLiberties = 0;
//What the problem adds to the computed explanation of its race, plain text, or null
let multipleChoiceSemeaiExplanation = null;
let customMultipleChoiceAnswer = 0;
//The explanation a multiple choice question shows after the answer
let mText = "";
let hasChosen = false;
var boardLockValue = 0;
let passEnabled = (playPage.problem.pass ? 1 : 0) + "";
let besogoRotation = -1;
const activeTopicTiles = playPage.page.activeTopicTiles;
const activeDifficultyTiles = playPage.page.activeDifficultyTiles;
const activeTagTiles = playPage.page.activeTagTiles;

if (playPage.problem.xpStatus)
{
	var xpStatus = new XPStatus(playPage.problem.xpStatus);
	xpStatus.update();
}
if (playPage.page.heroPowers)
{
	playPage.page.heroPowers.intuition ? enableIntuition() : disableIntuition();
	playPage.page.heroPowers.rejuvenation ? enableRejuvenation() : disableRejuvenation();
	playPage.page.heroPowers.sprint ? enableSprint() : disableSprint();
	playPage.page.heroPowers.refinement ? enableRefinement() : disableRefinement();
}

//The question a problem asks besides its moves, the liberties of a semeai or a multiple choice:
//it sets the board options and the state the answer is checked against
function setUpQuestion(problem, options)
{
	hasChosen = false;
	mText = "";
	options.multipleChoice = false;
	options.multipleChoiceSetup = [];
	options.multipleChoiceCustom = false;
	if (problem.semeai)
	{
		multipleChoiceLibertyCount = problem.semeai.libertyCount ?? 0;
		multipleChoiceSemeaiType = problem.semeai.semeaiTypeID;
		multipleChoiceInsideLiberties = problem.semeai.insideLiberties ?? 0;
		multipleChoiceSemeaiExplanation = problem.semeai.explanation ?? null;
	}
	let semeaiQuestion = problem.semeai?.question;
	if (semeaiQuestion)
	{
		//The liberties of each side, shown as marks in a random order
		options.multipleChoice = true;
		multipleChoiceLibertiesB = semeaiQuestion.libertiesB;
		multipleChoiceLibertiesW = semeaiQuestion.libertiesW;
		let sStatusB = semeaiQuestion.libertiesB;
		let sStatusW = semeaiQuestion.libertiesW;
		let a1 = []; let a2 = [];
		for (let i = 0; i < semeaiQuestion.variance; i++)
		{
			a1.push(sStatusB > 0 ? 1 : 0);
			a2.push(sStatusW > 0 ? 1 : 0);
			sStatusB--;
			sStatusW--;
		}
		let shuffle = values => values.map(value => ({ value, sort: Math.random() })).sort((a, b) => a.sort - b.sort).map(({ value }) => value);
		options.multipleChoiceSetup = [shuffle(a1), shuffle(a2)];
	}
	else if (problem.variant)
	{
		options.multipleChoiceCustom = problem.variant.type;
		customMultipleChoiceAnswer = problem.variant.correctAnswer;
		options.multipleChoiceCustomSetup = problem.variant.answers;
		mText = problem.variant.explanation;
	}
}

function toggleBoardLock(t)
{
	if (tryAgainTomorrow)
		t = true;
	boardLockValue = t ? 1 : 0;
}

var goldenTsumego = playPage.problem.goldenTsumego;
//A problem failed today is locked until the hearts come back, but in the mistake training
var lockedUntilTomorrow = mode != playPage.page.modes.mistakeTraining && (tStatus == 'F' || tStatus == 'X');
var locked = lockedUntilTomorrow || playPage.page.accountBlocked;
if (locked)
{
	tryAgainTomorrow = true;
	toggleBoardLock(true);
}
if (playPage.page.accountBlocked)
{
	document.getElementById("status").innerHTML = "<h2>Your account is temporarily locked.</h2>";
	document.getElementById("status").style.color = "var(--play-message-incorrect)";
}

var noXP = tStatus == 'S' || tStatus == 'C' || playPage.page.userID === null || mode == playPage.page.modes.time;

function redrawHearts()
{
	if (!document.getElementById("heart0"))
		return;
	for (var i = 0; i < maxHealth; i++)
	{
		var full = i < currentHealth;
		var el = document.getElementById("heart" + i);
		//an empty heart is the full one greyed (play.css), so it is as solid as the full ones it overlaps
		el.src = "/img/" + fullHeart + ".png";
		el.classList.toggle("play-player__heart--empty", !full);
	}
	var counter = document.getElementById("health-counter");
	if (counter)
		counter.textContent = currentHealth + " / " + maxHealth;
	document.getElementById("health").title = "Hearts " + currentHealth + "/" + maxHealth;
}
redrawHearts();

//The board, with the texture the layout links in the head (Tsumegos/play.php)
(function() {
	var options = { },
		searchString = location.search.substring(1),
		params = searchString.split("&"),
		div = document.getElementById('target'),
		i, value;

	for (i = 0; i < params.length; i++){
		value = params[i].split("=");
		options[value.shift()] = value.join("=");
	}

	options.panels = playPage.page.boardPanels;
	options.tsumegoPlayTool = 'auto';
	options.realstones = true;
	options.nowheel = true;
	options.nokeys = true;
	setUpQuestion(playPage.problem, options);
	options.corner = playPage.problem.corner;
	options.playerColor = playPage.problem.playerColor;
	options.swapColors = playPage.problem.swapColors;
	options.rootPath = '/besogo/';
	options.theme = playPage.problem.board.texture;
	options.themeParameters = [playPage.problem.board.black, playPage.problem.board.white];
	//No coordinates while solving, the board gets their room; they show while the comments are open (PlayPage.js)
	options.coord = 'none';
	options.sgf = '/placeholder.sgf';
	options.sgf2 = playPage.problem.sgf;
	options.light = playPage.page.light;
	if (options.height && options.width && options.resize === 'fixed')
	{
		div.style.height = options.height + 'px';
		div.style.width = options.width + 'px';
	}
	options.reviewMode = false;
	//a problem of a time mode run (or a challenge) is reviewed only once it is answered, whoever made it (PlayPage.js enableReview())
	options.reviewEnabled = mode != playPage.page.modes.time && (playPage.problem.solved || playPage.problem.requestSolution || authorProblem);
	besogo.create(div, options);
})();

//The buttons besogo makes under the board go where the page shows them (Tsumegos/play.php): Reset, Review
//and Next (History and Skip in the rating and the time mode) go to the right of the row of the messages
//above the board, as one group in this order in every state (Next turns green once the problem is solved,
//play-message--solved); the review's tree goes under the actions in the panel; the board's own settings
//(corner, rotation, colours, favourite, author) go to the info of the problem. They keep their ids,
//which the page's code renames as the state changes.
(function arrangePlayControls()
{
	let row = document.querySelector(".besogo-tsumegoPlayTool");
	let actions = document.querySelector(".play-actions");
	if (!row || !actions)
		return;
	let end = document.createElement("span");
	end.className = "play-actions__end";
	(document.querySelector(".play-message") ?? actions).appendChild(end);
	for (let id of ["besogo-pass-button", "history-button", "besogo-reset-button", "besogo-reset-button-inactive",
		"besogo-next-button", "besogo-next-button-inactive"])
	{
		let button = row.querySelector("#" + id);
		if (button)
		{
			button.classList.add("btn");
			end.appendChild(button);
		}
	}
	//Review goes to the panel, right after More
	let more = document.getElementById("editToggle");
	for (let review of row.querySelectorAll("#besogo-review-button, #besogo-review-button-inactive"))
	{
		review.classList.add("btn", "btn--danger");
		more ? more.after(review) : actions.appendChild(review);
	}
	//Next of a problem (not the Skip of the time mode) says what it does, it needs no tooltip
	if (mode == playPage.page.modes.level || mode == playPage.page.modes.mistakeTraining)
		end.querySelectorAll("#besogo-next-button, #besogo-next-button-inactive").forEach(next => next.removeAttribute("title"));
	if (problemSolved)
		markSolvedInTheRow();
	//The review's tree goes to the panel too, where it shows beside the board with the comments under it
	let reviewPanels = document.querySelector("#target .besogo-panels");
	if (reviewPanels)
	{
		actions.after(reviewPanels);
		fitReviewTree(reviewPanels);
	}
	//The answers of a multiple choice problem are the problem itself: they stay in sight, in a row right under the
	//board, instead of going with the board's settings behind More
	let answers = row.querySelectorAll('[id^="besogo-multipleChoice"]');
	let board = document.getElementById("target");
	if (answers.length && board)
	{
		let answerRow = document.createElement("div");
		answerRow.className = "play-answers";
		answers.forEach(answer => answerRow.appendChild(answer));
		board.after(answerRow);
	}
	let settings = document.getElementById("boardSettings");
	if (settings)
		while (row.firstChild)
			settings.appendChild(row.firstChild);
})();

//The problem is solved: the row of the messages says so, and Next becomes the main action (PlayPage.js too)
function markSolvedInTheRow()
{
	document.querySelector(".play-message")?.classList.add("play-message--solved");
	document.querySelector("#besogo-next-button, #besogo-next-button-inactive")?.classList.add("btn--primary");
}

//On a PC the review's tree gets room. The panel widens to show the whole tree, taking first the room the board
//leaves unused in its column (a board as tall as the window is narrower than its column), then the empty margins
//of the page; the board never shrinks, and a tree wider than all that scrolls sideways from its root. While the
//comments are closed the tree also takes the panel's height down to the bottom of the board column, so the page gets no
//taller; with the comments open it keeps its usual height (play.css) and shares the panel with them.
function fitReviewTree(reviewPanels)
{
	let tree = reviewPanels.querySelector(".besogo-tree");
	let comments = document.getElementById("commentsPanel");
	let screen = document.querySelector(".play-screen");
	if (!tree || !screen)
		return;
	let reset = () =>
	{
		screen.classList.remove("play-screen--review");
		screen.style.removeProperty("--review-panel");
		document.documentElement.style.removeProperty("--review-grow");
		tree.style.removeProperty("--tree-height");
	};
	let fit = () =>
	{
		let scroll = [tree.scrollLeft, tree.scrollTop];
		reset();
		let board = document.querySelector(".play-screen__main .besogo-board");
		let main = document.querySelector(".play-screen__main");
		let box = document.querySelector(".whitebox2");
		let svg = tree.querySelector("svg");
		if (getComputedStyle(reviewPanels).display === "none" || !board || !main || !box || !svg || !window.matchMedia("(width >= 64rem)").matches)
			return;
		//the width of the panel without the review, which grows with a wide window (--play-panel in play.css)
		const PANEL = screen.querySelector(".play-screen__panel").getBoundingClientRect().width;
		screen.classList.add("play-screen--review");
		let commentsOpen = comments && getComputedStyle(comments).display !== "none";
		//down to the bottom of what the board column shows: the board and the problem buttons under it
		let bottom = Math.max(...[...main.children].map(child => child.getBoundingClientRect().bottom));
		let height = Math.floor(bottom - tree.getBoundingClientRect().top);
		let svgBox = svg.getBoundingClientRect();
		//the box's border and padding around the tree, as its max-height includes them (play.css)
		let treeStyle = getComputedStyle(tree);
		let around = ["paddingTop", "paddingBottom", "borderTopWidth", "borderBottomWidth"].reduce((sum, side) => sum + parseFloat(treeStyle[side]), 0);
		//the panel's padding, and the width of a scrollbar when the tree is taller than its box
		let room = 34 + (svgBox.height + around > (commentsOpen ? 350 : Math.max(350, height)) ? 16 : 0);
		let extra = Math.ceil(svgBox.width) + room - PANEL;
		if (extra > 0)
		{
			let mainStyle = getComputedStyle(main);
			let unused = main.clientWidth - parseFloat(mainStyle.paddingLeft) - parseFloat(mainStyle.paddingRight) - board.getBoundingClientRect().width;
			let fromMain = Math.max(0, Math.min(extra, Math.floor(unused)));
			let margins = document.documentElement.clientWidth - box.getBoundingClientRect().width - 32;
			let grow = Math.max(0, Math.min(extra - fromMain, Math.floor(margins)));
			screen.style.setProperty("--review-panel", (PANEL + fromMain + grow) + "px");
			document.documentElement.style.setProperty("--review-grow", grow + "px");
		}
		if (!commentsOpen)
			tree.style.setProperty("--tree-height", height + "px");
		[tree.scrollLeft, tree.scrollTop] = scroll;
	};
	let observer = new MutationObserver(fit);
	observer.observe(reviewPanels, { attributes: true, attributeFilter: ["style"] });
	if (comments)
		observer.observe(comments, { attributes: true, attributeFilter: ["style"] });
	//besogo draws the tree again when it changes (the review shows the whole of it), as a new svg
	observer.observe(tree, { childList: true });
	window.addEventListener("resize", fit);
	fit();
}
