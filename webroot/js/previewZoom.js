/**
 * Preview toggle - switches between pill buttons and inline board previews.
 * Persists preference to localStorage.
 *
 * Any checkbox with class="preview-zoom-toggle" will toggle the
 * "preview-zoomed" class on its nearest .set-view-main ancestor,
 * falling back to <html> if there is no such ancestor.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'preview-mode';
	var ZOOM_CLASS = 'preview-zoomed';

	function findContainer(toggle) {
		if (toggle.dataset.target)
			return document.querySelector(toggle.dataset.target);
		return toggle.closest('.set-view-main') || toggle.closest('table') || document.documentElement;
	}

	function init() {
		var toggles = document.querySelectorAll('.preview-zoom-toggle');
		if (!toggles.length) return;

		var saved = localStorage.getItem(STORAGE_KEY);
		if (saved === '1') {
			document.documentElement.classList.add(ZOOM_CLASS);
			renderAllPreviews();
			toggles.forEach(function (t) { t.checked = true; });
		}

		toggles.forEach(function (toggle) {
			toggle.addEventListener('change', function () {
				var container = findContainer(toggle);
				if (this.checked) {
					container.classList.add(ZOOM_CLASS);
					renderPreviewsIn(container);
					localStorage.setItem(STORAGE_KEY, '1');
				} else {
					container.classList.remove(ZOOM_CLASS);
					container.querySelectorAll('a[data-sgf-preview] svg').forEach(function (svg) { svg.remove(); });
					localStorage.setItem(STORAGE_KEY, '0');
				}
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
