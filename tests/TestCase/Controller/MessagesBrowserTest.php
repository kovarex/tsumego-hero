<?php

use App\Model\Table\MessageTable;
use App\Utility\Auth;
use Facebook\WebDriver\WebDriverKeys;

//The messages page in the browser: writing to a player, reading what arrived and finding a player to write to.
/**
 * @retryAttempts 2
 * @retryIfException Facebook\WebDriver\Exception\WebDriverException
 */
class MessagesBrowserTest extends IntegrationTestCase
{
	private function prepare(): ContextPreparator
	{
		Auth::logout();
		return new ContextPreparator([
			'user' => ['name' => 'browserSender'],
			'other-users' => [['name' => 'browserRecipient', 'display_name' => 'Honinbo Shusaku']]]);
	}

	public function testWritingToAPlayerFromTheirProfile(): void
	{
		$context = $this->prepare();
		$recipient = $context->otherUsers[0];
		$browser = Browser::instance();

		$browser->get('users/view/' . $recipient['id']);
		$browser->clickCssSelect('.profile-header__message');

		$browser->waitUntilJs('location.pathname === "/messages/' . $recipient['id'] . '"');
		$browser->find('.messages-conversation__empty');
		$text = $browser->find('.messages-form__text');
		$text->sendKeys('Your ladder problem is wonderful, see https://tsumego.com/sets.');
		$text->sendKeys([WebDriverKeys::CONTROL, WebDriverKeys::ENTER]);

		$browser->waitUntilCssSelectorExistsWithText('.message--own .message__text', 'Your ladder problem is wonderful, see https://tsumego.com/sets.');
		$this->assertSame('https://tsumego.com/sets', $browser->find('.message--own .message__text a')->getAttribute('href'), 'The address became a link without the full stop');
		$this->assertSame('', $browser->find('.messages-form__text')->getAttribute('value'), 'The sent text stayed in the form');
		$browser->waitUntilCssSelectorExistsWithText('.messages-list__item--selected .messages-list__preview', 'You: Your ladder problem is wonderful, see https://tsumego.com/sets.');

		$stored = $this->fetchTable('Message')->find()->select(['sender_user_id', 'recipient_user_id', 'text'])->disableHydration()->toArray();
		$this->assertSame([[
			'sender_user_id' => $context->user['id'],
			'recipient_user_id' => $recipient['id'],
			'text' => 'Your ladder problem is wonderful, see https://tsumego.com/sets.']], $stored);
	}

	public function testReadingAMessageClearsTheUnreadCount(): void
	{
		$context = $this->prepare();
		$sender = $context->user;
		$recipient = $context->otherUsers[0];
		MessageTable::send($sender['id'], $recipient['id'], 'Shall we review the game?', new TestEmailer());
		$this->login('browserRecipient');
		$browser = Browser::instance();

		$browser->get('messages');
		$this->assertSame('1', $browser->driver->executeScript('return document.querySelector("#account-bar-messages .messages-unread-count").textContent;'));
		$browser->waitUntilCssSelectorExistsWithText('.messages-list__item--unread .messages-list__unread', '1');

		$browser->clickCssSelect('.messages-list__item[data-partner-id="' . $sender['id'] . '"]');
		$browser->waitUntilCssSelectorExistsWithText('.message .message__text', 'Shall we review the game?');
		$browser->waitUntilCssSelectorDoesntExist('.messages-list__item--unread');
		$browser->waitUntilJs('document.querySelector("#account-bar-messages .messages-unread-count").hidden');
		$this->assertSame('/messages/' . $sender['id'], $browser->driver->executeScript('return location.pathname;'));
		$this->assertSame(0, MessageTable::unreadCount($recipient['id']));

		// The badge stays away on the next page too
		$browser->get('messages');
		$this->assertFalse($browser->find('#account-bar-messages .messages-unread-count')->isDisplayed());
	}

	public function testFindingAPlayerToWriteTo(): void
	{
		$context = $this->prepare();
		$browser = Browser::instance();

		$browser->get('messages');
		$browser->find('.messages-list__empty');
		$browser->find('.messages-search__input')->sendKeys('shusa');
		$browser->waitUntilCssSelectorExistsWithText('.messages-search__result', 'Honinbo Shusaku');
		$browser->clickCssSelect('.messages-search__result');

		$browser->waitUntilJs('location.pathname === "/messages/' . $context->otherUsers[0]['id'] . '"');
		$this->assertStringStartsWith('Honinbo Shusaku', $browser->find('.messages-conversation__header a')->getText());
		$browser->find('.messages-conversation__empty');
	}

	public function testSettingsTurnTheEmailOff(): void
	{
		$context = $this->prepare();
		$browser = Browser::instance();

		$browser->get('users/settings');
		$browser->clickCssSelect('input[name="emailOnNewMessage"][value="0"]');

		$browser->waitUntil(fn() => (int) $context->reloadUser()['email_on_new_message'] === 0, 10, 'email turned off');
		$this->assertSame(0, (int) $context->user['email_on_new_message']);
	}
}
