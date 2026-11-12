<?php

namespace App\Controller;

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Model\Entity\User;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\UserTable;
use App\Utility\AdminActivityLogger;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\GoogleTokenVerifier;
use App\Utility\GoogleTokenVerifierInterface;
use App\Utility\JwtAuth;
use App\Utility\Mailer;
use App\Utility\PwnedPasswordChecker;
use App\Utility\PwnedPasswordCheckerInterface;
use App\Utility\Util;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\Validation\Validation;
use Exception;

//The account itself: signing in (with a password or with Google) and out, registering, the
//forced rename, the password (reset and change), the e-mail address and the deletion. The
//addresses stay /users/..., see config/routes.php, and so do the templates (templates/Users).
class AccountController extends AppController
{
	protected ?PwnedPasswordCheckerInterface $passwordChecker = null;

	protected ?GoogleTokenVerifierInterface $tokenVerifier = null;

	//A password that login alone would never reject, used to spend the same time on a
	//name that has no account. It costs what PASSWORD_DEFAULT costs, and
	//LoginComponentTest fails when the two drift apart.
	private static string $DUMMY_PASSWORD_HASH = '$2y$12$BZ5TgNGDEJZktTLaEt5Tx.zJfbhdsQxv5/O7wfVeX0QpDwVSdTGg.';

	private static int $DELETION_LINK_VALID_HOURS = 24;

	//How long the link of a password reset works, in hours.
	private static int $PASSWORD_RESET_LINK_VALID_HOURS = 2;

	//How long to wait before another reset mail is sent for the same account.
	private static int $PASSWORD_RESET_MINUTES_BETWEEN_MAILS = 5;

	public function beforeRender(EventInterface $event): void
	{
		parent::beforeRender($event);
		$this->viewBuilder()->setTemplatePath('Users');
	}

	public static function _dummyPasswordHash(): string
	{
		return self::$DUMMY_PASSWORD_HASH;
	}

	protected function getPasswordChecker(): PwnedPasswordCheckerInterface
	{
		return $this->passwordChecker ??= PwnedPasswordChecker::create();
	}

	//Whether the password is one an attacker would try first, because it is already
	//in a public breach. Such a password is refused wherever a password is set.
	private function isKnownLeaked(string $password): bool
	{
		return $this->getPasswordChecker()->isBreached($password);
	}

	//The message to show for a password that can not be used, or null when it can.
	//Every place that sets a password asks this, so the rules are the same when an
	//account is created, when the password is changed and when it is reset. Signing in
	//does not ask it, so an account with a shorter password still gets in.
	private function passwordProblem(string $password, string $repeated): ?string
	{
		if (mb_strlen($password) < Constants::$MINIMUM_PASSWORD_LENGTH)
			return 'The password needs at least ' . Constants::$MINIMUM_PASSWORD_LENGTH . ' characters.';
		if ($password !== $repeated)
			return 'The new passwords do not match.';
		if ($this->isKnownLeaked($password))
			return 'That password appeared in a public data breach, choose another one.';
		return null;
	}

	//Get the Google token verifier (lazily creates real verifier if not set)
	protected function getTokenVerifier(): GoogleTokenVerifierInterface
	{
		return $this->tokenVerifier ??= GoogleTokenVerifier::create(
			'986748597524-05gdpjqrfop96k6haga9gvj1f61sji6v.apps.googleusercontent.com');
	}

	#[HttpGet]
	#[HttpPost]
	public function resetpassword(): ?Response
	{
		// a signed in player has a password to change in the settings instead
		if (Auth::isLoggedIn())
			return $this->redirect('/sets');
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Sign In');
		$this->set('sent', !empty($this->inputs()));
		if (empty($this->inputs()))
			return null;

		$userEmail = $this->input('email');

		// Only allow password reset for local users (not Google Sign-In users)
		$user = UserTable::findLocalUserByEmail($userEmail);
		if (!$user)
		{
			// An address that can not get a link is answered like one that has no account,
			// so the form says nothing about which addresses are taken. The sign-in page
			// points Google accounts to their button before they submit this form.
			return null;
		}

		// Another mail on top of the one just sent only fills the inbox. The answer is
		// the same either way, so nothing about the account is revealed by skipping it.
		$askedAt = $user->password_reset_token_created;
		if ($askedAt && $askedAt->getTimestamp() > strtotime('-' . self::$PASSWORD_RESET_MINUTES_BETWEEN_MAILS . ' minutes'))
			return null;

		$hours = self::$PASSWORD_RESET_LINK_VALID_HOURS;
		$randomString = Util::generateRandomString(20);

		$emailer = $this->accountMail($userEmail, 'Password reset for your Tsumego Hero account');
		if (!$this->linkMailWasSent($emailer, 'Click the following button to reset your password (valid for ' . $hours . ' hours). If you have not requested the password reset,
then ignore this email. ' . Router::url('/users/newpassword/' . $randomString, true)))
		{
			$this->set('sent', false);
			CookieFlash::set('The mail could not be sent. Please try again in a minute.', 'error');
			return null;
		}

		// The link works with the token alone, so the token is remembered only once the mail
		// carrying it has left. A mail server that stays quiet holds nothing back.
		$this->fetchTable('User')->updateAll([
			'password_reset_token' => $randomString,
			'password_reset_token_created' => date('Y-m-d H:i:s')], ['id' => $user->id]);
		return null;
	}

	//A mail to the account from the site; send it with linkMailWasSent() or sendNotification()
	private function accountMail(string $to, string $subject): Mailer
	{
		$emailer = $this->_getEmailer();
		$emailer->from(Mailer::SITE_SENDER);
		$emailer->to($to);
		$emailer->subject($subject);
		return $emailer;
	}

	//Send the mail that carries a link. The token in it is remembered only when this
	//returns true, so a mail server that does not answer leaves nothing behind and the
	//user can go through the flow again right away.
	private function linkMailWasSent(Mailer $emailer, string $body): bool
	{
		return $this->mailWasSent($emailer, $body, 'link mail');
	}

	//Send a mail that only tells the account about a change that already happened. It is
	//not part of the change, so a mail server that does not answer is logged instead of
	//turned into an error page.
	private function sendNotification(Mailer $emailer, string $body): void
	{
		$this->mailWasSent($emailer, $body, 'notification');
	}

	private function mailWasSent(Mailer $emailer, string $body, string $kind): bool
	{
		try
		{
			$emailer->send($body);
			return true;
		}
		catch (Exception $e)
		{
			Log::error('A Tsumego Hero ' . $kind . ' could not be sent: ' . $e->getMessage());
			return false;
		}
	}

	//The account a link of a mail was sent to, while the link still works: its token is
	//in $tokenColumn and the time it was sent in $createdColumn. Null for an unknown or
	//old link, and for a deleted account.
	/** @param array<string>|null $fields */
	private function findByLinkToken(string $tokenColumn, string $createdColumn, ?string $token, int $validHours, ?array $fields = null): ?User
	{
		if (!$token)
			return null;
		$query = $this->fetchTable('User')->find();
		if ($fields !== null)
			$query->select($fields);
		/** @var User|null */
		return $query->where([
			$tokenColumn => $token,
			'deleted IS' => null,
			$createdColumn . ' >' => date('Y-m-d H:i:s', strtotime('-' . $validHours . ' hours'))])->first();
	}

	//$checksum: The token of the reset link
	#[HttpGet]
	#[HttpPost]
	public function newpassword(?string $checksum = null): mixed
	{
		// a signed in player has a password to change in the settings instead
		if (Auth::isLoggedIn())
			return $this->redirect('/sets');
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Sign In');
		$this->set('valid', false);
		$this->set('done', false);
		$this->set('checksum', (string) $checksum);

		$user = $this->findByLinkToken('password_reset_token', 'password_reset_token_created', $checksum, self::$PASSWORD_RESET_LINK_VALID_HOURS);
		if (!$user)
			return null;

		$this->set('valid', true);
		$password = (string) ($this->input('password1') ?? '');
		$repeated = (string) ($this->input('password2') ?? '');
		if ($password === '')
			return null;

		$problem = $this->passwordProblem($password, $repeated);
		if ($problem)
		{
			CookieFlash::set($problem, 'error');
			return null;
		}

		$this->fetchTable('User')->updateAll([
			'password_reset_token' => null,
			'password_reset_token_created' => null,
			'password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $user->id]);
		$this->sendPasswordChangedMail($user->email);
		CookieFlash::set('Password changed', 'success');
		return $this->redirect('/users/login');
	}

	private function getUserFromNameOrEmail(): ?User
	{
		$input = $this->input('username');
		if (empty($input))
			return null;

		// A local account is found by its login name, a Google account has none
		if ($user = $this->fetchTable('User')->find()->where(['name' => $input])->first())
			return $user;

		// Only a local account can be signed in with a password, so an address that is
		// also used by a Google account must not shadow it.
		return UserTable::findLocalUserByEmail($input);
	}

	#[HttpGet]
	#[HttpPost]
	public function login()
	{
		$this->set('_page', 'login');
		$this->set('_title', 'Tsumego Hero - Sign In');
		// the form shown again after a failed sign-in has no page to go back to
		$this->set('redirectUrl', '');
		$this->set('redirectSignature', '');

		// On GET request, prepare redirect URL with HMAC signature (fully stateless)
		if (!$this->request->is('post'))
		{
			$referer = $this->referer('/', true);
			// Don't redirect back to login page itself
			$redirectUrl = ($referer && strpos($referer, '/users/login') === false) ? $referer : '/sets/';

			// Sign the redirect URL with HMAC to prevent tampering (no session needed)
			$signature = $this->signRedirectUrl($redirectUrl);

			// Pass to view for hidden field and Google data-state
			$this->set('redirectUrl', $redirectUrl);
			$this->set('redirectSignature', $signature);
			return null;
		}

		if (!$this->input('username'))
			return null;
		$user = $this->getUserFromNameOrEmail();
		if (!$user)
		{
			// A name without an account is answered the same way as a wrong password, and
			// the hash is computed either way, so neither the message nor the time it takes
			// tells whether the account exists.
			password_verify((string) ($this->input('password') ?? ''), self::$DUMMY_PASSWORD_HASH);

			// The address may belong to a Google account only, which can't log in here
			$googleUser = $this->fetchTable('User')->find()->where(['email' => $this->input('username'), 'deleted IS' => null])->first();
			if ($googleUser && UserTable::signsInOnlyWithGoogle($googleUser))
				CookieFlash::set('This account uses Google Sign-In. Please use the "Sign in with Google" button.', 'info');
			else
				CookieFlash::set('Wrong username or password.', 'error');
			return null;
		}

		// Google users should use "Sign in with Google" button
		if (UserTable::signsInOnlyWithGoogle($user))
		{
			CookieFlash::set('This account uses Google Sign-In. Please use the "Sign in with Google" button.', 'info');
			return null;
		}

		// The minimum length is not asked here: an account whose password is shorter
		// still has to sign in.
		if (!password_verify((string) ($this->input('password') ?? ''), $user->password_hash))
		{
			CookieFlash::set('Wrong username or password.', 'error');
			return null;
		}

		$this->signIn(UserTable::rowOf($user));

		// Verify and use redirect URL from POST data
		$redirect = $this->getVerifiedRedirectUrl(
			$this->input('redirect'),
			$this->input('redirect_signature')
		);
		return $this->redirect($redirect);
	}

	//Sign a redirect URL with HMAC to prevent tampering. This allows stateless redirect URL handling without sessions.
	private function signRedirectUrl(string $redirectUrl): string
	{
		return hash_hmac('sha256', $redirectUrl, Configure::read('Security.salt'));
	}

	//Verify redirect URL signature and return safe redirect URL.
	//Returns default redirect if signature is invalid or URL is not relative.
	private function getVerifiedRedirectUrl(?string $redirectUrl, ?string $signature): string
	{
		$defaultRedirect = '/sets/';

		if (!$redirectUrl || !$signature)
			return $defaultRedirect;

		// Verify HMAC signature
		$expectedSignature = $this->signRedirectUrl($redirectUrl);
		if (!hash_equals($expectedSignature, $signature))
			return $defaultRedirect;

		// Prevent open redirect attacks - only allow relative URLs
		if (!$this->isRelativeUrl($redirectUrl))
			return $defaultRedirect;

		return $redirectUrl;
	}

	//Check if URL is relative (starts with / but not //)
	private function isRelativeUrl(string $url): bool
	{
		return strlen($url) > 0 && $url[0] === '/' && (strlen($url) < 2 || $url[1] !== '/');
	}

	#[HttpGet]
	#[HttpPost]
	public function add()
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Sign Up');

		// Prepare signed redirect URL for Google Sign-In state (fully stateless)
		$redirectUrl = '/sets/';
		$signature = $this->signRedirectUrl($redirectUrl);
		$this->set('redirectUrl', $redirectUrl);
		$this->set('redirectSignature', $signature);

		if (empty($this->inputs()))
			return;

		$password = (string) ($this->input('password1') ?? '');
		$problem = $this->passwordProblem($password, (string) ($this->input('password2') ?? ''));
		if ($problem)
		{
			CookieFlash::set($problem, 'error');
			return;
		}

		$userTable = $this->fetchTable('User');
		if (UserTable::emailTaken((string) $this->input('email')))
		{
			CookieFlash::set('Email already exists', 'error');
			return;
		}

		$normalizedName = UserTable::normalizeDisplayName($this->input('name'));
		if (UserTable::containsCharactersTheForumCannotStore($normalizedName))
		{
			CookieFlash::set('The name must not contain emoji or other rare symbols, please choose another one.', 'error');
			return;
		}
		$uniqueDisplayName = UserTable::generateUniqueDisplayName($normalizedName);
		$user = $userTable->newEntity([
			'name' => $this->input('name'),
			'email' => $this->input('email'),
			'password_hash' => password_hash($password, PASSWORD_DEFAULT),
			'display_name' => $uniqueDisplayName,
			// A taken display name gets a numbered suffix, which is not the name the player chose,
			// so they are asked to pick their own before continuing (the same as the Google path).
			'needs_display_name_change' => $uniqueDisplayName !== $normalizedName]);
		try
		{
			if (!$userTable->save($user))
			{
				CookieFlash::set('Unable to create user with this name', 'error');
				return;
			}
		}
		catch (Exception $e)
		{
			CookieFlash::set('Unable to create user with this name', 'error');
			return;
		}

		CookieFlash::set(__('Registration successful.'), 'success');
		return $this->redirect(['controller' => 'sets', 'action' => 'index']);
	}

	//Render the forced display-name change form. Only users flagged with
	//needs_display_name_change can reach this (free renaming is disabled).
	#[HttpGet]
	public function changename()
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$user = Auth::getUser();

		if (empty($user['needs_display_name_change']))
		{
			CookieFlash::set(__('You can only change your display name when asked to.'), 'error');
			return $this->redirect('/');
		}

		$this->set('_page', 'changename');
		$this->set('_title', 'Choose your display name');
		$this->set('user', $user);
		return null;
	}

	//Update user's display name. Only allowed while the account is flagged for it
	//(needs_display_name_change = 1). Clears the flag on success.
	#[HttpPost]
	public function updatename()
	{
		$userId = Auth::getUserID();
		if (!$userId)
		{
			CookieFlash::set(__('You must be logged in.'), 'error');
			return $this->redirect('/');
		}

		$user = $this->fetchTable('User')->find()
			->select(['display_name', 'needs_display_name_change'])
			->where(['id' => $userId])
			->first();
		if (!$user)
		{
			CookieFlash::set(__('User not found.'), 'error');
			return $this->redirect('/');
		}

		// Free renaming is intentionally disabled: this only serves an account that is
		// flagged for a rename.
		if (!$user->needs_display_name_change)
		{
			CookieFlash::set(__('You can only change your display name when asked to.'), 'error');
			return $this->redirect('/');
		}

		$newDisplayName = UserTable::normalizeDisplayName($this->input('display_name') ?? '');
		if ($newDisplayName === $user->display_name)
		{
			CookieFlash::set(__('Please choose a different display name.'), 'error');
			return $this->redirect('/users/changename');
		}
		$problem = $this->displayNameProblem($newDisplayName, $userId);
		if ($problem)
		{
			CookieFlash::set($problem, 'error');
			return $this->redirect('/users/changename');
		}

		$this->rename($userId, $user->display_name, $newDisplayName);
		CookieFlash::set(__('Display name updated successfully.'), 'success');
		return $this->redirect('/sets/');
	}

	//The message to show for a display name the account can not take, or null when it can.
	//$displayName: The name as UserTable::normalizeDisplayName() made it
	private function displayNameProblem(string $displayName, int $userId): ?string
	{
		if (empty($displayName))
			return __('Display name cannot be empty.');

		if (UserTable::containsCharactersTheForumCannotStore($displayName))
			return __('The display name must not contain emoji or other rare symbols.');

		// Validate display name length in characters, not bytes: the column holds 50
		// characters, and a name in another alphabet takes several bytes per character.
		$displayNameLength = mb_strlen($displayName);
		if ($displayNameLength < 3 || $displayNameLength > 50)
			return __('Display name must be between 3 and 50 characters.');

		// Check uniqueness - another user might have this display_name
		if ($this->fetchTable('User')->exists(['display_name' => $displayName, 'id !=' => $userId]))
			return __('This display name is already taken.');
		return null;
	}

	//Give the signed in account the display name (the forum too), and stop asking it for one.
	private function rename(int $userId, string $oldDisplayName, string $newDisplayName): void
	{
		UserTable::updateDisplayName($userId, $newDisplayName);
		AdminActivityLogger::log(AdminActivityTypeTable::DISPLAY_NAME_CHANGE, null, null, $oldDisplayName, $newDisplayName, $userId);
		Auth::saveUserField('display_name', $newDisplayName);
		Auth::saveUserField('needs_display_name_change', false);
	}

	#[HttpGet]
	public function logout(): Response
	{
		Auth::logout();
		return $this->redirect('/');
	}

	/** @return Response|null */
	#[HttpPost]
	public function googlesignin()
	{
		// CSRF protection: Google Sign-In provides g_csrf_token in both POST and cookie
		// These must match to prevent cross-site request forgery
		$csrfToken = $this->input('g_csrf_token') ?? '';
		$csrfCookie = Util::cookie('g_csrf_token') ?? '';
		if (empty($csrfToken) || $csrfToken !== $csrfCookie)
		{
			Log::warning('GoogleSignIn: CSRF validation failed');
			CookieFlash::set('CSRF validation failed', 'error');
			return $this->redirect('/users/login');
		}

		// Google Sign-In posts the credential as a form field
		$id_token = $this->input('credential') ?? '';

		$verifier = $this->getTokenVerifier();
		$token_data = $verifier->verify($id_token);

		if (!$token_data)
		{
			Log::debug('GoogleSignIn: Token verification failed');
			CookieFlash::set('Invalid Google token', 'error');
			return $this->redirect('/users/login');
		}

		$name = $token_data['name'] ?? '';
		// A Google profile name is free text and can hold an emoji, which the forum cannot
		// store; the character is dropped and the player is asked to pick a name below.
		$nameWithoutEmoji = UserTable::withoutCharactersTheForumCannotStore($name);
		$droppedCharacters = $nameWithoutEmoji !== $name;
		$name = $nameWithoutEmoji;
		// Fallback to last 6 digits of sub if no name provided
		if (empty($name))
			$name = 'User ' . substr($token_data['sub'] ?? '', -6);
		$email = $token_data['email'] ?? '';
		$picture = $token_data['picture'] ?? '';
		// 'sub' is the Google account id, it identifies the player on their next sign in
		$externalId = $token_data['sub'];

		$userTable = $this->fetchTable('User');
		$user = $userTable->find()->where(['external_id' => $externalId])->first();
		if ($user == null)
		{
			// A Google account gets no login name, it can't sign in with a password. A
			// taken display name gets a numbered suffix and the player is asked to choose
			// their own name before continuing.
			$normalizedName = UserTable::normalizeDisplayName($name);
			$uniqueDisplayName = UserTable::generateUniqueDisplayName($normalizedName);

			$userData = [];
			$userData['display_name'] = $uniqueDisplayName;
			$userData['needs_display_name_change'] = ($uniqueDisplayName !== $normalizedName || $droppedCharacters) ? true : false;
			$userData['email'] = $email;
			$userData['password_hash'] = UserTable::NO_PASSWORD;
			$userData['external_id'] = $externalId;
			$userData['picture'] = $picture;
			// A Google account shows the picture of its Google profile until the player
			// picks another avatar on the profile page.
			if ($picture)
				$userData['avatar_setting_id'] = UserTable::AVATAR_GOOGLE;

			if (!$userTable->save($userTable->newEntity($userData)))
			{
				CookieFlash::set('Failed to create Google user', 'error');
				return $this->redirect('/users/login');
			}
			$user = $userTable->find()->where(['external_id' => $externalId])->firstOrFail();
		}
		else
		{
			// Update profile data on each login (in case user changed Google profile)
			$updates = [];

			// Update picture if changed
			if ($picture && $user->picture !== $picture)
				$updates['picture'] = $picture;

			// An account with no picture starts showing the Google one as soon as a sign
			// in brings the first picture. A picture that only changed keeps the avatar
			// the player picked.
			if ($picture && empty($user->picture))
				$updates['avatar_setting_id'] = UserTable::AVATAR_GOOGLE;

			// Update email if changed and not empty. An account switched to email and password
			// keeps the address it signs in with, whatever the Google account has now.
			if ($email && $user->email !== $email && UserTable::signsInOnlyWithGoogle($user))
				$updates['email'] = $email;

			// Apply updates if any changed
			if (!empty($updates))
			{
				$userTable->updateAll($updates, ['id' => $user->id]);
				$user->patch($updates);
			}
		}
		$this->signIn(UserTable::rowOf($user));

		// Get redirect URL from state parameter (set via data-state in the button)
		// Google Sign-In provides built-in CSRF protection via g_csrf_token cookie
		// We use HMAC signature to prevent redirect URL tampering (stateless)
		$redirect = '/sets/';
		$stateJson = $this->input('state');
		if ($stateJson)
		{
			$stateData = json_decode(base64_decode($stateJson), true);
			if ($stateData)
				$redirect = $this->getVerifiedRedirectUrl(
					$stateData['redirect'] ?? null,
					$stateData['signature'] ?? null
				);
		}
		return $this->redirect($redirect);
	}

	//First step of deleting the account of the logged-in user: emails a link confirming the deletion
	//(confirm_delete_account).
	#[HttpGet]
	#[HttpPost]
	public function delete_account()
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/');
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Delete account');
		$this->set('sent', false);
		if (!$this->request->is('post'))
			return null;

		$token = bin2hex(random_bytes(32));

		$emailer = $this->accountMail(Auth::getUser()['email'], 'Deletion of your Tsumego Hero account');
		if (!$this->linkMailWasSent($emailer, 'You asked to delete your Tsumego Hero account ' . UserTable::displayName(Auth::getUser()) . '.
To confirm the deletion, open the following link (valid for ' . self::$DELETION_LINK_VALID_HOURS . ' hours): https://' . $this->request->host() . '/users/confirm_delete_account/' . $token . '
If you have not asked for this, ignore this email, your account stays as it is.'))
		{
			CookieFlash::set('The mail could not be sent. Please try again in a minute.', 'error');
			return null;
		}

		Auth::saveUserField('deletion_token', $token);
		Auth::saveUserField('deletion_token_created', date('Y-m-d H:i:s'));
		$this->set('sent', true);
		return null;
	}

	//Second step of deleting an account, opened from the emailed link. It works without being
	//signed in, like the password reset. GET only shows the confirmation button (email scanners open
	//links), the deletion happens on POST.
	//The account is only marked as deleted: everything it created stays (shown as DELETED_NAME),
	//it can't sign in anymore, and an admin can restore it.
	#[HttpGet]
	#[HttpPost]
	public function confirm_delete_account(?string $token = null)
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Delete account');
		$user = $this->findByLinkToken('deletion_token', 'deletion_token_created', $token, self::$DELETION_LINK_VALID_HOURS, ['id', 'display_name']);
		$this->set('valid', (bool) $user);
		$this->set('token', $token);
		if (!$user || !$this->request->is('post'))
			return null;

		$userID = $user->id;
		AdminActivityLogger::log(AdminActivityTypeTable::DELETE_USER, null, null, $user->display_name, null, $userID);
		UserTable::deleteAccount($userID);
		if (Auth::getUserID() == $userID)
			Auth::logout();
		CookieFlash::set('Your account has been deleted', 'success');
		return $this->redirect('/');
	}

	//Ask for a different e-mail address. The account keeps the address it has until
	//the player confirms the new one from the link sent there, so a session left open
	//somewhere can not move the account to an address of somebody else.
	#[HttpPost]
	public function emailPreference(): mixed
	{
		$this->Authorization->authorize('User', 'editPreferences');

		$user = Auth::getUser();
		$email = trim((string) ($this->input('email') ?? ''));
		$password = (string) ($this->input('password') ?? '');

		if (UserTable::signsInOnlyWithGoogle($user))
		{
			CookieFlash::set('This account signs in through Google, so it uses the address of your Google account.', 'error');
			return $this->redirect('/users/settings');
		}

		if (mb_strlen($email) > UserTable::EMAIL_MAX_LENGTH || !Validation::email($email))
		{
			CookieFlash::set('That is not a valid e-mail address.', 'error');
			return $this->redirect('/users/settings');
		}

		if ($email === $user['email'])
		{
			CookieFlash::set('This account already uses that address.', 'error');
			return $this->redirect('/users/settings');
		}

		if (UserTable::emailTaken($email))
		{
			CookieFlash::set('That address belongs to another account.', 'error');
			return $this->redirect('/users/settings');
		}

		if (!password_verify($password, $user['password_hash']))
		{
			CookieFlash::set('The password is not correct.', 'error');
			return $this->redirect('/users/settings');
		}

		if (!$this->askForAddressChange($user['email'], $email))
		{
			CookieFlash::set('The mail could not be sent. Please try again in a minute.', 'error');
			return $this->redirect('/users/settings');
		}

		CookieFlash::set('We sent a confirmation link to ' . $email . '. The address changes once you open it.', 'success');
		return $this->redirect('/users/settings');
	}

	//Start moving the signed in account from $current to the address $email: a confirmation link
	//goes to the new address and a link cancelling it to the current one. The account keeps
	//$current until the link is opened (confirmEmailChange). False when a mail could not be sent,
	//then nothing is asked for.
	private function askForAddressChange(string $current, string $email): bool
	{
		$hours = UsersController::$EMAIL_CHANGE_LINK_VALID_HOURS;
		$token = bin2hex(random_bytes(32));
		$cancelToken = bin2hex(random_bytes(32));

		$emailer = $this->accountMail($email, 'Confirm your new Tsumego Hero address');
		$confirmationSent = $this->linkMailWasSent($emailer, 'You asked to use this address for your Tsumego Hero account.'
			. "\nTo make it the address of the account, open this link (valid for $hours hours): "
			. Router::url('/users/confirmEmailChange/' . $token, true)
			. "\nIf you have not asked for this, ignore this email.");

		$warningSent = $confirmationSent && $this->linkMailWasSent($this->accountMail($current, 'An address change was asked for on your Tsumego Hero account'), 'Somebody asked to move your Tsumego Hero account to ' . $email . '.'
			. "\nIf that was not you, cancel it here (valid for $hours hours): "
			. Router::url('/users/cancelEmailChange/' . $cancelToken, true));
		if (!$warningSent)
			return false;

		Auth::saveUserFields([
			'unconfirmed_email' => $email,
			'unconfirmed_email_token' => $token,
			'unconfirmed_email_cancel_token' => $cancelToken,
			'unconfirmed_email_created' => date('Y-m-d H:i:s'),
		]);
		return true;
	}

	//Second step of an address change, opened from the link sent to the new address.
	//It works without being signed in, like the password reset. GET only shows the
	//confirmation button (email scanners open links), the change happens on POST.
	#[HttpGet]
	#[HttpPost]
	public function confirmEmailChange(?string $token = null)
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Confirm address');
		$user = $this->findByLinkToken('unconfirmed_email_token', 'unconfirmed_email_created', $token, UsersController::$EMAIL_CHANGE_LINK_VALID_HOURS);
		$this->set('valid', (bool) $user);
		$this->set('address', $user ? (string) $user->unconfirmed_email : '');
		$this->set('token', (string) $token);
		if (!$user || !$this->request->is('post'))
			return null;

		$userID = $user->id;
		$newAddress = (string) $user->unconfirmed_email;
		// The address was free when it was asked for, but the link works for a day, so
		// another account may have taken it in between.
		if (UserTable::emailTaken($newAddress))
		{
			$this->forgetAddressChange($userID);
			CookieFlash::set('That address belongs to another account now, so it was not used. Ask for another one in your settings.', 'error');
			return $this->redirect(Auth::isLoggedIn() && Auth::getUserID() === $userID ? '/users/settings' : '/users/login');
		}

		$givenUp = $user->email;
		$this->fetchTable('User')->updateAll(['email' => $newAddress] + self::NO_ADDRESS_CHANGE, ['id' => $userID]);

		$emailer = $this->accountMail($givenUp, 'Your Tsumego Hero address was changed');
		$this->sendNotification($emailer, 'The address of your Tsumego Hero account was changed to ' . $newAddress . '.'
			. "\nIf you did not ask for this, write to me@tsumego.com.");

		if (Auth::isLoggedIn() && Auth::getUserID() === $userID)
			Auth::init(UserTable::row($userID));

		CookieFlash::set('Your e-mail address has been changed.', 'success');
		return $this->redirect(Auth::isLoggedIn() ? '/users/settings' : '/users/login');
	}

	//Second step of cancelling an address change, opened from the link sent to the
	//address that is being given up. GET only shows the button, POST cancels.
	#[HttpGet]
	#[HttpPost]
	public function cancelEmailChange(?string $token = null)
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Cancel address change');
		$user = $this->findByLinkToken('unconfirmed_email_cancel_token', 'unconfirmed_email_created', $token, UsersController::$EMAIL_CHANGE_LINK_VALID_HOURS);
		$this->set('valid', (bool) $user);
		$this->set('address', $user ? (string) $user->unconfirmed_email : '');
		$this->set('token', (string) $token);
		if (!$user || !$this->request->is('post'))
			return null;

		$userID = $user->id;
		$this->forgetAddressChange($userID);

		if (Auth::isLoggedIn() && Auth::getUserID() === $userID)
			Auth::init(UserTable::row($userID));

		CookieFlash::set('The address change was cancelled.', 'success');
		return $this->redirect(Auth::isLoggedIn() ? '/users/settings' : '/users/login');
	}

	//An account with no address change pending: the links it was sent with stop working.
	//DailyCron::dropExpiredAddressChanges() clears the same columns of the old ones.
	private const array NO_ADDRESS_CHANGE = [
		'unconfirmed_email' => null,
		'unconfirmed_email_token' => null,
		'unconfirmed_email_cancel_token' => null,
		'unconfirmed_email_created' => null];

	//Drop a pending address change, so the links it was sent with stop working.
	private function forgetAddressChange(int $userID): void
	{
		$this->fetchTable('User')->updateAll(self::NO_ADDRESS_CHANGE, ['id' => $userID]);
	}

	//Change the password of a local account. The current password is asked for so
	//that a session left open on a shared computer is not enough to take the
	//account over.
	#[HttpPost]
	public function passwordPreference(): mixed
	{
		$this->Authorization->authorize('User', 'editPreferences');

		$user = Auth::getUser();
		if (UserTable::signsInOnlyWithGoogle($user))
		{
			CookieFlash::set('This account signs in through Google, so it has no password.', 'error');
			return $this->redirect('/users/settings');
		}

		$current = (string) ($this->input('current') ?? '');
		$new = (string) ($this->input('new1') ?? '');
		$repeated = (string) ($this->input('new2') ?? '');

		if (!password_verify($current, $user['password_hash']))
		{
			CookieFlash::set('The current password is not correct.', 'error');
			return $this->redirect('/users/settings');
		}

		$problem = $this->passwordProblem($new, $repeated);
		if ($problem)
		{
			CookieFlash::set($problem, 'error');
			return $this->redirect('/users/settings');
		}

		$hash = password_hash($new, PASSWORD_DEFAULT);
		Auth::saveUserField('password_hash', $hash);
		// The tokens of the other devices were issued with the old password and stop fitting
		JwtAuth::setAuthCookie((int) Auth::getUserID(), $hash);
		$this->sendPasswordChangedMail($user['email']);
		CookieFlash::set('Your password has been changed.', 'success');
		return $this->redirect('/users/settings');
	}

	//Switch an account that signs in with Google only to email and password. The player sets a
	//password, keeps the address of their Google account or gives another one (confirmed by a link,
	//like any address change), and may pick another display name in the same step. It is the same
	//account, so everything it has stays, and the Google button keeps signing it in as well. As it
	//changes how the account signs in, it needs a sign-in with Google from the last few minutes
	//(Auth::signedInRecently()), so a session left open somewhere is not enough.
	#[HttpGet]
	#[HttpPost]
	public function switchToPassword()
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		$user = Auth::getUser();
		if (!UserTable::signsInOnlyWithGoogle($user))
		{
			CookieFlash::set('This account signs in with its email and password already.', 'info');
			return $this->redirect('/users/settings');
		}

		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Switch to a username and password');
		$this->set('user', $user);
		$this->set('signedInRecently', Auth::signedInRecently());
		// The Google button of the page comes back here once the player signed in again
		$here = '/users/switchToPassword';
		$this->set('googleState', base64_encode((string) json_encode(['redirect' => $here, 'signature' => $this->signRedirectUrl($here)])));
		$this->set('email', $user['email']);
		$this->set('displayName', $user['display_name']);
		// A Google account has no login name, so it picks one here to sign in with, besides the address
		$needsUsername = empty($user['name']);
		$this->set('needsUsername', $needsUsername);
		$this->set('username', $needsUsername ? UserTable::suggestUsername((string) $user['email']) : $user['name']);
		if (!$this->request->is('post'))
			return null;

		if (!Auth::signedInRecently())
		{
			CookieFlash::set('For your security, sign in with Google again first.', 'error');
			return $this->redirect($here);
		}

		$userID = Auth::getUserID();
		$email = trim((string) ($this->input('email') ?? ''));
		$displayName = UserTable::normalizeDisplayName((string) ($this->input('display_name') ?? ''));
		if ($displayName === '')
			$displayName = $user['display_name'];
		$username = $needsUsername ? trim((string) ($this->input('username') ?? '')) : (string) $user['name'];
		// what the player typed stays in the form when something is wrong with it
		$this->set('email', $email);
		$this->set('displayName', $displayName);
		$this->set('username', $username);

		$problem = $this->passwordProblem((string) ($this->input('password1') ?? ''), (string) ($this->input('password2') ?? ''))
			?? ($needsUsername ? $this->usernameProblem($username, $userID) : null)
			?? $this->addressProblem($email, $userID)
			?? ($displayName !== $user['display_name'] ? $this->displayNameProblem($displayName, $userID) : null);
		if ($problem)
		{
			CookieFlash::set($problem, 'error');
			return null;
		}

		$addressChanges = $email !== $user['email'];
		if ($addressChanges && !$this->askForAddressChange($user['email'], $email))
		{
			CookieFlash::set('The mail could not be sent. Please try again in a minute.', 'error');
			return null;
		}
		if ($displayName !== $user['display_name'])
			$this->rename($userID, $user['display_name'], $displayName);

		$hash = password_hash((string) $this->input('password1'), PASSWORD_DEFAULT);
		Auth::saveUserFields(['name' => $username, 'password_hash' => $hash]);
		// The tokens of the other devices were issued without the password and stop fitting
		JwtAuth::setAuthCookie($userID, $hash);

		$emailer = $this->accountMail($user['email'], 'Your Tsumego Hero account now has a password');
		$this->sendNotification($emailer, 'Your Tsumego Hero account now signs in with its username ' . $username . ' or its email, and a password. Signing in with Google keeps working too.'
			. "\nIf you did not do this, set a new password at " . Router::url('/users/resetpassword', true) . ' and write to me@tsumego.com.');

		CookieFlash::set('Your account now signs in with the username ' . $username . ' or ' . $user['email'] . ', and your password.'
			. ($addressChanges ? ' We sent a confirmation link to ' . $email . ', the address changes once you open it.' : ''), 'success');
		return $this->redirect('/users/settings');
	}

	//The message to show for a login name the signed in account can not take, or null when it can.
	//The rules of a sign-up (UserTable::validationDefault(), the unique index), and no "@": signing in
	//looks for the name before the address, so a name that is an address would hide an account.
	private function usernameProblem(string $username, int $userID): ?string
	{
		$length = mb_strlen($username);
		if ($length < UserTable::USERNAME_MIN_LENGTH || $length > UserTable::USERNAME_MAX_LENGTH)
			return 'The username needs ' . UserTable::USERNAME_MIN_LENGTH . ' to ' . UserTable::USERNAME_MAX_LENGTH . ' characters.';
		if (str_contains($username, '@'))
			return 'The username must not contain "@".';
		if ($this->fetchTable('User')->exists(['name' => $username, 'id !=' => $userID]))
			return 'That username is taken, choose another one.';
		return null;
	}

	//The message to show for an address the signed in account can not take, or null when it can.
	//An address of another account is refused, so no account can take over the sign-in of another.
	private function addressProblem(string $email, int $userID): ?string
	{
		if (mb_strlen($email) > UserTable::EMAIL_MAX_LENGTH || !Validation::email($email))
			return 'That is not a valid e-mail address.';
		if (UserTable::emailTaken($email, $userID))
			return 'That address belongs to another account.';
		return null;
	}

	//Tell the account by mail that its password changed, so that a change made by
	//somebody else is noticed. The password itself is never part of the mail.
	private function sendPasswordChangedMail(string $email): void
	{
		$emailer = $this->accountMail($email, 'Your Tsumego Hero password was changed');
		$this->sendNotification($emailer, 'The password of your Tsumego Hero account was changed. If you did not change it, set a new one at '
			. Router::url('/users/resetpassword', true) . ' and write to me@tsumego.com.');
	}
}
