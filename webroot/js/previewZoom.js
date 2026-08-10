/**
 * Preview toggle — switches between pill buttons and inline board previews.
 * Persists preference to localStorage.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'preview-mode';
	var ZOOM_CLASS = 'preview-zoomed';

	function init() {
		var toggle = document.getElementById('preview-zoom-slider');
		if (!toggle) return;

		// Restore saved mode
		var saved = localStorage.getItem(STORAGE_KEY);
		if (saved === '1') {
			toggle.checked = true;
			enablePreviews();
		}

		toggle.addEventListener('change', function () {
			if (this.checked) {
				enablePreviews();
				localStorage.setItem(STORAGE_KEY, '1');
			} else {
				disablePreviews();
				localStorage.setItem(STORAGE_KEY, '0');
			}
		});
	}

	function enablePreviews() {
		document.documentElement.classList.add(ZOOM_CLASS);
		renderAllPreviews();
	}

	function disablePreviews() {
		document.documentElement.classList.remove(ZOOM_CLASS);
	}

	function renderAllPreviews() {
		var links = document.querySelectorAll('a[data-sgf-preview]:not(:has(svg))');
		links.forEach(function (target) {
			var data;
			try {
				data = JSON.parse(target.dataset.sgfPreview);
			} catch (e) {
				return;
			}
			if (!data || !data.black) return;
			createBoard(target, data.black, data.white, data.xMax, data.yMax, data.boardSize);
		});
	}

	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', init);
	else
		init();
})();
