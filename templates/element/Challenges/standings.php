<?php

use App\Model\Table\ChallengeTable;
use App\Model\Table\UserTable;
use App\Utility\Auth;

/**
 * The table of the players of a challenge, inside #challenge-standings: the page draws it, and so
 * does ChallengesController::standings() for the page that keeps it up to date while the challenge
 * is in progress. Places show once the challenge is finished.
 *
 * @var \App\View\AppView $this
 * @var array $challenge What ChallengeTable::withDetails() returned
 * @var array $standings What ChallengeTable::standings() returned
 * @var bool $isFinished Every player still in the challenge finished their run
 * @var list<int> $removableUserIDs The players the viewer, its creator, can remove now
 * @var bool $showResults Whether the viewer sees how everyone did with each problem
 * @var array<int, App\Utility\TsumegoButton> $problemButtons What ChallengeTable::problemButtons() returned, when $showResults
 * @var array $attemptsByRun What ChallengeTable::attemptsByRun() returned, when $showResults
 */

$challengeId = (int) $challenge['id'];
$finishedCount = count(array_filter($standings, fn(array $row) => $row['run'] === ChallengeTable::RUN_FINISHED));
$cellSymbols = ['failed' => '✗', 'timeout' => '⏱', 'skipped' => '–', 'queued' => ''];
?>
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
