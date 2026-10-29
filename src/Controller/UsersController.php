<?php

use App\Model\Entity\User;
use App\Model\Table\SetTable;
use App\Model\Table\SetConnectionTable;
use App\Model\Table\SgfTable;
use App\Model\Table\TsumegoStatusTable;
use App\Model\Table\TsumegoTable;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\RejectTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\UserTable;
use App\Utility\AdminActivityLogger;
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
use App\Utility\SgfCommentReorientation;
use App\Utility\UploadedAvatar;
use App\Utility\UserActivitiesRenderer;
use App\Utility\Util;
use App\Attribute\HttpPost;
use App\Attribute\HttpGet;
use App\Model\Table\TimeModeSessionStatusTable;

class UsersController extends AppController
{
	public $helpers = ['Html', 'Form', 'Highscore'];

	protected ?PwnedPasswordCheckerInterface $passwordChecker = null;

	protected ?GoogleTokenVerifierInterface $tokenVerifier = null;

	//A password that login alone would never reject, used to spend the same time on a
	//name that has no account. It costs what PASSWORD_DEFAULT costs, and
	//LoginComponentTest fails when the two drift apart.
	private static string $DUMMY_PASSWORD_HASH = '$2y$12$BZ5TgNGDEJZktTLaEt5Tx.zJfbhdsQxv5/O7wfVeX0QpDwVSdTGg.';

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
	public function resetpassword(): void
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Sign In');
		$this->set('sent', !empty($this->request->data));
		if (empty($this->request->data))
			return;

		$userEmail = $this->request->data['User']['email'];

		// Only allow password reset for local users (not Google Sign-In users)
		$user = UserTable::findLocalUserByEmail($userEmail);
		if (!$user)
		{
			// An address that can not get a link is answered like one that has no account,
			// so the form says nothing about which addresses are taken. The sign-in page
			// points Google accounts to their button before they submit this form.
			return;
		}

		// Another mail on top of the one just sent only fills the inbox. The answer is
		// the same either way, so nothing about the account is revealed by skipping it.
		$askedAt = $user->password_reset_token_created;
		if ($askedAt && $askedAt->getTimestamp() > strtotime('-' . self::$PASSWORD_RESET_MINUTES_BETWEEN_MAILS . ' minutes'))
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
		$this->fetchTable('User')->updateAll([
			'password_reset_token' => $randomString,
			'password_reset_token_created' => date('Y-m-d H:i:s')], ['id' => $user->id]);
	}

	//Send the mail that carries a link. The token in it is remembered only when this
	//returns true, so a mail server that does not answer leaves nothing behind and the
	//user can go through the flow again right away.
	//Returns whether the mail left
	/** @param CakeEmail $emailer The mailer from _getEmailer(), which a test replaces */
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

	//Send a mail that only tells the account about a change that already happened. It is
	//not part of the change, so a mail server that does not answer is logged instead of
	//turned into an error page.
	/** @param CakeEmail $emailer The mailer from _getEmailer(), which a test replaces */
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

		$user = $checksum === null ? null : $this->fetchTable('User')->find()->where([
			'password_reset_token' => $checksum,
			'deleted IS' => null,
			'password_reset_token_created >' => date('Y-m-d H:i:s', strtotime('-' . self::$PASSWORD_RESET_LINK_VALID_HOURS . ' hours'))])->first();
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

		$this->fetchTable('User')->updateAll([
			'password_reset_token' => null,
			'password_reset_token_created' => null,
			'password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $user->id]);
		$this->sendPasswordChangedMail($user->email);
		CookieFlash::set('Password changed', 'success');
		return $this->redirect('/users/login');
	}

	//The latest attempts of all players, or of the one player given (admin only)
	#[HttpGet]
	public function userstats(?string $userID = null): void
	{
		$this->Authorization->authorize('Admin');
		$this->set('_page', 'user');
		$this->set('_title', 'User activities');
		$this->set('userActivitiesRenderer', new UserActivitiesRenderer($this->params['url'], userID: $userID === null ? null : (int) $userID));
	}

	//The latest attempts on the problems of the set given, of all players without one (admin only)
	#[HttpGet]
	public function userstats3(?string $setID = null): void
	{
		$this->Authorization->authorize('Admin');
		$this->set('_page', 'user');
		$this->set('_title', 'User activities');
		$this->set('userActivitiesRenderer', new UserActivitiesRenderer($this->params['url'], setID: $setID === null ? null : (int) $setID));
		$this->render('userstats');
	}

	//The uploads became the SGF changes of the admin area
	#[HttpGet]
	public function uploads(): CakeResponse
	{
		return $this->redirect('/admin/sgf-changes');
	}

	//The admin panel of old, its tables are pages of the admin area now
	#[HttpGet]
	public function adminstats(): CakeResponse
	{
		return $this->redirect('/admin');
	}

	private function getUserFromNameOrEmail(): ?User
	{
		$input = $this->request->data['username'];
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
			$googleUser = $this->fetchTable('User')->find()->where(['email' => $this->request->data['username']])->first();
			if ($googleUser && UserTable::isGoogleUser($googleUser))
				CookieFlash::set('This account uses Google Sign-In. Please use the "Sign in with Google" button.', 'info');
			else
				CookieFlash::set('Wrong username or password.', 'error');
			return null;
		}

		// Google users should use "Sign in with Google" button
		if (UserTable::isGoogleUser($user))
		{
			CookieFlash::set('This account uses Google Sign-In. Please use the "Sign in with Google" button.', 'info');
			return null;
		}

		if (!$this->validateLogin($this->request->data, $user))
		{
			CookieFlash::set('Wrong username or password.', 'error');
			return null;
		}

		$this->signIn(UserTable::rowOf($user));

		// Verify and use redirect URL from POST data
		$redirect = $this->getVerifiedRedirectUrl(
			$this->request->data('redirect'),
			$this->request->data('redirect_signature')
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

		if (empty($this->request->data))
			return;

		$password = (string) ($this->request->data['User']['password1'] ?? '');
		$problem = $this->passwordProblem($password, (string) ($this->request->data['User']['password2'] ?? ''));
		if ($problem)
		{
			CookieFlash::set($problem, 'error');
			return;
		}

		$userTable = $this->fetchTable('User');
		if ($userTable->exists(['email' => $this->request->data['User']['email']]))
		{
			CookieFlash::set('Email already exists', 'error');
			return;
		}

		$normalizedName = UserTable::normalizeDisplayName($this->request->data['User']['name']);
		if (UserTable::containsCharactersTheForumCannotStore($normalizedName))
		{
			CookieFlash::set('The name must not contain emoji or other rare symbols, please choose another one.', 'error');
			return;
		}
		$uniqueDisplayName = UserTable::generateUniqueDisplayName($normalizedName);
		$user = $userTable->newEntity([
			'name' => $this->request->data['User']['name'],
			'email' => $this->request->data['User']['email'],
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

		$newDisplayName = UserTable::normalizeDisplayName($this->request->data['User']['display_name'] ?? '');

		if (empty($newDisplayName))
		{
			CookieFlash::set(__('Display name cannot be empty.'), 'error');
			return $this->redirect(['action' => 'changename']);
		}

		if (UserTable::containsCharactersTheForumCannotStore($newDisplayName))
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

		if ($newDisplayName === $user->display_name)
		{
			CookieFlash::set(__('Please choose a different display name.'), 'error');
			return $this->redirect(['action' => 'changename']);
		}

		// Check uniqueness - another user might have this display_name
		if ($this->fetchTable('User')->exists(['display_name' => $newDisplayName, 'id !=' => $userId]))
		{
			CookieFlash::set(__('This display name is already taken.'), 'error');
			return $this->redirect(['action' => 'changename']);
		}

		// Update display_name (including forum sync)
		UserTable::updateDisplayName($userId, $newDisplayName);

		AdminActivityLogger::log(AdminActivityTypeTable::DISPLAY_NAME_CHANGE, null, null, $user->display_name, $newDisplayName, $userId);

		// Clear the forced flag and update the session
		Auth::saveUserField('display_name', $newDisplayName);
		Auth::saveUserField('needs_display_name_change', false);

		CookieFlash::set(__('Display name updated successfully.'), 'success');
		return $this->redirect('/sets/');
	}

	#[HttpGet]
	public function highscore(): void
	{
		$this->set('_page', 'levelHighscore');
		$this->set('_title', 'Tsumego Hero - Highscore');

		$this->set('users', $this->queryHighscoreWithSelfView(
			"SELECT id, display_name, external_id, picture, avatar_setting_id, uploaded_avatar_hash, email, rating, premium_id, level, xp, solved,
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
			Auth::saveUserField('highscore_page_id', Constants::$HIGHSCORE_RATING);

		$this->set('users', $this->queryHighscoreWithSelfView(
			"SELECT id, display_name, external_id, picture, avatar_setting_id, uploaded_avatar_hash, email, rating, premium_id,
				ROW_NUMBER() OVER (ORDER BY rating DESC) as position
			FROM user
			WHERE deleted IS NULL"
		));
		$this->set('totalUsers', $this->countActiveUsers());
	}

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
				user.avatar_setting_id as avatar_setting_id,
				user.uploaded_avatar_hash as uploaded_avatar_hash,
				user.email as email,
				user.rating as rating,
				user.premium_id AS premium_id,
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
			Auth::saveUserField('highscore_page_id', Constants::$HIGHSCORE_RATING);

		$userId = Auth::isLoggedIn() ? Auth::getUserID() : -1;

		$this->set('users', $this->queryHighscoreWithSelfView("
			SELECT
				user.id AS id,
				user.display_name AS display_name,
				user.rating AS rating,
				user.picture AS picture,
				user.avatar_setting_id AS avatar_setting_id,
				user.uploaded_avatar_hash AS uploaded_avatar_hash,
				user.external_id AS external_id,
				user.premium_id AS premium_id,
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

	#[HttpGet]
	public function time_mode(): void
	{
		$this->set('_page', 'timeHighscore');
		$this->set('_title', 'Tsumego Hero - Time Highscore');

		// Build rank name -> ID lookup
		$rankNameToId = $this->fetchTable('TimeModeRank')->find('list', keyField: 'name', valueField: 'id')->toArray();

		$currentRank = '';
		$params1 = '';
		$params2 = '';

		if (Auth::isLoggedIn())
			Auth::saveUserField('highscore_page_id', Constants::$HIGHSCORE_TIME_MODE);

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
				$categoryId = TimeModeCategoryTable::SLOW;

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
				user.avatar_setting_id AS avatar_setting_id,
				user.uploaded_avatar_hash AS uploaded_avatar_hash,
				user.email AS email,
				user.rating AS rating,
				user.premium_id AS premium_id,
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
		return $this->fetchTable('User')->find()->where(['deleted IS' => null])->count();
	}

	//Run a single CTE query that returns the top ranked rows plus the rows around
	//the current user. The $rankedQuery must be a SELECT that produces a `position`
	//column via ROW_NUMBER().
	//$rankedQuery: SQL SELECT with ROW_NUMBER() ... as position
	//$idColumn: Column name used to identify the current user (default: 'id')
	//$topRows: How many top rows are always listed
	/**
	 * @param array<int, mixed> $extraParams Extra bound parameters for the ranked query
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

	//Build array of rank names from 15k down to 5d (matching time_mode_rank table order).
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

	#[HttpGet]
	public function leaderboard(): void
	{
		$this->set('_page', 'dailyHighscore');
		$this->set('_title', 'Tsumego Hero - Daily Highscore');

		$userId = Auth::isLoggedIn() ? Auth::getUserID() : -1;

		$this->set('users', $this->queryHighscoreWithSelfView(
			"SELECT id, name, display_name, external_id, picture, avatar_setting_id, uploaded_avatar_hash, email, rating, premium_id, daily_xp, daily_solved,
				ROW_NUMBER() OVER (ORDER BY daily_xp DESC) as position
			FROM user
			WHERE (daily_xp > 0 OR id = ?) AND deleted IS NULL",
			'id',
			[$userId]
		));

		$this->set('totalUsers', Util::query("SELECT COUNT(*) as cnt FROM user WHERE daily_xp > 0 AND deleted IS NULL")[0]['cnt']);
	}

	//Redirect to the logged-in user's own profile page.
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

		$as = $this->fetchTable('AchievementStatus')->find()->where(['user_id' => $id])->orderByDesc('created')->limit(12)->all();

		$user = UserTable::row((int) $id);
		if (!$user)
			return $this->redirect('/sets');
		$this->assertUserVisible($user);
		$this->set('_title', 'Profile of ' . $user['display_name']);

		// The profile is shared with the avatar the player picked, at the size a
		// preview takes. What the site draws is a png of known size and type; an
		// avatar of another host is shared as its url, and only the size a host is
		// told to send is known.
		$avatarChoice = UserTable::avatarChoice($user);
		$og = [
			'title' => 'Profile of ' . $user['display_name'],
			'description' => $user['display_name'] . ' on Tsumego',
			'image_alt' => 'Avatar of ' . $user['display_name'],
			'type' => 'profile',
			'profile_username' => $user['display_name'],
			'site_name' => 'Tsumego',
		];
		if ($avatarChoice === UserTable::AVATAR_BOARD)
		{
			$og['image'] = Router::url(Avatar::pngUrl($user['display_name']), true);
			$og['image_type'] = 'image/png';
			$og['image_width'] = Avatar::SHARE_SIZE;
			$og['image_height'] = Avatar::SHARE_SIZE;
		}
		elseif ($avatarChoice === UserTable::AVATAR_UPLOADED)
		{
			// An uploaded picture is stored at one size and type, so both are known.
			$og['image'] = Router::url(UserTable::getAvatarUrl($user), true);
			$og['image_type'] = 'image/webp';
			$og['image_width'] = UploadedAvatar::SIZE;
			$og['image_height'] = UploadedAvatar::SIZE;
		}
		else
		{
			$og['image'] = UserTable::getAvatarUrl($user, Avatar::SHARE_SIZE);
			if ($avatarChoice === UserTable::AVATAR_GRAVATAR)
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
            s2.time_mode_session_status_id = " . TimeModeSessionStatusTable::SOLVED . " AND
            s2.user_id = ?
        ORDER BY time_mode_rank.id DESC                            -- highest id = best rank
        LIMIT 1
    ) AS best_solved_rank_name,

    -- 2) Number of sessions in this category
    COUNT(s.id) AS session_count

FROM
	time_mode_category c
	LEFT JOIN time_mode_session s ON s.user_id = ? AND s.time_mode_category_id = c.id
GROUP BY c.id, c.name;", [$user['id'], $user['id']]));

		$this->set('timeGraph', Util::query('
SELECT
    DATE(time_mode_session.created) AS category,
    SUM(CASE WHEN time_mode_session.time_mode_session_status_id = ' . TimeModeSessionStatusTable::SOLVED . ' THEN 1 ELSE 0 END) AS Passes,
    SUM(CASE WHEN time_mode_session.time_mode_session_status_id = ' . TimeModeSessionStatusTable::FAILED . ' THEN 1 ELSE 0 END) AS Fails
FROM time_mode_session
WHERE time_mode_session.user_id = ? AND time_mode_session.challenge_id IS NULL
GROUP BY DATE(time_mode_session.created)
ORDER BY category DESC', [$user['id']]));

		$progress = TsumegoTable::publicProblemProgress((int) $user['id']);
		$this->set('solvedProblemCount', (int) $user['solved']);
		$this->set('tsumegoCount', TsumegoTable::problemCount());
		$this->set('publishedProblemCount', $progress['total']);
		$this->set('solvedPublishedCount', $progress['solved']);

		$achievementById = [];
		foreach ($this->fetchTable('Achievement')->find()->all() as $achievement)
			$achievementById[$achievement->id] = $achievement;

		$asList = [];
		foreach ($as as $item)
		{
			if (!isset($achievementById[$item->achievement_id]))
				continue;
			$achievement = $achievementById[$item->achievement_id];
			$asList[] = [
				'a_title' => $achievement->name,
				'a_description' => $achievement->description,
				'a_image' => $achievement->image,
				'a_color' => $achievement->color,
				'a_id' => $achievement->id,
				'a_xp' => $achievement->xp,
				'a_earned_count' => $item->value,
				'created' => $item->created->format('Y-m-d H:i:s'),
			];
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
		if ($user['deleted'])
			$user['display_name'] = $user['deleted_name'] . ' ' . UserTable::DELETED_NAME;

		$achievementCount = $this->fetchTable('Achievement')->find()->count();

		// The section of the training shows only for a player who trained.
		$this->set('mistakeTraining', MistakeTraining::profile((int) $user['id'], MistakeTraining::profileStart()));

		$this->set('dailyResults', $dailyResults);
		$this->set('user', $user);
		$this->set('as', $as);
		$this->set('aNum', $aNumx);
		$this->set('aEarnedTimes', $aEarnedTimes);
		$this->set('achievementCount', $achievementCount);
		return null;
	}

	//How the site behaves for the logged in player: appearance, how a problem is
	//dealt and the progress actions. The profile stays the public page, so
	//everything that only changes the player's own view lives here. The boards in
	//the rotation are picked in the dropdown in the header.
	#[HttpGet]
	public function settings(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$this->set('_page', 'user');
		$this->set('_title', 'Settings');

		$user = Auth::getUser();
		$this->set('user', $user);
		$this->set('tsumegoStatusToRestCount', TsumegoStatusTable::countOldProgress($user['id']));
		$this->set('canResetOldTsumegoStatuses', UserTable::canResetOldProgress($user));
		$this->set('unconfirmedEmail', self::waitingAddress($user));

		return null;
	}

	//The address an account is waiting for the confirmation of, or null when there is
	//none or its link is too old to work.
	private static function waitingAddress(array $user): ?string
	{
		if (empty($user['unconfirmed_email']) || empty($user['unconfirmed_email_created']))
			return null;
		if (strtotime($user['unconfirmed_email_created']) <= strtotime('-' . self::$EMAIL_CHANGE_LINK_VALID_HOURS . ' hours'))
			return null;
		return (string) $user['unconfirmed_email'];
	}

	#[HttpGet]
	public function authors(): void
	{
		$this->set('_page', 'about');
		$this->set('_title', 'Tsumego Hero - About');

		$count = [];
		$count[0]['author'] = 'Innokentiy Zabirov';
		$count[0]['collections'] = 's: <a href="' . SetTable::url(41) . '">Life & Death - Intermediate</a> and <a href="' . SetTable::url(122) . '">Gokyo Shumyo 1-4</a>';
		$count[0]['count'] = 0;
		$count[1]['author'] = 'Alexandre Dinerchtein';
		$count[1]['collections'] = ': <a href="' . SetTable::url(109) . '">Problems from Professional Games</a>';
		$count[1]['count'] = 0;
		$count[2]['author'] = 'David Ulbricht';
		$count[2]['collections'] = ': <a href="' . SetTable::url(41) . '">Life & Death - Intermediate</a>';
		$count[2]['count'] = 0;
		$count[3]['author'] = 'Bradford Malbon';
		$count[3]['collections'] = 's: <a href="' . SetTable::url(104) . '">Easy Life</a> and <a href="' . SetTable::url(105) . '">Easy Kill</a>';
		$count[3]['count'] = 0;
		$count[4]['author'] = 'Ryan Smith';
		$count[4]['collections'] = 's: <a href="' . SetTable::url(67) . '">Korean Problem Academy 1-4</a>';
		$count[4]['count'] = 0;
		$count[5]['author'] = 'Fupfv';
		$count[5]['collections'] = ': <a href="' . SetTable::url(139) . '">Gokyo Shumyo 4</a>';
		$count[5]['count'] = 0;
		$count[6]['author'] = 'саша черных';
		$count[6]['collections'] = ': <a href="' . SetTable::url(137) . '">Tsumego Master</a>';
		$count[6]['count'] = 0;
		$count[7]['author'] = 'Timo Kreuzer';
		$count[7]['collections'] = ': <a href="' . SetTable::url(137) . '">Tsumego Master</a>';
		$count[7]['count'] = 0;
		$count[8]['author'] = 'David Mitchell';
		$count[8]['collections'] = ': <a href="' . SetTable::url(143) . '">Diabolical</a>';
		$count[8]['count'] = 10;
		$count[9]['author'] = 'Omicron';
		$count[9]['collections'] = ': <a href="' . SetTable::url(145) . '">Tesujis in Real Board Positions</a>';
		$count[9]['count'] = 0;
		$count[10]['author'] = 'Sadaharu';
		$count[10]['collections'] = ': <a href="' . SetTable::url(146) . '">Tsumego of Fortitude</a>, <a href="' . SetTable::url(166) . '">Secret Tsumego from Hong Dojo</a>, <a href="' . SetTable::url(158) . '">Beautiful Tsumego</a> and more.';
		$count[10]['count'] = 0;
		$count[11]['author'] = 'Jérôme Hubert';
		$count[11]['collections'] = ': <a href="' . SetTable::url(150) . '">Kanzufu</a> and more.';
		$count[11]['count'] = 0;
		$count[12]['author'] = 'Kaan Malçok';
		$count[12]['collections'] = ': <a href="' . SetTable::url(163) . '">Xuanxuan Qijing</a>';
		$count[12]['count'] = 0;

		$this->set('count', $count);
	}

	#[HttpGet]
	public function logout(): void
	{
		Auth::logout();
	}

	private function validateLogin(array $data, User $user): bool
	{
		// The minimum length is not asked here: an account whose password is shorter
		// still has to sign in.
		return password_verify($data['password'], $user->password_hash);
	}

	/** @return CakeResponse|null */
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
			$userData['password_hash'] = 'google_oauth';
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

			// Update email if changed and not empty
			if ($email && $user->email !== $email)
				$updates['email'] = $email;

			// Apply updates if any changed
			if (!empty($updates))
			{
				$userTable->updateAll($updates, ['id' => $user->id]);
				$user->set($updates);
			}
		}
		$this->signIn(UserTable::rowOf($user));

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

	//How long the two links of an address change work, in hours. The cron forgets a
	//change that nobody confirmed, so it reads the same value.
	public static int $EMAIL_CHANGE_LINK_VALID_HOURS = 24;

	//How long the link of a password reset works, in hours.
	private static int $PASSWORD_RESET_LINK_VALID_HOURS = 2;

	//How long to wait before another reset mail is sent for the same account.
	private static int $PASSWORD_RESET_MINUTES_BETWEEN_MAILS = 5;

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

		$emailer = $this->_getEmailer();
		$emailer->from(['me@tsumego.com' => 'https://tsumego.com']);
		$emailer->to(Auth::getUser()['email']);
		$emailer->subject('Deletion of your Tsumego Hero account');
		if (!$this->linkMailWasSent($emailer, 'You asked to delete your Tsumego Hero account ' . UserTable::displayName(Auth::getUser()) . '.
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
		$user = $token ? $this->fetchTable('User')->find()
			->select(['id', 'display_name'])
			->where([
				'deletion_token' => $token,
				'deleted IS' => null,
				'deletion_token_created >' => date('Y-m-d H:i:s', strtotime('-' . self::$DELETION_LINK_VALID_HOURS . ' hours'))])
			->first() : null;
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

	//Restores a deleted account (admin only).
	#[HttpPost]
	public function restore_account(?string $id = null)
	{
		$this->Authorization->authorize('Admin', 'restoreAccount');
		$user = $this->fetchTable('User')->find()->select(['id', 'deleted_name'])->where(['id' => (int) $id])->first();
		if (!$user)
			throw new NotFoundException('User not found');

		$name = $user->deleted_name;
		if ($error = UserTable::restoreAccount($user->id))
		{
			CookieFlash::set('Account ' . $name . ' can\'t be restored: ' . $error, 'error');
			return $this->redirect('/admin/deleted-accounts');
		}
		AdminActivityLogger::log(AdminActivityTypeTable::RESTORE_USER, null, null, null, $name);
		CookieFlash::set('Account ' . $name . ' restored', 'success');
		return $this->redirect('/admin/deleted-accounts');
	}

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
					if ($error = UserTable::changeRole(Auth::getUserID(), UserTable::ROLE_PLAYER))
						$status = '<p style="color:#d63a49">' . h($error) . '</p>';
					else
						$redirect = true;
				}
				else
					$status = '<p style="color:#d63a49">Password incorrect.</p>';

		$this->set('redirect', $redirect);
		$this->set('status', $status);
		$this->set('admin', Auth::getUser());
	}

	#[HttpGet]
	public function solveHistory(string $userID): void
	{
		$profileUser = UserTable::row((int) $userID);
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
		ORDER BY " . SetConnectionTable::displayOrderSql('ss', 'sc') . "
		LIMIT 1)
	LEFT JOIN sgf ON sgf.id = " . SgfTable::currentVersionIDSql('tsumego_attempt.tsumego_id') . "
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

		$proposalToApprove = $this->fetchTable('Sgf')->find()->where(['id' => $sgfID])->first();
		if (!$proposalToApprove)
		{
			CookieFlash::set('Sgf proposal doesn\'t exist.', 'fail');
			return $this->redirect('/admin/sgf-proposals');
		}

		if ($proposalToApprove->sgf_status_id != SgfTable::STATUS_PROPOSED)
		{
			CookieFlash::set('Sgf proposal was already decided.', 'fail');
			return $this->redirect('/admin/sgf-proposals');
		}
		$replacedSgfID = SgfTable::findCurrentVersionID($proposalToApprove->tsumego_id);
		SgfTable::accept($proposalToApprove->id, Auth::getUserID());
		SgfCommentReorientation::afterAccepted($proposalToApprove->id);

		CookieFlash::set('Sgf proposal accepted', 'success');

		$userToApprove = $this->fetchTable('User')->find()->select(['display_name'])->where(['id' => $proposalToApprove->user_id])->first();
		AdminActivityLogger::log(AdminActivityTypeTable::ACCEPT_PROPOSAL, $proposalToApprove->tsumego_id, null, null, $userToApprove?->display_name);

		$tsumegoID = $proposalToApprove->tsumego_id;
		ProposalFeedback::send(
			(int) $proposalToApprove->user_id,
			'Your proposal for ' . ProposalFeedback::problemName($tsumegoID) . ' was accepted by ' . ProposalFeedback::adminName() . ', it is the current version of the problem now.',
			$tsumegoID,
			$proposalToApprove->description,
			ProposalFeedback::commentFromRequest($this->request->data['comment'] ?? null),
			$this->_getEmailer(),
			ProposalFeedback::diffUrl($proposalToApprove->id, $replacedSgfID));

		return $this->redirect('/admin/sgf-proposals');
	}

	#[HttpPost]
	public function rejectSGFProposal(string $sgfID)
	{
		$this->Authorization->authorize('Admin');

		$proposalToReject = $this->fetchTable('Sgf')->find()->where(['id' => $sgfID])->first();
		if (!$proposalToReject)
		{
			CookieFlash::set('Sgf proposal doesn\'t exist.', 'fail');
			return $this->redirect('/admin/sgf-proposals');
		}

		if ($proposalToReject->sgf_status_id != SgfTable::STATUS_PROPOSED)
		{
			CookieFlash::set('Sgf proposal was already decided.', 'fail');
			return $this->redirect('/admin/sgf-proposals');
		}

		$comment = ProposalFeedback::commentFromRequest($this->request->data['comment'] ?? null);
		if ($comment === '')
		{
			CookieFlash::set('Write the proposer why the proposal is rejected.', 'fail');
			return $this->redirect('/admin/sgf-proposals');
		}

		$rejectTable = $this->fetchTable('Reject');
		$rejectTable->saveOrFail($rejectTable->newEntity([
			'user_id' => $proposalToReject->user_id,
			'rejected_by_user_id' => Auth::getUserID(),
			'comment' => $comment,
			'tsumego_id' => $proposalToReject->tsumego_id,
			'sgf_id' => $proposalToReject->id,
			'reject_type_id' => RejectTable::TYPE_PROPOSAL]));
		// kept, so the proposer can still see what they proposed
		SgfTable::setStatus($proposalToReject->id, SgfTable::STATUS_REJECTED);
		CookieFlash::set('Sgf proposal rejected', 'success');

		$userToReject = $this->fetchTable('User')->find()->select(['display_name'])->where(['id' => $proposalToReject->user_id])->first();
		AdminActivityLogger::log(AdminActivityTypeTable::REJECT_PROPOSAL, $proposalToReject->tsumego_id, null, null, $userToReject?->display_name);

		$tsumegoID = $proposalToReject->tsumego_id;
		ProposalFeedback::send(
			(int) $proposalToReject->user_id,
			'Your proposal for ' . ProposalFeedback::problemName($tsumegoID) . ' was rejected by ' . ProposalFeedback::adminName() . '.',
			$tsumegoID,
			$proposalToReject->description,
			$comment,
			$this->_getEmailer(),
			ProposalFeedback::diffUrl($proposalToReject->id, SgfTable::findCurrentVersionID($tsumegoID)));

		return $this->redirect('/admin/sgf-proposals');
	}

	//Puts a rejected SGF proposal back among the ones waiting for a decision.
	#[HttpPost]
	public function reopenSGFProposal(string $sgfID)
	{
		$this->Authorization->authorize('Admin');

		$proposalToReopen = $this->fetchTable('Sgf')->find()->where(['id' => $sgfID])->first();
		if (!$proposalToReopen || $proposalToReopen->sgf_status_id != SgfTable::STATUS_REJECTED)
		{
			CookieFlash::set('There is no rejected sgf proposal to reopen.', 'fail');
			return $this->redirect('/admin/sgf-proposals');
		}
		SgfTable::setStatus($proposalToReopen->id, SgfTable::STATUS_PROPOSED);
		$this->fetchTable('Reject')->deleteAll(['sgf_id' => $proposalToReopen->id]);
		CookieFlash::set('Sgf proposal reopened', 'success');

		$proposer = $this->fetchTable('User')->find()->select(['display_name'])->where(['id' => $proposalToReopen->user_id])->first();
		AdminActivityLogger::log(AdminActivityTypeTable::REOPEN_PROPOSAL, $proposalToReopen->tsumego_id, null, null, $proposer?->display_name);

		return $this->redirect('/admin/sgf-proposals');
	}

	#[HttpPost]
	public function acceptTagConnectionProposal(string $tagConnectionID)
	{
		$this->Authorization->authorize('Admin');

		$tagConnectionTable = $this->fetchTable('TagConnection');
		$proposalToApprove = $tagConnectionTable->find()->where(['id' => (int) $tagConnectionID])->first();
		if (!$proposalToApprove)
		{
			CookieFlash::set('Tag proposal doesn\'t exist.', 'fail');
			return $this->redirect('/admin/tag-proposals');
		}

		if ($proposalToApprove->approved)
		{
			CookieFlash::set('Tag proposal was already accepted.', 'fail');
			return $this->redirect('/admin/tag-proposals');
		}
		$proposalToApprove->approved = true;

		$tag = $this->fetchTable('Tag')->get($proposalToApprove->tag_id);
		AdminActivityLogger::log(AdminActivityTypeTable::ACCEPT_TAG, $proposalToApprove->tsumego_id, null, null, $tag->name);
		$tagConnectionTable->saveOrFail($proposalToApprove);

		// tags are accepted by the dozen, so the proposer only hears about it when the admin had something to say
		$comment = ProposalFeedback::commentFromRequest($this->request->data['comment'] ?? null);
		if ($comment !== '')
		{
			$tsumegoID = $proposalToApprove->tsumego_id;
			ProposalFeedback::send(
				(int) $proposalToApprove->user_id,
				'Your tag ' . $tag->name . ' on ' . ProposalFeedback::problemName($tsumegoID) . ' was accepted by ' . ProposalFeedback::adminName() . '.',
				$tsumegoID,
				null,
				$comment,
				$this->_getEmailer());
		}
		return $this->redirect('/admin/tag-proposals');
	}

	#[HttpPost]
	public function rejectTagConnectionProposal(string $tagConnectionID)
	{
		$this->Authorization->authorize('Admin');

		$tagConnectionTable = $this->fetchTable('TagConnection');
		$proposalToReject = $tagConnectionTable->find()->where(['id' => (int) $tagConnectionID])->first();
		if (!$proposalToReject)
		{
			CookieFlash::set('Tag proposal doesn\'t exist.', 'fail');
			return $this->redirect('/admin/tag-proposals');
		}

		if ($proposalToReject->approved)
		{
			CookieFlash::set('Tag proposal was already accepted.', 'fail');
			return $this->redirect('/admin/tag-proposals');
		}

		$comment = ProposalFeedback::commentFromRequest($this->request->data['comment'] ?? null);
		if ($comment === '')
		{
			CookieFlash::set('Write the proposer why the tag is rejected.', 'fail');
			return $this->redirect('/admin/tag-proposals');
		}

		$tag = $this->fetchTable('Tag')->get($proposalToReject->tag_id);

		AdminActivityLogger::log(AdminActivityTypeTable::REJECT_TAG, $proposalToReject->tsumego_id, null, $tag->name, null);

		$rejectTable = $this->fetchTable('Reject');
		$rejectTable->saveOrFail($rejectTable->newEntity([
			'user_id' => $proposalToReject->user_id,
			'rejected_by_user_id' => Auth::getUserID(),
			'comment' => $comment,
			'tsumego_id' => $proposalToReject->tsumego_id,
			'reject_type_id' => RejectTable::TYPE_TAG,
			'tag_id' => $tag->id,
			'tag_name' => $tag->name]));
		$tagConnectionTable->deleteOrFail($proposalToReject);

		$tsumegoID = $proposalToReject->tsumego_id;
		ProposalFeedback::send(
			(int) $proposalToReject->user_id,
			'Your tag ' . $tag->name . ' on ' . ProposalFeedback::problemName($tsumegoID) . ' was rejected by ' . ProposalFeedback::adminName() . '.',
			$tsumegoID,
			null,
			$comment,
			$this->_getEmailer());

		CookieFlash::set('Tag proposal rejected', 'success');
		return $this->redirect('/admin/tag-proposals');
	}

	#[HttpPost]
	public function deleteOldTsumegoStatuses(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/sets');

		$user = Auth::getUser();
		if (!UserTable::canResetOldProgress($user))
			return $this->redirect('/users/settings');

		$deleted = TsumegoStatusTable::deleteOldProgress((int) $user['id']);
		CookieFlash::set('Deleted progress on ' . $deleted . ' problems', 'success');
		return $this->redirect('/users/settings');
	}

	//Ask for a different e-mail address. The account keeps the address it has until
	//the player confirms the new one from the link sent there, so a session left open
	//somewhere can not move the account to an address of somebody else.
	#[HttpPost]
	public function emailPreference(): mixed
	{
		$this->Authorization->authorize('User', 'editPreferences');

		$user = Auth::getUser();
		$email = trim((string) ($this->request->data['User']['email'] ?? ''));
		$password = (string) ($this->request->data['User']['password'] ?? '');

		if (UserTable::isGoogleUser($user))
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

		if ($this->fetchTable('User')->exists(['email' => $email]))
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

	//Second step of an address change, opened from the link sent to the new address.
	//It works without being signed in, like the password reset. GET only shows the
	//confirmation button (email scanners open links), the change happens on POST.
	#[HttpGet]
	#[HttpPost]
	public function confirmEmailChange(?string $token = null)
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Confirm address');
		$user = $this->findByEmailChangeToken('unconfirmed_email_token', $token);
		$this->set('valid', (bool) $user);
		$this->set('address', $user ? (string) $user->unconfirmed_email : '');
		$this->set('token', (string) $token);
		if (!$user || !$this->request->is('post'))
			return null;

		$userID = $user->id;
		$newAddress = (string) $user->unconfirmed_email;
		// The address was free when it was asked for, but the link works for a day, so
		// another account may have taken it in between.
		if ($this->fetchTable('User')->exists(['email' => $newAddress]))
		{
			$this->forgetAddressChange($userID);
			CookieFlash::set('That address belongs to another account now, so it was not used. Ask for another one in your settings.', 'error');
			return $this->redirect(Auth::isLoggedIn() && Auth::getUserID() === $userID ? '/users/settings' : '/users/login');
		}

		$givenUp = $user->email;
		$this->fetchTable('User')->updateAll([
			'email' => $newAddress,
			'unconfirmed_email' => null,
			'unconfirmed_email_token' => null,
			'unconfirmed_email_cancel_token' => null,
			'unconfirmed_email_created' => null], ['id' => $userID]);

		$emailer = $this->_getEmailer();
		$emailer->from(['me@tsumego.com' => 'https://tsumego.com']);
		$emailer->to($givenUp);
		$emailer->subject('Your Tsumego Hero address was changed');
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
		$user = $this->findByEmailChangeToken('unconfirmed_email_cancel_token', $token);
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

	//Drop a pending address change, so the links it was sent with stop working.
	private function forgetAddressChange(int $userID): void
	{
		$this->fetchTable('User')->updateAll([
			'unconfirmed_email' => null,
			'unconfirmed_email_token' => null,
			'unconfirmed_email_cancel_token' => null,
			'unconfirmed_email_created' => null], ['id' => $userID]);
	}

	//The account waiting for the given link, or null when the link is unknown or old.
	private function findByEmailChangeToken(string $field, ?string $token): ?User
	{
		if (!$token)
			return null;

		return $this->fetchTable('User')->find()->where([
			$field => $token,
			'deleted IS' => null,
			'unconfirmed_email_created >' => date('Y-m-d H:i:s', strtotime('-' . self::$EMAIL_CHANGE_LINK_VALID_HOURS . ' hours'))])->first();
	}

	//Change the password of a local account. The current password is asked for so
	//that a session left open on a shared computer is not enough to take the
	//account over.
	#[HttpPost]
	public function passwordPreference(): mixed
	{
		$this->Authorization->authorize('User', 'editPreferences');

		$user = Auth::getUser();
		if (UserTable::isGoogleUser($user))
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

	//Tell the account by mail that its password changed, so that a change made by
	//somebody else is noticed. The password itself is never part of the mail.
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

		$color = (int) ($this->request->data['color'] ?? UserTable::PLAYER_COLOR_SETTING_RANDOM);
		if ($color < 0 || $color > 1)
			$color = UserTable::PLAYER_COLOR_SETTING_RANDOM;

		Auth::saveUserField('player_color_setting', $color);

		$this->response->statusCode(200);
		$this->response->type('application/json');
		$this->response->body(json_encode(['status' => 'ok']));
	}

	#[HttpPost]
	public function boardOrientationPreference(): void
	{
		$this->autoRender = false;
		$this->Authorization->authorize('User', 'editPreferences');

		$orientation = (int) ($this->request->data['orientation'] ?? UserTable::BOARD_ORIENTATION_SETTING_RANDOM);
		if ($orientation < 0 || $orientation > 1)
			$orientation = UserTable::BOARD_ORIENTATION_SETTING_RANDOM;

		Auth::saveUserField('board_orientation_setting', $orientation);

		$this->response->statusCode(200);
		$this->response->type('application/json');
		$this->response->body(json_encode(['status' => 'ok']));
	}

	#[HttpPost]
	public function dateFormatPreference(): void
	{
		$this->autoRender = false;
		$this->Authorization->authorize('User', 'editPreferences');

		$format = (int) ($this->request->data['format'] ?? UserTable::DATE_FORMAT_ISO);
		if ($format < UserTable::DATE_FORMAT_ISO || $format > UserTable::DATE_FORMAT_BROWSER)
			$format = UserTable::DATE_FORMAT_ISO;

		Auth::saveUserField('date_format_id', $format);

		$this->response->statusCode(200);
		$this->response->type('application/json');
		$this->response->body(json_encode(['status' => 'ok']));
	}

	//Whether a new message is announced by an email, see MessageTable::send().
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

	//Store the avatar a player picked. A choice that is not on offer for this
	//account (a Google picture it does not have) is ignored, the same way the other
	//preferences ignore a value they can not store.
	#[HttpPost]
	public function avatarPreference(): void
	{
		$this->autoRender = false;
		$this->Authorization->authorize('User', 'editPreferences');

		$choice = (int) ($this->request->data['avatar'] ?? UserTable::AVATAR_BOARD);
		if (array_key_exists($choice, UserTable::avatarChoices(Auth::getUser())))
			Auth::saveUserField('avatar_setting_id', $choice);

		$this->response->statusCode(200);
		$this->response->type('application/json');
		$this->response->body(json_encode(['status' => 'ok']));
	}

	//Make a picture the player uploaded their avatar. It takes the place of the picture
	//they had before, so an account has one uploaded picture at most.
	#[HttpPost]
	public function avatarUpload(): mixed
	{
		$this->Authorization->authorize('User', 'editPreferences');

		try
		{
			// A request larger than post_max_size arrives without any of its files, which
			// is a picture too large and not a form sent without one.
			$file = $_FILES['avatar'] ?? ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > UploadedAvatar::maxBytes() ? ['error' => UPLOAD_ERR_INI_SIZE] : null);
			$avatar = UploadedAvatar::fromUpload($file);
			$hash = UploadedAvatar::store(Auth::getUserID(), $avatar);
			Auth::saveUserFields(['uploaded_avatar_hash' => $hash, 'avatar_setting_id' => UserTable::AVATAR_UPLOADED]);
			CookieFlash::set('Your picture is your avatar now.', 'success');
		}
		catch (InvalidArgumentException $e)
		{
			CookieFlash::set($e->getMessage(), 'error');
		}
		catch (RuntimeException $e)
		{
			CakeLog::write('error', 'Avatar upload of user ' . Auth::getUserID() . ': ' . $e->getMessage());
			CookieFlash::set('The picture could not be stored, please try again.', 'error');
		}

		return $this->redirect('/users/settings');
	}

	//Remove the picture the player uploaded. An account that showed it shows the board
	//again, the avatar every account has.
	#[HttpPost]
	public function avatarRemove(): mixed
	{
		$this->Authorization->authorize('User', 'editPreferences');

		$user = Auth::getUser();
		if (!empty($user['uploaded_avatar_hash']))
		{
			$fields = ['uploaded_avatar_hash' => null];
			if ((int) $user['avatar_setting_id'] === UserTable::AVATAR_UPLOADED)
				$fields['avatar_setting_id'] = UserTable::AVATAR_BOARD;
			Auth::saveUserFields($fields);
			UploadedAvatar::remove((int) $user['id']);
			CookieFlash::set('Your picture was removed.', 'success');
		}

		return $this->redirect('/users/settings');
	}
}
