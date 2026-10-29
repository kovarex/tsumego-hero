<?php

use App\Model\Entity\Tag;
use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\HtmlSanitizer;
use App\Utility\Util;

/**
 * @var View $this
 * @var array $allTags
 * @var Tag $tag
 * @var array|null $creator The user who created the tag, null when the account is gone
 * @var bool $canAddTag
 * @var bool $canDeleteTag
 */

?>

<div class="tags-container">
   <div class="tags-content">
				<h1><?php echo h($tag->name); ?></h1>
	<p><?php echo HtmlSanitizer::sanitize((string) $tag->description); ?></p>
	<?php
	$tagLink = trim((string) ($tag->link ?? ''));
	if ($tagLink !== '' && Util::isHttpUrl($tagLink))
		echo '<p><a href="' . h($tagLink) . '" target="_blank" rel="noopener noreferrer">' . h($tagLink) . '</a></p>';
	elseif ($tagLink !== '')
		echo '<p>' . h($tagLink) . '</p>';
	?>
	<?php if($tag->hint){ ?>
	<p><i>This tag gives a hint.</i></p>
	<?php } ?>
	<p>Created by <?php echo UserTable::renderLink($creator) ?>.</p>
	<?php if(Auth::isAdmin()){ ?>
		<a href="/tags/edit/<?php echo $tag->id; ?>" id="tag-edit">Edit</a>
		<?php if($canDeleteTag){ ?>
			|
			<form method="post" action="/tags/delete/<?php echo $tag->id; ?>" style="display:inline" onsubmit="return confirm('Delete this tag? It will be removed from every problem that has it.');">
				<input type="submit" value="Delete" id="tag-delete">
			</form>
		<?php } ?>
		| <a href="/tags">All tags</a>
	<?php } ?>
				</div>
        <div class="existing-tags-list">
				Other tags:
			<?php echo implode(', ', array_map(fn($tag) => '<a href="/tags/view/' . $tag['id'] . '">' . h($tag['name']) . '</a>', $allTags)); ?>
			<?php if ($canAddTag) { ?>
				<a class="add-tag-list-anchor" href="/tags/add">[Create new tag]</a>
			<?php } ?>
		</div>
  </div>
