//The card of a completed achievement: a dialog in the middle of the page over a dimmed backdrop.
//Both the page-load path (achievementUpdates embedded as JSON in templates/layout/default.php)
//and the solve path (the result of /tsumegos/result in webroot/js/PlayPage.js) feed this the same pure
//achievement data. The markup is built with createElement/textContent, so no
//HTML is ever injected and the /tsumegos/result endpoint stays pure data.
//Data shape: { id, name, description, xp, image, color }
//Several achievements at once wait in a queue: one card at a time, the next when it closes, "2 of 3" on each.
//A card closes on its buttons, Esc or a click on the backdrop; the dialog gives the focus back to where it was,
//so the board keeps its keys.
//The card wears the gold frame of a 100% collection (setCardFrame.js), a burst of sparkles comes out of its image and
//a few keep rising beside it while it is open. With reduced motion there are no sparkles.
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

	var closing = false, stopParticles = function () {}, frameObserver = null;
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
		stopParticles();
		if (frameObserver)
			frameObserver.disconnect();
		dialog.remove();
		achievementCards.dialog = null;
		showNextAchievementCard();
	});

	document.body.appendChild(dialog);
	achievementCards.dialog = dialog;
	updateAchievementCardStep();
	dialog.showModal();
	//the frame of a 100% set card, 1.4 times as wide for a card this size
	if (typeof drawGoldFrame === 'function')
	{
		drawGoldFrame(dialog, true, 1.4);
		if (typeof ResizeObserver === 'function')
		{
			frameObserver = new ResizeObserver(function () { drawGoldFrame(dialog, true, 1.4); });
			frameObserver.observe(dialog);
		}
	}
	if (!achievementCardsReduceMotion())
		stopParticles = startAchievementParticles(dialog);
}

//The sparkles, on a canvas reaching 160px past the card on each side: a burst of 44 out of the image that falls and
//fades in about a second and a half, in the achievement's colour and gold, then 7 slow ones rising and twinkling
//beside the card until it closes. Returns the function that stops them.
function startAchievementParticles(dialog)
{
	var burst = 44, rising = 7;
	var margin = 160, ratio = Math.min(window.devicePixelRatio || 1, 2);
	var canvas = document.createElement('canvas');
	canvas.className = 'achievement-card__particles';
	canvas.setAttribute('aria-hidden', 'true');
	var width = dialog.offsetWidth + 2 * margin, height = dialog.offsetHeight + 2 * margin;
	canvas.width = Math.round(width * ratio);
	canvas.height = Math.round(height * ratio);
	canvas.style.width = width + 'px';
	canvas.style.height = height + 'px';
	dialog.appendChild(canvas);
	var context = canvas.getContext('2d');
	context.scale(ratio, ratio);

	//The middle of the image, in the canvas
	var medal = dialog.querySelector('.achievement-card__medal'), x = medal.offsetWidth / 2, y = medal.offsetHeight / 2;
	for (var element = medal; element && element !== dialog; element = element.offsetParent)
	{
		x += element.offsetLeft;
		y += element.offsetTop;
	}
	x += margin;
	y += margin;
	var probe = document.createElement('span');
	probe.style.color = 'var(--achievement-ring)';
	dialog.appendChild(probe);
	var colors = [getComputedStyle(probe).color, '#ffd86b', '#fff1a6', '#e0a93a'];
	probe.remove();

	var sparkles = [], random = function (low, high) { return low + Math.random() * (high - low); };
	for (var i = 0; i < burst; i++)
	{
		var angle = random(0, Math.PI * 2), speed = random(180, 480);
		sparkles.push({x: x, y: y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed - 120, size: random(2.5, 5.5),
			color: colors[i % colors.length], star: i % 3 === 0, age: -random(0, 0.12), life: random(1.1, 1.6), spin: random(-6, 6)});
	}
	var floating = [];
	for (var j = 0; j < rising; j++)
		//beside the card, left and right in turn, so they never cross its text
		floating.push({x: j % 2 === 0 ? random(margin - 70, margin - 16) : random(width - margin + 16, width - margin + 70), y: random(margin, height - margin), size: random(2, 3.5),
			speed: random(10, 24), phase: random(0, Math.PI * 2), color: colors[j % colors.length], star: j % 2 === 0});

	var drawSparkle = function (sparkle, alpha, rotation)
	{
		context.globalAlpha = Math.max(0, Math.min(1, alpha));
		context.fillStyle = sparkle.color;
		context.beginPath();
		if (sparkle.star)
		{
			//a four-pointed star
			var r = sparkle.size * 2.2;
			for (var k = 0; k < 8; k++)
			{
				var a = rotation + k * Math.PI / 4, d = k % 2 === 0 ? r : r * 0.32;
				context[k === 0 ? 'moveTo' : 'lineTo'](sparkle.x + Math.cos(a) * d, sparkle.y + Math.sin(a) * d);
			}
			context.closePath();
		}
		else
			context.arc(sparkle.x, sparkle.y, sparkle.size, 0, Math.PI * 2);
		context.fill();
	};

	var running = true, last = null, start = null;
	var frame = function (time)
	{
		if (!running)
			return;
		if (last === null)
			last = start = time;
		var dt = Math.min(0.05, (time - last) / 1000), elapsed = (time - start) / 1000;
		last = time;
		context.clearRect(0, 0, width, height);
		var alive = false;
		sparkles.forEach(function (sparkle)
		{
			sparkle.age += dt;
			if (sparkle.age > sparkle.life)
				return;
			//one still waiting for its start keeps the burst going
			alive = true;
			if (sparkle.age < 0)
				return;
			var drag = Math.pow(0.3, dt);
			sparkle.vx *= drag;
			sparkle.vy = sparkle.vy * drag + 220 * dt;
			sparkle.x += sparkle.vx * dt;
			sparkle.y += sparkle.vy * dt;
			var t = sparkle.age / sparkle.life;
			drawSparkle(sparkle, 1 - t * t, sparkle.spin * sparkle.age);
		});
		//the floating ones come in as the burst ends, rising beside the card
		floating.forEach(function (sparkle)
		{
			sparkle.y -= sparkle.speed * dt;
			if (sparkle.y < margin - 60)
				sparkle.y = height - margin + 40;
			var fadeIn = Math.min(1, Math.max(0, elapsed - 0.8) / 0.8);
			drawSparkle(sparkle, fadeIn * (0.35 + 0.45 * Math.sin(elapsed * 2.4 + sparkle.phase)), 0);
		});
		context.globalAlpha = 1;
		if (alive || floating.length)
			requestAnimationFrame(frame);
		else
			canvas.remove();
	};
	requestAnimationFrame(frame);
	return function ()
	{
		running = false;
		canvas.remove();
	};
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
