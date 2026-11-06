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
	document.getElementById("status").innerHTML = "<h3><b>Your account is temporarily locked.</b></h3>";
	document.getElementById("status").style.color = "red";
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
		el.src = "/img/" + (full ? fullHeart : emptyHeart) + ".png";
		el.title = full ? "Heart" : "Empty Heart";
	}
	var counter = document.getElementById("health-counter");
	if (counter)
		counter.textContent = currentHealth + " / " + maxHealth;
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
	options.coord = 'western';
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
