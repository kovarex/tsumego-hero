<?php

/**
 * The friends of the player: the requests waiting for their answer, their friends, and the
 * requests they sent that wait for the other player. A friend is asked from their profile.
 *
 * @var \App\View\AppView $this
 * @var array $user The row of the player (Auth::getUser())
 * @var array{friends: list<array<string, mixed>>, requestsToMe: list<array<string, mixed>>, requestsFromMe: list<array<string, mixed>>} $lists What FriendshipTable::listsOf() returned
 */

use App\Model\Table\UserTable;
use App\Utility\Util;

//The form of an answer about a friend; it comes back to this page
$answer = fn(string $action, array $friend, string $label, string $class, string $confirm = ''): string
	=> '<form method="post" action="/friends/' . $action . '/' . (int) $friend['user_id'] . '"><input type="hidden" name="from" value="friends">'
		. '<button class="btn btn--small ' . $class . '" type="submit"'
		. ($confirm === '' ? '' : ' onclick="return confirm(' . h(json_encode($confirm)) . ');"')
		. '>' . $label . '</button></form>';
$date = fn(string $datetime): string => '<time datetime="' . Util::toIso8601($datetime) . '" data-format="date">' . h(substr($datetime, 0, 10)) . '</time>';
?>
<?php echo $this->element('user_header', ['user' => $user, 'activeTab' => 'friends']); ?>
<div class="friends">
	<?php if (!empty($lists['requestsToMe'])): ?>
	<section class="card card--green friends__section" id="friend-requests">
		<h2 class="friends__heading">Asking to be your friend</h2>
		<?php foreach ($lists['requestsToMe'] as $request): ?>
		<div class="friends__row">
			<span><?= UserTable::renderLink($request) ?> <span class="hint">since <?= $date($request['created']) ?></span></span>
			<span class="friends__actions">
				<?= $answer('accept', $request, 'Accept', 'btn--success') ?>
				<?= $answer('remove', $request, 'Decline', 'btn--neutral') ?>
			</span>
		</div>
		<?php endforeach; ?>
	</section>
	<?php endif; ?>

	<section class="card friends__section" id="friends-list">
		<h2 class="friends__heading">Friends</h2>
		<?php if (empty($lists['friends'])): ?>
		<p class="hint">None yet. To ask a player to be your friend, open their profile and press Add friend. Once they accept, the homepage shows you what your friends did lately, and shows them what you did.</p>
		<?php endif; ?>
		<?php foreach ($lists['friends'] as $friend): ?>
		<div class="friends__row">
			<span><?= UserTable::renderLink($friend) ?> <span class="hint">friends since <?= $date($friend['accepted'] ?? $friend['created']) ?></span></span>
			<span class="friends__actions">
				<a class="btn btn--small" href="/messages/<?= (int) $friend['user_id'] ?>">Message</a>
				<?= $answer('remove', $friend, 'Remove', 'btn--neutral', 'Remove ' . $friend['user_display_name'] . ' from your friends?') ?>
			</span>
		</div>
		<?php endforeach; ?>
	</section>

	<?php if (!empty($lists['requestsFromMe'])): ?>
	<section class="card friends__section" id="friend-requests-sent">
		<h2 class="friends__heading">Waiting for their answer</h2>
		<?php foreach ($lists['requestsFromMe'] as $request): ?>
		<div class="friends__row">
			<span><?= UserTable::renderLink($request) ?> <span class="hint">asked <?= $date($request['created']) ?></span></span>
			<span class="friends__actions"><?= $answer('remove', $request, 'Take back', 'btn--neutral') ?></span>
		</div>
		<?php endforeach; ?>
	</section>
	<?php endif; ?>
</div>
