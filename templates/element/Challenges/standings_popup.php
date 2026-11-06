<?php

use App\Model\Table\ChallengeTable;
use App\Model\Table\UserTable;

/**
 * The number of players who finished a challenge, which opens their standings right away: on hover, on
 * keyboard focus and on a tap (webroot/js/ChallengesPage.js). Screen readers get the standings as the
 * description of the button.
 *
 * @var \App\View\AppView $this
 * @var int $challengeId
 * @var list<array<string, mixed>> $standings The finished runs, the best first, see ChallengeTable::placesOfPlayer()
 * @var bool $showPlaces False while the challenge is in progress, which has no places yet
 */

$panelId = 'challenge-standings-' . $challengeId;
?>
<span class="challenge-standings-popup<?= $showPlaces ? '' : ' challenge-standings-popup--unranked' ?>"><button type="button" class="challenge-standings-popup__toggle" aria-expanded="false" aria-controls="<?= $panelId ?>" aria-describedby="<?= $panelId ?>"><?= count($standings) ?></button><span class="challenge-standings-popup__panel" id="<?= $panelId ?>">
		<span class="challenge-standings-popup__title"><?= count($standings) ?> finished</span>
		<?php foreach ($standings as $run): ?>
		<span class="challenge-standings-popup__run">
			<?php if ($showPlaces): ?>
			<span class="challenge-standings-popup__place"><?= $this->element('Challenges/placement', ['place' => (int) $run['place']]) ?></span>
			<?php endif; ?>
			<span class="challenge-standings-popup__player"><?= UserTable::renderLink($run) ?></span>
			<span class="challenge-standings-popup__points"><?= ChallengeTable::formatPoints((float) $run['points']) ?> points</span>
		</span>
		<?php endforeach; ?>
	</span></span><?php // no white space after, the phrase goes on right after the number
