<?php

use App\Utility\Auth;

/**
 * The player's conversations with other players. React draws the page and loads the
 * conversations itself (app/messages).
 *
 * @var View $this
 * @var array{id: int, displayName: string|null, avatarUrl: string|null, rating: float}|null $partner The player whose conversation is open
 */

$props = json_encode([
	'userId' => Auth::getUserID(),
	'isAdmin' => Auth::isAdmin(),
	'initialPartner' => $partner,
	'maxLength' => Message::MAX_LENGTH,
]);
?>
<div align="center">
	<?php echo $this->element('user_header', ['user' => Auth::getUser(), 'activeTab' => 'messages']); ?>
</div>

<div class="messages-page" data-messages-root data-props="<?php echo h($props); ?>">
	<div class="loading">Loading messages...</div>
</div>
