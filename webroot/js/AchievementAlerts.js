/**
 * Achievement popup renderer - the single source of truth for "Achievement
 * Completed" popups.
 *
 * Both the page-load path (achievementUpdates embedded as JSON in default.ctp)
 * and the AJAX solve path (play.ctp submitResult) feed this the same pure
 * achievement data. The markup is built with createElement/textContent, so no
 * HTML is ever injected and the /tsumegos/result endpoint stays pure data.
 *
 * Data shape: { id, name, description, xp, image, color }
 */
function showAchievementPopup(achievement)
{
	if (!achievement)
		return;
	var popup = document.createElement('div');
	popup.className = 'alertBox alertInfo ' + achievement.color + '3 achievement-popup';

	var banner = document.createElement('div');
	banner.className = 'alertBanner';
	banner.setAttribute('align', 'center');
	banner.appendChild(document.createTextNode('Achievement Completed '));
	var close = document.createElement('span');
	close.className = 'alertClose';
	close.textContent = 'x';
	// The popup is blocking and important, so only the close button dismisses
	// it; clicking the body does not (to avoid accidental dismissal).
	$(close).on('click', function ()
	{
		$(popup).fadeOut(500);
	});
	banner.appendChild(close);
	popup.appendChild(banner);

	var text = document.createElement('span');
	text.className = 'alertText';
	var img = document.createElement('img');
	img.id = 'hpIcon1';
	img.src = '/img/' + achievement.image + '.png';
	var title = document.createElement('b');
	title.textContent = achievement.name + ' - ' + achievement.description;
	var xp = document.createTextNode(' (' + achievement.xp + ' XP) ');
	var link = document.createElement('a');
	link.href = '/achievements/view/' + achievement.id;
	link.textContent = 'view';
	text.appendChild(img);
	text.appendChild(title);
	text.appendChild(xp);
	text.appendChild(link);
	popup.appendChild(text);

	document.body.appendChild(popup);
	$(popup).fadeIn(600);
}
