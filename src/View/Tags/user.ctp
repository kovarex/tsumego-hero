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
		return '<a href="/tags/view/' . $c->tagId . '"><i>' . $tag . '</i></a>';
	return '<i>' . $tag . '</i>';
}

function _tsumegoLink(ContributionRow $c): string
{
	return '<a href="/tsumegos/play/' . $c->tsumegoId . '">' . h($c->tsumegoLabel) . '</a>';
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
			<?php $color = $c->status === 'accepted' ? '#047804' : '#ce3a47'; ?>
				<tr>
					<td class="timeTableLeft versionColor" align="left">
						<?php if ($c->type === 'proposal'): ?>
							made a proposal for <?php echo _tsumegoLink($c) ?>
						<?php elseif ($c->type === 'tag'): ?>
							added the tag <?php echo _tagLink($c) ?> for <?php echo _tsumegoLink($c) ?>
						<?php else: ?>
							created a new tag: <?php echo _tagLink($c) ?>
						<?php endif; ?>
					</td>
					<td class="timeTableMiddle versionColor" align="left"><b style="color:<?php echo $color ?>"><?php echo h($c->status) ?></b></td>
					<td class="timeTableRight versionColor" align="left"><?php echo h($c->created) ?></td>
				</tr>
		<?php endforeach; ?>
	</tbody>
	</table>
</div>