<?php

use App\Model\Table\SetTable;
use App\Utility\Trash;
use App\Utility\Util;

/**
 * @var \App\View\AppView $this
 * @var array $sets
 * @var array $problems
 */

$renderDates = function (string $deleted): string
{
	$purgeDate = Trash::purgeDate($deleted);
	return '<td><time datetime="' . Util::toIso8601($deleted) . '" data-format="datetime">' . h($deleted) . '</time></td>'
		. '<td><time datetime="' . Util::toIso8601($purgeDate) . '" data-format="date">' . h($purgeDate) . '</time></td>';
};
$renderRestore = fn(string $action, string $id): string
	=> '<td><form method="post" action="' . $action . '">'
		. '<button type="submit" class="btn btn--small" id="' . $id . '">Restore</button></form></td>';
?>
<div class="trash">
	<h1 class="title4">Trash</h1>
	<p>Deleted problems and collections stay here for <?php echo Trash::$RETENTION_DAYS; ?> days, then they are removed for good, with everything that belongs to them.</p>

	<table class="data-table data-table--compact" id="trash-sets">
		<caption>Collections (<?php echo count($sets); ?>)</caption>
		<?php if ($sets): ?>
			<tr><th>Collection</th><th>Problems</th><th>Deleted by</th><th>Deleted</th><th>Removed for good</th><th></th></tr>
			<?php foreach ($sets as $set): ?>
				<tr>
					<td><a href="<?php echo SetTable::url((int) $set['id']); ?>"><?php echo h($set['title']); ?></a><?php echo $set['public'] == 0 ? ' (sandbox)' : ''; ?></td>
					<td><?php echo (int) $set['problem_count']; ?></td>
					<td><?php echo h($set['deleted_by'] ?? ''); ?></td>
					<?php echo $renderDates($set['deleted']); ?>
					<?php echo $renderRestore('/trash/restore-set/' . (int) $set['id'], 'restore-set-' . (int) $set['id']); ?>
				</tr>
			<?php endforeach; ?>
		<?php else: ?>
			<tr><td>No collection is in the trash.</td></tr>
		<?php endif; ?>
	</table>

	<table class="data-table data-table--compact" id="trash-problems">
		<caption>Problems (<?php echo count($problems); ?>)</caption>
		<?php if ($problems): ?>
			<tr><th>Problem</th><th>Description</th><th>Deleted by</th><th>Deleted</th><th>Removed for good</th><th></th></tr>
			<?php foreach ($problems as $problem): ?>
				<tr>
					<td>
						<?php if ($problem['set_connection_id']): ?>
							<a href="/<?php echo (int) $problem['set_connection_id']; ?>"><?php echo h($problem['set_title'] . ' ' . $problem['num']); ?></a>
						<?php else: ?>
							Problem <?php echo (int) $problem['id']; ?> (in no collection)
						<?php endif; ?>
					</td>
					<td><?php echo h($problem['description'] ?? ''); ?></td>
					<td><?php echo h($problem['deleted_by'] ?? ''); ?></td>
					<?php echo $renderDates($problem['deleted']); ?>
					<?php echo $renderRestore('/trash/restore-problem/' . (int) $problem['id'], 'restore-problem-' . (int) $problem['id']); ?>
				</tr>
			<?php endforeach; ?>
		<?php else: ?>
			<tr><td>No problem is in the trash.</td></tr>
		<?php endif; ?>
	</table>
</div>
