class XPStatus
{
	constructor({ solved, sprintRemainingSeconds, sprintMultiplier, goldenTsumego, goldenTsumegoMultiplier, resolving, resolvingMultiplier, userRating, tsumegoRating, progressDeletionCount, failsInARow, maximumRatedFailsInARow})
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
		this.failsInARow = failsInARow;
		this.maximumRatedFailsInARow = maximumRatedFailsInARow;

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
		let multiplier = this.goldenTsumego ? 1 : this.getProgressDeletionMultiplier();
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
		// after a reset of its part in the last month the problem is known, so the rating doesn't change
		if (this.progressDeletionCount > 0)
		{
			this.ratingHeader.textContent = 'Rating: ';
			this.ratingGainShort.textContent = 'unaffected (reset)';
			this.ratingGainLong.textContent = 'Problems of a part you reset in the last month don\'t change your rating.';
			this.ratingSeparator.textContent = '';
			this.ratingLossShort.textContent = '';
			this.ratingLossLong.textContent = '';
			return;
		}

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
			let ratingLoss = this.ratingChange(this.userRating, false);
			this.ratingSeparator.textContent = '/';
			this.ratingLossShort.textContent = showRatingShort(ratingLoss);
			if (this.failChangesRating())
				this.ratingLossLong.textContent = showRatingLong(ratingLoss);
			else
				this.ratingLossLong.textContent = 'You failed this problem ' + this.failsInARow
					+ ' times in a row, more fails don\'t lower your rating until you solve it.';
		}
	}

	// Only the first fails in a row on a problem change the rating, until it is solved
	failChangesRating()
	{
		return this.failsInARow < this.maximumRatedFailsInARow;
	}

	// The change of the player's rating the result makes, counted the same way as on the server
	ratingChange(rating, increase)
	{
		if (this.progressDeletionCount > 0)
			return 0;
		if (!increase && !this.failChangesRating())
			return 0;
		return calculateRatingChange(rating, this.tsumegoRating, increase ? 1 : 0, playerRatingCalculationModifier);
	}

	// Called once a fail is shown, so the next one is counted after it
	addFail()
	{
		this.failsInARow++;
		this.update();
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
