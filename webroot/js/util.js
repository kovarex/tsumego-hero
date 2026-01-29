function setCookie(name, value, days = 365)
{
	const date = new Date();
	date.setTime(date.getTime() + (days * 24*60*60*1000));
	const expires = "; expires=" + date.toUTCString();

	document.cookie =
		name + "=" + encodeURIComponent(value) +
		expires +
		"; path=/" +
		"; SameSite=Lax" +
		"; Secure";
}

function getCookie(name)
{
    return document.cookie
        .split('; ')
        .find(row => row.startsWith(name + '='))
        ?.split('=')[1] || null;
}

function isStatusAllowingInspection(status)
{
	return status == 'S' || status == 'C';
}

function formatMultiplier(value)
{
	if (value >= 1)
		return `&times;${value}`;

	const rounded = Math.round(1 / value);
	const tolerance = 0.02; // 2% tolerance

	if (rounded > 0 && Math.abs(1 / rounded - value) <= tolerance)
		return `1/${rounded}`;

	return `&times; ${value}`;
}

function showRatingShort(rating)
{
	const abs = Math.abs(rating);

	if (abs >= 1)
		return String(Math.round(rating));

	let exponent = Math.floor(Math.log10(abs));

	// exponent is negative, e.g. 0.0011 → exponent = -3
	const decimals = -exponent;
	return rating.toFixed(decimals);
}

function showRatingLong(rating)
{
	return String(Math.round((rating * 10)) / 10);
}

function updateSprintStatus(seconds)
{
	document.getElementById("status2").innerHTML = Math.floor(seconds / 60) + ":" + String(seconds % 60).padStart(2,'0');
	document.getElementById("status2").style.color = 'blue';
}

function updateSprint(seconds)
{
	countDownDate = new Date();
	countDownDate.setSeconds(countDownDate.getSeconds() + seconds);
	document.getElementById("sprint").src = "/img/hp1x.png";
	document.getElementById("sprint").style = "cursor: context-menu;";

	updateSprintStatus(seconds);

	var x = setInterval(function()
	{
		if (!xpStatus.isSprintActive())
			return;
		var seconds = Math.floor((countDownDate - new Date()) / 1000);
		xpStatus.set('sprintRemainingSeconds', seconds);
		updateSprintStatus(seconds);
		if (!xpStatus.isSprintActive())
		{
			clearInterval(x);
			return;
		}
	}, 250);
}

function enableIntuition()
{
	let intuitionElement = document.getElementById('intuition');
	intuitionElement.src = '/img/hp2.png';
	intuitionElement.onmouseover = function() { this.src = '/img/hp2h.png'; };
	intuitionElement.onmouseout = function() { this.src = '/img/hp2.png'; };
	intuitionElement.onclick = function() { intuition(); };
	intuitionElement.style.cursor = 'pointer';
}

function disableIntuition()
{
	let intuitionElement = document.getElementById('intuition');
	intuitionElement.src = '/img/hp2x.png';
	intuitionElement.onmouseover = null;
	intuitionElement.onmouseout = null;
	intuitionElement.onclick = null;
	intuitionElement.style.cursor = 'auto';
}

function intuition()
{
	$.ajax(
		{
			url: '/hero/intuition',
			type: 'POST',
			success: function(response)
			{
				disableIntuition();
				besogo.editor.intuitionHeroPower();
			}
		});
}

function disableRevelation()
{
	let revelationElement = document.getElementById("revelation");
	revelationElement.src = "/img/hp6x.png";
	revelationElement.onmouseover = null;
	revelationElement.onmouseout = null;
	revelationElement.onclick = null;
	revelationElement.style.cursor = 'auto';
	revelationElement.title = 'Revelation (' + revelationUseCount + '): Solves a problem, but you don\'t get any reward.';
}

function revelation()
{
	if (isStatusAllowingInspection(tStatus))
		return;

	makeAjaxCall('/hero/revelation/' + tsumegoID,
		(response) =>
		{
			document.getElementById("status").style.color = playGreenColor;
			document.getElementById("status").innerHTML = "<h2>Correct!</h2>";
			if (light)
				$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(0, 0, 0, 0.2)");
			else
				$(".besogo-board").css("box-shadow","0 2px 14px 0 rgba(67, 255, 40, 0.7), 0 6px 20px 0 rgba(80, 255, 0, 0.2)");
			besogo.editor.setReviewEnabled(true);
			besogo.editor.setControlButtonLock(false);
			toggleBoardLock(true);
			$("#besogo-review-button-inactive").attr("id","besogo-review-button");
			$("#commentSpace").show();
			updateCurrentNavigationButton('S');
			displaySettings();
			setCookie("revelation", "1");
			revelationUseCount--;
			disableRevelation();
		});
}

function enableRejuvenation()
{
	let rejuvenationElement = document.getElementById('rejuvenation');
	rejuvenationElement.src = '/img/hp3.png';
	rejuvenationElement.onmouseover = function() { this.src = '/img/hp3h.png'; };
	rejuvenationElement.onmouseout = function() { this.src = '/img/hp3.png'; };
	rejuvenationElement.onclick = () => rejuvenation();
	rejuvenationElement.style.cursor = 'pointer';
}

function disableRejuvenation()
{
	let rejuvenationElement = document.getElementById('rejuvenation');
	rejuvenationElement.src = '/img/hp3x.png';
	rejuvenationElement.onmouseover = null;
	rejuvenationElement.onmouseout = null;
	rejuvenationElement.onclick = null;
	rejuvenationElement.style.cursor = 'auto';
}

function enableSprint()
{
	let sprintElement = document.getElementById('sprint');
	sprintElement.src = '/img/hp1.png';
	sprintElement.onmouseover = function() { this.src = '/img/hp1h.png'; };
	sprintElement.onmouseout = function() { this.src = '/img/hp1.png'; };
	sprintElement.onclick = () => startSprint();
	sprintElement.style.cursor = 'pointer';
}

function disableSprint()
{
	let sprintElement = document.getElementById('sprint');
	sprintElement.src = '/img/hp1x.png';
	sprintElement.onmouseover = null;
	sprintElement.onmouseout = null;
	sprintElement.onclick = null;
	sprintElement.style.cursor = 'auto';
}

function startSprint()
{
	$.ajax(
		{
			url: '/hero/sprint',
			type: 'POST',
			success: function(response)
			{
				xpStatus.set('sprintRemainingSeconds', sprintSeconds);
				updateSprint(sprintSeconds);
				disableSprint();
			}
		});
}

function enableRefinement()
{
	let refinementElement = document.getElementById('refinement');
	refinementElement.src = '/img/hp4.png';
	refinementElement.onmouseover = function() { this.src = '/img/hp4h.png'; };
	refinementElement.onmouseout = function() { this.src = '/img/hp4.png'; };
	refinementElement.onclick = function() { window.location.href = '/hero/refinement'; };
	refinementElement.style.cursor = 'pointer';
}

function disableRefinement()
{
	let refinementElement = document.getElementById('refinement');
	refinementElement.src = '/img/hp4x.png';
	refinementElement.onmouseover = null;
	refinementElement.onmouseout = null;
	refinementElement.onclick = null;
	refinementElement.style.cursor = 'auto';
}

function beta(rating)
{
	return -7 * Math.log(3300 - rating)
}

// result 1 is win, and 0 is loss
function calculateRatingChange(rating, opponentRating, result, modifier)
{
	let Se = 1.0 / (1.0 + Math.exp(beta(opponentRating) - beta(rating)));
	let con = Math.pow(((3300 - rating) / 200), 1.6);
	let bonus = Math.log(1 + Math.exp((2300 - rating) / 80)) / 5;
	return modifier * (con * (result - Se) + bonus);
}

// changes should be reflrected in Rating.php
function ratingToXP(rating, multiplier)
{
	return Math.ceil(ratingToXPFloat(rating) * multiplier);
}

function ratingToXPFloat(rating)
{
	if (rating < 0)
		return 1 + Math.max(0, (rating / 1000 + 0.9) * 3);

	// until 1200 rating, the old formula but with half of the values
	if (rating < 1200)
		return Math.max(10, Math.pow(rating / 100, 1.55) - 6) / 2;

	// with higher ratings, it is important to have more aggressive exponential growth,
	return (Math.pow((rating - 500)/ 100, 2) - 10) / 2;
}

function makeIdValidName(name)
{
	let str = name.split("");
	for (let i = 0; i < str.length; i++)
		if (!str[i].match(/[a-z]/i) && !str[i].match(/[0-9]/i))
			str[i] = "-";
	return "tag-"+str.join("");
}

function makeAjaxCall(urlToCall, method)
{
	$.ajax({
		url: urlToCall,
		type: 'POST',
		complete: (xhr) =>
		{
			if (xhr.status >= 200 && xhr.status < 300)
				method(xhr.responseText);
			else
				alert(xhr.responseText || "Unknown ajax call error!");
		}
	});
}

// this needs to be up to date with Level.php
levelXPsections = [
	[11, 10],
	[19, 25],
	[39, 50],
	[69, 100],
	[99, 150],
	[100, 50000],
	[101, 1150],
	[10000, 0]];

class XPForNextCalculator
{
	constructor(level)
	{
		this.from = 1;
		this.result = 50;

		for (const section of levelXPsections)
			if (this.section(level, section[0], section[1]))
				break;
	}

	section(level, to, jump)
	{
		const steps = Math.min(to, level) - this.from;
		this.result += steps * jump;
		this.from = to;
		return level <= to;
	}
}

function getXPForNextLevel(level)
{
	return new XPForNextCalculator(level).result;
}
