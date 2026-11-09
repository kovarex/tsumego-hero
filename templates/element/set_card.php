<?php
use App\Model\Table\SetTable;

/**
 * Shared set card component used by the sets index, sandbox and "my sets" pages.
 *
 * A finished card gets a gold frame of dragon scales, and one finished with 100% accuracy the richer
 * frame with spikes and the pearl; webroot/js/setCardFrame.js draws both to the card's size.
 *
 * @var \App\View\AppView $this
 * @var array $set      Keys: id, name, amount, difficulty, solved, color. Optional: partition, perfect.
 * @var string|null $backgroundImage  Precomputed CSS background-image for the card.
 */

$percent = (int) ($set['solved'] ?? 0);
$problems = $set['amount'] == 1 ? 'problem' : 'problems';
$partition = $set['partition'] ?? -1;
if ($partition == -1)
	$partitionText = '';
else
	$partitionText = ' #' . ($partition + 1);
$backgroundImage = $backgroundImage ?? 'linear-gradient(rgba(169, 169, 169, 0.30), rgba(0, 0, 0, 0.35))';
$state = '';
if ($percent >= 100)
	$state = !empty($set['perfect']) ? ' set-card--finished set-card--perfect' : ' set-card--finished';
elseif ($percent == 0)
	$state = ' set-card--untouched';
?>
<a href="<?= SetTable::url($set['id'], $partition + 1) ?>" class="set-card__link">
	<div class="set-card topic-box<?= $set['id'] ?><?= $state ?>"
		style="background-color:<?= h($set['color']) ?>;background-image: <?= $backgroundImage ?>">
		<div class="set-card__head">
			<div class="set-card__title"><?= h($set['name']) ?><?= $partitionText ?></div>
			<?php if ($set['difficulty']): ?>
				<div class="set-card__rank"><?= $set['difficulty'] ?></div>
			<?php endif; ?>
		</div>
		<div class="set-card__foot">
			<span class="set-card__count" title="<?= $set['amount'] ?> <?= $problems ?>"><span class="set-card__stone" aria-hidden="true"></span><?= $set['amount'] ?><span class="visually-hidden"> <?= $problems ?></span></span>
			<span class="set-card__percent"><?= $percent ?>%</span>
		</div>
	</div>
</a>
