<?php

/**
 * The page of a challenge: what is played, the table of its players and a way in for the ones who
 * aren't in yet. How every player did with each problem shows to the players who finished their own
 * run, so nobody sees the problems before they play them.
 *
 * @var View $this
 * @var array $challenge What Challenge::withDetails() returned
 * @var array $standings What Challenge::standings() returned
 * @var bool $isParticipant
 * @var array|null $myRun The row of the viewer in $standings
 * @var bool $isFull The viewer isn't in the challenge and there is no room for them
 * @var string $joinUrl The link of the challenge to send around
 * @var bool $showResults Whether the viewer sees how everyone did with each problem
 * @var array $problems What Challenge::problems() returned, when $showResults
 * @var array $attemptsByRun What Challenge::attemptsByRun() returned, when $showResults
 * @var int|null $otherChallengeInProgressID Another challenge whose run the viewer is in the middle of
 */

use App\Utility\Auth;
use App\Utility\Util;

$challengeId = (int) $challenge['id'];
$finishedCount = count(array_filter($standings, fn(array $row) => $row['run'] === Challenge::RUN_FINISHED));
$cellSymbols = ['failed' => '✗', 'timeout' => '⏱', 'skipped' => '–', 'queued' => ''];
?>
<div class="challenges">
	<div class="challenges__header">
		<p class="challenges__crumb"><a href="/challenges">Challenges</a></p>
		<h1 class="challenges__title"><?= h(Challenge::title($challenge)) ?></h1>
		<p class="challenges__settings" id="challenge-settings"><?= h(Challenge::describe($challenge)) ?></p>
		<p class="hint">by <?= User::renderLink($challenge) ?>,
			<time datetime="<?= Util::toIso8601($challenge['created']) ?>" data-format="datetime"><?= h($challenge['created']) ?></time></p>
	</div>

	<section class="card card--green challenges__section challenges__action" id="challenge-action">
		<?php if (!Auth::isLoggedIn()): ?>
		<p><a href="/users/login">Sign in</a> to join the challenge.</p>
		<?php elseif ($isFull): ?>
		<p>The challenge is full, <?= Challenge::MAXIMUM_PARTICIPANTS ?> players are in it already.</p>
		<?php elseif ($myRun === null || $myRun['run'] === Challenge::RUN_NOT_STARTED): ?>
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
			<?php endif; ?>
		</span>
		<?php elseif ($myRun['run'] === Challenge::RUN_PLAYING): ?>
		<p>You are in the middle of your run, <?= (int) $myRun['played_count'] ?> of <?= (int) $challenge['problem_count'] ?> problems played.</p>
		<a class="btn btn--success" href="/timeMode/play" id="challenge-continue">Continue</a>
		<?php else: ?>
		<p id="challenge-my-result">You are <strong><?= Util::ordinal((int) $myRun['place']) ?></strong> of <?= $finishedCount ?>
			with <strong><?= Challenge::formatPoints($myRun['points']) ?></strong> points,
			<?= $myRun['solved_count'] ?> of <?= (int) $challenge['problem_count'] ?> problems solved.</p>
		<?php endif; ?>
	</section>

	<section class="challenges__section" id="challenge-standings">
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
						<?php foreach ($problems as $problem): ?>
						<th><?php if ($problem['set_connection_id'] !== null): ?><a href="/<?= $problem['set_connection_id'] ?>" title="<?= h($problem['title'] . ' (' . $problem['rank'] . ')') ?>"><?= $problem['order'] ?></a><?php else: ?><?= $problem['order'] ?><?php endif; ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($standings as $row): ?>
					<tr class="challenges__standing<?= Auth::isLoggedIn() && (int) $row['user_id'] === Auth::getUserID() ? ' challenges__standing--me' : '' ?>" data-user-id="<?= (int) $row['user_id'] ?>">
						<?php if ($row['run'] === Challenge::RUN_FINISHED): ?>
						<td class="challenges__place"><?= (int) $row['place'] ?></td>
						<td class="challenges__player"><?= User::renderLink($row) ?></td>
						<td class="challenges__points"><?= Challenge::formatPoints($row['points']) ?></td>
						<td><?= $row['solved_count'] ?>/<?= (int) $challenge['problem_count'] ?></td>
						<td><?= Challenge::formatSeconds($row['seconds']) ?></td>
						<?php else: ?>
						<td></td>
						<td class="challenges__player"><?= User::renderLink($row) ?></td>
						<td colspan="3" class="hint"><?= $row['run'] === Challenge::RUN_PLAYING
							? 'playing, ' . $row['played_count'] . '/' . (int) $challenge['problem_count']
							: 'not played yet' ?></td>
						<?php endif; ?>
						<?php foreach ($problems as $problem): ?>
						<?php
							$attempt = $row['session_id'] === null ? null : ($attemptsByRun[(int) $row['session_id']][$problem['order']] ?? null);
							$status = $attempt['status'] ?? 'queued';
						?>
						<td class="challenges__cell challenges__cell--<?= h($status) ?>"<?= $attempt !== null && $attempt['seconds'] !== null ? ' title="' . Challenge::formatSeconds($attempt['seconds']) . '"' : '' ?>>
							<?= $status === 'solved' ? Challenge::formatPoints((float) $attempt['points']) : $cellSymbols[$status] ?? '' ?>
						</td>
						<?php endforeach; ?>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ($showResults): ?>
		<p class="hint challenges__legend">The number of a problem opens it. A solved problem shows its points, the time is in its tooltip.
			<span class="challenges__cell challenges__cell--failed">✗</span> failed
			<span class="challenges__cell challenges__cell--timeout">⏱</span> out of time
			<span class="challenges__cell challenges__cell--skipped">–</span> skipped</p>
		<?php elseif ($finishedCount > 0): ?>
		<p class="hint">How everyone did with each problem shows once you finish your run.</p>
		<?php endif; ?>
	</section>

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
