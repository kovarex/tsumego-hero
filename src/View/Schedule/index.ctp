<?php

use App\Utility\TsumegoButton;

/**
 * @var View $this
 * @var array $p
 * @var array $recentlyPublished
 * @var array $sandboxSets
 * @var array $publicSets
 */
$today = date('Y-m-d');
?>
<div class="homeCenter2">
<div class="profile-header">
	<p class="profile-username">Publish Schedule</p>
	<p class="hint">Problems are prepared in a sandbox set and scheduled to move into a public set, one day at a time. The daily cron publishes what is due, and a schedule that starts today publishes right away.</p>
</div>

<div class="stack" style="gap:var(--space-5);align-items:stretch">
	<div class="card">
		<style>
			.preview-zoomed .tooltip-box svg { max-height: 110px; width: auto; height: auto; }
			.preview-day { margin-bottom: var(--space-3); }
			.problem-nav__number--collision::before { content: "\21BB"; margin-right: 2px; font-size: 0.7em; vertical-align: 1px; color: #c0392b; }
		</style>
		<h2 class="card__title">Upcoming</h2>
		<?php if (empty($p)): ?>
			<p class="hint">No problems scheduled.</p>
		<?php else: ?>
		<div class="set-view-main">
		<table class="data-table" style="width:100%">
			<thead>
				<tr>
					<th>Publish date</th>
					<th>Source set</th>
					<th>Problem <label style="cursor:pointer;font-weight:normal;font-size:11px;color:#888;margin-left:4px" title="Toggle board previews"><input type="checkbox" class="preview-zoom-toggle" style="vertical-align:middle;margin-right:2px">Preview</label></th>
					<th>Publishing to</th>
					<th>Scheduled by</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($p as $row): ?>
				<tr>
					<td><time datetime="<?php echo h($row['date']); ?>" data-format="date"><?php echo h($row['date']); ?></time></td>
					<td>
						<?php if ($row['sandbox_set_id']): ?>
							<a href="/sets/view/<?php echo $row['sandbox_set_id']; ?>"><?php echo h($row['sandbox_set_title'] ?: '-'); ?></a>
						<?php else: ?>
							<?php echo h($row['sandbox_set_title'] ?: '-'); ?>
						<?php endif; ?>
					</td>
					<td>
						<?php if ($row['sc_id']): ?>
							<ul class="setViewButtons"><?php new TsumegoButton((int) $row['tsumego_id'], (int) $row['sc_id'], (int) $row['num'], 'N', 0, $row['sgf'] ?? '')->render(); ?></ul>
						<?php else: ?>
							<span class="hint" title="The problem is no longer in a sandbox set and is not in the target set either, so there is nothing to move.">cannot be published</span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ($row['target_set_title']): ?>
							<a href="/sets/view/<?php echo $row['set_id']; ?>"><?php echo h($row['target_set_title']); ?></a>
						<?php else: ?>
							-
						<?php endif; ?>
					</td>
					<td>
						<?php if ($row['created_by_id']): ?>
							<a href="/users/view/<?php echo $row['created_by_id']; ?>"><?php echo h($row['created_by_name']); ?></a>
						<?php else: ?>
							-
						<?php endif; ?>
					</td>
					<td>
						<form method="post" action="/schedule/cancel/<?php echo $row['id']; ?>" style="display:inline"
							onsubmit="return confirm('Cancel scheduling this problem?')">
							<button class="btn btn--danger" type="submit">Cancel</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="hint">Cancelling a row only removes it from the schedule; the problem stays where it is.</p>
		</div>
		<?php endif; ?>
	</div>

	<div class="card">
		<h2 class="card__title">Recently published</h2>
		<?php if (empty($recentlyPublished)): ?>
			<p class="hint">Nothing published in the last 7 days.</p>
		<?php else: ?>
		<table class="data-table" style="width:100%">
			<thead>
				<tr>
					<th>Publish date</th>
					<th>Problem</th>
					<th>Published in</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($recentlyPublished as $row): ?>
				<tr>
					<td><time datetime="<?php echo h($row['date']); ?>" data-format="date"><?php echo h($row['date']); ?></time></td>
					<td><a href="/tsumegos/play/<?php echo (int) $row['tsumego_id']; ?>">Problem <?php echo (int) $row['num']; ?></a></td>
					<td>
						<?php if ($row['target_set_title']): ?>
							<a href="/sets/view/<?php echo (int) $row['set_id']; ?>"><?php echo h($row['target_set_title']); ?></a>
						<?php else: ?>
							-
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<div class="card card--green" style="max-width:500px">
		<h2 class="card__title">Schedule problems</h2>
		<form method="post" action="/schedule/add" style="text-align:left" id="schedule-form">
			<div class="form-field">
				<label class="form-field__label" for="schedule-from">Source sandbox set</label>
				<select class="form-field__control" id="schedule-from" name="set_id_from">
					<?php foreach ($sandboxSets as $set): ?>
						<option value="<?php echo $set['Set']['id']; ?>"><?php echo h($set['Set']['title']); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="hint">Problems are taken from here, in the order the set has them.</span>
			</div>
			<div class="form-field">
				<label class="form-field__label" for="schedule-to">Target public set</label>
				<select class="form-field__control" id="schedule-to" name="set_id_to">
					<?php foreach ($publicSets as $set): ?>
						<option value="<?php echo $set['Set']['id']; ?>"><?php echo h($set['Set']['title']); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="hint">Publishing takes the problem out of the sandbox and puts it here; a number that is already taken is renumbered.</span>
			</div>
			<div class="form-field">
				<label class="form-field__label" for="schedule-count">How many problems</label>
				<input class="form-field__control" type="number" id="schedule-count" name="count" value="1" min="1" max="100" required>
				<span class="hint">At most this many: a problem already in the target set, or already scheduled, is skipped.</span>
			</div>
			<div class="form-field">
				<label class="form-field__label" for="schedule-num">Start from problem number (optional)</label>
				<input class="form-field__control" type="number" id="schedule-num" name="num" min="1" placeholder="Leave empty for first unscheduled">
				<span class="hint">Only problems from this position in the source set on. Leave it empty to start at the first unscheduled one.</span>
			</div>
			<div class="form-field">
				<label class="form-field__label" for="schedule-per-day">Problems per day</label>
				<input class="form-field__control" type="number" id="schedule-per-day" name="per_day" value="5" min="1" max="50" required>
				<span class="hint">Problems are published on consecutive days, this many each day.</span>
			</div>
			<div class="form-field">
				<label class="form-field__label" for="schedule-date">First publish date</label>
				<input class="form-field__control" type="date" id="schedule-date" name="start_date" value="<?php echo $today; ?>" min="<?php echo $today; ?>" required>
				<span class="hint">Starting today publishes right away.</span>
			</div>
			<button class="btn" type="submit" id="schedule-submit">Schedule</button>
		</form>
		<div style="margin-top:var(--space-4)">
			<label style="cursor:pointer;font-size:12px;color:#888;margin-bottom:var(--space-2);display:inline-block" title="Toggle board previews">
				<input type="checkbox" class="preview-zoom-toggle" data-target="#schedule-preview" style="vertical-align:middle;margin-right:4px">Preview
			</label>
			<div id="schedule-preview" class="set-view-main">
			<style>
				#schedule-preview.preview-zoomed .tooltip-box svg { max-height: 110px; width: auto; height: auto; }
			</style>
		</div>
		</div>
	</div>
</div>
</div>

<script>
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
	var todayIso = '<?php echo $today; ?>';

	function refreshSubmitLabel() {
		submitButton.textContent = dateField.value === todayIso ? 'Schedule and publish now' : 'Schedule';
	}

	function confirmPublishingNow(event) {
		if (dateField.value !== todayIso) return;
		var count = document.getElementById('schedule-count').value;
		var target = document.getElementById('schedule-to').selectedOptions[0].text;
		var problems = count === '1' ? 'problem' : 'problems';
		var question = 'Publishing starts today, so up to ' + count + ' ' + problems + ' go into "' + target + '" right away. Continue?';
		if (!confirm(question)) event.preventDefault();
	}

	form.addEventListener('submit', confirmPublishingNow);
	dateField.addEventListener('change', refreshSubmitLabel);
	dateField.addEventListener('input', refreshSubmitLabel);
	dateField.addEventListener('change', debounce);
	refreshSubmitLabel();

	fetchPreview();
})();
</script>

