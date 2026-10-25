<?php

use App\Utility\Util;

/**
 * @var View $this
 * @var array $deletedUsers
 */
?>
<div class="admin-area">
	<table class="data-table data-table--compact" id="deleted-accounts">
		<caption>Deleted Accounts (<?php echo count($deletedUsers); ?>)</caption>
		<?php if (!$deletedUsers): ?>
			<tr><td>No account is deleted.</td></tr>
		<?php endif; ?>
		<?php foreach ($deletedUsers as $deletedUser): ?>
			<tr>
				<td><a href="/users/view/<?php echo (int) $deletedUser['User']['id']; ?>"><?php echo h($deletedUser['User']['deleted_name']); ?></a></td>
				<td><time datetime="<?php echo Util::toIso8601($deletedUser['User']['deleted']); ?>" data-format="datetime"><?php echo h($deletedUser['User']['deleted']); ?></time></td>
				<td><form method="post" action="/users/restore_account/<?php echo (int) $deletedUser['User']['id']; ?>" onsubmit="return confirm('Restore this account?');">
					<button type="submit" class="btn btn--small" id="restore-user-<?php echo (int) $deletedUser['User']['id']; ?>">Restore</button></form></td>
			</tr>
		<?php endforeach; ?>
	</table>
</div>
