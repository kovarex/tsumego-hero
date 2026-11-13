class TimeModeTimer
{
	constructor(secondsToSolve)
	{
		this.secondsToSolve = secondsToSolve;
		this.remaining = secondsToSolve;
		this.timeModeTimer = null;
		this.updateTimeModeCaption(); // first initial update on page load
	}

	start()
	{
		this.timeModeTimer = setInterval(() => this.timeModeUpdate(), 100);
	}

	updateTimeModeCaption()
	{
		let countdown = document.getElementById("time-mode-countdown");
		if (countdown)
			countdown.innerHTML = `${Math.floor(this.remaining/60)}:${(this.remaining%60).toFixed(1).padStart(4,"0")}`;
	}

	timeModeUpdate()
	{
		this.remaining = Math.max(0, this.secondsToSolve + bonusSeconds - elapsedSeconds());
		this.updateTimeModeCaption();

		if (this.remaining == 0)
		{
			locked = true;
			tryAgainTomorrow = true;
			// a line lost before the time ran out was already sent as a fail
			if (unrevealedFail)
				revealFail();
			else
				submitResult(playOutcome.timedOut);

			let countdown = document.getElementById("time-mode-countdown");
			if (countdown)
				countdown.style.color = "var(--color-danger)";
			document.getElementById("status").style.color = "var(--play-message-incorrect)";
			document.getElementById("status").innerHTML = "<h2>Time's up!</h2>";
			this.stop();
			toggleBoardLock(true);
			enableReview();
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
