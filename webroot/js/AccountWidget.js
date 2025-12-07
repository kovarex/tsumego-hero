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
		this.heroLogout = document.getElementById("heroLogout");

		this.bar.style.boxShadow = "";
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
		this.bar.style.webkitTransition = "all 1s ease";
		this.rating += calculateRatingChange(this.rating, xpStatus.tsumegoRating, increase, 0.5);
		if  (increase)
		{
			this.xp += xpStatus.getXP();
			while (this.checkLevelUp());
		}
		this.xpIncreaseFx.style.display = 'inline-block';
		this.setup();
		setTimeout(() => {this.xpIncreaseFx.style.display = "none";}, 1000);
	}

	checkLevelUp()
	{
		let xpForNextLevel = getXPForNextLevel(this.level);
		if (xpForNextLevel > this.xp)
			return false;
		this.xp -= xpForNextLevel;
		return true;
	}
}
