//The board of the play page, created as soon as Tsumegos/play.php has its <div id="target">, so the
//first paint of the page already has it, with the hearts and the hero powers above it; without them,
//the page showed for a moment with its buttons under the title, which then jumped down. The state of
//the page the board reads is set up here too. The rest of the page's code, which needs the whole page,
//is PlayPage.js at its end. Both are classic scripts, so their top-level names are shared.
//The state is set from playPage by initPlayState() and the board made by createPlayBoard(), when the page
//opens and again when it shows its next problem in place (PlayNavigation.js).
var hadMisplay, tryAgainTomorrow, currentHealth, maxHealth, fullHeart, emptyHeart, heartResetTime, countDownDate;
var revelationUseCount, clockStart, bonusSeconds, timeToFirstClick, freePlayMode, reviewEnabled;
// The effective mode the server plays by, so the board cannot disagree with it.
var mode;
var authorProblem, tsumegoID, playGreenColor, tStatus, failAlreadyReported;
// A problem solved before, played again for a class assignment: the solve is sent for the
// assignment, though it changes nothing else
var assignmentReplay;
// The fail sent when the line was lost, which the player isn't shown until the line is over
var unrevealedFail;
var previousButtonLink, nextButtonLink;
// A training try ends at the first mistake, Reset opens the problem again for the next one
var resetButtonLink;
var noSkipNextButtonLink, timeModeTimer, author, userSets, besogoNoLogin, besogoUserId, sprintSeconds, problemSolved;
var playerRatingCalculationModifier;
//The question of the problem, set by setUpQuestion(); webroot/js/multipleChoice*.js read them
let multipleChoiceLibertiesB, multipleChoiceLibertiesW, multipleChoiceLibertyCount, multipleChoiceSemeaiType, multipleChoiceInsideLiberties;
//What the problem adds to the computed explanation of its race, plain text, or null
let multipleChoiceSemeaiExplanation;
let customMultipleChoiceAnswer;
//The explanation a multiple choice question shows after the answer
let mText;
let hasChosen;
var boardLockValue;
let passEnabled, besogoRotation;
var activeTopicTiles, activeDifficultyTiles, activeTagTiles;
var xpStatus = null;
var goldenTsumego, lockedUntilTomorrow, locked, noXP;

function initPlayState()
{
	hadMisplay = false;
	tryAgainTomorrow = false;
	currentHealth = playPage.page.currentHealth;
	maxHealth = playPage.page.maxHealth;
	fullHeart = playPage.page.fullHeart;
	emptyHeart = playPage.page.emptyHeart;
	heartResetTime = new Date(new Date().setUTCHours(24,0,0,0)).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
	countDownDate = new Date();
	revelationUseCount = playPage.page.revelationUseCount;
	clockStart = 0;
	bonusSeconds = 0;
	timeToFirstClick = null;
	freePlayMode = false;
	reviewEnabled = false;
	mode = playPage.page.mode;
	authorProblem = playPage.problem.authorProblem;
	tsumegoID = playPage.problem.tsumegoID;
	playGreenColor = playPage.page.light == 'dark' ? '#0cbb0c' : 'green';
	tStatus = playPage.problem.status;
	failAlreadyReported = false;
	assignmentReplay = playPage.problem.assignmentReplay;
	unrevealedFail = null;
	window._submitResultPromise = null;

	previousButtonLink = playPage.problem.previousLink;
	nextButtonLink = playPage.problem.nextLink;
	resetButtonLink = playPage.problem.resetLink;
	noSkipNextButtonLink = playPage.problem.noSkipNextLink;
	timeModeTimer = (mode == playPage.page.modes.time ? new TimeModeTimer(playPage.page.timeModeSecondsToSolve) : null);
	author = playPage.problem.author;
	userSets = playPage.problem.userSets;
	besogoNoLogin = playPage.page.userID === null;
	besogoUserId = playPage.page.userID !== null ? playPage.page.userID : undefined;
	sprintSeconds = playPage.page.sprintSeconds;
	problemSolved = playPage.problem.solved;
	playerRatingCalculationModifier = playPage.page.playerRatingCalculationModifier;
	multipleChoiceLibertiesB = 0;
	multipleChoiceLibertiesW = 0;
	multipleChoiceLibertyCount = 0;
	multipleChoiceSemeaiType = 0;
	multipleChoiceInsideLiberties = 0;
	multipleChoiceSemeaiExplanation = null;
	customMultipleChoiceAnswer = 0;
	mText = "";
	hasChosen = false;
	boardLockValue = 0;
	passEnabled = (playPage.problem.pass ? 1 : 0) + "";
	besogoRotation = -1;
	activeTopicTiles = playPage.page.activeTopicTiles;
	activeDifficultyTiles = playPage.page.activeDifficultyTiles;
	activeTagTiles = playPage.page.activeTagTiles;

	xpStatus = null;
	if (playPage.problem.xpStatus)
	{
		xpStatus = new XPStatus(playPage.problem.xpStatus);
		xpStatus.update();
	}
	if (playPage.page.heroPowers)
	{
		playPage.page.heroPowers.intuition ? enableIntuition() : disableIntuition();
		playPage.page.heroPowers.rejuvenation ? enableRejuvenation() : disableRejuvenation();
		playPage.page.heroPowers.sprint ? enableSprint() : disableSprint();
		playPage.page.heroPowers.refinement ? enableRefinement() : disableRefinement();
	}

	goldenTsumego = playPage.problem.goldenTsumego;
	//A problem failed today is locked until the hearts come back, but in the mistake training
	lockedUntilTomorrow = mode != playPage.page.modes.mistakeTraining && (tStatus == 'F' || tStatus == 'X');
	locked = lockedUntilTomorrow || playPage.page.accountBlocked;
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
	noXP = tStatus == 'S' || tStatus == 'C' || playPage.page.userID === null || mode == playPage.page.modes.time;
	redrawHearts();
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

//The board, with the texture the layout links in the head (Tsumegos/play.php)
function createPlayBoard()
{
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
}

initPlayState();
createPlayBoard();
