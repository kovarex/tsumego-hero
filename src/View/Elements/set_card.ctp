<?php
/**
 * Shared set card component used by the sets index, sandbox and "my sets" pages.
 *
 * @var View $this
 * @var array $set      Keys: id, name, amount, difficulty, solved, color. Optional: partition.
 * @var string|null $backgroundImage  Precomputed CSS background-image for the card.
 */

$percent = (int) ($set['solved'] ?? 0);
$problems = $set['amount'] == 1 ? 'problem' : 'problems';
$tilde = $set['amount'] == 1 ? '' : '~';
$partition = $set['partition'] ?? -1;
if ($partition == -1)
{
	$partitionText = '';
	$partitionLink = '';
}
else
{
	$partitionText = ' #' . ($partition + 1);
	$partitionLink = '/' . ($partition + 1);
}
$backgroundImage = $backgroundImage ?? 'linear-gradient(rgba(169, 169, 169, 0.30), rgba(0, 0, 0, 0.35))';
?>
<a href="/sets/view/<?= $set['id'] ?><?= $partitionLink ?>" class="set-card__link">
	<div class="set-card topic-box<?= $set['id'] ?>"
		style="background-color:<?= h($set['color']) ?>;background-image: <?= $backgroundImage ?>">
		<?php if ($percent >= 100): ?>
			<div class="set-card__completed">completed</div>
		<?php endif; ?>
		<div class="set-card__top"><?= h($set['name']) ?><?= $partitionText ?></div>
		<div class="set-card__middle-left"><?= $set['amount'] ?> <?= $problems ?></div>
		<?php if ($set['difficulty']): ?>
			<div class="set-card__middle-right"><?= $tilde ?><?= $set['difficulty'] ?></div>
		<?php endif; ?>
		<div class="set-card__bottom">
			<div class="progress progress--thin<?= $percent >= 100 ? ' progress--complete' : '' ?>">
				<div class="progress__label" data-target="<?= $percent ?>"><?= $percent ?>%</div>
				<div class="progress__track">
					<div class="progress__fill" style="width: <?= $percent ?>%"></div>
				</div>
			</div>
		</div>
	</div>
</a>
