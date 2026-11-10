//The profile (Users/view.php): each of the two columns of statistics under it shows the chart its
//buttons pick (data-profile-side and data-profile-show), remembered in the cookie lastProfile<side>.
//Show all of the achievements (profile_achievements.php) shows the rows past the rarest ones, and back.
(function()
{
	const selections = ['level', 'rating', 'time'];
	const sections = {level: 'userShowLevel', rating: 'userShowRating', time: 'userShowTime'};
	const defaults = {Left: 'level', Right: 'rating'};

	function show(selection, side)
	{
		if (!selections.includes(selection))
			selection = defaults[side];
		setCookie('lastProfile' + side, selection);
		for (const [name, id] of Object.entries(sections))
		{
			const active = name === selection;
			const button = document.getElementById(id + 'Button' + side);
			//The chart shown is marked the way the highscore boards and the time mode mark theirs
			if (button)
			{
				button.classList.toggle('btn--inactive', active);
				button.setAttribute('aria-pressed', active);
			}
			const section = document.getElementById(id + side);
			if (active)
				Display.fadeIn(section, 250);
			else
				Display.hide(section);
		}
	}

	document.querySelectorAll('[data-profile-show]').forEach(function(button)
	{
		button.addEventListener('click', function() { show(button.dataset.profileShow, button.dataset.profileSide); });
	});
	show(getCookie('lastProfileLeft'), 'Left');
	show(getCookie('lastProfileRight'), 'Right');

	const achievementsToggle = document.getElementById('profile-achievements-toggle');
	if (achievementsToggle)
		achievementsToggle.addEventListener('click', function()
		{
			const expand = achievementsToggle.getAttribute('aria-expanded') !== 'true';
			document.querySelectorAll('[data-profile-achievement-more]').forEach(function(row) { row.hidden = !expand; });
			achievementsToggle.setAttribute('aria-expanded', expand ? 'true' : 'false');
			achievementsToggle.textContent = expand ? achievementsToggle.dataset.showFewer : achievementsToggle.dataset.showAll;
		});
})();
