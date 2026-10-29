<?php

use App\Model\Table\ChallengeTable;

/**
 * The page of a challenge: what is played, the table of its players and a way in for the ones who
 * aren't in yet. How every player did with each problem shows to the players who finished their own
 * run, so nobody sees the problems before they play them.
 *
 * @var View $this
 * @var array $challenge What ChallengeTable::withDetails() returned
 * @var array $standings What ChallengeTable::standings() returned
 * @var bool $isParticipant In the challenge and not declined
 * @var bool $hasDeclined The viewer declined the challenge
 * @var array|null $myRun The row of the viewer in $standings
 * @var bool $isFull The viewer isn't in the challenge and there is no room for them
 * @var string $joinUrl The link of the challenge to send around
 * @var bool $showResults Whether the viewer sees how everyone did with each problem
 * @var array<int, App\Utility\TsumegoButton> $problemButtons What ChallengeTable::problemButtons() returned, when $showResults
 * @var array $attemptsByRun What ChallengeTable::attemptsByRun() returned, when $showResults
 * @var int|null $otherChallengeInProgressID Another challenge whose run the viewer is in the middle of
 */

use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\Util;

$challengeId = (int) $challenge['id'];
$finishedCount = count(array_filter($standings, fn(array $row) => $row['run'] === ChallengeTable::RUN_FINISHED));
$cellSymbols = ['failed' => '✗', 'timeout' => '⏱', 'skipped' => '–', 'queued' => ''];
?>
<div class="challenges">
	<div class="challenges__header">
		<p class="challenges__crumb"><a href="/challenges">Challenges</a></p>
		<h1 class="challenges__title"><?= h(ChallengeTable::title($challenge)) ?></h1>
		<p class="challenges__settings" id="challenge-settings"><?= h(ChallengeTable::describe($challenge)) ?></p>
		<p class="hint">by <?= UserTable::renderLink($challenge) ?>,
			<time datetime="<?= Util::toIso8601($challenge['created']) ?>" data-format="datetime"><?= h($challenge['created']) ?></time></p>
	</div>

	<section class="card card--green challenges__section challenges__action" id="challenge-action">
		<?php if (!Auth::isLoggedIn()): ?>
		<p><a href="/users/login">Sign in</a> to join the challenge.</p>
		<?php elseif ($isFull): ?>
		<p>The challenge is full, <?= ChallengeTable::MAXIMUM_PARTICIPANTS ?> players are in it already.</p>
		<?php elseif ($myRun === null || $myRun['run'] === ChallengeTable::RUN_NOT_STARTED): ?>
		<?php if ($hasDeclined): ?>
		<p id="challenge-declined"><strong>You declined this challenge.</strong> You can still change your mind and play it.</p>
		<?php endif; ?>
		<p>Every problem has <?= (int) $challenge['seconds_per_problem'] ?> seconds, and the faster you solve it, the more points you get.
			The clock of a problem starts when it is shown, and you can play your run only once.</p>
		<?php if ($otherChallengeInProgressID): ?>
		<p class="hint" id="challenge-other-in-progress">You are in the middle of <a href="/challenges/<?= $otherChallengeInProgressID ?>">Challenge #<?= $otherChallengeInProgressID ?></a>,
			playing this one ends it, and the problems you didn't play there get no points.</p>
		<?php endif; ?>
		<span class="challenges__actions">
			<form method="post" action="/challenges/play/<?= $challengeId ?>"><button class="btn btn--success" type="submit" id="challenge-play">Play now</button></form>
			<?php if (!$isParticipant): ?>
			<form method="post" action="/challenges/join/<?= $challengeId ?>"><button class="btn btn--neutral" type="submit" id="challenge-join">Join, play later</button></form>
			<?php else: ?>
			<form method="post" action="/challenges/decline/<?= $challengeId ?>" onsubmit="return confirm('Decline the challenge?');"><button class="btn btn--neutral" type="submit" id="challenge-decline">Decline</button></form>
			<?php endif; ?>
		</span>
		<?php elseif ($myRun['run'] === ChallengeTable::RUN_PLAYING): ?>
		<p>You are in the middle of your run, <?= (int) $myRun['played_count'] ?> of <?= (int) $challenge['problem_count'] ?> problems played.</p>
		<a class="btn btn--success" href="/timeMode/play" id="challenge-continue">Continue</a>
		<?php else: ?>
		<p id="challenge-my-result">You are <strong><?= Util::ordinal((int) $myRun['place']) ?></strong> of <?= $finishedCount ?>
			with <strong><?= ChallengeTable::formatPoints($myRun['points']) ?></strong> points,
			<?= $myRun['solved_count'] ?> of <?= (int) $challenge['problem_count'] ?> problems solved.</p>
		<?php endif; ?>
	</section>

	<section class="challenges__section challenges__section--wide" id="challenge-standings">
		<h2 class="challenges__heading">Players</h2>
		<div class="challenges__table-wrapper">
			<table class="challenges__table">
				<thead>
					<tr>
						<th>#</th>
						<th class="challenges__player">Player</th>
						<th>Points</th>
						<th>Solved</th>
						<th>Time</th>
						<?php foreach ($problemButtons as $button): ?>
						<th class="challenges__problem"><ul class="setViewButtons"><?php $button->render(); ?></ul></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($standings as $row): ?>
					<tr class="challenges__standing<?= Auth::isLoggedIn() && (int) $row['user_id'] === Auth::getUserID() ? ' challenges__standing--me' : '' ?>" data-user-id="<?= (int) $row['user_id'] ?>">
						<?php if ($row['run'] === ChallengeTable::RUN_FINISHED): ?>
						<td class="challenges__place"><?= (int) $row['place'] ?></td>
						<td class="challenges__player"><?= UserTable::renderLink($row) ?></td>
						<td class="challenges__points"><?= ChallengeTable::formatPoints($row['points']) ?></td>
						<td><?= $row['solved_count'] ?>/<?= (int) $challenge['problem_count'] ?></td>
						<td><?= ChallengeTable::formatSeconds($row['seconds']) ?></td>
						<?php else: ?>
						<td></td>
						<td class="challenges__player"><?= UserTable::renderLink($row) ?></td>
						<td colspan="3" class="hint"><?= $row['run'] === ChallengeTable::RUN_PLAYING
							? 'playing, ' . $row['played_count'] . '/' . (int) $challenge['problem_count']
							: ($row['run'] === ChallengeTable::RUN_DECLINED ? 'declined' : 'not played yet') ?></td>
						<?php endif; ?>
						<?php foreach (array_keys($problemButtons) as $order): ?>
						<?php
							$attempt = $row['session_id'] === null ? null : ($attemptsByRun[(int) $row['session_id']][$order] ?? null);
							$status = $attempt['status'] ?? 'queued';
						?>
						<td class="challenges__cell challenges__cell--<?= h($status) ?>"<?= $attempt !== null && $attempt['seconds'] !== null ? ' title="' . ChallengeTable::formatSeconds($attempt['seconds']) . '"' : '' ?>>
							<?= $status === 'solved' ? ChallengeTable::formatPoints((float) $attempt['points']) : $cellSymbols[$status] ?? '' ?>
						</td>
						<?php endforeach; ?>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ($showResults): ?>
		<p class="hint challenges__legend">A problem button opens the problem and shows its board when you point at it, its colour is how you did with it. A solved problem shows its points, the time is in its tooltip.
			<span class="challenges__cell challenges__cell--failed">✗</span> failed
			<span class="challenges__cell challenges__cell--timeout">⏱</span> out of time
			<span class="challenges__cell challenges__cell--skipped">–</span> skipped</p>
		<?php elseif ($finishedCount > 0): ?>
		<p class="hint">How everyone did with each problem shows once you finish your run.</p>
		<?php endif; ?>
	</section>
	<?php if (!empty($problemButtons)): ?>
	<script>
		// The table scrolls sideways when it is wider than the white box, which would cut off the board
		// previews of its problem buttons, so a preview is put under its button on the screen instead.
		document.getElementById('challenge-standings').addEventListener('mouseover', function(event)
		{
			const link = event.target.closest('.challenges__problem a[data-tsumego-id]');
			if (!link)
				return;
			const box = link.querySelector('.tooltip-box');
			const rect = link.getBoundingClientRect();
			box.style.top = (rect.bottom + 4) + 'px';
			box.style.left = (rect.left - 1) + 'px';
		});
	</script>
	<?php endif; ?>

	<?php if ($isParticipant): ?>
	<section class="card card--purple challenges__section" id="challenge-invite">
		<h2 class="challenges__heading">Invite more players</h2>
		<form method="post" action="/challenges/invite/<?= $challengeId ?>">
			<div class="form-field">
				<label class="form-field__label" for="invite-players">Names of the players, separated by commas</label>
				<input class="form-field__control" type="text" id="invite-players" name="players" required>
			</div>
			<button class="btn" type="submit">Invite</button>
		</form>
		<div class="form-field challenges__join-link">
			<label class="form-field__label" for="join-link">Or send this link, anyone who opens it can join</label>
			<input class="form-field__control" type="text" id="join-link" value="<?= h($joinUrl) ?>" readonly onclick="this.select();">
		</div>
	</section>
	<?php endif; ?>
</div>
