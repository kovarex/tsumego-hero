<?php

use App\Utility\TsumegoButton;
use App\Utility\ViteManifest;

/**
 * @var \App\View\AppView $this
 * @var array $upcoming
 * @var array $recentlyPublished
 * @var array $sandboxSets
 * @var array $publicSets
 */
$today = date('Y-m-d');
?>
<div class="homeCenter2 schedule-page">
<div class="profile-header">
	<p class="profile-username">Publish Schedule</p>
	<p class="hint">Problems are prepared in a sandbox set and scheduled to move into a public set, one day at a time. The daily cron publishes what is due, and a schedule that starts today publishes right away.</p>
</div>

<div class="stack schedule-page">
	<div class="card">
		<h2 class="card__title">Upcoming</h2>
		<?php if (empty($upcoming)): ?>
			<p class="hint">No problems scheduled.</p>
		<?php else: ?>
		<div class="set-view-main">
		<table class="data-table schedule-page__table">
			<thead>
				<tr>
					<th>Publish date</th>
					<th>Source set</th>
					<th>Problem <label class="schedule-page__column-toggle" title="Toggle board previews"><input type="checkbox" class="preview-zoom-toggle">Preview</label></th>
					<th>Publishing to</th>
					<th>Scheduled by</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($upcoming as $row): ?>
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
						<?php if ($row['tsumego_deleted']): ?>
							<a class="hint" href="/trash" title="A problem in the trash is not published. Restore it, or cancel this row.">in the trash</a>
						<?php elseif ($row['sc_id']): ?>
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
						<form method="post" action="/schedule/cancel/<?php echo $row['id']; ?>" class="inline-form"
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
		<table class="data-table schedule-page__table">
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

	<div class="card card--green schedule-page__form-card">
		<h2 class="card__title">Schedule problems</h2>
		<form method="post" action="/schedule/add" class="schedule-page__form" id="schedule-form">
			<div class="form-field">
				<label class="form-field__label" for="schedule-from">Source sandbox set</label>
				<select class="form-field__control" id="schedule-from" name="set_id_from">
					<?php foreach ($sandboxSets as $set): ?>
						<option value="<?php echo $set['id']; ?>"><?php echo h($set['title']); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="hint">Problems are taken from here, in the order the set has them.</span>
			</div>
			<div class="form-field">
				<label class="form-field__label" for="schedule-to">Target public set</label>
				<select class="form-field__control" id="schedule-to" name="set_id_to">
					<?php foreach ($publicSets as $set): ?>
						<option value="<?php echo $set['id']; ?>"><?php echo h($set['title']); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="hint">Publishing takes the problem out of the sandbox and puts it here; a number that is already taken is renumbered.</span>
			</div>
			<div class="form-field">
				<label class="form-field__label" for="schedule-count">How many problems (optional)</label>
				<input class="form-field__control" type="number" id="schedule-count" name="count" min="1" placeholder="All">
				<span class="hint">Leave it empty for all of them. A problem already in the target set, or already scheduled, is skipped.</span>
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
			<button class="btn btn--primary" type="submit" id="schedule-submit">Schedule</button>
		</form>
		<div class="schedule-page__preview">
			<label class="schedule-page__preview-toggle" title="Toggle board previews">
				<input type="checkbox" class="preview-zoom-toggle" data-target="#schedule-preview">Preview
			</label>
			<div id="schedule-preview" class="set-view-main"></div>
		</div>
	</div>
</div>
</div>

<?php echo ViteManifest::legacyScript('schedule'); ?>
