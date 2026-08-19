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
	<p class="title">
		<br>
		Tags and proposals by <?php echo h($viewedUser['name']) ?>
		<br><br> 
	</p>
	<?php echo PaginationHelper::render($pageIndex, (int) ceil($count / $pageSize), 'page'); ?>
	<table class="highscoreTable" border="0">
		<tbody>
		<tr>
			<th align="left">Action</th>
			<th align="left">Status</th>
			<th align="left">Timestamp</th>
		</tr>
		<?php foreach ($contributions as $c): ?>
			<?php
				if ($c->status === 'accepted')
					$color = '#047804';
				elseif ($c->status === 'pending')
					$color = '#b08000';
				else
					$color = '#ce3a47';
			?>
				<tr>
					<td class="timeTableLeft versionColor" align="left">
					<?php if ($c->type === 'proposal'): ?>
						Proposal for <?php echo _tsumegoLink($c) ?> was submitted
					<?php elseif ($c->type === 'tag'): ?>
						Tag <?php echo _tagLink($c) ?> was added to <?php echo _tsumegoLink($c) ?>
					<?php else: ?>
						Tag <?php echo _tagLink($c) ?> was created
						<?php endif; ?>
					</td>
					<td class="timeTableMiddle versionColor" align="left"><b style="color:<?php echo $color ?>"><?php echo h($c->status) ?></b></td>
					<td class="timeTableRight versionColor" align="left"><time datetime="<?php echo Util::toIso8601($c->created) ?>" data-format="datetime"><?php echo h($c->created) ?></time></td>
				</tr>
		<?php endforeach; ?>
	</tbody>
	</table>
</div>