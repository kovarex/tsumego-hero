//The menu every page has (src/View/Layouts/default.ctp): the sound button.

var soundsEnabled = true;

function updateSoundValue(value)
{
	soundsEnabled = value;
	if (typeof besogo !== 'undefined' && besogo.editor)
		besogo.editor.setSoundEnabled(value);
}

//setting: 'on', 'off', or null when a guest has not picked yet, who hears sound like the button shows
function initSound(setting)
{
	if (setting)
		setCookie('sound', setting);
	updateSoundValue(setting !== 'off');
}

function setSoundCookie(value)
{
	//One cookie for the whole site, written for path /. Copies written earlier for single
	//page paths would still outrank it, so those are expired here.
	document.cookie = 'sound=' + value + ';path=/';
	['/users', '/users/view', '/sets/view', '/tsumegos/play'].forEach(function (page)
	{
		document.cookie = 'sound=;path=' + page + ';max-age=0';
	});
}

function changeSound()
{
	//A visitor who has not picked anything hears sound: only an explicit "off" is silent.
	var soundIsOn = getCookie('sound') != 'off';
	document.getElementById('soundButtonImage').src = soundIsOn ? '/img/sound-icon2.png' : '/img/sound-icon1.png';
	setSoundCookie(soundIsOn ? 'off' : 'on');
	updateSoundValue(!soundIsOn);
}
