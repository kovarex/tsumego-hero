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

//The decoded value, '' when the cookie is not set
function getCookie(name)
{
	const row = document.cookie.split('; ').find(row => row.startsWith(name + '='));
	return row ? decodeURIComponent(row.substring(name.length + 1)) : '';
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
	if (abs == 0)
		return '0';

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
	document.getElementById("status2").style.color = 'gray';
	document.getElementById("status2").style.fontWeight = 800;
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

//A hero power button of the play page: its picture hp<number>.png, hp<number>h.png under the
//pointer and hp<number>x.png while the power can't be used
function enableHeroPower(id, number, use)
{
	let element = document.getElementById(id);
	element.src = '/img/hp' + number + '.png';
	element.onmouseover = function() { this.src = '/img/hp' + number + 'h.png'; };
	element.onmouseout = function() { this.src = '/img/hp' + number + '.png'; };
	element.onclick = use;
	element.style.cursor = 'pointer';
}

function disableHeroPower(id, number)
{
	let element = document.getElementById(id);
	element.src = '/img/hp' + number + 'x.png';
	element.onmouseover = null;
	element.onmouseout = null;
	element.onclick = null;
	element.style.cursor = 'auto';
}

function enableIntuition()
{
	enableHeroPower('intuition', 2, () => intuition());
}

function disableIntuition()
{
	disableHeroPower('intuition', 2);
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
	disableHeroPower('revelation', 6);
	document.getElementById('revelation').title = 'Revelation (' + revelationUseCount + '): Solves a problem, but you don\'t get any reward.';
}

function revelation()
{
	if (isStatusAllowingInspection(tStatus))
		return;

	makeAjaxCall('/hero/revelation/' + tsumegoID,
		(response) =>
		{
			tryAgainTomorrow = false;
			locked = false;
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
			revelationUseCount--;
			disableRevelation();
		});
}

function enableRejuvenation()
{
	enableHeroPower('rejuvenation', 3, () => rejuvenation());
}

function disableRejuvenation()
{
	disableHeroPower('rejuvenation', 3);
}

function enableSprint()
{
	enableHeroPower('sprint', 1, () => startSprint());
}

function disableSprint()
{
	disableHeroPower('sprint', 1);
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
	enableHeroPower('refinement', 4, () => { window.location.href = '/hero/refinement'; });
}

function disableRefinement()
{
	disableHeroPower('refinement', 4);
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
