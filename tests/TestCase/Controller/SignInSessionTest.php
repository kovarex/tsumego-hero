<?php

use App\Model\Table\UserTable;
use Cake\Routing\Router;
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

	//The forum's provider of the single sign-on, as phpBB loads it from the project, without the
	//services of phpBB its constructor takes: only its checks of the token are used.
	private static function forumProvider(string $forumRoot = ROOT . '/webroot/forums/'): object
	{
		require_once ROOT . '/webroot/forums/phpbb/auth/provider/provider_interface.php';
		require_once ROOT . '/webroot/forums/ext/tsumego/customsso/auth/provider/jwtauth.php';
		$provider = new ReflectionClass(\tsumego\customsso\auth\provider\jwtauth::class)->newInstanceWithoutConstructor();
		new ReflectionProperty($provider, 'phpbb_root_path')->setValue($provider, $forumRoot);
		return $provider;
	}

	//A protected method of the forum's provider
	private static function callForum(object $provider, string $method, mixed ...$arguments): mixed
	{
		return new ReflectionMethod($provider, $method)->invoke($provider, ...$arguments);
	}

	//A token of the site as anyone could sign it with the given key
	private static function tokenSignedWith(string $key, int $userID): string
	{
		return \Firebase\JWT\JWT::encode(['sub' => $userID, 'iat' => time(), 'exp' => time() + 3600, 'pwd' => JwtAuth::claimOf('hash')], $key, 'HS256');
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

	//The forum checks the cookie with the secret of config/jwt_secret.php and reads the player from the
	//user table by the id in it, while the password the token was issued with is still the account's.
	public function testTheForumSignsInThePlayerOfTheCookie(): void
	{
		$context = new ContextPreparator(['user' => ['name' => 'forumPlayer', 'display_name' => 'Forum Player', 'email' => 'forum.player@example.com']]);

		$this->signInWithPassword('forumPlayer');

		$forum = self::forumProvider();
		$claims = self::callForum($forum, 'validateJwtToken', $this->browserCookies()['auth_token']);
		$this->assertSame($context->user['id'], $claims['sub']);
		$row = Util::query('SELECT id, display_name as name, email, password_hash FROM user WHERE id = ' . (int) $claims['sub'])[0];
		$this->assertSame(self::callForum($forum, 'passwordClaim', $row['password_hash']), $claims['pwd']);
		unset($row['password_hash']);
		$this->assertSame(['id' => $context->user['id'], 'name' => 'Forum Player', 'email' => 'forum.player@example.com'], $row);
	}

	//The forum reads the secret of the site from config/jwt_secret.php, and takes no token signed
	//with the salt, which was in git.
	public function testTheForumChecksTheCookieWithTheSecretOfTheSite(): void
	{
		$forum = self::forumProvider();
		$this->assertTrue(hash_equals((string) \Cake\Core\Configure::read('Security.jwtSecret'), self::callForum($forum, 'getJwtSecret')), 'the forum reads the secret the site signs with');

		$this->assertSame(42, self::callForum($forum, 'validateJwtToken', self::tokenSignedWith((string) \Cake\Core\Configure::read('Security.jwtSecret'), 42))['sub']);
		$this->assertNull(self::callForum($forum, 'validateJwtToken', self::tokenSignedWith((string) \Cake\Core\Configure::read('Security.salt'), 42)));
	}

	//Without config/jwt_secret.php the forum signs nobody in and says why
	public function testTheForumFailsLoudlyWithoutTheSecret(): void
	{
		$forum = self::forumProvider(TMP . 'project-without-the-secret/webroot/forums/');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('config/jwt_secret.php');
		self::callForum($forum, 'validateJwtToken', self::tokenSignedWith('any-key-of-at-least-thirty-two-characters', 42));
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
