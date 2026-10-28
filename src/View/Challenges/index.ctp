<?php

use App\Model\Table\ChallengeTable;
use App\Model\Table\TimeModeSessionStatusTable;

/**
 * The challenges of a player and the form to start one.
 *
 * @var View $this
 * @var array $challenges What ChallengeTable::ofPlayer() returned
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
				<input class="form-field__control" type="text" id="challenge-players" name="players" value="<?= h((string) $opponent) ?>" placeholder="Names of the players, separated by commas">
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
			$canDecline = $challenge['my_run_status_id'] === null && $challenge['my_declined'] === null;
			if ($challenge['my_declined'] !== null && $challenge['my_run_status_id'] === null)
				$myRun = 'you declined';
			elseif ($challenge['my_run_status_id'] === null)
				$myRun = '<strong>not played yet</strong>';
			elseif ((int) $challenge['my_run_status_id'] === TimeModeSessionStatusTable::IN_PROGRESS)
				$myRun = '<strong>playing</strong>';
			else
				$myRun = 'your points ' . ChallengeTable::formatPoints((float) $challenge['my_points']);
		?>
		<div class="challenges__row">
			<span>
				<a href="/challenges/<?= (int) $challenge['id'] ?>"><?= h(ChallengeTable::title($challenge)) ?></a>
				<span class="hint"><?= h(ChallengeTable::describe($challenge)) ?></span>
			</span>
			<span class="hint">
				by <?= User::renderLink($challenge) ?>
				· <?= (int) $challenge['finished_count'] ?>/<?= (int) $challenge['participant_count'] ?> played
				· <?= $myRun ?>
				<?php if ($canDecline): ?>
				<form class="challenges__inline-form" method="post" action="/challenges/decline/<?= (int) $challenge['id'] ?>" onsubmit="return confirm('Decline the challenge?');"><button class="btn btn--small btn--neutral" type="submit">Decline</button></form>
				<?php endif; ?>
			</span>
		</div>
		<?php endforeach; ?>
	</section>
</div>
