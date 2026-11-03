//The schedule page (templates/Schedule/index.php): the preview of what the form would schedule, on
//which day, and the confirmation before a schedule that starts today publishes.
(function() {
	var preview = document.getElementById('schedule-preview');
	var timer = null;

	function fetchPreview() {
		var from = document.getElementById('schedule-from').value;
		var to = document.getElementById('schedule-to').value;
		var count = document.getElementById('schedule-count').value;
		var num = document.getElementById('schedule-num').value;
		var perDay = document.getElementById('schedule-per-day').value;
		var startDate = document.getElementById('schedule-date').value;
		if (!from || !to) return;

		var url = '/schedule/preview?set_id_from=' + from + '&set_id_to=' + to + '&count=' + count
			+ '&num=' + (num || 0) + '&per_day=' + perDay + '&start_date=' + encodeURIComponent(startDate || '');
		fetch(url).then(function(r) {
			if (!r.ok) throw new Error('HTTP ' + r.status);
			return r.json();
		}).then(function(data) {
			if (!data || data.length === 0) {
				preview.innerHTML = '<p class="hint">Nothing left to schedule here: every problem of this set is already in the target set or already scheduled.</p>';
				return;
			}
			var html = '';
			var hasCollision = false;
			var currentDay = null;
			for (var i = 0; i < data.length; i++) {
				var c = data[i];
				if (c.date !== currentDay) {
					if (currentDay !== null) html += '</div></div>';
					currentDay = c.date;
					html += '<div class="preview-day"><p class="hint">Publishes on ' + dayLabel(c.date) + '</p><div class="problem-nav__buttons">';
				}
				var sgfAttr = c.preview ? " data-sgf-preview='" + JSON.stringify(c.preview) + "'" : '';
				var numCls = c.num_collision ? ' problem-nav__number--collision' : '';
				var numTitle = c.num_collision ? ' title="Number already used in target set - will be renumbered on publish"' : '';
				html += '<li class="problem-nav__item problem-nav__item--N"><a class="tooltip" href="/' + c.sc_id + '" data-tsumego-id="' + c.tsumego_id + '"' + sgfAttr + '>';
				html += '<div class="problem-nav__number' + numCls + '"' + numTitle + '>' + c.num + '</div><div class="problem-nav__ratio">-</div><div class="problem-nav__time">-</div>';
				html += '<span class="tooltip-box"><div class="tooltip-label statusN">Not visited</div><div class="tooltip-desc">' + (c.num_collision ? 'Number already used in target set - will be renumbered on publish.' : 'You haven\'t seen this problem yet.') + '</div></span>';
				html += '</a></li>';
				if (c.num_collision) hasCollision = true;
			}
			if (currentDay !== null) html += '</div></div>';
			if (hasCollision) {
				html += '<p class="hint">Numbers marked (&#8635;) are already used in the target set and will be renumbered on publish.</p>';
			}
			preview.innerHTML = html;
			if (typeof window.renderPreviews === 'function') window.renderPreviews();
		}).catch(function() {
			preview.innerHTML = '<p class="hint">Failed to load preview.</p>';
		});
	}

	function dayLabel(iso) {
		var d = new Date(iso + 'T00:00:00Z');
		if (isNaN(d)) return iso;
		return d.toLocaleDateString(undefined, { timeZone: 'UTC', weekday: 'short', month: 'short', day: 'numeric' });
	}

	function debounce() {
		clearTimeout(timer);
		timer = setTimeout(fetchPreview, 300);
	}

	document.getElementById('schedule-from').addEventListener('change', debounce);
	document.getElementById('schedule-to').addEventListener('change', debounce);
	document.getElementById('schedule-count').addEventListener('input', debounce);
	document.getElementById('schedule-count').addEventListener('change', debounce);
	var numField = document.getElementById('schedule-num');
	numField.addEventListener('input', debounce);
	numField.addEventListener('change', debounce);
	numField.addEventListener('keyup', debounce);
	var perDayField = document.getElementById('schedule-per-day');
	perDayField.addEventListener('input', debounce);
	perDayField.addEventListener('change', debounce);

	// A schedule that starts today publishes in the same request, so the button says so
	// and the submission asks before anything goes into a public set.
	var form = document.getElementById('schedule-form');
	var dateField = document.getElementById('schedule-date');
	var submitButton = document.getElementById('schedule-submit');
	// the field can't go before today, so its minimum is today
	var todayIso = dateField.min;

	function refreshSubmitLabel() {
		submitButton.textContent = dateField.value === todayIso ? 'Schedule and publish now' : 'Schedule';
	}

	function confirmPublishingNow(event) {
		if (dateField.value !== todayIso) return;
		var target = document.getElementById('schedule-to').selectedOptions[0].text;
		var question = 'Publishing starts today, so the problems of the first day go into "' + target + '" right away. Continue?';
		if (!confirm(question)) event.preventDefault();
	}

	form.addEventListener('submit', confirmPublishingNow);
	dateField.addEventListener('change', refreshSubmitLabel);
	dateField.addEventListener('input', refreshSubmitLabel);
	dateField.addEventListener('change', debounce);
	refreshSubmitLabel();

	fetchPreview();
})();
