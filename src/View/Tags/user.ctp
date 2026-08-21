<?php

/**
 * @var View $this
 * @var array $viewedUser
 * @var ContributionRow[] $contributions
 * @var int $count
 * @var int $pageIndex
 * @var int $pageSize
 */

if (!function_exists('_tagLink')):

	function _tagLink(ContributionRow $c): string
	{
		$tag = h($c->tag);
		if ($c->tagId)
			return '<b><a href="/tags/view/' . $c->tagId . '"><i>' . $tag . '</i></a></b>';
		return '<b><i>' . $tag . '</i></b>';
	}

	function _tsumegoLink(ContributionRow $c): string
	{
		return '<b><a href="/tsumegos/play/' . $c->tsumegoId . '">' . h($c->tsumegoLabel) . '</a></b>';
	}

endif;

?>

<div align="center">
	<p class="profile-username"><?php echo h($viewedUser['name']); ?></p>
	<?php echo $this->element('user_subnav', ['userID' => $viewedUser['id'], 'activeTab' => 'contributions']); ?>
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
		<?php foreach ($contributions as $c): ?>
			<?php
				if ($c->status === 'accepted')
					$color = 'var(--color-green)';
				elseif ($c->status === 'pending')
					$color = 'var(--color-yellow)';
				else
					$color = 'var(--color-red)';
			?>
				<tr>
					<td>
					<?php if ($c->type === 'proposal'): ?>
						Proposal for <?php echo _tsumegoLink($c) ?> was submitted
					<?php elseif ($c->type === 'tag'): ?>
						Tag <?php echo _tagLink($c) ?> was added to <?php echo _tsumegoLink($c) ?>
					<?php else: ?>
						Tag <?php echo _tagLink($c) ?> was created
						<?php endif; ?>
					</td>
					<td><b style="color:<?php echo $color ?>"><?php echo h($c->status) ?></b></td>
					<td><time datetime="<?php echo Util::toIso8601($c->created) ?>" data-format="datetime"><?php echo h($c->created) ?></time></td>
				</tr>
		<?php endforeach; ?>
	</tbody>
	</table>
	<?php echo PaginationHelper::render($pageIndex, (int) ceil($count / $pageSize), 'page'); ?>
</div>