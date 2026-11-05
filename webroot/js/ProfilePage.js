/* eslint-disable no-restricted-globals -- still uses jQuery until it is converted */
//The profile (Users/view.php): each of the two columns of statistics under it shows the chart its
//buttons pick (data-profile-side and data-profile-show), remembered in the cookie lastProfile<side>.
(function()
{
	const selections = ['level', 'rating', 'time', 'achievement'];
	const sections = {level: 'userShowLevel', rating: 'userShowRating', time: 'userShowTime', achievement: 'userShowAchievements'};
	const defaults = {Left: 'level', Right: 'rating'};

	function show(selection, side)
	{
		if (!selections.includes(selection))
			selection = defaults[side];
		setCookie('lastProfile' + side, selection);
		for (const [name, id] of Object.entries(sections))
		{
			const active = name === selection;
			$('#' + id + 'Button' + side).toggleClass('btn--inactive', !active);
			if (active)
				$('#' + id + side).fadeIn(250);
			else
				$('#' + id + side).hide();
		}
	}

	document.querySelectorAll('[data-profile-show]').forEach(function(button)
	{
		button.addEventListener('click', function() { show(button.dataset.profileShow, button.dataset.profileSide); });
	});
	show(getCookie('lastProfileLeft'), 'Left');
	show(getCookie('lastProfileRight'), 'Right');
})();
