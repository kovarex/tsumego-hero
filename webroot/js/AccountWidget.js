class TimeModeState
{
	rank;
	failCount;
	successCount;
	overallCount;

	constructor({rank, failCount, successCount, overallCount})
	{
		this.rank = rank;
		this.failCount = failCount;
		this.successCount = successCount;
		this.overallCount = overallCount;
	}
}

class AccountWidget
{
	rating;
	xp;
	level;
	show; // 'level' or 'xp'
	bar;
	barCaption;
	accountBar;
	hovered = false;
	xpIncreaseFx;
	modeSelector;
	heroProfile;
	heroBar;
	heroAchievements;
	heroSettings;
	heroLogout;
	timeMode;

	constructor({rating, xp, level, show, timeMode})
	{
		this.rating = rating;
		this.xp = xp;
		this.level = level;
		this.show = show;
		this.timeMode = timeMode;
		this.bar = document.getElementById('xp-bar-fill');
		this.barCaption = document.getElementById('account-bar-xp');
		this.accountBar = document.getElementById('account-bar-user');
		this.xpIncreaseFx = document.getElementById('xp-increase-fx');
		this.textBarInMenu = document.getElementById('textBarInMenu');
		this.modeSelector = document.getElementById('modeSelector');
		this.heroProfile = document.getElementById("heroProfile");
		this.heroBar = document.getElementById("heroBar");
		this.heroAchievements = document.getElementById("heroAchievements");
		this.heroSettings = document.getElementById("heroSettings");
		this.heroLogout = document.getElementById("heroLogout");

		this.bar.style.boxShadow = "";
		window.addEventListener('pagehide', () => AccountWidget.markUnshownResultLeft());
	}

	setup()
	{
		if (this.show == 'level')
			this.showLevel();
		else if (this.show == 'rating')
			this.showRating();
		else if (this.show == 'time')
			this.showTimeMode();
		else
			throw new Error('Unknown this.show value:' + this.show);
	}

	showLevel()
	{
		this.bar.className = 'xp-bar-fill-c1';
		this.accountBar.className = 'account-bar-level';
		this.modeSelector.className = 'modeSelectorInLevelBar';
		this.setBarRatio(this.xp / getXPForNextLevel(this.level));
		this.barCaption.innerHTML = this.hovered ? (this.xp + '/' + getXPForNextLevel(this.level)) : 'Level ' + this.level;
	}

	showRating()
	{
		this.bar.className = 'xp-bar-fill-c2';
		this.accountBar.className = 'account-bar-rating';
		this.modeSelector.className = 'modeSelectorInRatingBar';
		let rank = Rating.getRankFromRating(this.rating);
		let rankStart = Rating.getRankMinimalRating(rank);
		let nextRank = Rating.getRankMinimalRating(rank + 1);
		let rankSize = nextRank - rankStart;
		this.setBarRatio((this.rating - rankStart) / rankSize);
		this.barCaption.innerHTML = this.hovered ? Math.round(this.rating) : Rating.getReadableRankFromRating(this.rating);
	}

	showTimeMode()
	{
		if (this.hovered)
		{
			let message = this.timeMode.successCount + ' right';
			if (this.timeMode.failCount)
				message += '  ' + this.timeMode.failCount + ' bad';
			this.barCaption.innerHTML = message;
		}
		else
			this.barCaption.innerHTML = 'Time mode ' + this.timeMode.rank;
		this.setBarRatio((this.timeMode.failCount + this.timeMode.successCount) / this.timeMode.overallCount);
	}

	setBarRatio(ratio)
	{
		this.bar.style.width = ratio * 100 + '%';
	}

	hover()
	{
		this.hovered = true;
		this.setup();
		this.heroProfile.style.display = "inline-block";
		if (this.heroBar)
			this.heroBar.style.display = "inline-block";
		this.heroAchievements.style.display = "inline-block";
		this.heroSettings.style.display = "inline-block";
		this.heroLogout.style.display = "inline-block";
	}

	noHover()
	{
		this.hovered = false;
		this.setup();
		this.heroProfile.style.display = "none";
		if (this.heroBar)
			this.heroBar.style.display = "none";
		this.heroAchievements.style.display = "none";
		this.heroSettings.style.display = "none";
		this.heroLogout.style.display = "none";
	}

	switchBarInMenu()
	{
		if(this.show == 'rating')
		{
			this.textBarInMenu.innerHTML = 'Rating Bar';
			this.show = 'level';
		}
		else
		{
			this.textBarInMenu.innerHTML = 'Level Bar';
			this.show = 'rating';
		}
		setCookie('showInAccountWidget', this.show);

		this.setup();
	}

	animate(increase)
	{
		if (this.show == 'time')
		{
			if (increase)
				this.timeSucceeded++;
			else
				this.timeFailed++;
			this.showTimeMode();
			return;
		}
		this.animateTo(this.valuesAfter(increase));
	}

	animateTo(values)
	{
		this.bar.style.webkitTransition = "all 1s ease";
		this.setValues(values);
		this.xpIncreaseFx.style.display = 'inline-block';
		this.setup();
		setTimeout(() =>
		{
			this.xpIncreaseFx.style.display = "none";
			AccountWidget.forgetUnshownResult();
		}, 1000);
	}

	values()
	{
		return {rating: this.rating, xp: this.xp, level: this.level};
	}

	setValues(values)
	{
		this.rating = values.rating;
		this.xp = values.xp;
		this.level = values.level;
	}

	// What the bar is expected to show after the result, before the server answers
	valuesAfter(increase)
	{
		let values = this.values();
		values.rating += calculateRatingChange(values.rating, xpStatus.tsumegoRating, increase, 0.5);
		if (increase)
		{
			values.xp += xpStatus.getXP();
			while (values.xp >= getXPForNextLevel(values.level))
			{
				values.xp -= getXPForNextLevel(values.level);
				values.level++;
			}
		}
		return values;
	}

	// Called when a result is sent. The result is kept until the bar has shown it in full,
	// so when the page is left (refresh, Next, a redirect) sooner, the next page shows it.
	rememberResult(increase)
	{
		if (this.show == 'time')
			return;
		let unshown = AccountWidget.loadUnshownResult();
		AccountWidget.saveUnshownResult({
			before: unshown ? unshown.before : this.values(),
			after: this.valuesAfter(increase)});
	}

	// Plays the result the previous page was left before showing, as if it had just happened here
	replayUnshownResult()
	{
		let unshown = AccountWidget.loadUnshownResult();
		AccountWidget.forgetUnshownResult();
		if (!unshown || !unshown.leftAt || Date.now() - unshown.leftAt > AccountWidget.unshownResultTimeout)
			return;
		if (this.show == 'time')
			return;
		let target = this.values();
		// the result can still be on its way to the server when this page is rendered
		if (AccountWidget.sameValues(target, unshown.before))
			target = unshown.after;
		this.bar.style.webkitTransition = "none";
		this.setValues(unshown.before);
		this.setup();
		this.bar.offsetWidth; // the bar has to be drawn at the old values first, or there is nothing to animate from
		this.animateTo(target);
	}

	// the page prints the rating with less digits than the result answer has
	static sameValues(a, b)
	{
		return Math.abs(a.rating - b.rating) < 0.001 && a.xp == b.xp && a.level == b.level;
	}

	static unshownResultKey = 'accountWidgetUnshownResult';
	// a result left unshown longer ago than this (in ms) isn't news anymore
	static unshownResultTimeout = 30000;

	static loadUnshownResult()
	{
		try
		{
			return JSON.parse(sessionStorage.getItem(AccountWidget.unshownResultKey));
		}
		catch (e)
		{
			return null;
		}
	}

	static saveUnshownResult(unshown)
	{
		try
		{
			sessionStorage.setItem(AccountWidget.unshownResultKey, JSON.stringify(unshown));
		}
		catch (e) {}
	}

	static forgetUnshownResult()
	{
		try
		{
			sessionStorage.removeItem(AccountWidget.unshownResultKey);
		}
		catch (e) {}
	}

	// The time the page was left, as a result left unshown for long isn't replayed
	static markUnshownResultLeft()
	{
		let unshown = AccountWidget.loadUnshownResult();
		if (!unshown)
			return;
		unshown.leftAt = Date.now();
		AccountWidget.saveUnshownResult(unshown);
	}
}

function levelBarChange(num)
{
	if (typeof accountWidget === 'undefined')
		return;

	if (num === 1)
	{
		accountWidget.show = 'level';
		accountWidget.showLevel();
	}
	else
	{
		accountWidget.show = 'rating';
		accountWidget.showRating();
	}
	setCookie('showInAccountWidget', accountWidget.show);
	setCookie('levelBar', num);
}
