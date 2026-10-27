//Preview toggle - switches between pill buttons and inline board previews. Persists preference to localStorage.
//Any checkbox with class="preview-zoom-toggle" switches its own container between the two modes:
//the element it names in data-target, else the block of boards it sits in, else the whole page.
//A page can carry several of them, one per table. The mode the page opens in is applied through
//the same container as a click, so the two can never disagree about where the class lives.
(function () {
	'use strict';

	var STORAGE_KEY = 'preview-mode';
	var ZOOM_CLASS = 'preview-zoomed';

	function containerOf(toggle) {
		if (toggle.dataset.target)
			return document.querySelector(toggle.dataset.target);
		return toggle.closest('.set-view-main') || toggle.closest('table') || document.documentElement;
	}

	function setMode(toggle, on) {
		var container = containerOf(toggle) || document.documentElement;
		container.classList.toggle(ZOOM_CLASS, on);
		if (on)
			renderPreviewsIn(container);
		else
			container.querySelectorAll('a[data-sgf-preview] svg').forEach(function (svg) { svg.remove(); });
	}

	function init() {
		var toggles = document.querySelectorAll('.preview-zoom-toggle');
		if (!toggles.length) return;

		var on = localStorage.getItem(STORAGE_KEY) === '1';
		toggles.forEach(function (toggle) {
			toggle.checked = on;
			if (on)
				setMode(toggle, true);
			toggle.addEventListener('change', function () {
				setMode(toggle, this.checked);
				localStorage.setItem(STORAGE_KEY, this.checked ? '1' : '0');
			});
		});
	}

	function renderPreviewsIn(container) {
		container.querySelectorAll('a[data-sgf-preview]:not(:has(svg))').forEach(function (target) {
			var data;
			try { data = JSON.parse(target.dataset.sgfPreview); } catch (e) { return; }
			if (!data || !data.black) return;
			createBoard(target, data.black, data.white, data.xMax, data.yMax, data.boardSize);
		});
	}

	function renderAllPreviews() {
		renderPreviewsIn(document.documentElement);
	}

	window.renderPreviews = renderAllPreviews;

	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', init);
	else
		init();
})();
