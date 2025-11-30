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
		"; Secure" +
		"; Partitioned";
}

function getCookie(name)
{
    return document.cookie
        .split('; ')
        .find(row => row.startsWith(name + '='))
        ?.split('=')[1] || null;
}

function formatMultiplier(value) {
	if (value >= 1)
		return `&times;${value}`;

	const rounded = Math.round(1 / value);
	const tolerance = 0.02; // 2% tolerance

	if (rounded > 0 && Math.abs(1 / rounded - value) <= tolerance)
		return `1/${rounded}`;

	return `&times; ${value}`;
}

function showRatingShort(rating) {
	return String(Math.round(rating));
}

function showRatingLong(rating) {
	return String(Math.round((rating * 10)) / 10);
}

class XPStatus
{
	constructor({ solved, sprintRemainingSeconds, sprintMultiplier, goldenTsumego, goldenTsumegoMultiplier, resolving, resolvingMultiplier, userRating, tsumegoRating, progressDeletionCount})
	{
		this.solved = solved;
		this.sprintRemainingSeconds = sprintRemainingSeconds;
		this.sprintMultiplier = sprintMultiplier;
		this.goldenTsumego = goldenTsumego;
		this.goldenTsumegoMultiplier = goldenTsumegoMultiplier;
		this.resolving = resolving;
		this.resolvingMultiplier = resolvingMultiplier;
		this.tsumegoRating = tsumegoRating;
		this.userRating = userRating;
	    this.progressDeletionCount = progressDeletionCount;

		// cache the element
		this.xpDisplayText = document.querySelector("#xpDisplayText");
		this.ratingHeader = document.querySelector("#ratingHeader");
		this.ratingGainShort = document.querySelector("#ratingGainShort");
		this.ratingGainLong = document.querySelector("#ratingGainLong");
		this.ratingSeparator = document.querySelector('#ratingSeparator');
		this.ratingLossShort = document.querySelector("#ratingLossShort");
		this.ratingLossLong = document.querySelector("#ratingLossLong");
		if (this.isSprintActive())
			updateSprint(this.sprintRemainingSeconds, true);
	}

	getProgressDeletionMultiplier() {
		if (this.progressDeletionCount == 0)
			return 1;
		if (this.progressDeletionCount == 1)
			return 0.5;
		if (this.progressDeletionCount == 2)
			return 0.2;
		if (this.progressDeletionCount == 3)
			return 0.1;
		return 0.01;
	}

	getMultiplier()
	{
		let multiplier = this.getProgressDeletionMultiplier();
		if (this.goldenTsumego)
			multiplier *= this.goldenTsumegoMultiplier;
		else if (this.resolving)
			multiplier *= this.resolvingMultiplier;
		if (this.isSprintActive())
			multiplier *= this.sprintMultiplier;
		return multiplier;
	}

	updateXPPart()
	{
		let multiplier  = this.getMultiplier();
		let xpPart = String(Math.ceil(this.getXPInternal(multiplier))) + ' XP';
		if (multiplier != 1)
			xpPart += ' (' + formatMultiplier(multiplier) + ')';

		if (this.goldenTsumego)
		{
			xpPart = 'Golden: ' + xpPart;
			this.xpDisplayText.className = 'xpDisplay goldenTsumegoXpDisplay';
		}
		else if (this.solved)
		{
			xpPart = 'Solved: ' + xpPart;
			this.xpDisplayText.className = 'xpDisplay solvedXpDisplay';
		}
		else if (this.isSprintActive())
		{
			xpPart = 'Sprint: ' + xpPart;
			this.xpDisplayText.className = 'xpDisplay sprintXpDisplay';
		}
		else
			this.xpDisplayText.className = 'xpDisplay';
		this.xpDisplayText.innerHTML = xpPart;
	}

	updateRatingPart()
	{
		let ratingGain = calculateRatingChange(this.userRating, this.tsumegoRating, 1, playerRatingCalculationModifier);
		this.ratingGainShort.textContent = '+' + showRatingShort(ratingGain);
		this.ratingGainLong.textContent = '+' + showRatingLong(ratingGain);
		this.ratingHeader.textContent = 'Rating: ';

		if (this.solved)
		{
			this.ratingGainShort.className = 'xpDisplay solvedXpDisplay';
			this.ratingHeader.className = 'xpDisplay solvedXpDisplay';
			this.ratingSeparator.textContent = '';
			this.ratingLossShort.textContent = '';
			this.ratingLossLong.textContent = '';
		}
		else
		{
			let ratingLoss = calculateRatingChange(this.userRating, this.tsumegoRating, 0, playerRatingCalculationModifier);
			this.ratingSeparator.textContent = '/';
			this.ratingLossShort.textContent = showRatingShort(ratingLoss);
			this.ratingLossLong.textContent = showRatingLong(ratingLoss);
		}
	}

	update()
	{
		this.updateXPPart();
		this.updateRatingPart();
	}

	isSprintActive()
	{
		return this.sprintRemainingSeconds > 0;
	}

	set(field, value)
	{
		if (!(field in this)) {
			console.warn(`XPStatus: unknown field "${field}"`);
			return;
		}

		this[field] = value;
		this.update();
	}

	getXP()
	{
		return this.getXPInternal(this.getMultiplier());
	}

	getXPInternal(multiplier)
	{
		return ratingToXP(this.tsumegoRating) * multiplier;
	}
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

function ratingToXP(rating)
{
	// until 1200 rating, the old formula but with half of the values
	if (rating < 1200) {
		return Math.max(10, Math.pow(rating / 100, 1.55) - 6) / 2;
	}

	// with higher ratings, it is important to have more aggressive exponential growth,
	return (Math.pow((rating - 500)/ 100, 2) - 10) / 2;
}

class TimeModeTimer
{
	constructor()
	{
		this.updateTimeModeCaption(); // first initial update on page load
		this.timeModeTimer = setInterval(() => this.timeModeUpdate(), 100);
	}

	updateTimeModeCaption()
	{
		$("#time-mode-countdown").html(`${Math.floor(tcount/60)}:${(tcount%60).toFixed(1).padStart(4,"0")}`);
	}

	timeModeUpdate()
	{
		tcount = Math.max(0, tcount - 0.1);
		this.updateTimeModeCaption();

		if (tcount == 0)
		{
			timeUp = true;
			locked = true;
			tryAgainTomorrow = true;
			setCookie("misplays", 1);
			setCookie("timeout", 1);

			$("#time-mode-countdown").css("color","#e03c4b");
			document.getElementById("status").style.color = "#e03c4b";
			document.getElementById("status").innerHTML = "<h2>Time up</h2>";
			this.stop();
			toggleBoardLock(true);
		}
	}

	stop()
	{
		clearInterval(this.timeModeTimer);
		nextButtonLink = noSkipNextButtonLink;
		document.getElementById("besogo-next-button").value = "Next";
		document.getElementById("besogo-next-button").title = "next problem";
	}
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
				alert("Ajax call returned: " + (xhr.responseText || "Unknown error"));
		}
	});
	/*
	$.ajax(
		{
			url: urlToCall,
			type: 'POST',
			success: (response) => method(response)
		});*/
}
