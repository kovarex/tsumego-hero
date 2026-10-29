<?php

use App\Utility\Auth;

/**
 * @var \App\View\AppView $this
 * @var int $newActivityCount
 * @var int $sgfProposalCount
 * @var int $tagProposalCount
 * @var int $newTagCount
 * @var int $openIssueCount
 * @var int $newSgfChangeCount
 * @var int $deletedAccountCount
 */

//A link of the admin page, with a red count when something waits there
$renderLink = function (string $url, string $label, int $count = 0, string $countMeaning = ''): void
{
	echo '<li><a class="admin-area__link" href="' . $url . '">' . $label;
	if ($count > 0)
		echo '<span class="admin-area__badge" title="' . h($countMeaning) . '">' . $count . '</span>';
	echo '</a></li>';
};
?>
<div class="admin-area">
	<h1 class="title4">Admin</h1>

	<ul class="admin-area__links" id="admin-links">
		<?php
		$renderLink('/admin/activity', 'Admin activity', $newActivityCount, 'Done by other admins since you last looked');
		$renderLink('/admin/sgf-proposals', 'SGF proposals', $sgfProposalCount, 'Waiting for a decision');
		$renderLink('/admin/tag-proposals', 'Tag proposals', $tagProposalCount, 'Existing tags proposed for a problem, waiting for a decision');
		$renderLink('/admin/new-tags', 'New tags', $newTagCount, 'New tags proposed, waiting for a decision');
		$renderLink('/tsumego-issues', 'Issues', $openIssueCount, 'Open issues');
		$renderLink('/tags', 'Tag overview');
		$renderLink('/admin/sgf-changes', 'SGF changes', $newSgfChangeCount, 'Changed or accepted by others since you last looked');
		?>
	</ul>

	<ul class="admin-area__links admin-area__links--tools" id="admin-tools">
		<?php
		$renderLink('/schedule', 'Publish schedule');
		$renderLink('/trash', 'Trash');
		$renderLink('/editor', 'Editor');
		$renderLink('/users/userstats', 'User activities');
		$renderLink('/tsumegos/mergeForm', 'Merge duplicates');
		echo '<li><a class="admin-area__link" href="/admin/deleted-accounts">Deleted accounts'
			. ($deletedAccountCount > 0 ? '<span class="admin-area__count">' . $deletedAccountCount . '</span>' : '') . '</a></li>';
		if (Auth::isOwner())
			$renderLink('/roles', 'Admins and owners');
		?>
	</ul>
</div>
