function setCookie(cookie, value){
	document.cookie = cookie + '=' + value + ';SameSite=Lax;path=/;';
}

function formatMultiplier(value) {
	if (value >= 1)
		return `&times; ${value}`;

	const rounded = Math.round(1 / value);
	const tolerance = 0.02; // 2% tolerance

	if (rounded > 0 && Math.abs(1 / rounded - value) <= tolerance)
		return `1/${rounded}`;

	return `&times; ${value}`;
}

class XPStatus
{
	constructor({ baseXP, solved, sprintRemainingSeconds, goldenTsumego, goldenTsumegoMultiplier, resolving, resolvingMultiplier})
	{
		this.baseXP = baseXP;
		this.solved = solved;
		this.sprintRemainingSeconds = sprintRemainingSeconds;
		this.goldenTsumego = goldenTsumego;
		this.goldenTsumegoMultiplier = goldenTsumegoMultiplier;
		this.resolving = resolving;
		this.resolvingMultiplier = resolvingMultiplier;

		// cache the element
		this.xpDisplay = document.querySelector("#xpDisplay");
	}

	update()
	{
		let multiplier = 1;
		if (this.goldenTsumego)
			multiplier *= this.goldenTsumegoMultiplier;
		else if (this.resolving)
			multiplier *= this.resolvingMultiplier;

		let xpPart = String(Math.ceil(this.baseXP * multiplier)) + ' XP';
		if (multiplier != 1)
			xpPart += ' (' + formatMultiplier(multiplier) + ')';

		if (this.goldenTsumego)
		{
			xpPart = 'Golden (' + xpPart + ')';
			this.xpDisplay.style.color = '#0cbb0c';
		}
		else if (this.solved)
		{
			xpPart = 'Solved (' + xpPart + ')';
			this.xpDisplay.style.color = 'green';
		}
		else if (this.sprintRemainingSeconds > 0)
		{
			xpPart = 'Sprint (' + xpPart + ')';
			this.xpDisplay.style.color = 'blue';
		}

		this.xpDisplay.textContent = xpPart;
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
