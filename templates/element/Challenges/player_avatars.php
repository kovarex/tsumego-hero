<?php

use App\Model\Table\UserTable;
use App\Utility\Rating;

/**
 * The avatars of the players who finished a challenge, overlapping, the best first: the first few, and
 * "+N" for the rest. The name and the rank of each are in its tooltip and its label.
 *
 * @var \App\View\AppView $this
 * @var list<array<string, mixed>> $runs The finished runs in their order, with the columns of ClassroomTable::USER_COLUMNS
 */

$most = 5;
// with one more, the "+1" would take the place of an avatar
$shown = count($runs) <= $most + 1 ? $runs : array_slice($runs, 0, $most);
$rest = count($runs) - count($shown);
?>
<span class="challenges__avatars"><?php foreach ($shown as $run): ?><?php
	$name = UserTable::displayName($run);
	$label = $name . ($run['user_rating'] === null ? '' : ' ' . Rating::getReadableRankFromRating((float) $run['user_rating']));
	$avatarUrl = UserTable::getAvatarUrl([
		'id' => (int) $run['user_id'],
		'display_name' => $run['user_display_name'],
		'avatar_setting_id' => $run['user_avatar_setting_id'],
		'uploaded_avatar_hash' => $run['user_uploaded_avatar_hash'],
		'external_id' => $run['user_external_id'],
		'picture' => $run['user_picture'],
		'email' => $run['user_email'],
	]);
?><a class="challenges__avatar" href="/users/view/<?= (int) $run['user_id'] ?>" title="<?= h($label) ?>" aria-label="<?= h($label) ?>"><?= UserTable::avatarImage($avatarUrl, '', true, UserTable::boardUrl(['display_name' => $run['user_display_name']])) ?></a><?php endforeach; ?><?php if ($rest > 0): ?><span class="challenges__avatars-rest" title="<?= $rest ?> more"><?= '+' . $rest ?></span><?php endif; ?></span><?php
