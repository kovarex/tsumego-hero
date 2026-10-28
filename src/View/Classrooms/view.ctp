<?php

/**
 * The page of a class. The teacher manages the class here; a pupil sees the assignments with
 * their own progress; an invited player answers the invitation; anyone else can ask to join.
 *
 * @var View $this
 * @var \App\Model\Entity\Classroom $classroom
 * @var array|null $teacher
 * @var bool $isTeacher
 * @var \App\Model\Entity\ClassroomMember|null $membership The membership of the viewer, in whatever state
 * @var array $assignments What ClassroomAssignmentTable::ofClassroom() returned, with the drafts for the teacher
 * @var array $members What ClassroomMemberTable::ofClassroom() returned, for the teacher
 * @var string $joinUrl The link of the class to send around, for the teacher
 */

use App\Model\Table\ClassroomAssignmentTable;
use App\Model\Table\ClassroomMemberStatusTable;
use App\Utility\Auth;

$classroomId = $classroom->id;
$membershipStatus = $membership?->classroom_member_status_id;
$isPupil = $membershipStatus === ClassroomMemberStatusTable::ACCEPTED;
?>
<div class="classes">
	<div class="classes__header">
		<p class="classes__crumb"><a href="/classes">Classes</a></p>
		<h1 class="classes__title"><?= h($classroom->name) ?></h1>
		<p class="hint">teacher <?= User::renderLink($teacher) ?></p>
	</div>

	<?php if (!$isTeacher && !$isPupil): ?>
	<section class="card card--green classes__section classes__join" id="classes-join">
		<?php if (!Auth::isLoggedIn()): ?>
		<p><a href="/users/login">Sign in</a> to ask to join this class.</p>
		<?php elseif ($membershipStatus === ClassroomMemberStatusTable::INVITED): ?>
		<p><?= User::renderLink($teacher) ?> invites you to this class. The teacher will see which of the problems they give to the class you solve.</p>
		<span class="classes__actions">
			<form method="post" action="/classes/accept-member/<?= $membership->id ?>"><button class="btn btn--success" type="submit">Accept</button></form>
			<form method="post" action="/classes/remove-member/<?= $membership->id ?>"><button class="btn btn--neutral" type="submit">Decline</button></form>
		</span>
		<?php elseif ($membershipStatus === ClassroomMemberStatusTable::REQUESTED): ?>
		<p>You asked to join. You are in once the teacher accepts.</p>
		<form method="post" action="/classes/remove-member/<?= $membership->id ?>"><button class="btn btn--neutral" type="submit">Take back</button></form>
		<?php else: ?>
		<p>Ask to join and you are in once the teacher accepts. The teacher will see which of the problems they give to the class you solve.</p>
		<form method="post" action="/classes/join/<?= $classroomId ?>"><button class="btn" type="submit">Ask to join</button></form>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if ($isTeacher || $isPupil): ?>
	<section class="classes__section" id="classes-assignments">
		<div class="classes__section-header">
			<h2 class="classes__heading">Assignments</h2>
			<?php if ($isTeacher): ?>
			<form method="post" action="/classes/create-assignment/<?= $classroomId ?>">
				<button class="btn" type="submit" id="new-assignment">New assignment</button>
			</form>
			<?php endif; ?>
		</div>
		<?php if (empty($assignments)): ?>
		<p class="hint"><?= $isTeacher ? 'Nothing assigned yet.' : 'Nothing to solve yet.' ?></p>
		<?php endif; ?>
		<?php foreach ($assignments as $assignment): ?>
		<?= $this->element('classroom_assignment', ['assignment' => $assignment, 'isTeacher' => $isTeacher]) ?>
		<?php endforeach; ?>
		<p class="hint classes__legend">
			<span class="classes__cell classes__cell--solved">✓</span> solved without a misplay
			<span class="classes__cell classes__cell--solved classes__cell--with-misplays">✓<sup class="classes__misplays">2</sup></span> solved after 2 misplays
			<span class="classes__cell classes__cell--late">✓</span> solved after the due day
			<span class="classes__cell classes__cell--earlier">✓</span> solved only before it was assigned
			<span class="classes__cell classes__cell--tried">2</span> misplays, not solved yet
		</p>
	</section>
	<?php endif; ?>

	<?php if ($isTeacher): ?>
	<section class="card card--purple classes__section" id="classes-pupils">
		<h2 class="classes__heading">Pupils</h2>
		<?php foreach ($members[ClassroomMemberStatusTable::REQUESTED] as $member): ?>
		<div class="classes__row classes__row--request">
			<span><?= User::renderLink($member) ?> <span class="hint">asks to join</span></span>
			<span class="classes__actions">
				<form method="post" action="/classes/accept-member/<?= (int) $member['membership_id'] ?>"><button class="btn btn--small btn--success" type="submit">Accept</button></form>
				<form method="post" action="/classes/remove-member/<?= (int) $member['membership_id'] ?>"><button class="btn btn--small btn--neutral" type="submit">Decline</button></form>
			</span>
		</div>
		<?php endforeach; ?>
		<?php if (empty($members[ClassroomMemberStatusTable::ACCEPTED])): ?>
		<p class="hint">No pupils yet.</p>
		<?php endif; ?>
		<?php foreach ($members[ClassroomMemberStatusTable::ACCEPTED] as $member): ?>
		<div class="classes__row">
			<span><?= User::renderLink($member) ?></span>
			<span class="classes__actions">
				<form method="post" action="/classes/remove-member/<?= (int) $member['membership_id'] ?>" onsubmit="return confirm('Remove this pupil from the class?');"><button class="btn btn--small btn--neutral" type="submit">Remove</button></form>
			</span>
		</div>
		<?php endforeach; ?>
		<?php foreach ($members[ClassroomMemberStatusTable::INVITED] as $member): ?>
		<div class="classes__row">
			<span><?= User::renderLink($member) ?> <span class="hint">invited, hasn't accepted yet</span></span>
			<span class="classes__actions">
				<form method="post" action="/classes/remove-member/<?= (int) $member['membership_id'] ?>"><button class="btn btn--small btn--neutral" type="submit">Take back</button></form>
			</span>
		</div>
		<?php endforeach; ?>

		<form class="classes__form" method="post" action="/classes/invite/<?= $classroomId ?>">
			<div class="form-field">
				<label class="form-field__label" for="invite-name">Invite a player</label>
				<input class="form-field__control" type="text" id="invite-name" name="name" placeholder="Name of the player" required>
			</div>
			<button class="btn" type="submit">Invite</button>
		</form>
		<div class="form-field classes__join-link">
			<label class="form-field__label" for="join-link">Or send this link, and accept the players who ask to join</label>
			<input class="form-field__control" type="text" id="join-link" value="<?= h($joinUrl) ?>" readonly onclick="this.select();">
		</div>
	</section>

	<section class="classes__section classes__danger">
		<form method="post" action="/classes/delete/<?= $classroomId ?>" onsubmit="return confirm('Delete the class with its assignments? The progress of the pupils stays theirs.');">
			<button class="btn btn--danger" type="submit">Delete the class</button>
		</form>
	</section>
	<?php endif; ?>

	<?php if ($isPupil): ?>
	<section class="classes__section classes__danger">
		<form method="post" action="/classes/remove-member/<?= $membership->id ?>" onsubmit="return confirm('Leave the class?');">
			<button class="btn btn--neutral" type="submit">Leave the class</button>
		</form>
	</section>
	<?php endif; ?>
</div>
