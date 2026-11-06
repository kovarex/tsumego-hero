//The answer to the question of a semeai problem (the play page asks who wins the capturing race):
//the four choices are coloured right and wrong and the explanation of the race is shown.
//num: the choice of the player, as the SEMEAI_ANSWER below
function displayMultipleChoiceResult(num)
{
	if (hasChosen)
		return;
	const {text, correct} = semeaiExplanation();
	for (let answer = 1; answer <= 4; answer++)
		colorMultipleChoice(answer, answer == correct ? "#3ecf78" : "#e0747f");
	if (num == correct)
	{
		displayResult("S");
		showMultipleChoiceBanner("toast__banner--success", 'Correct!<span class="toast__close">x</span>');
	}
	else
	{
		displayResult("F");
		showMultipleChoiceBanner("toast__banner--error", 'Incorrect!<span class="toast__close">x</span>');
	}
	showMultipleChoiceText(text);
	hasChosen = true;
}

//The button of a choice (1 to 4) gets the colour of a right or wrong answer
function colorMultipleChoice(answer, color)
{
	let button = document.getElementById("besogo-multipleChoice" + answer);
	if (button)
		button.style.backgroundColor = color;
}

//The banner of the alert of the answer says whether it was right
function showMultipleChoiceBanner(className, html)
{
	document.querySelectorAll("#multipleChoiceAlerts .toast__banner").forEach(banner =>
	{
		banner.classList.add(className);
		banner.innerHTML = html;
	});
}

//The alert of the answer fades in with its explanation
function showMultipleChoiceText(html)
{
	let text = document.getElementById("multipleChoiceText");
	if (text)
		text.innerHTML = html;
	Display.fadeIn("#multipleChoiceAlerts", 500);
}

const SEMEAI_ANSWER = {blackDead: 1, whiteDead: 2, unsettled: 3, seki: 4};

const SEMEAI_SEKI_RULES = "Seki possible if there is one or more inside liberties.<br>The favorite's task is to kill.<br>The favorite counts his exclusive liberties.<br>The underdog's task is Seki.<br>The underdog counts his exclusive plus all inside liberties.<br>";

//The explanation of the problem's race and the right answer, followed by what the problem adds to
//it (tsumego_semeai.explanation, plain text)
function semeaiExplanation()
{
	const result = semeaiComputedExplanation();
	if (multipleChoiceSemeaiExplanation)
	{
		const added = document.createElement("div");
		added.textContent = multipleChoiceSemeaiExplanation;
		result.text += (result.text.endsWith("<br>") ? "" : "<br>") + added.innerHTML + "<br>";
	}
	return result;
}

//The race from the liberties the play page left each side (PlayPage.js): its liberty count less
//those it took from the other color
function semeaiComputedExplanation()
{
	const count = parseInt(multipleChoiceLibertyCount);
	const inside = parseInt(multipleChoiceInsideLiberties);
	const black = count - parseInt(multipleChoiceLibertiesW);
	const white = count - parseInt(multipleChoiceLibertiesB);
	switch (Number(multipleChoiceSemeaiType))
	{
		case 1: return semeaiNoEyesFewInsideLiberties(black, white);
		case 2: return semeaiNoEyesInsideLiberties(black, white, inside);
		case 3: return semeaiEyeAgainstNoEye(count);
		case 4: return semeaiSameSizedBigEyes(black, white, inside);
		case 5: return semeaiBigEyeAgainstSmallerEye(black, white);
		case 6: return semeaiSmallEyes(black, white, inside);
		default: return {text: "", correct: 0};
	}
}

function semeaiNoEyesFewInsideLiberties(black, white)
{
	const result = semeaiPlainRace(black, white, "The position is unsettled. (Whoever plays first wins.)");
	result.text = "Semeai Type 1: No eyes, less than 2 inside liberties.<br>No Seki possible.<br> Black has " + semeaiLiberties(black)
		+ ".<br> White has " + semeaiLiberties(white) + ".<br>" + result.text;
	return result;
}

//The favorite (more outside liberties) counts one inside liberty, the underdog all of them
function semeaiNoEyesInsideLiberties(black, white, inside)
{
	let text = "Semeai Type 2: No eyes, 2 or more inside liberties.<br>The favorite's task is to kill.<br>The favorite counts his outside liberties plus 1 inside liberty.<br>The underdog's task is Seki.<br>The underdog counts his outside liberties plus all inside liberties.<br>";
	if (black == white)
		return {text: text + "Seki - same amount of liberties.<br>", correct: SEMEAI_ANSWER.seki};
	const favorite = black > white ? {name: "Black", outside: black} : {name: "White", outside: white};
	const underdog = black > white ? {name: "White", outside: white} : {name: "Black", outside: black};
	const favoriteLiberties = favorite.outside + 1;
	const underdogLiberties = underdog.outside + inside;
	text += favorite.name + " is the favorite with more outside liberties.<br>"
		+ underdog.name + " has " + semeaiLiberties(underdogLiberties) + ".(underdog: " + underdog.outside + " outside + " + inside + " inside)<br>"
		+ favorite.name + " has " + semeaiLiberties(favoriteLiberties) + ".(favorite: " + favorite.outside + " outside + 1 inside)<br>";
	const lead = favoriteLiberties - underdogLiberties;
	if (lead === 0)
		return {text: text + "Unsettled. (whoever plays first accomplishes the task)<br>", correct: SEMEAI_ANSWER.unsettled};
	if (lead < 0)
		return {text: text + "Seki.<br>", correct: SEMEAI_ANSWER.seki};
	return {text: text + underdog.name + " is dead. (" + lead + " move(s) behind)<br>", correct: semeaiDeadAnswer(underdog.name)};
}

//All inside liberties count for the group with the eye. A few problems show the liberties as the
//tesujis that gain some leave them.
function semeaiEyeAgainstNoEye(count)
{
	let takenFromBlack = multipleChoiceLibertiesB;
	let takenFromWhite = multipleChoiceLibertiesW;
	let shownBlack = count - takenFromWhite;
	let shownWhite = count - takenFromBlack;
	if ([29826, 29827].some(id => id == tsumegoID) && takenFromBlack == 1 && takenFromWhite == 2)
	{
		takenFromWhite = 1;
		shownBlack = shownWhite = {29827: 15}[tsumegoID] ?? 5;
	}
	const result = semeaiPlainRace(count - takenFromWhite, count - takenFromBlack, "The position is unsettled. (Whoever plays first wins.)");
	result.text = "Semeai Type 3: Eye vs no eye.<br>No Seki possible.<br> All inside liberties count for the group with the eye.<br>Black has "
		+ semeaiLiberties(shownBlack) + ".<br> White has " + semeaiLiberties(shownWhite) + ".<br>" + result.text;
	return result;
}

function semeaiSameSizedBigEyes(black, white, inside)
{
	let favoriteLine = "";
	let race;
	if (black == white && inside != 0)
	{
		favoriteLine = "Seki - same amount of liberties.<br>";
		race = {text: "", correct: SEMEAI_ANSWER.seki};
	}
	else if (black == white)
		race = {text: "Black has " + black + " liberties. <br> White has " + white + " liberties. <br>Unsettled. (whoever plays first wins)", correct: SEMEAI_ANSWER.unsettled};
	else
	{
		race = semeaiExclusiveLibertiesRace(black, white, inside);
		if (inside == 0)
			race.text = "Black has " + race.black + " liberties. <br> White has " + race.white + " liberties. <br>" + race.result;
		else
			favoriteLine = race.favoriteLine;
	}
	const rules = inside == 0 ? "No Seki possible because there are no inside liberties.<br>" : SEMEAI_SEKI_RULES;
	return {text: "Semeai Type 4: Same sized big eyes.<br>" + rules + " " + favoriteLine + " " + race.text, correct: race.correct};
}

function semeaiBigEyeAgainstSmallerEye(black, white)
{
	const result = semeaiPlainRace(black, white, "The position is unsettled. (whoever plays first wins)");
	result.text = "Semeai Type 5: Big eye vs smaller eye.<br>No Seki possible.<br> All inside liberties count for the group with the bigger eye.<br> Black has "
		+ black + " liberties.<br> White has " + white + " liberties.<br>" + result.text;
	return result;
}

function semeaiSmallEyes(black, white, inside)
{
	let favoriteLine = "";
	let race;
	if (black == white && inside != 0)
	{
		favoriteLine = "Seki - same amount of liberties.<br>";
		race = {text: "", correct: SEMEAI_ANSWER.seki};
	}
	else if (black == white)
		race = {text: "Black has " + black + " liberties. ()<br> White has " + white + " liberties. ()<br>Unsettled. (whoever plays first accomplishes the task)", correct: SEMEAI_ANSWER.unsettled};
	else
	{
		race = semeaiExclusiveLibertiesRace(black, white, inside);
		favoriteLine = race.favoriteLine;
	}
	return {text: "Semeai Type 6: Small eye vs small eye.<br>" + SEMEAI_SEKI_RULES + " " + favoriteLine + race.text, correct: race.correct};
}

//The side with fewer liberties dies, the same number is unsettled
function semeaiPlainRace(black, white, unsettled)
{
	if (black < white)
		return {text: semeaiDead("Black", white - black), correct: SEMEAI_ANSWER.blackDead};
	if (black > white)
		return {text: semeaiDead("White", black - white), correct: SEMEAI_ANSWER.whiteDead};
	return {text: unsettled, correct: SEMEAI_ANSWER.unsettled};
}

//Eyes of a kind (types 4 and 6): the favorite (more exclusive liberties) counts those and has to
//kill, the underdog adds all inside liberties and lives in seki when that is more
function semeaiExclusiveLibertiesRace(black, white, inside)
{
	const favorite = black > white ? "Black" : "White";
	const underdog = black > white ? "White" : "Black";
	const favoriteLiberties = Math.max(black, white);
	const underdogExclusive = Math.min(black, white);
	const underdogLiberties = underdogExclusive + inside;
	const liberties = {[favorite]: favoriteLiberties, [underdog]: underdogLiberties};
	const notes = {[favorite]: "favorite: " + favoriteLiberties + " exclusive liberties", [underdog]: "underdog: " + underdogExclusive + " exclusive + " + inside + " inside"};
	let result;
	let correct;
	if (underdogLiberties > favoriteLiberties)
	{
		result = "Seki. (" + favorite + " is " + semeaiMoves(underdogLiberties - favoriteLiberties) + " behind for killing)";
		correct = SEMEAI_ANSWER.seki;
	}
	else if (underdogLiberties < favoriteLiberties)
	{
		result = semeaiDead(underdog, favoriteLiberties - underdogLiberties);
		correct = semeaiDeadAnswer(underdog);
	}
	else
	{
		result = "Unsettled. (whoever plays first accomplishes the task)";
		correct = SEMEAI_ANSWER.unsettled;
	}
	return {
		favoriteLine: favorite + " is the favorite with more exclusive liberties.<br>",
		black: liberties.Black,
		white: liberties.White,
		result,
		text: "Black has " + liberties.Black + " liberties. (" + notes.Black + ")<br> White has " + liberties.White + " liberties. (" + notes.White + ")<br>" + result,
		correct};
}

function semeaiDead(color, movesBehind)
{
	return color + " is dead. (" + semeaiMoves(movesBehind) + " behind)";
}

function semeaiDeadAnswer(color)
{
	return color == "Black" ? SEMEAI_ANSWER.blackDead : SEMEAI_ANSWER.whiteDead;
}

function semeaiMoves(count)
{
	return count + " move" + (count == 1 ? "" : "s");
}

function semeaiLiberties(count)
{
	return count + " " + (count == 1 ? "liberty" : "liberties");
}
