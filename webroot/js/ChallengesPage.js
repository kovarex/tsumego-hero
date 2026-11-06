//The list of challenges (templates/Challenges/index.php): the standings behind the number of the players who
//finished a challenge (templates/element/Challenges/standings_popup.php). The CSS shows them on hover and on
//focus; this adds the tap, which toggles them, and Escape, which hides them until the pointer or the focus leaves.
(function() {
	var popups = document.querySelectorAll('.challenge-standings-popup');

	function isShown(popup) {
		return !popup.classList.contains('is-dismissed') && popup.matches(':hover, :focus-within, .is-open');
	}

	function sync(popup) {
		popup.querySelector('.challenge-standings-popup__toggle').setAttribute('aria-expanded', isShown(popup) ? 'true' : 'false');
	}

	function close(popup) {
		popup.classList.remove('is-open');
		popup.classList.add('is-dismissed');
		sync(popup);
	}

	function reset(popup) {
		popup.classList.remove('is-dismissed');
		sync(popup);
	}

	popups.forEach(function(popup) {
		popup.querySelector('.challenge-standings-popup__toggle').addEventListener('click', function() {
			if (isShown(popup) && popup.classList.contains('is-open')) {
				close(popup);
				return;
			}
			popup.classList.remove('is-dismissed');
			popup.classList.add('is-open');
			sync(popup);
		});
		popup.addEventListener('mouseenter', function() { sync(popup); });
		popup.addEventListener('mouseleave', function() {
			if (!popup.matches(':focus-within'))
				reset(popup);
			sync(popup);
		});
		popup.addEventListener('focusin', function() { sync(popup); });
		popup.addEventListener('focusout', function(event) {
			if (popup.contains(event.relatedTarget))
				return;
			popup.classList.remove('is-open');
			reset(popup);
		});
	});

	document.addEventListener('keydown', function(event) {
		if (event.key !== 'Escape')
			return;
		popups.forEach(function(popup) {
			if (isShown(popup))
				close(popup);
		});
	});

	//A tap anywhere else closes the standings opened by a tap
	document.addEventListener('click', function(event) {
		popups.forEach(function(popup) {
			if (popup.classList.contains('is-open') && !popup.contains(event.target)) {
				popup.classList.remove('is-open');
				sync(popup);
			}
		});
	});
})();
