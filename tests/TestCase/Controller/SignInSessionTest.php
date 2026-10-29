<?php

use App\Model\Table\UserTable;
use App\Utility\Auth;
use App\Utility\JwtAuth;
use App\Utility\Util;

//The session of a signed in player is the auth_token cookie alone: signing in sets it, every request
//reads the player from it, and signing out clears it. The forum at /forums signs the player in from
//the same cookie (webroot/forums/ext/tsumego/customsso/auth/provider/jwtauth.php).
class SignInSessionTest extends IntegrationTestCase
{
	private const PASSWORD = 'test'; // the password of the users ContextPreparator makes

	private function signInWithPassword(string $name): void
	{
		$this->logout();
		JwtAuth::clearCache();
		$this->post('/users/login', ['username' => $name, 'password' => self::PASSWORD]);
	}

	//The user id in the cookie, checked the way the forum does it: the HMAC of the token with the
	//salt it reads out of config/core.php.
	private static function userIdTheForumReads(string $token): ?int
	{
		preg_match("/Configure::write\\('Security\\.salt',\\s*'([^']+)'/", (string) file_get_contents(ROOT . '/config/core.php'), $matches);
		[$header, $payload, $signature] = explode('.', $token);
		$expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $header . '.' . $payload, $matches[1], true)), '+/', '-_'), '=');
		if (!hash_equals($expected, $signature))
			return null;
		return json_decode(base64_decode(strtr($payload, '-_', '+/')), true)['sub'] ?? null;
	}

	public function testSigningInWithThePasswordSetsTheCookieOfTheSession(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'sessionPlayer']]);

		$this->signInWithPassword('sessionPlayer');

		$this->assertTrue(Auth::isLoggedIn());
		$this->assertSame($context->user['id'], Auth::getUserID());
		$token = $this->browserCookies()['auth_token'] ?? null;
		$this->assertNotNull($token, 'Signing in sets the cookie');
		$this->assertSame($context->user['id'], JwtAuth::validateToken($token));
	}

	//The forum checks the cookie with the salt of config/core.php and reads the player from the user
	//table by the id in it.
	public function testTheForumSignsInThePlayerOfTheCookie(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'forumPlayer', 'display_name' => 'Forum Player', 'email' => 'forum.player@example.com']]);

		$this->signInWithPassword('forumPlayer');

		$userID = self::userIdTheForumReads($this->browserCookies()['auth_token']);
		$this->assertSame($context->user['id'], $userID);
		$row = Util::query('SELECT id, display_name as name, email FROM user WHERE id = ' . (int) $userID)[0];
		$this->assertSame(['id' => $context->user['id'], 'name' => 'Forum Player', 'email' => 'forum.player@example.com'], $row);
	}

	//The next request finds the player in the cookie, as the same row as before: the columns as they
	//are typed, the dates as strings.
	public function testTheCookieSignsThePlayerInOnTheNextRequest(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'returningPlayer', 'rating' => 1234.5]]);
		$this->signInWithPassword('returningPlayer');
		$token = $this->browserCookies()['auth_token'];

		Auth::logout();
		JwtAuth::clearCache();
		$_COOKIE['auth_token'] = $token;
		Auth::init();

		$this->assertTrue(Auth::isLoggedIn());
		$user = Auth::getUser();
		$this->assertSame(UserTable::row($context->user['id']), $user);
		$this->assertSame($context->user['id'], $user['id']);
		$this->assertSame('returningPlayer', $user['name']);
		$this->assertSame(1234.5, $user['rating']);
		$this->assertIsInt($user['xp']);
		$this->assertFalse($user['sound']);
		$this->assertIsString($user['created']);
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $user['created']);
		$this->assertNull($user['deleted']);
	}

	public function testSigningOutClearsTheCookie(): void
	{
		new ContextPreparator(['user' => ['name' => 'leavingPlayer']]);
		$this->signInWithPassword('leavingPlayer');
		$this->assertNotEmpty($this->browserCookies()['auth_token'] ?? null);

		$this->get('/users/logout');

		$this->assertFalse(Auth::isLoggedIn());
		$this->assertArrayNotHasKey('auth_token', $_COOKIE);
		JwtAuth::clearCache();
		Auth::init();
		$this->assertFalse(Auth::isLoggedIn(), 'Nothing is left to sign the player in again');
	}

	//A deleted account is signed out by its cookie, and the cookie goes.
	public function testTheCookieOfADeletedAccountSignsNobodyIn(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'goneSoon']]);
		$this->signInWithPassword('goneSoon');
		$token = $this->browserCookies()['auth_token'];
		UserTable::deleteAccount($context->user['id']);

		Auth::logout();
		JwtAuth::clearCache();
		$_COOKIE['auth_token'] = $token;
		Auth::init();

		$this->assertFalse(Auth::isLoggedIn());
		$this->assertArrayNotHasKey('auth_token', $_COOKIE);
	}

	//The sign in goes back to the page the player came from, and to the home page when no page said so
	public function testTheLoginPageGoesBackToTheRefererOrHome(): void
	{
		new ContextPreparator(['user' => null]);
		$this->get('/users/login');
		$this->assertSame('/', $this->viewVariable('redirectUrl'));

		$this->configRequest(['headers' => ['Referer' => Router::url('/sets/5', true)]]);
		$this->get('/users/login');
		$this->assertSame('/sets/5', $this->viewVariable('redirectUrl'));
	}

	//The login redirect is signed, so a sign in only goes back where the site sent the player from.
	public function testSigningInGoesToTheSignedRedirectOnly(): void
	{
		new ContextPreparator(['user' => ['name' => 'redirectedPlayer']]);
		$this->logout();
		$this->get('/users/login');
		$signature = $this->viewVariable('redirectSignature');
		$redirect = $this->viewVariable('redirectUrl');

		$this->post('/users/login', ['username' => 'redirectedPlayer', 'password' => self::PASSWORD, 'redirect' => $redirect, 'redirect_signature' => $signature]);
		$this->assertStringEndsWith($redirect, $this->headerValue('Location'));

		$this->logout();
		$this->post('/users/login', ['username' => 'redirectedPlayer', 'password' => self::PASSWORD, 'redirect' => '/users/settings', 'redirect_signature' => $signature]);
		$this->assertStringEndsWith('/sets/', $this->headerValue('Location'), 'A redirect the signature is not of is not followed');
	}
}
