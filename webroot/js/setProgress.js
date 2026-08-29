/**
 * Animate set-card progress numbers from 0 to their data-target on page load.
 *
 * Progressive enhancement only: the static text (the final value, server-rendered)
 * is the fallback when JS is disabled or the user prefers reduced motion.
 */
(function () {
	function init() {
		var prefersReducedMotion = window.matchMedia
			&& window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		var numbers = document.querySelectorAll('.set-progress-number[data-target]');
		if (!numbers.length || prefersReducedMotion)
			return;

		var duration = 600;

		function animate(element, target) {
			var start = null;

			function frame(now) {
				if (start === null)
					start = now;

				var progress = Math.min((now - start) / duration, 1);
				var eased = 1 - Math.pow(1 - progress, 2); // ease-out
				element.textContent = Math.round(eased * target) + '%';

				if (progress < 1)
					requestAnimationFrame(frame);
			}

			requestAnimationFrame(frame);
		}

		for (var i = 0; i < numbers.length; i++)
			animate(numbers[i], parseInt(numbers[i].getAttribute('data-target'), 10) || 0);
	}

	// This file is bundled into the global legacy bundle which loads in <head>,
	// so the set cards are not in the DOM yet - wait for them.
	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', init);
	else
		init();
})();
