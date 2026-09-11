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
		$("#time-mode-countdown").html(`${Math.floor(this.remaining/60)}:${(this.remaining%60).toFixed(1).padStart(4,"0")}`);
	}

	timeModeUpdate()
	{
		this.remaining = Math.max(0, this.secondsToSolve + bonusSeconds - elapsedSeconds());
		this.updateTimeModeCaption();

		if (this.remaining == 0)
		{
			locked = true;
			tryAgainTomorrow = true;
			submitResult(false, true);

			$("#time-mode-countdown").css("color","var(--feedback-error)");
			document.getElementById("status").style.color = "var(--feedback-error)";
			document.getElementById("status").innerHTML = "<h2>Time's up!</h2>";
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
