<?php

/**
 * @var View $this
 * @var array $allTags
 * @var array $tn
 * @var bool $canAddTag
 * @var bool $canDeleteTag
 */

?>

<div class="tags-container">
   <div class="tags-content">
				<h1><?php echo h($tn['Tag']['name']); ?></h1>
	<p><?php echo HtmlSanitizer::sanitize((string) ($tn['Tag']['description'] ?? '')); ?></p>
	<?php
	$tagLink = trim((string) ($tn['Tag']['link'] ?? ''));
	if ($tagLink !== '' && Util::isHttpUrl($tagLink))
		echo '<p><a href="' . h($tagLink) . '" target="_blank" rel="noopener noreferrer">' . h($tagLink) . '</a></p>';
	elseif ($tagLink !== '')
		echo '<p>' . h($tagLink) . '</p>';
	?>
	<?php if($tn['Tag']['hint'] == 1){ ?>
	<p><i>This tag gives a hint.</i></p>
	<?php } ?>
	<p>Created by <?php echo h($tn['Tag']['user']) ?>.</p>
	<?php if(Auth::isAdmin()){ ?>
		<a href="/tags/edit/<?php echo $tn['Tag']['id']; ?>" id="tag-edit">Edit</a>
		<?php if($canDeleteTag){ ?>
			|
			<form method="post" action="/tags/delete/<?php echo $tn['Tag']['id']; ?>" style="display:inline" onsubmit="return confirm('Delete this tag?');">
				<input type="hidden" name="data[Tag][delete]" value="<?php echo $tn['Tag']['id']; ?>">
				<input type="submit" value="Delete">
			</form>
		<?php } ?>
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
