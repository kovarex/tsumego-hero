<?php

/**
 * @var \App\View\AppView $this
 * @var array $setsNew
 * @var array $profileUser
 * @var bool $isOwn
 */
?>
<div align="center" class="display1">
	<?= $this->element('user_header', ['user' => $profileUser, 'activeTab' => 'sets']) ?>

	<?php if ($isOwn): ?>
		<div class="set-index-intro">
			<p>Your personal problem sets. Create sets to organize your favorites.</p>
			<a class="btn" href="/sets/create">Create Set</a>
		</div>
	<?php endif; ?>

	<div align="center" class="set-index display1">
	<?php if (empty($setsNew)): ?>
		<p class="set-index-empty"><?= $isOwn ? 'No sets yet. Click the heart on any problem to start your first set, or <a href="/sets/create">create one here</a>.' : 'This user has no public sets.' ?></p>
	<?php else: ?>
		<?php foreach ($setsNew as $set): ?>
			<?= $this->element('set_card', ['set' => $set]) ?>
		<?php endforeach; ?>
	<?php endif; ?>
	</div>
</div>
