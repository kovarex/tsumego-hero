<?php

/**
 * Tag overview for admins: every tag with how many problems carry it. A count opens the
 * list of those problems, loaded when it is first opened, so a tag can be judged (and
 * deleted) without leaving the page.
 *
 * @var View $this
 * @var array $tags What Tag::listWithProblemCounts() returned
 * @var string $sort 'name' or 'problems'
 * @var bool $canDeleteTag
 */

$problemCountText = fn(int $count): string => $count . ' ' . ($count === 1 ? 'problem' : 'problems');
?>
<div class="tag-overview">
	<h1>Tags</h1>
	<p>
		<?php echo count($tags); ?> tags, sorted
		<?php if ($sort === 'name'): ?>
			<b>by name</b> | <a href="/tags?sort=problems" id="tag-overview-sort-problems">by problems</a>
		<?php else: ?>
			<a href="/tags" id="tag-overview-sort-name">by name</a> | <b>by problems</b>
		<?php endif; ?>
		| <a href="/tags/add">Create new tag</a>
	</p>
	<table class="data-table data-table--compact">
		<thead>
		<tr>
			<th>Tag</th>
			<th>Problems</th>
			<?php if ($canDeleteTag): ?>
				<th></th>
			<?php endif; ?>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($tags as $tag): ?>
			<?php
				$problemCount = (int) $tag['problem_count'];
				$proposedCount = (int) $tag['proposed_count'];
			?>
			<tr id="tag-<?php echo $tag['id']; ?>">
				<td>
					<a href="/tags/view/<?php echo $tag['id']; ?>"><?php echo h($tag['name']); ?></a>
					<?php if (!$tag['approved']): ?>
						<i class="tag-overview__note">(proposed tag)</i>
					<?php endif; ?>
					<?php if ($tag['hint']): ?>
						<i class="tag-overview__note">(hint)</i>
					<?php endif; ?>
				</td>
				<td>
					<?php if ($problemCount + $proposedCount > 0): ?>
						<button type="button" class="tag-overview__toggle" data-tag-id="<?php echo $tag['id']; ?>" aria-expanded="false" aria-controls="tag-problems-<?php echo $tag['id']; ?>">
							<?php echo $problemCountText($problemCount); ?><?php if ($proposedCount > 0) echo ' + ' . $proposedCount . ' proposed'; ?>
						</button>
					<?php else: ?>
						<?php echo $problemCountText(0); ?>
					<?php endif; ?>
				</td>
				<?php if ($canDeleteTag): ?>
					<td>
						<form method="post" action="/tags/delete/<?php echo $tag['id']; ?>"
							data-confirm="<?php echo h('Delete the tag "' . $tag['name'] . '"? It is on ' . $problemCountText($problemCount + $proposedCount) . ', which will lose it.'); ?>"
							onsubmit="return confirm(this.dataset.confirm);">
							<button type="submit" class="btn btn--danger btn--small" id="tag-delete-<?php echo $tag['id']; ?>">Delete</button>
						</form>
					</td>
				<?php endif; ?>
			</tr>
			<?php if ($problemCount + $proposedCount > 0): ?>
				<tr class="tag-overview__problems" id="tag-problems-<?php echo $tag['id']; ?>" hidden>
					<td colspan="<?php echo $canDeleteTag ? 3 : 2; ?>"><ol></ol></td>
				</tr>
			<?php endif; ?>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<script>
	document.querySelectorAll('.tag-overview__toggle').forEach(toggle => toggle.addEventListener('click', async () =>
	{
		const row = document.getElementById('tag-problems-' + toggle.dataset.tagId);
		row.hidden = !row.hidden;
		toggle.setAttribute('aria-expanded', String(!row.hidden));
		if (row.hidden || row.dataset.loaded)
			return;
		row.dataset.loaded = 'true';

		const list = row.querySelector('ol');
		list.textContent = 'Loading…';
		const response = await fetch('/tags/problems/' + toggle.dataset.tagId);
		if (!response.ok)
		{
			list.textContent = 'Loading the problems failed.';
			delete row.dataset.loaded;
			return;
		}
		list.textContent = '';
		for (const problem of await response.json())
		{
			const item = document.createElement('li');
			const link = document.createElement('a');
			link.href = problem.url;
			link.textContent = problem.name;
			item.append(link);
			if (!problem.approved)
			{
				const note = document.createElement('i');
				note.className = 'tag-overview__note';
				note.textContent = ' (proposed)';
				item.append(note);
			}
			list.append(item);
		}
	}));
</script>
