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

	getProgressDeletionMultiplier()
	{
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
		return ratingToXP(this.tsumegoRating, multiplier);
	}
}
