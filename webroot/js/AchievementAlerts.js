//The card of a completed achievement: a dialog in the middle of the page over a dimmed backdrop.
//Both the page-load path (achievementUpdates embedded as JSON in templates/layout/default.php)
//and the solve path (the result of /tsumegos/result in webroot/js/PlayPage.js) feed this the same pure
//achievement data. The markup is built with createElement/textContent, so no
//HTML is ever injected and the /tsumegos/result endpoint stays pure data.
//Data shape: { id, name, description, xp, image, color }
//Several achievements at once wait in a queue: one card at a time, the next when it closes, "2 of 3" on each.
//A card closes on its buttons, Esc or a click on the backdrop; the dialog gives the focus back to where it was,
//so the board keeps its keys.
var achievementCards = {queue: [], shown: 0, dialog: null};

function showAchievementPopup(achievement)
{
	if (!achievement)
		return;
	achievementCards.queue.push(achievement);
	if (achievementCards.dialog)
		updateAchievementCardStep();
	else
		showNextAchievementCard();
}

//Closes the card shown and drops the ones waiting (the browser tests use it to get the page back)
function closeAllAchievementPopups()
{
	achievementCards.queue = [];
	if (achievementCards.dialog)
		achievementCards.dialog.close();
}

function showNextAchievementCard()
{
	var achievement = achievementCards.queue.shift();
	if (!achievement)
	{
		achievementCards.shown = 0;
		return;
	}
	achievementCards.shown++;

	var dialog = document.createElement('dialog');
	dialog.className = 'achievement-card ' + achievement.color + '3';
	dialog.setAttribute('aria-labelledby', 'achievement-card-name');
	var card = document.createElement('div');
	card.className = 'achievement-card__content';

	var head = document.createElement('p');
	head.className = 'achievement-card__kicker';
	head.appendChild(document.createTextNode('Achievement unlocked'));
	var step = document.createElement('span');
	step.className = 'achievement-card__step';
	head.appendChild(step);
	card.appendChild(head);

	var close = document.createElement('button');
	close.type = 'button';
	close.className = 'btn btn--ghost btn--icon achievement-card__close';
	close.setAttribute('aria-label', 'Close');
	close.textContent = '×';
	card.appendChild(close);

	var medal = document.createElement('div');
	medal.className = 'achievement-card__medal';
	var image = document.createElement('img');
	image.className = 'achievement-card__image';
	image.src = imageUrl('/img/' + achievement.image + '.png');
	image.alt = '';
	medal.appendChild(image);
	card.appendChild(medal);

	var name = document.createElement('h2');
	name.className = 'achievement-card__name';
	name.id = 'achievement-card-name';
	name.textContent = achievement.name;
	card.appendChild(name);

	var description = document.createElement('p');
	description.className = 'achievement-card__description';
	description.textContent = achievement.description;
	card.appendChild(description);

	//A repeat of a repeatable achievement grants no XP, so it shows none
	if (achievement.xp > 0)
	{
		var xp = document.createElement('span');
		xp.className = 'achievement-card__xp';
		xp.textContent = '+' + achievement.xp + ' XP';
		card.appendChild(xp);
	}

	var actions = document.createElement('div');
	actions.className = 'achievement-card__actions';
	var view = document.createElement('a');
	view.className = 'btn';
	view.href = '/achievements/view/' + achievement.id;
	view.textContent = 'View achievement';
	var next = document.createElement('button');
	next.type = 'button';
	next.className = 'btn btn--primary achievement-card__next';
	next.autofocus = true;
	actions.appendChild(view);
	actions.appendChild(next);
	card.appendChild(actions);
	dialog.appendChild(card);

	var closing = false;
	var finish = function ()
	{
		if (closing)
			return;
		closing = true;
		dialog.classList.add('achievement-card--closing');
		setTimeout(function () { dialog.close(); }, achievementCardsReduceMotion() ? 0 : 150);
	};
	close.addEventListener('click', finish);
	next.addEventListener('click', finish);
	//Esc closes through the same fade instead of the dialog's own instant close
	dialog.addEventListener('cancel', function (event)
	{
		event.preventDefault();
		finish();
	});
	//The content fills the dialog, so a click on the dialog itself is a click on the backdrop
	dialog.addEventListener('click', function (event)
	{
		if (event.target === dialog)
			finish();
	});
	//The keys pressed on the card stay on it: the arrows don't go to the next problem behind it
	dialog.addEventListener('keydown', function (event) { event.stopPropagation(); });
	dialog.addEventListener('close', function ()
	{
		dialog.remove();
		achievementCards.dialog = null;
		showNextAchievementCard();
	});

	document.body.appendChild(dialog);
	achievementCards.dialog = dialog;
	updateAchievementCardStep();
	dialog.showModal();
}

//"2 of 3" and Next while more wait, Continue on the last one
function updateAchievementCardStep()
{
	var dialog = achievementCards.dialog;
	var total = achievementCards.shown + achievementCards.queue.length;
	dialog.querySelector('.achievement-card__step').textContent = total > 1 ? achievementCards.shown + ' of ' + total : '';
	dialog.querySelector('.achievement-card__next').textContent = achievementCards.queue.length > 0 ? 'Next' : 'Continue';
}

function achievementCardsReduceMotion()
{
	return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
