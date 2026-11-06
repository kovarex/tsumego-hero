<?php

use App\Utility\Util;

/**
 * The place of a finished run in a challenge as an icon: a gold, silver or bronze medal for the
 * first three places, the plain number in a ring after them. The label says the place for screen
 * readers and in the tooltip.
 *
 * @var \App\View\AppView $this
 * @var int $place From 1
 */

$label = Util::ordinal($place) . ' place';
$medal = [1 => 'gold', 2 => 'silver', 3 => 'bronze'][$place] ?? null;
?>
<?php if ($medal !== null): ?>
<span class="challenge-place challenge-place--<?= $medal ?>" role="img" aria-label="<?= $label ?>" title="<?= $label ?>"><svg viewBox="0 0 24 28" width="24" height="28" aria-hidden="true" focusable="false"><path class="challenge-place__ribbon-left" d="M5 0h6l3 9H8z"/><path class="challenge-place__ribbon-right" d="M13 0h6l-3 9h-6z"/><circle class="challenge-place__rim" cx="12" cy="18" r="9.5"/><circle class="challenge-place__face" cx="12" cy="18" r="7.5"/><text class="challenge-place__number" x="12" y="22" text-anchor="middle"><?= $place ?></text></svg></span>
<?php else: ?>
<span class="challenge-place challenge-place--rank" role="img" aria-label="<?= $label ?>" title="<?= $label ?>"><?= $place ?></span>
<?php endif; ?>
