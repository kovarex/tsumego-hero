<?php

use App\Utility\Trash;
use App\Utility\Util;

/**
 * Tells an admin that the problem or collection they opened is in the trash, when it leaves it
 * for good, and lets them take it back.
 *
 * @var View $this
 * @var string $what 'problem' or 'collection'
 * @var string $deleted When it was moved to the trash
 * @var string $restoreAction Where the restore form posts
 */
$purgeDate = Trash::purgeDate($deleted);
?>
<div class="alert alert-warning trash-notice" role="status">
	This <?php echo h($what); ?> is in the trash, only admins can see it.
	It will be deleted for good on <time datetime="<?php echo Util::toIso8601($purgeDate); ?>" data-format="date"><?php echo h($purgeDate); ?></time>.
	<form method="post" action="<?php echo h($restoreAction); ?>" class="trash-notice__restore">
		<button type="submit" class="btn btn--small" id="trash-notice-restore">Restore</button>
	</form>
</div>
