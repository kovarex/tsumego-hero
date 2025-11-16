function setCookie(cookie, value){
	document.cookie = cookie + '=' + value + ';SameSite=Lax;path=/;';
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

class XPStatus
{
	constructor({ baseXP, solved, sprintRemainingSeconds, sprintMultiplier, goldenTsumego, goldenTsumegoMultiplier, resolving, resolvingMultiplier})
	{
		this.baseXP = baseXP;
		this.solved = solved;
		this.sprintRemainingSeconds = sprintRemainingSeconds;
		this.sprintMultiplier = sprintMultiplier;
		this.goldenTsumego = goldenTsumego;
		this.goldenTsumegoMultiplier = goldenTsumegoMultiplier;
		this.resolving = resolving;
		this.resolvingMultiplier = resolvingMultiplier;

		// cache the element
		this.xpDisplay = document.querySelector("#xpDisplay");
		if (this.isSprintActive())
			updateSprint(this.sprintRemainingSeconds, true);
	}

	update()
	{
		let multiplier = 1;
		if (this.goldenTsumego)
			multiplier *= this.goldenTsumegoMultiplier;
		else if (this.resolving)
			multiplier *= this.resolvingMultiplier;
		if (this.isSprintActive())
			multiplier *= this.sprintMultiplier;

		let xpPart = String(Math.ceil(this.baseXP * multiplier)) + ' XP';
		if (multiplier != 1)
			xpPart += ' (' + formatMultiplier(multiplier) + ')';

		if (this.goldenTsumego)
		{
			xpPart = 'Golden: ' + xpPart;
			this.xpDisplay.className = 'xpDisplay goldenTsumegoXpDisplay';
		}
		else if (this.solved)
		{
			xpPart = 'Solved: ' + xpPart;
			this.xpDisplay.className = 'xpDisplay solvedXpDisplay';
		}
		else if (this.isSprintActive())
		{
			xpPart = 'Sprint: ' + xpPart;
			this.xpDisplay.className = 'xpDisplay sprintXpDisplay';
		}
		else
			this.xpDisplay.className = 'xpDisplay';

		this.xpDisplay.innerHTML = xpPart;
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

function startSprint(seconds)
{
	if (!sprintEnabled)
		return;
	$.ajax(
		{
			url: '/hero/sprint',
			type: 'POST',
			data: {},
			dataType: 'json',
			success: function(response) {}
		});
	xpStatus.set('sprintRemainingSeconds', seconds);
	updateSprint(seconds);
	sprintEnabled = false;
}

function intuition()
{
	$.ajax(
		{
			url: '/hero/intuition',
			type: 'POST',
			success: function(response)
			{
				document.getElementById("intuition").src = "/img/hp2x.png";
				document.getElementById("intuition").style = "cursor: context-menu;";
				besogo.editor.intuitionHeroPower();
			}
		});
}
