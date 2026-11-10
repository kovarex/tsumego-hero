<?php

use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\JwtAuth;

//The whole switch of a Google account to a username and password in the browser: from the settings
//through the form, then signing in again with the username and with the address, as the same player.
#[RetryOnBrowserFailure]
class GoogleToPasswordBrowserTest extends IntegrationTestCase
{
	private const PASSWORD = 'brnoHorse42';

	public function testAGoogleAccountSwitchesAndSignsInWithItsUsernameAndItsAddress(): void
	{
		new ContextPreparator(['user' => null]);
		Auth::logout();
		$table = $this->fetchTable('User');
		$player = $table->saveOrFail($table->newEntity([
			'name' => null,
			'display_name' => 'Jana Marie Novakova',
			'email' => 'jana.novakova@gmail.com',
			'password_hash' => UserTable::NO_PASSWORD,
			'external_id' => '104455667788990011223',
			'level' => 7,
			'solved' => 37], ['validate' => false]));
		$browser = Browser::instance();

		// signed in with Google just now: the session of a Google sign-in
		$browser->getAnonymous('empty.php');
		$browser->setCookie('auth_token', JwtAuth::createToken($player->id, UserTable::NO_PASSWORD));
		$browser->getAnonymous('users/settings');
		$browser->clickId('switch-to-password-link');
		$browser->waitUntilJs('document.querySelector("#UserSwitchToPasswordForm") !== null');
		$this->assertSame('jana.novakova', $browser->findById('SwitchUsername')->getAttribute('value'), 'a username made of the address is offered');
		$this->assertSame('jana.novakova@gmail.com', $browser->findById('SwitchEmail')->getAttribute('value'));

		$browser->findById('SwitchUsername')->clear()->sendKeys('janaPlays');
		$browser->findById('SwitchDisplayName')->clear()->sendKeys('Jana N');
		$browser->findById('SwitchPassword1')->sendKeys(self::PASSWORD);
		$browser->findById('SwitchPassword2')->sendKeys(self::PASSWORD);
		$browser->find('#UserSwitchToPasswordForm button[type="submit"]')->click();
		$browser->waitUntilJs('location.pathname === "/users/settings"');
		$this->assertStringContainsString('Your account now signs in with the username janaPlays', $browser->pageText());

		foreach (['janaPlays', 'jana.novakova@gmail.com'] as $signInWith)
		{
			$browser->getAnonymous('users/logout');
			$browser->getAnonymous('users/login');
			$browser->findById('UserName')->sendKeys($signInWith);
			$browser->findById('password')->sendKeys(self::PASSWORD);
			$browser->find('#UserLoginForm button[type="submit"]')->click();
			$browser->waitUntilJs('document.querySelector("#account-bar-user a") !== null');

			$name = $browser->find('#account-bar-user a');
			$this->assertSame('Jana N', trim($name->getDomProperty('textContent')), 'signed in with ' . $signInWith . ', under the new display name');
			$this->assertStringEndsWith('/users/view/' . $player->id, $name->getAttribute('href'), 'as the same player');
			$this->assertSame('Level 7', trim($browser->find('#account-bar-xp')->getDomProperty('textContent')), 'with the progress it had');
		}
		$this->assertSame(37, UserTable::row($player->id)['solved']);
	}
}
