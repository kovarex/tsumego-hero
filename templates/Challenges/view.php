<?php

use App\Model\Table\ChallengeTable;
/**
 * The page of a challenge: what is played, the table of its players and a way in for the ones who
 * aren't in yet. How every player did with each problem shows to the players who finished their own
 * run, so nobody sees the problems before they play them.
 *
 * @var \App\View\AppView $this
 * @var array $challenge What ChallengeTable::withDetails() returned
 * @var array $standings What ChallengeTable::standings() returned
 * @var array|null $rematch The rematch of the challenge, ChallengeTable::rematchOf()
 * @var bool $isInRematch Whether the player looking is in the rematch
 * @var bool $isParticipant In the challenge and not declined
 * @var bool $hasDeclined The viewer declined the challenge
 * @var array|null $myRun The row of the viewer in $standings
 * @var bool $isFull The viewer isn't in the challenge and there is no room for them
 * @var string $joinUrl The link of the challenge to send around
 * @var bool $showResults Whether the viewer sees how everyone did with each problem
 * @var array<int, App\Utility\TsumegoButton> $problemButtons What ChallengeTable::problemButtons() returned, when $showResults
 * @var array $attemptsByRun What ChallengeTable::attemptsByRun() returned, when $showResults
 * @var int|null $otherChallengeInProgressID Another challenge whose run the viewer is in the middle of
 * @var bool $wasRemoved The creator removed the viewer from the challenge
 * @var bool $isFinished Every player still in the challenge finished their run, so it has places
 * @var list<int> $removableUserIDs The players the viewer, its creator, can remove now
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
		<?php elseif ($wasRemoved): ?>
		<p id="challenge-removed">The creator removed you from this challenge.</p>
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
		<?php elseif (!$isFinished): ?>
		<p id="challenge-my-result">You finished with <strong><?= ChallengeTable::formatPoints($myRun['points']) ?></strong> points
			(<?= ChallengeTable::percentOfMaximum($myRun['points'], (int) $challenge['problem_count']) ?>%),
			<?= $myRun['solved_count'] ?> of <?= (int) $challenge['problem_count'] ?> problems solved.</p>
		<p class="hint" id="challenge-in-progress">The challenge is in progress, <?= $finishedCount ?> of <?= (int) $challenge['participant_count'] ?> players finished.
			The places show once everyone has finished.</p>
		<?php else: ?>
		<p id="challenge-my-result"><?= $this->element('Challenges/placement', ['place' => (int) $myRun['place']]) ?> You are <strong><?= Util::ordinal((int) $myRun['place']) ?></strong> of <?= $finishedCount ?>
			with <strong><?= ChallengeTable::formatPoints($myRun['points']) ?></strong> points,
			<?= $myRun['solved_count'] ?> of <?= (int) $challenge['problem_count'] ?> problems solved.</p>
		<?php if ($rematch !== null): ?>
		<p id="challenge-rematch-started">Rematch started by <?= UserTable::renderLink($rematch) ?>
			<a class="btn btn--success" href="/challenges/<?= (int) $rematch['id'] ?>" id="challenge-rematch-open"><?= $isInRematch ? 'Open' : 'Join' ?></a></p>
		<?php else: ?>
		<form method="post" action="/challenges/rematch/<?= $challengeId ?>">
			<button class="btn btn--success" type="submit" id="challenge-rematch" title="A new challenge with these settings and new problems, its players invited">Rematch</button>
		</form>
		<?php endif; ?>
		<?php endif; ?>
	</section>

	<section class="challenges__section challenges__section--wide" id="challenge-standings">
		<h2 class="challenges__heading">Players</h2>
		<div class="challenges__table-wrapper">
			<table class="challenges__table">
				<thead>
					<tr>
						<?php if ($isFinished): ?>
						<th>#</th>
						<?php endif; ?>
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
						<?php if ($isFinished): ?>
						<td class="challenges__place"><?= $row['place'] !== null ? $this->element('Challenges/placement', ['place' => (int) $row['place']]) : '' ?></td>
						<?php endif; ?>
						<td class="challenges__player"><?= UserTable::renderLink($row) ?>
							<?php if (in_array((int) $row['user_id'], $removableUserIDs, true)): ?>
							<form class="challenges__inline-form" method="post" action="/challenges/remove/<?= $challengeId ?>"
								onsubmit="return confirm('Remove the player from the challenge? It then finishes without them.');">
								<input type="hidden" name="user_id" value="<?= (int) $row['user_id'] ?>">
								<button class="btn btn--small btn--neutral challenges__remove" type="submit"
									title="<?= $row['run'] === ChallengeTable::RUN_NOT_STARTED ? 'They haven\'t started their run'
										: 'No new problem for ' . ChallengeTable::formatTimeForAProblem(ChallengeTable::REMOVABLE_AFTER_TIMES_FOR_A_PROBLEM * (int) $challenge['seconds_per_problem']) . ', twice the time for a problem' ?>">Remove</button>
							</form>
							<?php endif; ?>
						</td>
						<?php if ($row['run'] === ChallengeTable::RUN_FINISHED): ?>
						<td class="challenges__points"><?= ChallengeTable::formatPoints($row['points']) ?></td>
						<td><?= $row['solved_count'] ?>/<?= (int) $challenge['problem_count'] ?></td>
						<td><?= ChallengeTable::formatSeconds($row['seconds']) ?></td>
						<?php else: ?>
						<td colspan="3" class="hint challenges__run"><?= match ($row['run'])
						{
							ChallengeTable::RUN_PLAYING => 'playing, ' . $row['played_count'] . '/' . (int) $challenge['problem_count'],
							ChallengeTable::RUN_DECLINED => 'declined',
							ChallengeTable::RUN_REMOVED => 'removed by the creator',
							default => 'not played yet',
						} ?></td>
						<?php endif; ?>
						<?php foreach (array_keys($problemButtons) as $order): ?>
						<?php
							$attempt = $row['session_id'] === null ? null : ($attemptsByRun[(int) $row['session_id']][$order] ?? null);
							$status = $attempt['status'] ?? 'queued';
						?>
						<?php $failedLine = $attempt['failedLine'] ?? null; ?>
						<td class="challenges__cell challenges__cell--<?= h($status) ?><?= $failedLine ? ' challenges__cell--line' : '' ?>"<?= $attempt !== null && $attempt['seconds'] !== null ? ' title="' . ChallengeTable::formatSeconds($attempt['seconds']) . '"' : '' ?>
							<?= $failedLine ? ' tabindex="0" data-failed-line="' . h(json_encode($failedLine)) . '"' : '' ?>>
							<?= $status === 'solved' ? ChallengeTable::formatPoints((float) $attempt['points']) : $cellSymbols[$status] ?? '' ?>
							<?php if ($failedLine): ?><span class="tooltip-box challenges__line-board"></span><?php endif; ?>
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
			<span class="challenges__cell challenges__cell--skipped">–</span> skipped.
			Point at or tap a ✗ or ⏱ to see the moves played until then.</p>
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

		// A failed or timed out problem shows the moves played until then, numbered, on its board, when its
		// cell is pointed at, tapped or focused; the board is drawn the first time and put under the cell,
		// or above it when it doesn't fit below, and follows the cell when the page scrolls.
		let shownLineCell = null;
		function placeFailedLine(cell)
		{
			const box = cell.querySelector('.challenges__line-board');
			const rect = cell.getBoundingClientRect();
			const above = rect.top - 4 - box.offsetHeight;
			box.style.top = (rect.bottom + 4 + box.offsetHeight > window.innerHeight && above >= 0 ? above : rect.bottom + 4) + 'px';
			box.style.left = Math.max(4, Math.min(rect.left, window.innerWidth - box.offsetWidth - 4)) + 'px';
		}
		function showFailedLine(event)
		{
			const cell = event.target.closest('.challenges__cell--line');
			if (!cell)
				return;
			const box = cell.querySelector('.challenges__line-board');
			if (!box.querySelector('svg'))
			{
				const data = JSON.parse(cell.dataset.failedLine);
				const svg = createBoard(cell, data.black, data.white, data.xMax, data.yMax, data.boardSize, '', {moves: data.line, firstColor: data.firstColor});
				// half again the size of a button's preview, so the numbers can be read
				const width = parseFloat(svg.style.width), height = parseFloat(svg.style.height);
				svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
				svg.style.width = (width * 1.5) + 'px';
				svg.style.height = (height * 1.5) + 'px';
			}
			shownLineCell = cell;
			placeFailedLine(cell);
		}
		const standings = document.getElementById('challenge-standings');
		standings.addEventListener('mouseover', showFailedLine);
		standings.addEventListener('focusin', showFailedLine);
		standings.addEventListener('click', showFailedLine);
		// the page, or the table sideways
		document.addEventListener('scroll', () => shownLineCell && placeFailedLine(shownLineCell), {capture: true, passive: true});
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
