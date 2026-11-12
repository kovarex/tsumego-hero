<?php

use App\Model\Table\ChallengeTable;
use App\Model\Table\UserTable;
use App\Utility\Auth;
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
 * @var array $waiting The rows of $challenges waiting for the player (ChallengeTable::isWaitingFor())
 * @var bool $formOpen Whether the form to start a challenge shows unfolded
 */

?>
<div class="challenges">
	<?php // what waits for the player comes first, one row each with the way in ?>
	<?php if (!empty($waiting)): ?>
	<section class="card card--green challenges__section challenges__waiting" id="challenges-waiting" aria-labelledby="challenges-waiting-heading">
		<h2 class="challenges__heading" id="challenges-waiting-heading">Waiting for you (<?= count($waiting) ?>)</h2>
		<div class="challenges__waiting-list" role="list">
			<?php foreach ($waiting as $challenge): ?>
			<?php $challengeId = (int) $challenge['id']; ?>
			<div class="challenges__waiting-row" role="listitem" data-challenge-id="<?= $challengeId ?>">
				<div class="challenges__waiting-what">
					<a class="challenges__waiting-title" href="/challenges/<?= $challengeId ?>"><?= h(ChallengeTable::title($challenge)) ?></a>
					<span class="hint">by <?= (int) $challenge['created_by_user_id'] === Auth::getUserID() ? 'you' : UserTable::renderLink($challenge) ?>,
						<?= h(ChallengeTable::describe($challenge)) ?>, <?= $this->element('Challenges/list_time', ['time' => $challenge['created']]) ?></span>
				</div>
				<div class="challenges__waiting-actions">
					<a class="btn btn--success" href="/challenges/<?= $challengeId ?>" id="challenge-open-<?= $challengeId ?>">Open challenge</a>
					<form class="challenges__inline-form" method="post" action="/challenges/decline/<?= $challengeId ?>" onsubmit="return confirm('Decline the challenge?');"><button class="btn btn--small btn--neutral" type="submit">Decline</button></form>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<p class="hint challenges__intro">Challenge your friends: everyone gets the same problems with the same time for each, like in the time mode,
		and plays them whenever they like. The table of the challenge shows who has the most points.</p>

	<?php // folded behind its button; the summary is the button that opens and closes it ?>
	<details class="challenges__create" id="challenges-create"<?= $formOpen ? ' open' : '' ?>>
		<summary class="btn btn--ghost" id="challenges-new"><span class="challenges__create-open">New challenge</span><span class="challenges__create-close">Close</span></summary>
		<section class="card card--purple challenges__section">
			<h2 class="challenges__heading">New challenge</h2>
			<form method="post" action="/challenges/create">
				<div class="form-field">
					<label class="form-field__label" for="challenge-name">Name <span class="hint">(optional)</span></label>
					<input class="form-field__control" type="text" id="challenge-name" name="name" maxlength="<?= ChallengeTable::NAME_MAXIMUM_LENGTH ?>" placeholder="Like Tea house or At home, otherwise it gets a number" autocomplete="off">
				</div>
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
				<button class="btn btn--primary" type="submit" id="challenge-create">Create challenge</button>
				<p class="hint">The invited players find the challenge in their challenges. You can also send them the link, anyone who opens it can join.</p>
			</form>
		</section>
	</details>

	<section class="card challenges__section" id="challenges-list">
		<h2 class="challenges__heading">Your challenges</h2>
		<?php if (empty($challenges)): ?>
		<p class="hint">None yet.</p>
		<?php else: ?>
		<?php // a table on a computer; on a phone each row stacks into a few lines, with the words of the headers it hides (.challenges__list-label) ?>
		<table class="challenges__list">
			<thead>
				<tr>
					<th scope="col">Challenge</th>
					<th scope="col">Place</th>
					<th scope="col">Points</th>
					<th scope="col">Problems</th>
					<th scope="col">Players</th>
					<th scope="col">Finished</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($challenges as $challenge): ?>
				<?php
					$challengeId = (int) $challenge['id'];
					$canDecline = ChallengeTable::isWaitingFor($challenge);
					// a challenge in progress has no places yet: its standings list who finished, without the places
					$isFinished = ChallengeTable::isFinished($challenge);
					$placing = $places[$challengeId] ?? null;
					$standings = $placing === null ? '' : $this->element('Challenges/standings_popup', ['challengeId' => $challengeId, 'standings' => $placing['standings'], 'showPlaces' => $isFinished]);
					$finishedOf = $standings . ($isFinished ? '' : ' of ' . (int) $challenge['participant_count']) . ' finished';
					$myRunFinished = $placing !== null && $placing['myPlace'] !== null;
				?>
				<tr class="challenges__row">
					<td class="challenges__list-title"><a href="/challenges/<?= $challengeId ?>"><?= h(ChallengeTable::title($challenge)) ?></a></td>
					<td class="challenges__list-place">
						<?php if ($myRunFinished && $isFinished): ?>
						<span class="challenges__my-place"><?= $this->element('Challenges/placement', ['place' => $placing['myPlace']]) ?> <?= Util::ordinal($placing['myPlace']) ?></span> of <?= $standings ?>
						<?php else: ?>
						<?php if ($challenge['my_removed'] !== null): ?>
						<span class="challenges__status">Removed by the creator</span>
						<?php elseif ($challenge['my_declined'] !== null && $challenge['my_run_status_id'] === null): ?>
						<span class="challenges__status">Declined</span>
						<?php elseif ($challenge['my_run_status_id'] === null): ?>
						<strong class="challenges__status">Not played yet</strong>
						<?php elseif (!$myRunFinished): ?>
						<strong class="challenges__status">Playing</strong>
						<?php endif; ?>
						<?php if ($placing !== null): ?>
						<span class="hint challenges__finished-of"><?= $finishedOf ?></span>
						<?php endif; ?>
						<?php if ($canDecline): ?>
						<form class="challenges__inline-form" method="post" action="/challenges/decline/<?= $challengeId ?>" onsubmit="return confirm('Decline the challenge?');"><button class="btn btn--small btn--neutral" type="submit">Decline</button></form>
						<?php endif; ?>
						<?php endif; ?>
					</td>
					<?php if ($myRunFinished): ?>
					<?php $points = (float) $challenge['my_points']; ?>
					<td class="challenges__list-points"><strong><?= ChallengeTable::formatPoints($points) ?></strong><span class="challenges__list-label"> points</span> (<?= ChallengeTable::percentOfMaximum($points, (int) $challenge['problem_count']) ?>%)</td>
					<?php else: ?>
					<td class="challenges__list-points challenges__list-points--none"><span class="hint">–</span></td>
					<?php endif; ?>
					<td class="challenges__list-problems hint"><?= h(ChallengeTable::describe($challenge)) ?></td>
					<?php // who finished it, the best first ?>
					<td class="challenges__list-players"><?= $placing === null ? '' : $this->element('Challenges/player_avatars', ['runs' => $placing['standings']]) ?></td>
					<td class="challenges__list-when hint">
						<?php if ($isFinished && $placing !== null): ?>
						<?php // when the last player finished ?>
						<span class="challenges__list-label">finished </span><?= $this->element('Challenges/list_time', ['time' => max(array_column($placing['standings'], 'finished'))]) ?>
						<?php else: ?>
						in progress, started <?= $this->element('Challenges/list_time', ['time' => $challenge['created']]) ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</section>
</div>
<?= ViteManifest::legacyScript('challenges') ?>
