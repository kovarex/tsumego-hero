<?php
/**
 * @var View $this
 * @var array $p
 * @var array $sandboxSets
 * @var array $publicSets
 */
App::uses('TsumegoButton', 'Utility');
$tomorrow = date('Y-m-d', strtotime('tomorrow'));
?>
<div class="homeCenter2">
<div class="profile-header">
	<p class="profile-username">Publish Schedule</p>
</div>

<div class="stack" style="gap:var(--space-5);align-items:stretch">
	<div class="card">
		<style>
			.preview-zoomed .tooltip-box svg { max-height: 110px; width: auto; height: auto; }
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
							<?php echo (int) $row['num']; ?>
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
		</div>
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
			</div>
			<div class="form-field">
				<label class="form-field__label" for="schedule-to">Target public set</label>
				<select class="form-field__control" id="schedule-to" name="set_id_to">
					<?php foreach ($publicSets as $set): ?>
						<option value="<?php echo $set['Set']['id']; ?>"><?php echo h($set['Set']['title']); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="form-field">
				<label class="form-field__label" for="schedule-count">How many problems</label>
				<input class="form-field__control" type="number" id="schedule-count" name="count" value="1" min="1" max="100" required>
			</div>
			<div class="form-field">
				<label class="form-field__label" for="schedule-num">Start from problem number (optional)</label>
				<input class="form-field__control" type="number" id="schedule-num" name="num" min="1" placeholder="Leave empty for first unscheduled">
			</div>
			<div class="form-field">
				<label class="form-field__label" for="schedule-date">First publish date</label>
				<input class="form-field__control" type="date" id="schedule-date" name="start_date" value="<?php echo $tomorrow; ?>" min="<?php echo $tomorrow; ?>" required>
				<span class="hint">Each problem is published on a consecutive day.</span>
			</div>
			<button class="btn" type="submit">Schedule</button>
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
		if (!from || !to) return;

		var url = '/schedule/preview?set_id_from=' + from + '&set_id_to=' + to + '&count=' + count + '&num=' + (num || 0);
		fetch(url).then(function(r) {
			if (!r.ok) throw new Error('HTTP ' + r.status);
			return r.json();
		}).then(function(data) {
			if (!data || data.length === 0) {
				preview.innerHTML = '<p class="hint">No eligible problems found.</p>';
				return;
			}
			var html = '<ul class="setViewButtons">';
			for (var i = 0; i < data.length; i++) {
				var c = data[i];
				var sgfAttr = c.preview ? " data-sgf-preview='" + JSON.stringify(c.preview) + "'" : '';
				var numAttr = c.num_collision ? ' style="color:#c0392b" title="Number already used in target set - will be renumbered on publish"' : '';
				html += '<li class="statusN"><a class="tooltip" href="/' + c.sc_id + '" data-tsumego-id="' + c.tsumego_id + '"' + sgfAttr + '>';
				html += '<div class="setViewButtons1"' + numAttr + '>' + c.num + '</div><div class="setViewButtons2">-</div><div class="setViewButtons3">-</div>';
				html += '<span class="tooltip-box"><div class="tooltip-label statusN">Not visited</div><div class="tooltip-desc">You haven\'t seen this problem yet.</div></span>';
				html += '</a></li>';
			}
			html += '</ul>';
			preview.innerHTML = html;
			if (typeof window.renderPreviews === 'function') window.renderPreviews();
		}).catch(function() {
			preview.innerHTML = '<p class="hint">Failed to load preview.</p>';
		});
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

	fetchPreview();
})();
</script>

