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
