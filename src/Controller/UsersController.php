<?php

use App\Utility\AdminActivityLogger;
use App\Utility\AdminActivityRenderer;
use App\Utility\Auth;
use App\Utility\Avatar;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\GoogleTokenVerifier;
use App\Utility\GoogleTokenVerifierInterface;
use App\Utility\JwtAuth;
use App\Utility\MistakeTraining;
use App\Utility\ProposalFeedback;
use App\Utility\PwnedPasswordChecker;
use App\Utility\PwnedPasswordCheckerInterface;
use App\Utility\RejectedSGFProposalsRenderer;
use App\Utility\SGFProposalsRenderer;
use App\Utility\SgfCommentReorientation;
use App\Utility\TagConnectionProposalsRenderer;
use App\Utility\TagProposalsRenderer;
use App\Utility\TimeModeUtil;
use App\Utility\TsumegoUtil;
use App\Utility\Util;
use App\Attribute\HttpPost;
use App\Attribute\HttpGet;

class UsersController extends AppController
{
	public $helpers = ['Html', 'Form', 'Highscore'];

	protected ?PwnedPasswordCheckerInterface $passwordChecker = null;

	protected ?GoogleTokenVerifierInterface $tokenVerifier = null;

	/**
	 * A password that login alone would never reject, used to spend the same time on a
	 * name that has no account. It costs what PASSWORD_DEFAULT costs, and
	 * LoginComponentTest fails when the two drift apart.
	 */
	private static string $DUMMY_PASSWORD_HASH = '$2y$12$BZ5TgNGDEJZktTLaEt5Tx.zJfbhdsQxv5/O7wfVeX0QpDwVSdTGg.';

	public static function _dummyPasswordHash(): string
	{
		return self::$DUMMY_PASSWORD_HASH;
	}

	protected function getPasswordChecker(): PwnedPasswordCheckerInterface
	{
		return $this->passwordChecker ??= PwnedPasswordChecker::create();
	}

	/**
	 * Whether the password is one an attacker would try first, because it is already
	 * in a public breach. Such a password is refused wherever a password is set.
	 */
	private function isKnownLeaked(string $password): bool
	{
		return $this->getPasswordChecker()->isBreached($password);
	}

	/**
	 * The message to show for a password that can not be used, or null when it can.
	 * Every place that sets a password asks this, so the rules are the same when an
	 * account is created, when the password is changed and when it is reset. Signing in
	 * does not ask it, so an account with a shorter password still gets in.
	 */
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

	/**
	 * Get the Google token verifier (lazily creates real verifier if not set)
	 */
	protected function getTokenVerifier(): GoogleTokenVerifierInterface
	{
		return $this->tokenVerifier ??= GoogleTokenVerifier::create(
			'986748597524-05gdpjqrfop96k6haga9gvj1f61sji6v.apps.googleusercontent.com');
	}

	/**
	 * @return void
	 */
	#[HttpGet]
	#[HttpPost]
	public function resetpassword(): void
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Sign In');
		$this->set('sent', !empty($this->request->data));
		if (empty($this->request->data))
			return;

		$userEmail = $this->request->data['User']['email'];

		// Only allow password reset for local users (not Google Sign-In users)
		$user = $this->User->findLocalUserByEmail($userEmail);
		if (!$user)
		{
			// An address that can not get a link is answered like one that has no account,
			// so the form says nothing about which addresses are taken. The sign-in page
			// points Google accounts to their button before they submit this form.
			return;
		}

		// Another mail on top of the one just sent only fills the inbox. The answer is
		// the same either way, so nothing about the account is revealed by skipping it.
		$askedAt = $user['User']['passwordreset_created'];
		if ($askedAt && strtotime($askedAt) > strtotime('-' . self::$PASSWORD_RESET_MINUTES_BETWEEN_MAILS . ' minutes'))
			return;

		$hours = self::$PASSWORD_RESET_LINK_VALID_HOURS;
		$randomString = Util::generateRandomString(20);

		$emailer = $this->_getEmailer();
		$emailer->from(['me@tsumego.com' => 'https://tsumego.com']);
		$emailer->to($userEmail);
		$emailer->subject('Password reset for your Tsumego Hero account');
		if (!$this->linkMailWasSent($emailer, 'Click the following button to reset your password (valid for ' . $hours . ' hours). If you have not requested the password reset,
then ignore this email. ' . Router::url('/users/newpassword/' . $randomString, true)))
		{
			$this->set('sent', false);
			CookieFlash::set('The mail could not be sent. Please try again in a minute.', 'error');
			return;
		}

		// The link works with the token alone, so the token is remembered only once the mail
		// carrying it has left. A mail server that stays quiet holds nothing back.
		$user['User']['passwordreset'] = $randomString;
		$user['User']['passwordreset_created'] = date('Y-m-d H:i:s');
		$this->User->save($user);
	}

	/**
	 * Send the mail that carries a link. The token in it is remembered only when this
	 * returns true, so a mail server that does not answer leaves nothing behind and the
	 * user can go through the flow again right away.
	 *
	 * @param CakeEmail $emailer The mailer from _getEmailer(), which a test replaces
	 * @return bool Whether the mail left
	 */
	private function linkMailWasSent(mixed $emailer, string $body): bool
	{
		try
		{
			$emailer->send($body);
		}
		catch (Exception $e)
		{
			CakeLog::write('error', 'A Tsumego Hero link mail could not be sent: ' . $e->getMessage());
			return false;
		}

		return true;
	}

	/**
	 * Send a mail that only tells the account about a change that already happened. It is
	 * not part of the change, so a mail server that does not answer is logged instead of
	 * turned into an error page.
	 *
	 * @param CakeEmail $emailer The mailer from _getEmailer(), which a test replaces
	 */
	private function sendNotification(mixed $emailer, string $body): void
	{
		try
		{
			$emailer->send($body);
		}
		catch (Exception $e)
		{
			CakeLog::write('error', 'A Tsumego Hero notification could not be sent: ' . $e->getMessage());
		}
	}

	// @param string|null $checksum Password reset checksum
	#[HttpGet]
	#[HttpPost]
	public function newpassword(?string $checksum = null): mixed
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Sign In');
		$this->set('valid', false);
		$this->set('done', false);
		$this->set('checksum', (string) $checksum);

		$user = $checksum === null ? null : $this->User->find('first', ['conditions' => [
			'passwordreset' => $checksum,
			'deleted IS NULL',
			'passwordreset_created >' => date('Y-m-d H:i:s', strtotime('-' . self::$PASSWORD_RESET_LINK_VALID_HOURS . ' hours'))]]);
		if (!$user)
			return null;

		$this->set('valid', true);
		$password = (string) ($this->request->data['User']['password1'] ?? '');
		$repeated = (string) ($this->request->data['User']['password2'] ?? '');
		if ($password === '')
			return null;

		$problem = $this->passwordProblem($password, $repeated);
		if ($problem)
		{
			CookieFlash::set($problem, 'error');
			return null;
		}

		$user['User']['passwordreset'] = null;
		$user['User']['passwordreset_created'] = null;
		$user['User']['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
		$this->User->save($user);
		$this->sendPasswordChangedMail($user['User']['email']);
		CookieFlash::set('Password changed', 'success');
		return $this->redirect('/users/login');
	}

	/**
	 * @param string|null $uid User ID
	 * @return void
	 */
	#[HttpGet]
	public function userstats(?string $uid = null): void
	{
		$this->Authorization->authorize('Admin');
		$this->set('_page', 'user');
		$this->set('_title', 'USER STATS');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');
		if ($uid == null)
			$ur = $this->TsumegoAttempt->find('all', ['limit' => 500, 'order' => 'created DESC']);
		elseif ($uid == 99)
			$ur = $this->TsumegoAttempt->find('all', [
				'order' => 'created DESC',
				'conditions' => [
					'tsumego_id >=' => 19752,
					'tsumego_id <=' => 19761,
				],
			]);
		else
			$ur = $this->TsumegoAttempt->find('all', ['limit' => 500, 'order' => 'created DESC', 'conditions' => ['user_id' => $uid]]);

		$urCount = count($ur);
		for ($i = 0; $i < $urCount; $i++)
		{
			$u = $this->User->findById($ur[$i]['TsumegoAttempt']['user_id']);
			$ur[$i]['TsumegoAttempt']['user_display_name'] = $u['User']['display_name'];
			$ur[$i]['TsumegoAttempt']['level'] = $u['User']['level'];
			$t = $this->Tsumego->findById($ur[$i]['TsumegoAttempt']['tsumego_id']);
			$scT = $this->SetConnection->findDisplaySetConnection($t['Tsumego']['id']);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$ur[$i]['TsumegoAttempt']['tsumego_num'] = $scT['SetConnection']['num'];
			$ur[$i]['TsumegoAttempt']['tsumego_xp'] = $t['Tsumego']['difficulty'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$ur[$i]['TsumegoAttempt']['set_name'] = $s['Set']['title'];
		}

		$noIndex = false;
		if ($uid != null)
			$noIndex = true;
		if (isset($this->params['url']['c']))
			$this->set('count', 1);
		else
			$this->set('count', 0);
		$this->set('noIndex', $noIndex);
		$this->set('ur', $ur);
		$this->set('uid', $uid);
	}

	/**
	 * @param string|null $sid Set ID
	 * @return void
	 */
	#[HttpGet]
	public function userstats3(?string $sid = null): void
	{
		$this->Authorization->authorize('Admin');
		$this->set('_page', 'user');
		$this->set('_title', 'USER STATS');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');

		if ($sid == null)
			$ur = $this->TsumegoAttempt->find('all', ['limit' => 500, 'order' => 'created DESC']);
		else
		{
			$ts = TsumegoUtil::collectTsumegosFromSet((int) $sid);
			$ids = [];
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++)
				array_push($ids, $ts[$i]['Tsumego']['id']);
			$ur = $this->TsumegoAttempt->find('all', ['order' => 'created DESC', 'conditions' => ['tsumego_id' => $ids]]);
		}

		$urCount = count($ur);
		for ($i = 0; $i < $urCount; $i++)
		{
			$u = $this->User->findById($ur[$i]['TsumegoAttempt']['user_id']);
			$ur[$i]['TsumegoAttempt']['user_display_name'] = $u['User']['display_name'];
			$t = $this->Tsumego->findById($ur[$i]['TsumegoAttempt']['tsumego_id']);
			$ur[$i]['TsumegoAttempt']['tsumego_num'] = $t['Tsumego']['num'];
			$ur[$i]['TsumegoAttempt']['tsumego_xp'] = $t['Tsumego']['difficulty'];
			$scT = $this->SetConnection->findDisplaySetConnection($t['Tsumego']['id']);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$ur[$i]['TsumegoAttempt']['set_name'] = $s['Set']['title'];
		}

		$noIndex = false;
		if ($sid != null)
			$noIndex = true;
		if (isset($this->params['url']['c']))
			$this->set('count', 1);
		else
			$this->set('count', 0);
		$this->set('noIndex', $noIndex);
		$this->set('ur', $ur);
		//$this->set('uid', $uid);
	}

	/**
	 * @return void
	 */
	#[HttpGet]
	public function uploads(): void
	{
		$this->Authorization->authorize('Admin');
		$this->set('_page', 'set');
		$this->set('_title', 'Uploads');
		$this->loadModel('Sgf');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');

		$s = $this->Sgf->find('all', [
			'limit' => 250,
			'order' => 'created DESC',
			'conditions' => ['sgf_status_id !=' => Sgf::STATUS_REJECTED],
		]);

		//to display the correct version
		$versionCounter = [];

		$sCount = count($s);
		for ($i = 0; $i < $sCount; $i++)
		{


			$u = $this->User->findById($s[$i]['Sgf']['user_id']);
			$s[$i]['Sgf']['user'] = $u['User']['display_name'];
			$t = $this->Tsumego->findById($s[$i]['Sgf']['tsumego_id']);
			$scT = $this->SetConnection->findDisplaySetConnection($t['Tsumego']['id']);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$set = $this->Set->findById($t['Tsumego']['set_id']);
			$s[$i]['Sgf']['title'] = $set['Set']['title'] . ' ' . $set['Set']['title2'] . ' #' . $scT['SetConnection']['num'];
			$s[$i]['Sgf']['num'] = $scT['SetConnection']['num'];

			if(isset($versionCounter[$scT['SetConnection']['tsumego_id']]))
				$versionCounter[$scT['SetConnection']['tsumego_id']]++;
			else
				$versionCounter[$scT['SetConnection']['tsumego_id']] = 0;
			$s[$i]['Sgf']['version'] = count($this->Sgf->find('all', ['conditions' => ['tsumego_id' => $scT['SetConnection']['tsumego_id'], 'sgf_status_id !=' => Sgf::STATUS_REJECTED]])) - $versionCounter[$scT['SetConnection']['tsumego_id']];

			$s[$i]['Sgf']['delete'] = false;
			$s[$i]['Sgf']['diff'] = $this->Sgf->findPreviousVersionID($s[$i]['Sgf']['tsumego_id'], $s[$i]['Sgf']['id']);
		}
		$this->set('s', $s);
	}

	#[HttpGet]
	public function adminstats(): void
	{
		$this->Authorization->authorize('Admin');
		$this->set('_page', 'user');
		$this->set('_title', 'Admin Panel');
		$this->loadModel('User');
		$this->loadModel('DayRecord');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('AdminActivityType');

		$this->set('deletedUsers', $this->User->find('all', [
			'conditions' => ['deleted IS NOT NULL'],
			'order' => 'deleted DESC']) ?: []);

		// Pagination data
		$this->set('sgfProposalsRenderer', new SGFProposalsRenderer($this->params['url']));
		$this->set('rejectedSgfProposalsRenderer', new RejectedSGFProposalsRenderer($this->params['url']));
		$this->set('tagProposalsRenderer', new TagProposalsRenderer($this->params['url']));
		$this->set('adminActivityRenderer', new AdminActivityRenderer($this->params['url']));
		$this->set('tagConnectionProposalsRenderer', new TagConnectionProposalsRenderer($this->params['url']));
	}

	private function getUserFromNameOrEmail(): ?array
	{
		$input = $this->request->data['username'];
		if (empty($input))
			return null;

		// A local account is found by its login name, a Google account has none
		if ($user = $this->User->findByName($input))
			return $user;

		// Only a local account can be signed in with a password, so an address that is
		// also used by a Google account must not shadow it.
		return $this->User->findLocalUserByEmail($input);
	}

	#[HttpGet]
	#[HttpPost]
	public function login()
	{
		$this->set('_page', 'login');
		$this->set('_title', 'Tsumego Hero - Sign In');

		// On GET request, prepare redirect URL with HMAC signature (fully stateless)
		if (!$this->request->is('post'))
		{
			$referer = $this->referer(null, true);
			// Don't redirect back to login page itself
			$redirectUrl = ($referer && strpos($referer, '/users/login') === false) ? $referer : '/sets/';

			// Sign the redirect URL with HMAC to prevent tampering (no session needed)
			$signature = $this->signRedirectUrl($redirectUrl);

			// Pass to view for hidden field and Google data-state
			$this->set('redirectUrl', $redirectUrl);
			$this->set('redirectSignature', $signature);
			return null;
		}

		if (!$this->request->data['username'])
			return null;
		$user = $this->getUserFromNameOrEmail();
		if (!$user)
		{
			// A name without an account is answered the same way as a wrong password, and
			// the hash is computed either way, so neither the message nor the time it takes
			// tells whether the account exists.
			password_verify((string) ($this->request->data['password'] ?? ''), self::$DUMMY_PASSWORD_HASH);

			// The address may belong to a Google account only, which can't log in here
			$googleUser = $this->User->findByEmail($this->request->data['username']);
			if ($googleUser && User::isGoogleUser($googleUser['User']))
				CookieFlash::set('This account uses Google Sign-In. Please use the "Sign in with Google" button.', 'info');
			else
				CookieFlash::set('Wrong username or password.', 'error');
			return null;
		}

		// Google users should use "Sign in with Google" button
		if (User::isGoogleUser($user['User']))
		{
			CookieFlash::set('This account uses Google Sign-In. Please use the "Sign in with Google" button.', 'info');
			return null;
		}

		if (!$this->validateLogin($this->request->data, $user))
		{
			CookieFlash::set('Wrong username or password.', 'error');
			return null;
		}

		$this->signIn($user);

		// Verify and use redirect URL from POST data
		$redirect = $this->getVerifiedRedirectUrl(
			$this->request->data('redirect'),
			$this->request->data('redirect_signature')
		);
		return $this->redirect($redirect);
	}

	/**
	 * Sign a redirect URL with HMAC to prevent tampering.
	 * This allows stateless redirect URL handling without sessions.
	 */
	private function signRedirectUrl(string $redirectUrl): string
	{
		return hash_hmac('sha256', $redirectUrl, Configure::read('Security.salt'));
	}

	/**
	 * Verify redirect URL signature and return safe redirect URL.
	 * Returns default redirect if signature is invalid or URL is not relative.
	 */
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

	/**
	 * Check if URL is relative (starts with / but not //)
	 */
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

		if (empty($this->request->data))
			return;

		$password = (string) ($this->request->data['User']['password1'] ?? '');
		$problem = $this->passwordProblem($password, (string) ($this->request->data['User']['password2'] ?? ''));
		if ($problem)
		{
			CookieFlash::set($problem, 'error');
			return;
		}

		if ($this->User->findByEmail($this->request->data['User']['email']))
		{
			CookieFlash::set('Email already exists', 'error');
			return;
		}

		$userData = $this->request->data;
		$userData['User']['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
		$userData['User']['name'] = $this->request->data['User']['name'];
		$normalizedName = User::normalizeDisplayName($this->request->data['User']['name']);
		if (User::containsCharactersTheForumCannotStore($normalizedName))
		{
			CookieFlash::set('The name must not contain emoji or other rare symbols, please choose another one.', 'error');
			return;
		}
		$uniqueDisplayName = User::generateUniqueDisplayName($normalizedName);
		$userData['User']['display_name'] = $uniqueDisplayName;
		// A taken display name gets a numbered suffix, which is not the name the player chose,
		// so they are asked to pick their own before continuing (the same as the Google path).
		$userData['User']['needs_display_name_change'] = $uniqueDisplayName !== $normalizedName;
		$userData['User']['email'] = $this->request->data['User']['email'];

		$this->User->create();
		try
		{
			if (!$this->User->save($userData, true))
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

	/**
	 * Render the forced display-name change form. Only users flagged with
	 * needs_display_name_change can reach this (free renaming is disabled).
	 *
	 * @return mixed
	 */
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

	/**
	 * Update user's display name. Only allowed while the account is flagged for it
	 * (needs_display_name_change = 1). Clears the flag on success.
	 *
	 * @return mixed
	 */
	#[HttpPost]
	public function updatename()
	{
		$userId = Auth::getUserID();
		if (!$userId)
		{
			CookieFlash::set(__('You must be logged in.'), 'error');
			return $this->redirect('/');
		}

		$user = $this->User->findById($userId);
		if (!$user)
		{
			CookieFlash::set(__('User not found.'), 'error');
			return $this->redirect('/');
		}

		// Free renaming is intentionally disabled: this only serves an account that is
		// flagged for a rename.
		if (empty($user['User']['needs_display_name_change']))
		{
			CookieFlash::set(__('You can only change your display name when asked to.'), 'error');
			return $this->redirect('/');
		}

		$newDisplayName = User::normalizeDisplayName($this->request->data['User']['display_name'] ?? '');

		if (empty($newDisplayName))
		{
			CookieFlash::set(__('Display name cannot be empty.'), 'error');
			return $this->redirect(['action' => 'changename']);
		}

		if (User::containsCharactersTheForumCannotStore($newDisplayName))
		{
			CookieFlash::set(__('The display name must not contain emoji or other rare symbols.'), 'error');
			return $this->redirect(['action' => 'changename']);
		}

		// Validate display name length in characters, not bytes: the column holds 50
		// characters, and a name in another alphabet takes several bytes per character.
		$displayNameLength = mb_strlen($newDisplayName);
		if ($displayNameLength < 3 || $displayNameLength > 50)
		{
			CookieFlash::set(__('Display name must be between 3 and 50 characters.'), 'error');
			return $this->redirect(['action' => 'changename']);
		}

		if ($newDisplayName === $user['User']['display_name'])
		{
			CookieFlash::set(__('Please choose a different display name.'), 'error');
			return $this->redirect(['action' => 'changename']);
		}

		// Check uniqueness - another user might have this display_name
		$existingUser = $this->User->find('first', [
			'conditions' => [
				'display_name' => $newDisplayName,
				'id !=' => $userId,
			],
		]);
		if ($existingUser)
		{
			CookieFlash::set(__('This display name is already taken.'), 'error');
			return $this->redirect(['action' => 'changename']);
		}

		// Update display_name (including forum sync)
		User::updateDisplayName($userId, $newDisplayName);

		AdminActivityLogger::log(AdminActivityType::DISPLAY_NAME_CHANGE, null, null, $user['User']['display_name'], $newDisplayName, $userId);

		// Clear the forced flag and update the session
		Auth::saveUserField('display_name', $newDisplayName);
		Auth::saveUserField('needs_display_name_change', false);

		CookieFlash::set(__('Display name updated successfully.'), 'success');
		return $this->redirect('/sets/');
	}

	/**
	 * @return void
	 */
	#[HttpGet]
	public function highscore(): void
	{
		$this->set('_page', 'levelHighscore');
		$this->set('_title', 'Tsumego Hero - Highscore');

		$this->set('users', $this->queryHighscoreWithSelfView(
			"SELECT id, display_name, external_id, picture, pref_avatar, email, rating, premium, level, xp, solved,
				ROW_NUMBER() OVER (ORDER BY level DESC, xp DESC) as position
			FROM user
			WHERE deleted IS NULL",
			topRows: 1000
		));
		$this->set('totalUsers', $this->countActiveUsers());
	}

	#[HttpGet]
	public function rating(): void
	{
		$this->set('_page', 'ratingHighscore');
		$this->set('_title', 'Tsumego Hero - Rating');

		if (Auth::isLoggedIn())
		{
			$ux = $this->User->findById(Auth::getUserID());
			$ux['User']['lastHighscore'] = Constants::$HIGHSCORE_RATING;
			$this->User->save($ux);
		}

		$this->set('users', $this->queryHighscoreWithSelfView(
			"SELECT id, display_name, external_id, picture, pref_avatar, email, rating, premium,
				ROW_NUMBER() OVER (ORDER BY rating DESC) as position
			FROM user
			WHERE deleted IS NULL"
		));
		$this->set('totalUsers', $this->countActiveUsers());
	}

	/**
	 * @return void
	 */
	#[HttpGet]
	public function added_tags(): void
	{
		$this->set('_page', 'tagHighscore');
		$this->set('_title', 'Tsumego Hero - Added Tags');

		$userId = Auth::isLoggedIn() ? Auth::getUserID() : -1;

		$this->set('tagContributors', $this->queryHighscoreWithSelfView("
			SELECT
				user.id as id,
				user.display_name as display_name,
				user.external_id as external_id,
				user.picture as picture,
				user.pref_avatar as pref_avatar,
				user.email as email,
				user.rating as rating,
				user.premium as premium,
				COUNT(tag_connection.id) AS tag_count,
				ROW_NUMBER() OVER (ORDER BY COUNT(tag_connection.id) DESC) as position
			FROM user
			LEFT JOIN tag_connection ON tag_connection.user_id = user.id AND tag_connection.approved = true
			WHERE (tag_connection.id IS NOT NULL OR user.id = ?) AND user.deleted IS NULL
			GROUP BY user.id", 'id', [$userId]));
		$this->set('totalUsers', $this->countActiveUsers());
	}

	#[HttpGet]
	public function achievements(): void
	{
		$this->set('_page', 'achievementHighscore');
		$this->set('_title', 'Tsumego Hero - Achievements Highscore');

		if (Auth::isLoggedIn())
		{
			$ux = $this->User->findById(Auth::getUserID());
			$ux['User']['lastHighscore'] = Constants::$HIGHSCORE_RATING;
			$this->User->save($ux);
		}

		$userId = Auth::isLoggedIn() ? Auth::getUserID() : -1;

		$this->set('users', $this->queryHighscoreWithSelfView("
			SELECT
				user.id AS id,
				user.display_name AS display_name,
				user.rating AS rating,
				user.picture AS picture,
				user.pref_avatar AS pref_avatar,
				user.external_id AS external_id,
				user.premium AS premium,
				user.email AS email,
				COUNT(DISTINCT achievement_status.achievement_id) AS achievement_score,
				COALESCE(SUM(CASE WHEN achievement_status.value > 1 THEN achievement_status.value ELSE 0 END), 0) AS achievement_earned_times,
				ROW_NUMBER() OVER (
					ORDER BY COUNT(DISTINCT achievement_status.achievement_id) DESC,
						COALESCE(SUM(achievement_status.value), 0) DESC) as position
			FROM user
			LEFT JOIN achievement_status
				ON achievement_status.user_id = user.id
			WHERE (achievement_status.id IS NOT NULL OR user.id = ?) AND user.deleted IS NULL
			GROUP BY user.id", 'id', [$userId]));
	}

	/**
	 * @return void
	 */
	#[HttpGet]
	public function time_mode(): void
	{
		$this->set('_page', 'timeHighscore');
		$this->set('_title', 'Tsumego Hero - Time Highscore');

		$this->loadModel('TimeModeSession');
		$this->loadModel('TimeModeRank');

		// Build rank name -> ID lookup
		$allRanks = $this->TimeModeRank->find('all');
		$rankNameToId = [];
		foreach ($allRanks as $r)
			$rankNameToId[$r['TimeModeRank']['name']] = $r['TimeModeRank']['id'];

		$currentRank = '';
		$params1 = '';
		$params2 = '';

		if (Auth::isLoggedIn())
		{
			$ux = $this->User->findById(Auth::getUserID());
			$ux['User']['lastHighscore'] = Constants::$HIGHSCORE_TIME_MODE;
			$this->User->save($ux);
		}

		if (isset($this->params['url']['category']))
		{
			$params1 = $this->params['url']['category'];
			$params2 = $this->params['url']['rank'] ?? '15k';
			$currentRank = $params2;
			$categoryId = intval($params1) + 1;
			$rankId = $rankNameToId[$params2] ?? 1;
		}
		else
		{
			$user = Auth::isLoggedIn() ? Auth::getUser() : null;
			if ($user && ($user['last_time_mode_category_id'] ?? null))
				$categoryId = $user['last_time_mode_category_id'];
			else
				$categoryId = TimeModeCategory::SLOW;

			$params1 = $categoryId - 1;
			$params2 = '15k';
			$currentRank = $params2;
			$rankId = $rankNameToId[$params2] ?? 1;
		}

		$userId = Auth::isLoggedIn() ? Auth::getUserID() : -1;

		$this->set('users', $this->queryHighscoreWithSelfView("
			SELECT
				user.id AS id,
				user.display_name AS display_name,
				user.external_id AS external_id,
				user.picture AS picture,
				user.pref_avatar AS pref_avatar,
				user.email AS email,
				user.rating AS rating,
				user.premium AS premium,
				COALESCE(MAX(time_mode_session.points), 0) AS points,
				ROW_NUMBER() OVER (ORDER BY COALESCE(MAX(time_mode_session.points), 0) DESC) as position
			FROM user
			LEFT JOIN time_mode_session ON time_mode_session.user_id = user.id
				AND time_mode_session.time_mode_category_id = " . intval($categoryId) . "
				AND time_mode_session.time_mode_rank_id = " . intval($rankId) . "
			WHERE (time_mode_session.id IS NOT NULL OR user.id = ?) AND user.deleted IS NULL
			GROUP BY user.id", 'id', [$userId]));
		$this->set('totalUsers', $this->countActiveUsers());

		// Build rank buttons: single query replaces 60+ individual lookups
		$existingSessions = Util::query("
			SELECT time_mode_category_id, time_mode_rank_id
			FROM time_mode_session
			GROUP BY time_mode_category_id, time_mode_rank_id");
		$sessionExists = [];
		foreach ($existingSessions as $row)
			$sessionExists[$row['time_mode_category_id'] . '_' . $row['time_mode_rank_id']] = true;

		$rankNames = $this->buildRankNames();
		$modes = [];
		$modes2 = [];
		for ($i = 0; $i < 3; $i++)
		{
			$modes[$i] = [];
			$modes2[$i] = [];
			foreach ($rankNames as $j => $rankName)
			{
				$modes2[$i][$j] = $rankName;
				$rId = $rankNameToId[$rankName] ?? null;
				$modes[$i][$j] = ($rId && isset($sessionExists[($i + 1) . '_' . $rId])) ? 1 : $rankName;
			}
		}

		$this->set('params1', $params1);
		$this->set('params2', $params2);
		$this->set('modes', $modes);
		$this->set('modes2', $modes2);
	}

	private function countActiveUsers(): int
	{
		return $this->User->find('count', ['conditions' => ['deleted IS NULL']]);
	}

	/**
	 * Run a single CTE query that returns the top ranked rows plus the rows around
	 * the current user. The $rankedQuery must be a SELECT that produces a `position`
	 * column via ROW_NUMBER().
	 *
	 * @param string $rankedQuery SQL SELECT with ROW_NUMBER() ... as position
	 * @param string $idColumn Column name used to identify the current user (default: 'id')
	 * @param array<int, mixed> $extraParams Extra bound parameters for the ranked query
	 * @param int $topRows How many top rows are always listed
	 * @return array<int, array<string, mixed>>
	 */
	private function queryHighscoreWithSelfView(string $rankedQuery, string $idColumn = 'id', array $extraParams = [], int $topRows = 30): array
	{
		$userId = Auth::isLoggedIn() ? Auth::getUserID() : -1;
		$neighborRadius = 3;

		$sql = "
			WITH ranked AS ({$rankedQuery}),
			self_pos AS (SELECT position FROM ranked WHERE {$idColumn} = ?)
			SELECT r.*
			FROM ranked r
			LEFT JOIN self_pos sp ON 1=1
			WHERE r.position <= {$topRows}
			   OR (sp.position IS NOT NULL
			       AND r.position BETWEEN sp.position - ? AND sp.position + ?)
			ORDER BY r.position";

		return Util::query($sql, array_merge($extraParams, [$userId, $neighborRadius, $neighborRadius]));
	}

	/**
	 * Build array of rank names from 15k down to 5d (matching time_mode_rank table order).
	 */
	private function buildRankNames(): array
	{
		$names = [];
		$rank = 15;
		while ($rank > -5)
		{
			if ($rank >= 1)
				$names[] = $rank . 'k';
			else
				$names[] = (($rank - 1) * -1) . 'd';
			$rank--;
		}
		return $names;
	}

	/**
	 * @return void
	 */
	#[HttpGet]
	public function leaderboard(): void
	{
		$this->set('_page', 'dailyHighscore');
		$this->set('_title', 'Tsumego Hero - Daily Highscore');

		$userId = Auth::isLoggedIn() ? Auth::getUserID() : -1;

		$this->set('users', $this->queryHighscoreWithSelfView(
			"SELECT id, name, display_name, external_id, picture, pref_avatar, email, rating, premium, daily_xp, daily_solved,
				ROW_NUMBER() OVER (ORDER BY daily_xp DESC) as position
			FROM user
			WHERE (daily_xp > 0 OR id = ?) AND deleted IS NULL",
			'id',
			[$userId]
		));

		$this->set('totalUsers', Util::query("SELECT COUNT(*) as cnt FROM user WHERE daily_xp > 0 AND deleted IS NULL")[0]['cnt']);
	}

	/**
	 * Redirect to the logged-in user's own profile page.
	 *
	 * @return void
	 */
	#[HttpGet]
	public function profile(): void
	{
		if (!Auth::isLoggedIn())
			$this->redirect('/users/login');
		$this->redirect('/users/view/' . Auth::getUserID());
	}

	#[HttpGet]
	public function meSolveHistory(): void
	{
		if (!Auth::isLoggedIn())
			$this->redirect('/users/login');
		$this->redirect('/users/solveHistory/' . Auth::getUserID());
	}

	#[HttpGet]
	public function meContributions(): void
	{
		if (!Auth::isLoggedIn())
			$this->redirect('/users/login');
		$this->redirect('/tags/user/' . Auth::getUserID());
	}

	#[HttpGet]
	public function view(?string $id = null): mixed
	{
		$this->set('_page', 'user');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('SetConnection');
		$this->loadModel('TimeModeSession');

		$as = $this->AchievementStatus->find('all', ['limit' => 12, 'order' => 'created DESC', 'conditions' => ['user_id' => $id]]);
		$ach = $this->Achievement->find('all');

		$user = $this->User->findById($id);
		if (!$user)
			return $this->redirect('/sets');
		$this->assertUserVisible($user['User']);
		$this->set('_title', 'Profile of ' . $user['User']['display_name']);

		// The profile is shared with the avatar the player picked, at the size a
		// preview takes. What the site draws is a png of known size and type; an
		// avatar of another host is shared as its url, and only the size a host is
		// told to send is known.
		$avatarChoice = User::avatarChoice($user['User']);
		$og = [
			'title' => 'Profile of ' . $user['User']['display_name'],
			'description' => $user['User']['display_name'] . ' on Tsumego',
			'image_alt' => 'Avatar of ' . $user['User']['display_name'],
			'type' => 'profile',
			'profile_username' => $user['User']['display_name'],
			'site_name' => 'Tsumego',
		];
		if ($avatarChoice === User::AVATAR_BOARD)
		{
			$og['image'] = Router::url(Avatar::pngUrl($user['User']['display_name']), true);
			$og['image_type'] = 'image/png';
			$og['image_width'] = Avatar::SHARE_SIZE;
			$og['image_height'] = Avatar::SHARE_SIZE;
		}
		else
		{
			$og['image'] = User::getAvatarUrl($user['User'], Avatar::SHARE_SIZE);
			if ($avatarChoice === User::AVATAR_GRAVATAR)
			{
				// Gravatar answers with the size it is asked for, so the size of the
				// picture is known even though its format is not.
				$og['image_width'] = Avatar::SHARE_SIZE;
				$og['image_height'] = Avatar::SHARE_SIZE;
			}
		}
		$this->set('og', $og);

		$oldest = new DateTime(date('Y-m-d', strtotime('-183 days')));
		$oldest = $oldest->format('Y-m-d');

		$dailyResults = Util::query("
			SELECT
				DATE(created) AS day,
				SUM(CASE WHEN solved = 1 THEN 1 ELSE 0 END) AS Solves,
				SUM(CASE WHEN solved = 0 THEN 1 ELSE 0 END) AS Fails,
				MAX(user_rating) AS Rating
			FROM tsumego_attempt
			WHERE user_id = :user_id
			  AND created > :oldest
			GROUP BY DATE(created)
			ORDER BY day ASC
		", ['user_id' => $id, 'oldest'  => $oldest]);

		$this->set('timeModeRanks', Util::query("
SELECT
    c.name AS category_name,

    -- 1) Best solved session rank name (highest rank.id)
    (
        SELECT time_mode_rank.name
        FROM time_mode_session s2
        JOIN time_mode_rank ON time_mode_rank.id = s2.time_mode_rank_id
        WHERE
            s2.time_mode_category_id = c.id AND
            s2.time_mode_session_status_id = " . TimeModeUtil::$SESSION_STATUS_SOLVED . " AND
            s2.user_id = ?
        ORDER BY time_mode_rank.id DESC                            -- highest id = best rank
        LIMIT 1
    ) AS best_solved_rank_name,

    -- 2) Number of sessions in this category
    COUNT(s.id) AS session_count

FROM
	time_mode_category c
	LEFT JOIN time_mode_session s ON s.user_id = ? AND s.time_mode_category_id = c.id
GROUP BY c.id, c.name;", [$user['User']['id'], $user['User']['id']]));

		$this->set('timeGraph', Util::query('
SELECT
    DATE(time_mode_session.created) AS category,
    SUM(CASE WHEN time_mode_session.time_mode_session_status_id = ' . TimeModeUtil::$SESSION_STATUS_SOLVED . ' THEN 1 ELSE 0 END) AS Passes,
    SUM(CASE WHEN time_mode_session.time_mode_session_status_id = ' . TimeModeUtil::$SESSION_STATUS_FAILED . ' THEN 1 ELSE 0 END) AS Fails
FROM time_mode_session
WHERE time_mode_session.user_id = ?
GROUP BY DATE(time_mode_session.created)
ORDER BY category DESC', [$user['User']['id']]));

		$progress = ClassRegistry::init('Tsumego')->publicProblemProgress((int) $user['User']['id']);
		$tsumegoCount = $progress['total'];
		$this->set('solvedProblemCount', $progress['solved']);

		$achievementById = [];
		foreach ($ach as $achievement)
			$achievementById[$achievement['Achievement']['id']] = $achievement['Achievement'];

		$asList = [];
		foreach ($as as $item)
		{
			$achievementId = (int) $item['AchievementStatus']['achievement_id'];
			if (!isset($achievementById[$achievementId]))
				continue;
			$achievement = $achievementById[$achievementId];
			$item['AchievementStatus']['a_title'] = $achievement['name'];
			$item['AchievementStatus']['a_description'] = $achievement['description'];
			$item['AchievementStatus']['a_image'] = $achievement['image'];
			$item['AchievementStatus']['a_color'] = $achievement['color'];
			$item['AchievementStatus']['a_id'] = $achievement['id'];
			$item['AchievementStatus']['a_xp'] = $achievement['xp'];
			$item['AchievementStatus']['a_earned_count'] = (int) $item['AchievementStatus']['value'];
			$asList[] = $item;
		}
		$as = $asList;

		$counts = Util::query(
			'SELECT COUNT(DISTINCT achievement_status.achievement_id) AS completed,
				COALESCE(SUM(CASE WHEN achievement_status.value > 1 THEN achievement_status.value ELSE 0 END), 0) AS earned_times
			FROM achievement_status
			WHERE achievement_status.user_id = ?',
			[$id]
		)[0];
		$aNumx = (int) $counts['completed'];
		$aEarnedTimes = (int) $counts['earned_times'];

		// only admins get to the profile of a deleted account, they see who it was
		if ($user['User']['deleted'])
			$user['User']['display_name'] = $user['User']['deleted_name'] . ' ' . User::DELETED_NAME;

		$aCount = $this->Achievement->find('all');

		// The section of the training shows only for a player who trained.
		$this->set('mistakeTraining', MistakeTraining::profile((int) $user['User']['id'], MistakeTraining::profileStart()));

		$this->set('dailyResults', $dailyResults);
		$this->set('user', $user);
		$this->set('tsumegoCount', $tsumegoCount);
		$this->set('as', $as);
		$this->set('aNum', $aNumx);
		$this->set('aEarnedTimes', $aEarnedTimes);
		$this->set('aCount', $aCount);
		return null;
	}

	/**
	 * How the site behaves for the logged in player: appearance, how a problem is
	 * dealt and the progress actions. The profile stays the public page, so
	 * everything that only changes the player's own view lives here. The boards in
	 * the rotation are picked in the dropdown in the header.
	 */
	#[HttpGet]
	public function settings(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$this->set('_page', 'user');
		$this->set('_title', 'Settings');

		$user = Auth::getUser();
		$this->set('user', $user);
		$this->set('tsumegoStatusToRestCount', TsumegoStatus::countOldProgress($user['id']));
		$this->set('canResetOldTsumegoStatuses', User::canResetOldProgress($user));
		$this->set('unconfirmedEmail', self::waitingAddress($user));

		return null;
	}

	/**
	 * The address an account is waiting for the confirmation of, or null when there is
	 * none or its link is too old to work.
	 *
	 * @param array $user
	 */
	private static function waitingAddress(array $user): ?string
	{
		if (empty($user['unconfirmed_email']) || empty($user['unconfirmed_email_created']))
			return null;
		if (strtotime($user['unconfirmed_email_created']) <= strtotime('-' . self::$EMAIL_CHANGE_LINK_VALID_HOURS . ' hours'))
			return null;
		return (string) $user['unconfirmed_email'];
	}

	/**
	 * @return void
	 */
	#[HttpGet]
	public function authors(): void
	{
		$this->loadModel('User');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');

		$this->set('_page', 'about');
		$this->set('_title', 'Tsumego Hero - About');

		$authors = $this->Tsumego->find('all', [
			'order' => 'created DESC',
			'conditions' => [
				'NOT' => [
					'author' => ['Joschka Zimdars'],
				],
			],
		]);
		$set = $this->Set->find('all');
		$setMap = [];
		$setMap2 = [];
		$setCount = count($set);
		for ($i = 0; $i < $setCount; $i++)
		{
			$divider = ' ';
			$setMap[$set[$i]['Set']['id']] = $set[$i]['Set']['title'] . $divider . $set[$i]['Set']['title2'];
			$setMap2[$set[$i]['Set']['id']] = $set[$i]['Set']['public'];
		}

		$count = [];
		$count[0]['author'] = 'Innokentiy Zabirov';
		$count[0]['collections'] = 's: <a href="' . Set::url(41) . '">Life & Death - Intermediate</a> and <a href="' . Set::url(122) . '">Gokyo Shumyo 1-4</a>';
		$count[0]['count'] = 0;
		$count[1]['author'] = 'Alexandre Dinerchtein';
		$count[1]['collections'] = ': <a href="' . Set::url(109) . '">Problems from Professional Games</a>';
		$count[1]['count'] = 0;
		$count[2]['author'] = 'David Ulbricht';
		$count[2]['collections'] = ': <a href="' . Set::url(41) . '">Life & Death - Intermediate</a>';
		$count[2]['count'] = 0;
		$count[3]['author'] = 'Bradford Malbon';
		$count[3]['collections'] = 's: <a href="' . Set::url(104) . '">Easy Life</a> and <a href="' . Set::url(105) . '">Easy Kill</a>';
		$count[3]['count'] = 0;
		$count[4]['author'] = 'Ryan Smith';
		$count[4]['collections'] = 's: <a href="' . Set::url(67) . '">Korean Problem Academy 1-4</a>';
		$count[4]['count'] = 0;
		$count[5]['author'] = 'Fupfv';
		$count[5]['collections'] = ': <a href="' . Set::url(139) . '">Gokyo Shumyo 4</a>';
		$count[5]['count'] = 0;
		$count[6]['author'] = 'саша черных';
		$count[6]['collections'] = ': <a href="' . Set::url(137) . '">Tsumego Master</a>';
		$count[6]['count'] = 0;
		$count[7]['author'] = 'Timo Kreuzer';
		$count[7]['collections'] = ': <a href="' . Set::url(137) . '">Tsumego Master</a>';
		$count[7]['count'] = 0;
		$count[8]['author'] = 'David Mitchell';
		$count[8]['collections'] = ': <a href="' . Set::url(143) . '">Diabolical</a>';
		$count[8]['count'] = 10;
		$count[9]['author'] = 'Omicron';
		$count[9]['collections'] = ': <a href="' . Set::url(145) . '">Tesujis in Real Board Positions</a>';
		$count[9]['count'] = 0;
		$count[10]['author'] = 'Sadaharu';
		$count[10]['collections'] = ': <a href="' . Set::url(146) . '">Tsumego of Fortitude</a>, <a href="' . Set::url(166) . '">Secret Tsumego from Hong Dojo</a>, <a href="' . Set::url(158) . '">Beautiful Tsumego</a> and more.';
		$count[10]['count'] = 0;
		$count[11]['author'] = 'Jérôme Hubert';
		$count[11]['collections'] = ': <a href="' . Set::url(150) . '">Kanzufu</a> and more.';
		$count[11]['count'] = 0;
		$count[12]['author'] = 'Kaan Malçok';
		$count[12]['collections'] = ': <a href="' . Set::url(163) . '">Xuanxuan Qijing</a>';
		$count[12]['count'] = 0;

		$this->set('count', $count);
		$this->set('t', $authors);
	}

	/**
	 * @return void
	 */
	#[HttpGet]
	public function logout(): void
	{
		Auth::logout();
	}

	private function validateLogin(array $data, array $user): bool
	{
		if (!$user)
			return false;
		// The minimum length is not asked here: an account whose password is shorter
		// still has to sign in.
		return password_verify($data['password'], $user['User']['password_hash']);
	}

	/**
	 * @return CakeResponse|null
	 */
	#[HttpPost]
	public function googlesignin()
	{
		// CSRF protection: Google Sign-In provides g_csrf_token in both POST and cookie
		// These must match to prevent cross-site request forgery
		$csrfToken = $_POST['g_csrf_token'] ?? ($this->request->data['g_csrf_token'] ?? '');
		$csrfCookie = $_COOKIE['g_csrf_token'] ?? '';
		if (empty($csrfToken) || $csrfToken !== $csrfCookie)
		{
			CakeLog::write('warning', 'GoogleSignIn: CSRF validation failed');
			CookieFlash::set('CSRF validation failed', 'error');
			return $this->redirect('/users/login');
		}

		// Google Sign-In posts credential directly, but testAction uses $this->request->data
		$id_token = $_POST['credential'] ?? ($this->request->data['credential'] ?? '');

		$verifier = $this->getTokenVerifier();
		$token_data = $verifier->verify($id_token);

		if (!$token_data)
		{
			CakeLog::write('debug', 'GoogleSignIn: Token verification failed');
			CookieFlash::set('Invalid Google token', 'error');
			return $this->redirect('/users/login');
		}

		$name = $token_data['name'] ?? '';
		// A Google profile name is free text and can hold an emoji, which the forum cannot
		// store; the character is dropped and the player is asked to pick a name below.
		$nameWithoutEmoji = User::withoutCharactersTheForumCannotStore($name);
		$droppedCharacters = $nameWithoutEmoji !== $name;
		$name = $nameWithoutEmoji;
		// Fallback to last 6 digits of sub if no name provided
		if (empty($name))
			$name = 'User ' . substr($token_data['sub'] ?? '', -6);
		$email = $token_data['email'] ?? '';
		$picture = $token_data['picture'] ?? '';
		// 'sub' is the Google account id, it identifies the player on their next sign in
		$externalId = $token_data['sub'];

		$u = $this->User->find('first', ['conditions' => ['external_id' => $externalId]]);
		if ($u == null)
		{
			// A Google account gets no login name, it can't sign in with a password. A
			// taken display name gets a numbered suffix and the player is asked to choose
			// their own name before continuing.
			$normalizedName = User::normalizeDisplayName($name);
			$uniqueDisplayName = User::generateUniqueDisplayName($normalizedName);

			$userData = [];
			$userData['User']['display_name'] = $uniqueDisplayName;
			$userData['User']['needs_display_name_change'] = ($uniqueDisplayName !== $normalizedName || $droppedCharacters) ? true : false;
			$userData['User']['email'] = $email;
			$userData['User']['password_hash'] = 'google_oauth';
			$userData['User']['external_id'] = $externalId;
			$userData['User']['picture'] = $picture;
			// A Google account shows the picture of its Google profile until the player
			// picks another avatar on the profile page.
			if ($picture)
				$userData['User']['pref_avatar'] = User::AVATAR_GOOGLE;

			$this->User->create();
			$saveResult = $this->User->save($userData);
			if (!$saveResult)
			{
				CookieFlash::set('Failed to create Google user', 'error');
				return $this->redirect('/users/login');
			}
			$u = $this->User->find('first', ['conditions' => ['external_id' => $externalId]]);
		}
		else
		{
			// Update profile data on each login (in case user changed Google profile)
			$updates = [];

			// Update picture if changed
			if ($picture && $u['User']['picture'] !== $picture)
				$updates['picture'] = $picture;

			// An account with no picture starts showing the Google one as soon as a sign
			// in brings the first picture. A picture that only changed keeps the avatar
			// the player picked.
			if ($picture && empty($u['User']['picture']))
				$updates['pref_avatar'] = User::AVATAR_GOOGLE;

			// Update email if changed and not empty
			if ($email && $u['User']['email'] !== $email)
				$updates['email'] = $email;

			// Apply updates if any changed
			if (!empty($updates))
			{
				$this->User->id = $u['User']['id'];
				foreach ($updates as $field => $value)
				{
					$this->User->saveField($field, $value);
					$u['User'][$field] = $value;
				}
			}
		}
		$this->signIn($u);

		// Get redirect URL from state parameter (set via data-state in the button)
		// Google Sign-In provides built-in CSRF protection via g_csrf_token cookie
		// We use HMAC signature to prevent redirect URL tampering (stateless)
		$redirect = '/sets/';
		$stateJson = $_POST['state'] ?? ($this->request->data['state'] ?? null);
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

	private static int $DELETION_LINK_VALID_HOURS = 24;

	/**
	 * How long the two links of an address change work, in hours. The cron forgets a
	 * change that nobody confirmed, so it reads the same value.
	 */
	public static int $EMAIL_CHANGE_LINK_VALID_HOURS = 24;

	/**
	 * How long the link of a password reset works, in hours.
	 */
	private static int $PASSWORD_RESET_LINK_VALID_HOURS = 2;

	/**
	 * How long to wait before another reset mail is sent for the same account.
	 */
	private static int $PASSWORD_RESET_MINUTES_BETWEEN_MAILS = 5;

	/**
	 * First step of deleting the account of the logged-in user: emails a link confirming the deletion
	 * (confirm_delete_account).
	 */
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

		$emailer = $this->_getEmailer();
		$emailer->from(['me@tsumego.com' => 'https://tsumego.com']);
		$emailer->to(Auth::getUser()['email']);
		$emailer->subject('Deletion of your Tsumego Hero account');
		if (!$this->linkMailWasSent($emailer, 'You asked to delete your Tsumego Hero account ' . User::displayName(Auth::getUser()) . '.
To confirm the deletion, open the following link (valid for ' . self::$DELETION_LINK_VALID_HOURS . ' hours): https://' . $_SERVER['HTTP_HOST'] . '/users/confirm_delete_account/' . $token . '
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

	/**
	 * Second step of deleting an account, opened from the emailed link. It works without being
	 * signed in, like the password reset. GET only shows the confirmation button (email scanners open
	 * links), the deletion happens on POST.
	 *
	 * The account is only marked as deleted: everything it created stays (shown as DELETED_NAME),
	 * it can't sign in anymore, and an admin can restore it.
	 */
	#[HttpGet]
	#[HttpPost]
	public function confirm_delete_account(?string $token = null)
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Delete account');
		$user = $token ? $this->User->find('first', ['conditions' => [
			'deletion_token' => $token,
			'deleted IS NULL',
			'deletion_token_created >' => date('Y-m-d H:i:s', strtotime('-' . self::$DELETION_LINK_VALID_HOURS . ' hours'))]]) : null;
		$this->set('valid', (bool) $user);
		$this->set('token', $token);
		if (!$user || !$this->request->is('post'))
			return null;

		$userID = (int) $user['User']['id'];
		AdminActivityLogger::log(AdminActivityType::DELETE_USER, null, null, $user['User']['display_name'], null, $userID);
		User::deleteAccount($userID);
		Cache::delete('recent_achievements_7', 'default');
		if (Auth::getUserID() == $userID)
			Auth::logout();
		CookieFlash::set('Your account has been deleted', 'success');
		return $this->redirect('/');
	}

	/**
	 * Restores a deleted account (admin only).
	 */
	#[HttpPost]
	public function restore_account(?string $id = null)
	{
		$this->Authorization->authorize('Admin', 'restoreAccount');
		$user = $this->User->findById($id);
		if (!$user)
			throw new NotFoundException('User not found');

		$name = $user['User']['deleted_name'];
		if ($error = User::restoreAccount((int) $user['User']['id']))
		{
			CookieFlash::set('Account ' . $name . ' can\'t be restored: ' . $error, 'error');
			return $this->redirect('/users/adminstats');
		}
		AdminActivityLogger::log(AdminActivityType::RESTORE_USER, null, null, null, $name);
		Cache::delete('recent_achievements_7', 'default');
		CookieFlash::set('Account ' . $name . ' restored', 'success');
		return $this->redirect('/users/adminstats');
	}

	/**
	 */
	#[HttpGet]
	#[HttpPost]
	public function demote_admin()
	{
		$redirect = false;
		$status = '';
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		if (!empty($this->request->data))
			if (isset($this->request->data['User']['demote']))
				if (password_verify($this->request->data['User']['demote'], Auth::getUser()['password_hash']))
				{
					if ($error = User::changeRole(Auth::getUserID(), User::ROLE_PLAYER))
						$status = '<p style="color:#d63a49">' . h($error) . '</p>';
					else
						$redirect = true;
				}
				else
					$status = '<p style="color:#d63a49">Password incorrect.</p>';

		$user = Auth::getUser();

		$this->set('redirect', $redirect);
		$this->set('status', $status);
		$this->set('u', ['User' => $user]);
	}

	#[HttpGet]
	public function solveHistory(string $userID): void
	{
		$profileUser = $this->User->findById($userID)['User'] ?? null;
		if (!$profileUser)
			throw new NotFoundException('User not found');
		$this->assertUserVisible($profileUser);
		$userName = $profileUser['display_name'];
		$PAGE_SIZE = 500;
		$pageIndex = isset($this->request->query['page']) ? max(1, (int) $this->request->query['page']) : 1;
		$count = Util::query("SELECT COUNT(*) FROM tsumego_attempt where user_id = ?", [$userID])[0]['COUNT(*)'];
		$offset = ($pageIndex - 1) * $PAGE_SIZE;

		$attempts = Util::query("
SELECT
	s.title AS set_title,
	s.id AS set_id,
	tsumego_attempt.tsumego_id AS tsumego_id,
	set_connection.id AS set_connection_id,
	set_connection.num AS num,
	tsumego_status.status AS status,
	tsumego_attempt.created AS created,
	tsumego_attempt.gain AS xp_gain,
	tsumego_attempt.solved AS solved,
	tsumego_attempt.misplays AS misplays,
	tsumego_attempt.user_rating AS user_rating,
	COALESCE(sgf.sgf, '') AS sgf
FROM
	tsumego_attempt
	LEFT JOIN set_connection ON set_connection.id = (
		SELECT sc.id
		FROM set_connection sc
		JOIN `set` ss ON ss.id = sc.set_id
		WHERE sc.tsumego_id = tsumego_attempt.tsumego_id
		ORDER BY " . SetConnection::displayOrderSql('ss', 'sc') . "
		LIMIT 1)
	LEFT JOIN sgf ON sgf.id = " . Sgf::currentVersionIDSql('tsumego_attempt.tsumego_id') . "
	LEFT JOIN tsumego_status ON tsumego_status.user_id = ? AND tsumego_status.tsumego_id = tsumego_attempt.tsumego_id
	LEFT JOIN `set` s ON set_connection.set_id = s.id
WHERE
	tsumego_attempt.user_id=?
ORDER BY created DESC
LIMIT " . $PAGE_SIZE . "
OFFSET " . $offset, [$userID, $userID]);

		$this->set('_page', 'solveHistory');
		$this->set('_title', $userName . '\'s Solve History');
		$this->set('userID', $userID);
		$this->set('profileUser', $profileUser);
		$this->set('count', $count);
		$this->set('pageIndex', $pageIndex);
		$this->set('PAGE_SIZE', $PAGE_SIZE);
		$this->set('attempts', $attempts);
	}

	#[HttpPost]
	public function acceptSGFProposal(string $sgfID)
	{
		$this->Authorization->authorize('Admin');

		$proposalToApprove = ClassRegistry::init('Sgf')->findById($sgfID);
		if (!$proposalToApprove)
		{
			CookieFlash::set('Sgf proposal doesn\'t exist.', 'fail');
			return $this->redirect('/users/adminstats');
		}

		$proposalToApprove = $proposalToApprove['Sgf'];
		if ($proposalToApprove['sgf_status_id'] != Sgf::STATUS_PROPOSED)
		{
			CookieFlash::set('Sgf proposal was already decided.', 'fail');
			return $this->redirect('/users/adminstats');
		}
		$replacedSgfID = ClassRegistry::init('Sgf')->findCurrentVersionID((int) $proposalToApprove['tsumego_id']);
		ClassRegistry::init('Sgf')->setStatus((int) $proposalToApprove['id'], Sgf::STATUS_ACCEPTED);
		SgfCommentReorientation::afterAccepted((int) $proposalToApprove['id']);

		AppController::_handleContribution(Auth::getUserID(), 'reviewed');
		AppController::_handleContribution($proposalToApprove['user_id'], 'made_proposal');
		CookieFlash::set('Sgf proposal accepted', 'success');

		$userToApprove = $this->User->findById($proposalToApprove['user_id']);
		AdminActivityLogger::log(AdminActivityType::ACCEPT_PROPOSAL, $proposalToApprove['tsumego_id'], null, null, $userToApprove['User']['display_name']);

		$tsumegoID = (int) $proposalToApprove['tsumego_id'];
		ProposalFeedback::send(
			(int) $proposalToApprove['user_id'],
			'Your proposal for ' . ProposalFeedback::problemName($tsumegoID) . ' was accepted by ' . ProposalFeedback::adminName() . ', it is the current version of the problem now.',
			$tsumegoID,
			$proposalToApprove['description'],
			ProposalFeedback::commentFromRequest($this->request->data['comment'] ?? null),
			$this->_getEmailer(),
			ProposalFeedback::diffUrl((int) $proposalToApprove['id'], $replacedSgfID));

		return $this->redirect('/users/adminstats');
	}

	#[HttpPost]
	public function rejectSGFProposal(string $sgfID)
	{
		$this->Authorization->authorize('Admin');

		$proposalToReject = ClassRegistry::init('Sgf')->findById($sgfID);
		if (!$proposalToReject)
		{
			CookieFlash::set('Sgf proposal doesn\'t exist.', 'fail');
			return $this->redirect('/users/adminstats');
		}

		$proposalToReject = $proposalToReject['Sgf'];

		if ($proposalToReject['sgf_status_id'] != Sgf::STATUS_PROPOSED)
		{
			CookieFlash::set('Sgf proposal was already decided.', 'fail');
			return $this->redirect('/users/adminstats');
		}

		$comment = ProposalFeedback::commentFromRequest($this->request->data['comment'] ?? null);
		if ($comment === '')
		{
			CookieFlash::set('Write the proposer why the proposal is rejected.', 'fail');
			return $this->redirect('/users/adminstats');
		}

		$reject = [];
		$reject['user_id'] = $proposalToReject['user_id'];
		$reject['rejected_by_user_id'] = Auth::getUserID();
		$reject['comment'] = $comment;
		$reject['tsumego_id'] = $proposalToReject['tsumego_id'];
		$reject['sgf_id'] = $proposalToReject['id'];
		$reject['type'] = 'proposal';
		ClassRegistry::init('Reject')->create();
		ClassRegistry::init('Reject')->save($reject);
		// kept, so the proposer can still see what they proposed
		ClassRegistry::init('Sgf')->setStatus((int) $proposalToReject['id'], Sgf::STATUS_REJECTED);
		CookieFlash::set('Sgf proposal rejected', 'success');

		$userToReject = $this->User->findById($proposalToReject['user_id']);
		AdminActivityLogger::log(AdminActivityType::REJECT_PROPOSAL, $proposalToReject['tsumego_id'], null, null, $userToReject['User']['display_name']);

		$tsumegoID = (int) $proposalToReject['tsumego_id'];
		ProposalFeedback::send(
			(int) $proposalToReject['user_id'],
			'Your proposal for ' . ProposalFeedback::problemName($tsumegoID) . ' was rejected by ' . ProposalFeedback::adminName() . '.',
			$tsumegoID,
			$proposalToReject['description'],
			$comment,
			$this->_getEmailer(),
			ProposalFeedback::diffUrl((int) $proposalToReject['id'], ClassRegistry::init('Sgf')->findCurrentVersionID($tsumegoID)));

		return $this->redirect('/users/adminstats');
	}

	/** Puts a rejected SGF proposal back among the ones waiting for a decision. */
	#[HttpPost]
	public function reopenSGFProposal(string $sgfID)
	{
		$this->Authorization->authorize('Admin');

		$proposalToReopen = ClassRegistry::init('Sgf')->findById($sgfID);
		if (!$proposalToReopen || $proposalToReopen['Sgf']['sgf_status_id'] != Sgf::STATUS_REJECTED)
		{
			CookieFlash::set('There is no rejected sgf proposal to reopen.', 'fail');
			return $this->redirect('/users/adminstats');
		}
		$proposalToReopen = $proposalToReopen['Sgf'];

		ClassRegistry::init('Sgf')->setStatus((int) $proposalToReopen['id'], Sgf::STATUS_PROPOSED);
		ClassRegistry::init('Reject')->deleteAll(['Reject.sgf_id' => $proposalToReopen['id']], false);
		CookieFlash::set('Sgf proposal reopened', 'success');

		$proposer = $this->User->findById($proposalToReopen['user_id']);
		AdminActivityLogger::log(AdminActivityType::REOPEN_PROPOSAL, $proposalToReopen['tsumego_id'], null, null, $proposer ? $proposer['User']['display_name'] : null);

		return $this->redirect('/users/adminstats');
	}

	#[HttpPost]
	public function acceptTagConnectionProposal(string $tagConnectionID)
	{
		$this->Authorization->authorize('Admin');

		$proposalToApprove = ClassRegistry::init('TagConnection')->findById($tagConnectionID);
		if (!$proposalToApprove)
		{
			CookieFlash::set('Tag proposal doesn\'t exist.', 'fail');
			return $this->redirect('/users/adminstats');
		}

		$proposalToApprove = $proposalToApprove['TagConnection'];
		if ($proposalToApprove['approved'] != 0)
		{
			CookieFlash::set('Tag proposal was already accepted.', 'fail');
			return $this->redirect('/users/adminstats');
		}
		$proposalToApprove['approved'] = 1;

		$tag = ClassRegistry::init('Tag')->findById($proposalToApprove['tag_id'])['Tag'];
		AdminActivityLogger::log(AdminActivityType::ACCEPT_TAG, $proposalToApprove['tsumego_id'], null, null, $tag['name']);
		ClassRegistry::init('TagConnection')->save($proposalToApprove);
		AppController::_handleContribution(Auth::getUserID(), 'reviewed');
		AppController::_handleContribution($proposalToApprove['user_id'], 'added_tag');

		// tags are accepted by the dozen, so the proposer only hears about it when the admin had something to say
		$comment = ProposalFeedback::commentFromRequest($this->request->data['comment'] ?? null);
		if ($comment !== '')
		{
			$tsumegoID = (int) $proposalToApprove['tsumego_id'];
			ProposalFeedback::send(
				(int) $proposalToApprove['user_id'],
				'Your tag ' . $tag['name'] . ' on ' . ProposalFeedback::problemName($tsumegoID) . ' was accepted by ' . ProposalFeedback::adminName() . '.',
				$tsumegoID,
				null,
				$comment,
				$this->_getEmailer());
		}
		return $this->redirect('/users/adminstats');
	}

	#[HttpPost]
	public function rejectTagConnectionProposal(string $tagConnectionID)
	{
		$this->Authorization->authorize('Admin');

		$proposalToReject = ClassRegistry::init('TagConnection')->findById($tagConnectionID);
		if (!$proposalToReject)
		{
			CookieFlash::set('Tag proposal doesn\'t exist.', 'fail');
			return $this->redirect('/users/adminstats');
		}

		$proposalToReject = $proposalToReject['TagConnection'];

		if ($proposalToReject['approved'] != 0)
		{
			CookieFlash::set('Tag proposal was already accepted.', 'fail');
			return $this->redirect('/users/adminstats');
		}

		$comment = ProposalFeedback::commentFromRequest($this->request->data['comment'] ?? null);
		if ($comment === '')
		{
			CookieFlash::set('Write the proposer why the tag is rejected.', 'fail');
			return $this->redirect('/users/adminstats');
		}

		$reject = [];
		$reject['user_id'] = $proposalToReject['user_id'];
		$reject['rejected_by_user_id'] = Auth::getUserID();
		$reject['comment'] = $comment;
		$reject['tsumego_id'] = $proposalToReject['tsumego_id'];
		$tag = ClassRegistry::init('Tag')->findById($proposalToReject['tag_id'])['Tag'];
		$reject['type'] = $tag['name'];

		AdminActivityLogger::log(AdminActivityType::REJECT_TAG, $proposalToReject['tsumego_id'], null, $tag['name'], null);

		ClassRegistry::init('Reject')->create();
		ClassRegistry::init('Reject')->save($reject);
		ClassRegistry::init('TagConnection')->delete($proposalToReject['id']);

		$tsumegoID = (int) $proposalToReject['tsumego_id'];
		ProposalFeedback::send(
			(int) $proposalToReject['user_id'],
			'Your tag ' . $tag['name'] . ' on ' . ProposalFeedback::problemName($tsumegoID) . ' was rejected by ' . ProposalFeedback::adminName() . '.',
			$tsumegoID,
			null,
			$comment,
			$this->_getEmailer());

		CookieFlash::set('Tag proposal rejected', 'success');
		return $this->redirect('/users/adminstats');
	}

	#[HttpPost]
	public function deleteOldTsumegoStatuses(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/sets');

		$user = Auth::getUser();
		if (!User::canResetOldProgress($user))
			return $this->redirect('/users/settings');

		$deleted = TsumegoStatus::deleteOldProgress((int) $user['id']);
		CookieFlash::set('Deleted progress on ' . $deleted . ' problems', 'success');
		return $this->redirect('/users/settings');
	}

	/**
	 * Ask for a different e-mail address. The account keeps the address it has until
	 * the player confirms the new one from the link sent there, so a session left open
	 * somewhere can not move the account to an address of somebody else.
	 */
	#[HttpPost]
	public function emailPreference(): mixed
	{
		$this->Authorization->authorize('User', 'editPreferences');

		$user = Auth::getUser();
		$email = trim((string) ($this->request->data['User']['email'] ?? ''));
		$password = (string) ($this->request->data['User']['password'] ?? '');

		if (User::isGoogleUser($user))
		{
			CookieFlash::set('This account signs in through Google, so it uses the address of your Google account.', 'error');
			return $this->redirect('/users/settings');
		}

		// The column holds 50 characters, a longer address can not be stored
		if (mb_strlen($email) > 50 || !Validation::email($email))
		{
			CookieFlash::set('That is not a valid e-mail address.', 'error');
			return $this->redirect('/users/settings');
		}

		if ($email === $user['email'])
		{
			CookieFlash::set('This account already uses that address.', 'error');
			return $this->redirect('/users/settings');
		}

		if ($this->User->findByEmail($email))
		{
			CookieFlash::set('That address belongs to another account.', 'error');
			return $this->redirect('/users/settings');
		}

		if (!password_verify($password, $user['password_hash']))
		{
			CookieFlash::set('The password is not correct.', 'error');
			return $this->redirect('/users/settings');
		}

		$hours = self::$EMAIL_CHANGE_LINK_VALID_HOURS;
		$token = bin2hex(random_bytes(32));
		$cancelToken = bin2hex(random_bytes(32));

		$emailer = $this->_getEmailer();
		$emailer->from(['me@tsumego.com' => 'https://tsumego.com']);
		$emailer->to($email);
		$emailer->subject('Confirm your new Tsumego Hero address');
		$confirmationSent = $this->linkMailWasSent($emailer, 'You asked to use this address for your Tsumego Hero account.'
			. "\nTo make it the address of the account, open this link (valid for $hours hours): "
			. Router::url('/users/confirmEmailChange/' . $token, true)
			. "\nIf you have not asked for this, ignore this email.");

		$emailer->to($user['email']);
		$emailer->subject('An address change was asked for on your Tsumego Hero account');
		$warningSent = $confirmationSent && $this->linkMailWasSent($emailer, 'Somebody asked to move your Tsumego Hero account to ' . $email . '.'
			. "\nIf that was not you, cancel it here (valid for $hours hours): "
			. Router::url('/users/cancelEmailChange/' . $cancelToken, true));
		if (!$warningSent)
		{
			CookieFlash::set('The mail could not be sent. Please try again in a minute.', 'error');
			return $this->redirect('/users/settings');
		}

		Auth::saveUserFields([
			'unconfirmed_email' => $email,
			'unconfirmed_email_token' => $token,
			'unconfirmed_email_cancel_token' => $cancelToken,
			'unconfirmed_email_created' => date('Y-m-d H:i:s'),
		]);

		CookieFlash::set('We sent a confirmation link to ' . $email . '. The address changes once you open it.', 'success');
		return $this->redirect('/users/settings');
	}

	/**
	 * Second step of an address change, opened from the link sent to the new address.
	 * It works without being signed in, like the password reset. GET only shows the
	 * confirmation button (email scanners open links), the change happens on POST.
	 */
	#[HttpGet]
	#[HttpPost]
	public function confirmEmailChange(?string $token = null)
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Confirm address');
		$user = $this->findByEmailChangeToken('unconfirmed_email_token', $token);
		$this->set('valid', (bool) $user);
		$this->set('address', $user ? (string) $user['User']['unconfirmed_email'] : '');
		$this->set('token', (string) $token);
		if (!$user || !$this->request->is('post'))
			return null;

		$userID = (int) $user['User']['id'];
		$newAddress = (string) $user['User']['unconfirmed_email'];
		// The address was free when it was asked for, but the link works for a day, so
		// another account may have taken it in between.
		if ($this->User->findByEmail($newAddress))
		{
			$this->forgetAddressChange($userID);
			CookieFlash::set('That address belongs to another account now, so it was not used. Ask for another one in your settings.', 'error');
			return $this->redirect(Auth::isLoggedIn() && Auth::getUserID() === $userID ? '/users/settings' : '/users/login');
		}

		$givenUp = (string) $user['User']['email'];
		$this->User->save(['User' => [
			'id' => $userID,
			'email' => $newAddress,
			'unconfirmed_email' => null,
			'unconfirmed_email_token' => null,
			'unconfirmed_email_cancel_token' => null,
			'unconfirmed_email_created' => null,
		]], ['validate' => false, 'fieldList' => [
			'email', 'unconfirmed_email', 'unconfirmed_email_token',
			'unconfirmed_email_cancel_token', 'unconfirmed_email_created']]);

		$emailer = $this->_getEmailer();
		$emailer->from(['me@tsumego.com' => 'https://tsumego.com']);
		$emailer->to($givenUp);
		$emailer->subject('Your Tsumego Hero address was changed');
		$this->sendNotification($emailer, 'The address of your Tsumego Hero account was changed to ' . $user['User']['unconfirmed_email'] . '.'
			. "\nIf you did not ask for this, write to me@tsumego.com.");

		if (Auth::isLoggedIn() && Auth::getUserID() === $userID)
			Auth::init($this->User->findById($userID));

		CookieFlash::set('Your e-mail address has been changed.', 'success');
		return $this->redirect(Auth::isLoggedIn() ? '/users/settings' : '/users/login');
	}

	/**
	 * Second step of cancelling an address change, opened from the link sent to the
	 * address that is being given up. GET only shows the button, POST cancels.
	 */
	#[HttpGet]
	#[HttpPost]
	public function cancelEmailChange(?string $token = null)
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Cancel address change');
		$user = $this->findByEmailChangeToken('unconfirmed_email_cancel_token', $token);
		$this->set('valid', (bool) $user);
		$this->set('address', $user ? (string) $user['User']['unconfirmed_email'] : '');
		$this->set('token', (string) $token);
		if (!$user || !$this->request->is('post'))
			return null;

		$userID = (int) $user['User']['id'];
		$this->forgetAddressChange($userID);

		if (Auth::isLoggedIn() && Auth::getUserID() === $userID)
			Auth::init($this->User->findById($userID));

		CookieFlash::set('The address change was cancelled.', 'success');
		return $this->redirect(Auth::isLoggedIn() ? '/users/settings' : '/users/login');
	}

	/**
	 * Drop a pending address change, so the links it was sent with stop working.
	 */
	private function forgetAddressChange(int $userID): void
	{
		$this->User->save(['User' => [
			'id' => $userID,
			'unconfirmed_email' => null,
			'unconfirmed_email_token' => null,
			'unconfirmed_email_cancel_token' => null,
			'unconfirmed_email_created' => null,
		]], ['validate' => false, 'fieldList' => [
			'unconfirmed_email', 'unconfirmed_email_token',
			'unconfirmed_email_cancel_token', 'unconfirmed_email_created']]);
	}

	/**
	 * The account waiting for the given link, or null when the link is unknown or old.
	 *
	 * @return array|null
	 */
	private function findByEmailChangeToken(string $field, ?string $token): ?array
	{
		if (!$token)
			return null;

		$user = $this->User->find('first', ['conditions' => [
			$field => $token,
			'deleted IS NULL',
			'unconfirmed_email_created >' => date('Y-m-d H:i:s', strtotime('-' . self::$EMAIL_CHANGE_LINK_VALID_HOURS . ' hours'))]]);
		return $user ?: null;
	}

	/**
	 * Change the password of a local account. The current password is asked for so
	 * that a session left open on a shared computer is not enough to take the
	 * account over.
	 */
	#[HttpPost]
	public function passwordPreference(): mixed
	{
		$this->Authorization->authorize('User', 'editPreferences');

		$user = Auth::getUser();
		if (User::isGoogleUser($user))
		{
			CookieFlash::set('This account signs in through Google, so it has no password.', 'error');
			return $this->redirect('/users/settings');
		}

		$current = (string) ($this->request->data['current'] ?? '');
		$new = (string) ($this->request->data['new1'] ?? '');
		$repeated = (string) ($this->request->data['new2'] ?? '');

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

	/**
	 * Tell the account by mail that its password changed, so that a change made by
	 * somebody else is noticed. The password itself is never part of the mail.
	 */
	private function sendPasswordChangedMail(string $email): void
	{
		$emailer = $this->_getEmailer();
		$emailer->from(['me@tsumego.com' => 'https://tsumego.com']);
		$emailer->to($email);
		$emailer->subject('Your Tsumego Hero password was changed');
		$this->sendNotification($emailer, 'The password of your Tsumego Hero account was changed. If you did not change it, set a new one at '
			. Router::url('/users/resetpassword', true) . ' and write to me@tsumego.com.');
	}

	#[HttpPost]
	public function playerColorPreference(): void
	{
		$this->autoRender = false;
		$this->Authorization->authorize('User', 'editPreferences');

		$color = (int) ($this->request->data['color'] ?? User::PREF_PLAYER_COLOR_RANDOM);
		if ($color < 0 || $color > 1)
			$color = User::PREF_PLAYER_COLOR_RANDOM;

		Auth::saveUserField('pref_player_color', $color);

		$this->response->statusCode(200);
		$this->response->type('application/json');
		$this->response->body(json_encode(['status' => 'ok']));
	}

	#[HttpPost]
	public function boardOrientationPreference(): void
	{
		$this->autoRender = false;
		$this->Authorization->authorize('User', 'editPreferences');

		$orientation = (int) ($this->request->data['orientation'] ?? User::PREF_BOARD_ORIENTATION_RANDOM);
		if ($orientation < 0 || $orientation > 1)
			$orientation = User::PREF_BOARD_ORIENTATION_RANDOM;

		Auth::saveUserField('pref_board_orientation', $orientation);

		$this->response->statusCode(200);
		$this->response->type('application/json');
		$this->response->body(json_encode(['status' => 'ok']));
	}

	#[HttpPost]
	public function dateFormatPreference(): void
	{
		$this->autoRender = false;
		$this->Authorization->authorize('User', 'editPreferences');

		$format = (int) ($this->request->data['format'] ?? User::DATE_FORMAT_ISO);
		if ($format < User::DATE_FORMAT_ISO || $format > User::DATE_FORMAT_BROWSER)
			$format = User::DATE_FORMAT_ISO;

		Auth::saveUserField('date_format_id', $format);

		$this->response->statusCode(200);
		$this->response->type('application/json');
		$this->response->body(json_encode(['status' => 'ok']));
	}

	/**
	 * Whether a new message is announced by an email, see Message::send().
	 */
	#[HttpPost]
	public function emailOnNewMessagePreference(): void
	{
		$this->autoRender = false;
		$this->Authorization->authorize('User', 'editPreferences');

		Auth::saveUserField('email_on_new_message', ($this->request->data['enabled'] ?? '') === '1' ? 1 : 0);

		$this->response->statusCode(200);
		$this->response->type('application/json');
		$this->response->body(json_encode(['status' => 'ok']));
	}

	/**
	 * Store the avatar a player picked. A choice that is not on offer for this
	 * account (a Google picture it does not have) is ignored, the same way the other
	 * preferences ignore a value they can not store.
	 */
	#[HttpPost]
	public function avatarPreference(): void
	{
		$this->autoRender = false;
		$this->Authorization->authorize('User', 'editPreferences');

		$choice = (int) ($this->request->data['avatar'] ?? User::AVATAR_BOARD);
		if (array_key_exists($choice, User::avatarChoices(Auth::getUser())))
			Auth::saveUserField('pref_avatar', $choice);

		$this->response->statusCode(200);
		$this->response->type('application/json');
		$this->response->body(json_encode(['status' => 'ok']));
	}
}
