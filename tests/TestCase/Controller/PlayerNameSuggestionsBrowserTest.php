<?php

use App\Model\Table\FriendshipTable;
use App\Utility\Auth;
use Facebook\WebDriver\WebDriverKeys;

//The list of players under the invitation field of a challenge, used with the keyboard.
#[RetryOnBrowserFailure]
class PlayerNameSuggestionsBrowserTest extends IntegrationTestCase
{
	public function testTheKeyboardPicksNamesFriendsFirst(): void
	{
		Auth::logout();
		new ContextPreparator([
			'user' => ['name' => 'inviter'],
			'other-users' => [
				['name' => 'stranger', 'display_name' => 'Kogo Alpha'],
				['name' => 'friend', 'display_name' => 'Zed Kogo'],
				['name' => 'other', 'display_name' => 'Shusaku']],
			'time-mode-ranks' => ['5k'],
		]);
		FriendshipTable::ask((int) ContextPreparator::getUserIdFromName('friend'), (int) ContextPreparator::getUserIdFromName('inviter'));
		FriendshipTable::accept((int) ContextPreparator::getUserIdFromName('inviter'), (int) ContextPreparator::getUserIdFromName('friend'));
		$browser = Browser::instance();
		$browser->get('challenges');

		$field = $browser->find('#challenge-players');
		$this->assertSame('combobox', $field->getAttribute('role'));
		$field->sendKeys('kogo');
		$browser->waitUntilCssSelectorDisplayed('.player-suggestions [role="option"]');
		$this->assertSame('true', $field->getAttribute('aria-expanded'));
		$options = $browser->getElements('.player-suggestions [role="option"]');
		$this->assertCount(2, $options);
		$this->assertStringContainsString('Zed Kogo', $options[0]->getText());
		$this->assertStringContainsString('friend', $options[0]->getText());
		$this->assertStringContainsString('Kogo Alpha', $options[1]->getText());

		$field->sendKeys([WebDriverKeys::ARROW_DOWN, WebDriverKeys::ARROW_DOWN]);
		$this->assertSame($options[1]->getAttribute('id'), $field->getAttribute('aria-activedescendant'));
		$this->assertSame('true', $options[1]->getAttribute('aria-selected'));
		$field->sendKeys(WebDriverKeys::ENTER);
		$this->assertSame('Kogo Alpha, ', $field->getAttribute('value'));
		$this->assertSame('false', $field->getAttribute('aria-expanded'));

		// a picked name isn't offered again, and Escape closes the list
		$field->sendKeys('kogo');
		$browser->waitUntilCssSelectorDisplayed('.player-suggestions [role="option"]');
		$this->assertCount(1, $browser->getElements('.player-suggestions [role="option"]'));
		$field->sendKeys(WebDriverKeys::ESCAPE);
		$browser->waitUntilCssSelectorNotDisplayed('.player-suggestions');
		$this->assertSame('Kogo Alpha, kogo', $field->getAttribute('value'));
		$browser->assertNoJsErrors();
	}
}
