<?php

use App\Model\Table\ChallengeTable;
use App\Model\Table\UserTable;
use App\Utility\Util;
use App\Utility\ViteManifest;

/**
 * The challenges of a player and the form to start one.
 *
 * @var \App\View\AppView $this
 * @var array $challenges What ChallengeTable::ofPlayer() returned
 * @var array $places What ChallengeTable::placesOfPlayer() returned
 * @var array<\App\Model\Entity\TimeModeRank> $ranks The rows of time_mode_rank, the easiest first
 * @var int|null $defaultRankID The rank of the player, to start the form with
 * @var string|null $opponent The name of the player to invite, from the button on a profile
 */

?>
<div class="challenges">
	<h1 class="challenges__title">Challenges</h1>
	<p class="hint challenges__intro">Challenge your friends: everyone gets the same problems with the same time for each, like in the time mode,
		and plays them whenever they like. The table of the challenge shows who has the most points.</p>

	<section class="card card--purple challenges__section" id="challenges-create">
		<h2 class="challenges__heading">New challenge</h2>
		<form method="post" action="/challenges/create">
			<div class="challenges__form-row">
				<div class="form-field">
					<label class="form-field__label" for="challenge-minimum-rank">Problems from</label>
					<select class="form-field__control" id="challenge-minimum-rank" name="minimum_rank">
						<?php foreach ($ranks as $rank): ?>
						<option value="<?= $rank->id ?>"<?= $rank->id === $defaultRankID ? ' selected' : '' ?>><?= h($rank->name) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-field">
					<label class="form-field__label" for="challenge-maximum-rank">to</label>
					<select class="form-field__control" id="challenge-maximum-rank" name="maximum_rank">
						<?php foreach ($ranks as $rank): ?>
						<option value="<?= $rank->id ?>"<?= $rank->id === $defaultRankID ? ' selected' : '' ?>><?= h($rank->name) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-field">
					<label class="form-field__label" for="challenge-seconds">Time for a problem</label>
					<select class="form-field__control" id="challenge-seconds" name="seconds_per_problem">
						<?php foreach (ChallengeTable::SECONDS_PER_PROBLEM_CHOICES as $seconds): ?>
						<option value="<?= $seconds ?>"<?= $seconds === ChallengeTable::DEFAULT_SECONDS_PER_PROBLEM ? ' selected' : '' ?>><?= ChallengeTable::formatTimeForAProblem($seconds) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-field">
					<label class="form-field__label" for="challenge-problem-count">Problems</label>
					<select class="form-field__control" id="challenge-problem-count" name="problem_count">
						<?php foreach (ChallengeTable::PROBLEM_COUNT_CHOICES as $count): ?>
						<option value="<?= $count ?>"<?= $count === ChallengeTable::DEFAULT_PROBLEM_COUNT ? ' selected' : '' ?>><?= $count ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="form-field">
				<label class="form-field__label" for="challenge-players">Invite players</label>
				<input class="form-field__control" type="text" id="challenge-players" name="players" value="<?= h((string) $opponent) ?>" placeholder="Names of the players, separated by commas" data-player-suggestions="/challenges/players">
			</div>
			<button class="btn" type="submit" id="challenge-create">Create challenge</button>
			<p class="hint">The invited players find the challenge in their challenges. You can also send them the link, anyone who opens it can join.</p>
		</form>
	</section>

	<section class="card challenges__section" id="challenges-list">
		<h2 class="challenges__heading">Your challenges</h2>
		<?php if (empty($challenges)): ?>
		<p class="hint">None yet.</p>
		<?php endif; ?>
		<?php foreach ($challenges as $challenge): ?>
		<?php
			$challengeId = (int) $challenge['id'];
			$canDecline = $challenge['my_run_status_id'] === null && $challenge['my_declined'] === null && $challenge['my_removed'] === null;
			// a challenge in progress has no places yet: its standings list who finished, without the places
			$isFinished = ChallengeTable::isFinished($challenge);
			$placing = $places[$challengeId] ?? null;
			$link = '<a href="/challenges/' . $challengeId . '">' . h(ChallengeTable::title($challenge)) . '</a>';
			$standings = $placing === null ? '' : $this->element('Challenges/standings_popup', ['challengeId' => $challengeId, 'standings' => $placing['standings'], 'showPlaces' => $isFinished]);
			$finishedOf = $standings . ($isFinished ? '' : ' of ' . (int) $challenge['participant_count']) . ' finished';
			// when the last player finished, or when the challenge started while it is in progress
			$when = $isFinished && $placing !== null ? max(array_column($placing['standings'], 'finished')) : $challenge['created'];
		?>
		<div class="challenges__row">
			<div class="challenges__row-main">
				<?php if ($placing !== null && $placing['myPlace'] !== null): ?>
				<?php $points = (float) $challenge['my_points']; ?>
				<?php $myPoints = '<strong>' . ChallengeTable::formatPoints($points) . ' points</strong> (' . ChallengeTable::percentOfMaximum($points, (int) $challenge['problem_count']) . '%)'; ?>
				<?php if ($isFinished): ?>
				<span class="challenges__my-place"><?= $this->element('Challenges/placement', ['place' => $placing['myPlace']]) ?> <?= Util::ordinal($placing['myPlace']) ?></span>
				of <?= $standings ?> with <?= $myPoints ?> in <?= $link ?>
				<?php else: ?>
				<?= $myPoints ?> in <?= $link ?> · <?= $finishedOf ?>
				<?php endif; ?>
				<?php else: ?>
				<?php if ($challenge['my_removed'] !== null): ?>
				Removed by the creator: <?= $link ?>
				<?php elseif ($challenge['my_declined'] !== null && $challenge['my_run_status_id'] === null): ?>
				Declined: <?= $link ?>
				<?php elseif ($challenge['my_run_status_id'] === null): ?>
				<strong>Not played yet:</strong> <?= $link ?>
				<?php else: ?>
				<strong>Playing:</strong> <?= $link ?>
				<?php endif; ?>
				<?php if ($placing !== null): ?>
				· <?= $finishedOf ?>
				<?php endif; ?>
				<?php endif; ?>
				<span class="hint challenges__when"><?= $isFinished ? '' : 'started ' ?><time datetime="<?= Util::toIso8601($when) ?>" data-format="relative"><?= date('Y-m-d H:i', (int) strtotime($when)) ?></time></span>
			</div>
			<span class="hint challenges__row-details">
				<?= h(ChallengeTable::describe($challenge)) ?>
				· by <?= UserTable::renderLink($challenge) ?>
				<?php if ($canDecline): ?>
				<form class="challenges__inline-form" method="post" action="/challenges/decline/<?= $challengeId ?>" onsubmit="return confirm('Decline the challenge?');"><button class="btn btn--small btn--neutral" type="submit">Decline</button></form>
				<?php endif; ?>
			</span>
		</div>
		<?php endforeach; ?>
	</section>
</div>
<?= ViteManifest::legacyScript('challenges') ?>
