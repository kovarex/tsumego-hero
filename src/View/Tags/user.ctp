<?php

use App\Utility\ContributionRow;
use App\Utility\Util;

/**
 * @var View $this
 * @var array $viewedUser
 * @var ContributionRow[] $contributions
 * @var int $count
 * @var int $pageIndex
 * @var int $pageSize
 */

if (!function_exists('_tagLink')):

	function _tagLink(ContributionRow $contribution): string
	{
		$tag = h($contribution->tag);
		if ($contribution->tagId)
			return '<b><a href="/tags/view/' . $contribution->tagId . '"><i>' . $tag . '</i></a></b>';
		return '<b><i>' . $tag . '</i></b>';
	}

	function _tsumegoLink(ContributionRow $contribution): string
	{
		return '<b><a href="/tsumegos/play/' . $contribution->tsumegoId . '">' . h($contribution->tsumegoLabel) . '</a></b>';
	}

endif;

?>

<div align="center">
	<?php echo $this->element('user_header', ['user' => $viewedUser, 'activeTab' => 'contributions']); ?>
	<?php echo PaginationHelper::render($pageIndex, (int) ceil($count / $pageSize), 'page'); ?>
	<table class="data-table">
		<thead>
		<tr>
			<th>Action</th>
			<th>Status</th>
			<th>Timestamp</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($contributions as $contribution): ?>
			<?php
				if ($contribution->status === 'accepted')
					$color = 'var(--color-green)';
				elseif ($contribution->status === 'pending')
					$color = 'var(--color-yellow)';
				else
					$color = 'var(--color-red)';
			?>
				<tr>
					<td>
					<?php if ($contribution->type === 'proposal'): ?>
						Proposal for <?php echo _tsumegoLink($contribution) ?> was submitted
					<?php elseif ($contribution->type === 'tag'): ?>
						Tag <?php echo _tagLink($contribution) ?> was added to <?php echo _tsumegoLink($contribution) ?>
					<?php else: ?>
						Tag <?php echo _tagLink($contribution) ?> was created
						<?php endif; ?>
					</td>
					<td><b style="color:<?php echo $color ?>"><?php echo h($contribution->status) ?></b></td>
					<td><time datetime="<?php echo Util::toIso8601($contribution->created) ?>" data-format="datetime"><?php echo h($contribution->created) ?></time></td>
				</tr>
		<?php endforeach; ?>
	</tbody>
	</table>
	<?php echo PaginationHelper::render($pageIndex, (int) ceil($count / $pageSize), 'page'); ?>
</div>