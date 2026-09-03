<?php
/**
 * @var string|null $revision
 * @var array $entries
 */
$changelogProps = json_encode(['entries' => $entries], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<div class="changelog-page">
	<h1 class="changelog-page__title">What's new</h1>
	<div data-changelog-root data-props="<?= h($changelogProps) ?>"></div>
	<?php if (!empty($revision)): ?>
		<p class="changelog-page__revision">
			<a class="changelog-page__link" href="https://github.com/kovarex/tsumego-hero/commit/<?= h($revision) ?>"><?= h(substr($revision, 0, 7)) ?></a>
		</p>
	<?php endif; ?>
</div>
