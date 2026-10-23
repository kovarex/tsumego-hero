<?php

/**
 * The classes of a player: the invitations waiting for their answer, the classes they are in, the
 * requests to join waiting for a teacher, the classes they teach and the form to start one.
 *
 * @var View $this
 * @var array $taught What Classroom::taughtBy() returned
 * @var array $memberships What Classroom::ofPupil() returned
 */

$invitations = $memberships[ClassroomMemberStatus::INVITED];
$requests = $memberships[ClassroomMemberStatus::REQUESTED];
$classes = $memberships[ClassroomMemberStatus::ACCEPTED];
?>
<div class="classes">
	<h1 class="classes__title">Classes</h1>

	<?php if (!empty($invitations)): ?>
	<section class="card card--green classes__section" id="classes-invitations">
		<h2 class="classes__heading">Invitations</h2>
		<?php foreach ($invitations as $invitation): ?>
		<div class="classes__row">
			<span><?= User::renderLink($invitation) ?> invites you to <a href="/classes/<?= (int) $invitation['classroom_id'] ?>"><?= h($invitation['name']) ?></a></span>
			<span class="classes__actions">
				<form method="post" action="/classes/accept-member/<?= (int) $invitation['membership_id'] ?>"><button class="btn btn--small btn--success" type="submit">Accept</button></form>
				<form method="post" action="/classes/remove-member/<?= (int) $invitation['membership_id'] ?>"><button class="btn btn--small btn--neutral" type="submit">Decline</button></form>
			</span>
		</div>
		<?php endforeach; ?>
	</section>
	<?php endif; ?>

	<section class="card classes__section" id="classes-joined">
		<h2 class="classes__heading">Classes you are in</h2>
		<?php if (empty($classes)): ?>
		<p class="hint">None yet. To join a class, open the link your teacher sends you and ask to join, or accept an invitation from your teacher.</p>
		<?php endif; ?>
		<?php foreach ($classes as $class): ?>
		<div class="classes__row">
			<span><a href="/classes/<?= (int) $class['classroom_id'] ?>"><?= h($class['name']) ?></a></span>
			<span class="hint">teacher <?= User::renderLink($class) ?></span>
		</div>
		<?php endforeach; ?>
		<?php foreach ($requests as $request): ?>
		<div class="classes__row">
			<span><a href="/classes/<?= (int) $request['classroom_id'] ?>"><?= h($request['name']) ?></a>
				<span class="hint">waiting for <?= User::renderLink($request) ?> to accept you</span></span>
			<span class="classes__actions">
				<form method="post" action="/classes/remove-member/<?= (int) $request['membership_id'] ?>"><button class="btn btn--small btn--neutral" type="submit">Take back</button></form>
			</span>
		</div>
		<?php endforeach; ?>
	</section>

	<section class="card card--purple classes__section" id="classes-taught">
		<h2 class="classes__heading">Classes you teach</h2>
		<?php foreach ($taught as $class): ?>
		<div class="classes__row">
			<span><a href="/classes/<?= $class['id'] ?>"><?= h($class['name']) ?></a></span>
			<span class="hint">
				<?= $class['pupil_count'] ?> pupil<?= $class['pupil_count'] == 1 ? '' : 's' ?>
				<?php if ($class['request_count'] > 0): ?>
				· <strong><?= $class['request_count'] ?> asking to join</strong>
				<?php endif; ?>
			</span>
		</div>
		<?php endforeach; ?>
		<form class="classes__form" method="post" action="/classes/create" id="classes-create">
			<div class="form-field">
				<label class="form-field__label" for="classroom-name">Start a class</label>
				<input class="form-field__control" type="text" id="classroom-name" name="name" maxlength="<?= Classroom::MAXIMUM_NAME_LENGTH ?>" placeholder="Lukas, Thursday" required>
			</div>
			<button class="btn" type="submit">Create class</button>
			<p class="hint">Then invite your pupils on the page of the class, or send them its link so they ask to join. Give them problems from one of your collections or of the site, and see who solved what.</p>
		</form>
	</section>
</div>
