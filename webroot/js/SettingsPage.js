//The settings page (Users/settings.php). A choice takes effect when it is clicked: a setting kept on the
//server is posted to the address of its data-save as the field of its data-field, and with data-reload
//the page is reloaded once it is stored, for what the server draws (the avatar, the dates).
//The appearance (data-appearance) is kept in the browser by the scripts of the menu.
(function()
{
	function save(input)
	{
		fetch(input.dataset.save, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: input.dataset.field + '=' + encodeURIComponent(input.value)
		}).then(function(response)
		{
			if (response.ok && input.dataset.reload !== undefined)
				location.reload();
		});
	}

	function changeAppearance(input)
	{
		if (input.dataset.appearance === 'levelBar')
			levelBarChange(Number(input.value));
		else if (input.dataset.appearance === 'theme')
		{
			// darkAndLight() switches and stores the choice, so it is only called when the
			// player picked the other theme.
			if ((input.value === 'dark') !== !light)
				darkAndLight();
		}
		else if (input.dataset.appearance === 'sound')
		{
			setSoundCookie(input.value);
			updateSoundValue(input.value === 'on');
			const icon = document.getElementById('soundButtonImage');
			if (icon)
				icon.src = input.value === 'on' ? '/img/sound-icon1.png' : '/img/sound-icon2.png';
		}
	}

	document.querySelectorAll('input[data-save]').forEach(function(input)
	{
		input.addEventListener('click', function() { save(input); });
	});
	document.querySelectorAll('input[data-appearance]').forEach(function(input)
	{
		input.addEventListener('click', function() { changeAppearance(input); });
	});

	// The change buttons only reveal the form they belong to, so the page stays a list
	// of what is set now until the player asks to edit one of them.
	document.querySelectorAll('[data-opens]').forEach(function(button)
	{
		const form = document.getElementById(button.dataset.opens);
		if (!form)
			return;
		button.addEventListener('click', function()
		{
			const opened = form.style.display !== 'none';
			form.style.display = opened ? 'none' : 'block';
			button.textContent = opened ? 'change' : 'cancel';
		});
	});

	const resetButton = document.querySelector('[data-reset-count]');
	if (resetButton)
		resetButton.addEventListener('click', function(event)
		{
			event.preventDefault();
			if (!confirm('Are you sure that you want to delete your progress on ' + resetButton.dataset.resetCount + ' problems?'))
				return;
			const form = document.createElement('form');
			form.method = 'POST';
			form.action = '/users/deleteOldTsumegoStatuses';
			document.body.appendChild(form);
			form.submit();
		});
})();
