<?php

/**
 * @var View $this
 * @var array $setsNew
 * @var array|null $profileUser
 * @var bool $isOwn
 */

$pageTitle = $isOwn ? 'My Sets' : h($profileUser['name']) . "'s Sets";
?>
<div align="center" class="display1" style="padding:var(--space-5) var(--space-4)">
	<h4><?= $pageTitle ?></h4>

	<?php if ($isOwn): ?>
		<div style="text-align:left;margin-bottom:var(--space-4)">
			<p>Your personal problem sets. Create sets to organize your favorites.</p>
			<a class="btn" href="/sets/create">Create Set</a>
		</div>
	<?php endif; ?>

	<div align="center" class="set-index display1">
	<?php if (empty($setsNew)): ?>
		<br><br>
		<p><?= $isOwn ? 'No sets yet. Click the heart on any problem to start your first set, or <a href="/sets/create">create one here</a>.' : 'This user has no public sets.' ?></p>
	<?php else: ?>
		<?php foreach ($setsNew as $set): ?>
			<?= $this->element('set_card', ['set' => $set]) ?>
		<?php endforeach; ?>
	<?php endif; ?>
	</div>
</div>
