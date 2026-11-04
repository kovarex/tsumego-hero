<?php

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use InvalidArgumentException;
use RuntimeException;
use App\Model\Table\SetConnectionTable;
use App\Model\Table\SgfTable;
use App\Model\Table\TsumegoStatusTable;
use App\Model\Table\TsumegoTable;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\TimeModeCategoryTable;
use App\Model\Table\TimeModeRankTable;
use App\Model\Table\UserTable;
use App\Utility\AdminActivityLogger;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\Highscore;
use App\Utility\MistakeTraining;
use App\Utility\UploadedAvatar;
use App\Utility\UserProfile;
use App\Utility\UserActivitiesRenderer;
use App\Utility\Util;
use App\Attribute\HttpPost;
use App\Attribute\HttpGet;
use Cake\Log\Log;

//The players: their profile, solve history and settings, the highscores, and the admin pages
//about them. The account itself (signing in, the password, the address, the deletion) is
//AccountController.
class UsersController extends AppController
{
	//How long the two links of an address change work, in hours. The cron forgets a
	//change that nobody confirmed, so it reads the same value.
	public static int $EMAIL_CHANGE_LINK_VALID_HOURS = 24;

	//The latest attempts of all players, or of the one player given (admin only)
	#[HttpGet]
	public function userstats(?string $userID = null): void
	{
		$this->Authorization->authorize('Admin');
		$this->set('_page', 'user');
		$this->set('_title', 'User activities');
		$this->set('userActivitiesRenderer', new UserActivitiesRenderer($this->queries(), userID: $userID === null ? null : (int) $userID));
	}

	//The latest attempts on the problems of the set given, of all players without one (admin only)
	#[HttpGet]
	public function userstats3(?string $setID = null): void
	{
		$this->Authorization->authorize('Admin');
		$this->set('_page', 'user');
		$this->set('_title', 'User activities');
		$this->set('userActivitiesRenderer', new UserActivitiesRenderer($this->queries(), setID: $setID === null ? null : (int) $setID));
		$this->render('userstats');
	}

	//The uploads became the SGF changes of the admin area
	#[HttpGet]
	public function uploads(): Response
	{
		return $this->redirect('/admin/sgf-changes');
	}

	//The admin panel of old, its tables are pages of the admin area now
	#[HttpGet]
	public function adminstats(): Response
	{
		return $this->redirect('/admin');
	}

	#[HttpGet]
	public function highscore(): void
	{
		$this->set('_page', 'levelHighscore');
		$this->set('_title', 'Tsumego Hero - Highscore');
		$this->rememberHighscorePage(Constants::$HIGHSCORE_LEVEL);
		$this->set('users', Highscore::level());
		$this->set('totalUsers', Highscore::activePlayerCount());
	}

	#[HttpGet]
	public function rating(): void
	{
		$this->set('_page', 'ratingHighscore');
		$this->set('_title', 'Tsumego Hero - Rating');
		$this->rememberHighscorePage(Constants::$HIGHSCORE_RATING);
		$this->set('users', Highscore::rating());
		$this->set('totalUsers', Highscore::activePlayerCount());
	}

	#[HttpGet]
	public function added_tags(): void
	{
		$this->set('_page', 'tagHighscore');
		$this->set('_title', 'Tsumego Hero - Added Tags');
		$this->set('tagContributors', Highscore::tags());
		$this->set('totalUsers', Highscore::activePlayerCount());
	}

	#[HttpGet]
	public function achievements(): void
	{
		$this->set('_page', 'achievementHighscore');
		$this->set('_title', 'Tsumego Hero - Achievements Highscore');
		$this->set('users', Highscore::achievements());
		$this->set('totalUsers', null); // no count of the players under this table
	}

	//The best runs of a time mode category at one rank. The category is the one the player
	//played last until the buttons pick one, the rank 15k.
	#[HttpGet]
	public function time_mode(): void
	{
		$this->set('_page', 'timeHighscore');
		$this->set('_title', 'Tsumego Hero - Time Highscore');
		$this->rememberHighscorePage(Constants::$HIGHSCORE_TIME_MODE);

		// the buttons pass the category as its id - 1, and the rank by its name
		if ($this->query('category') !== null)
		{
			$categoryID = (int) $this->query('category') + 1;
			$rankName = $this->query('rank') ?? '15k';
		}
		else
		{
			$categoryID = (int) ((Auth::isLoggedIn() ? Auth::getUser()['last_time_mode_category_id'] : null) ?: TimeModeCategoryTable::SLOW);
			$rankName = '15k';
		}

		$rankNames = $this->fetchTable('TimeModeRank')->find('list', keyField: 'id', valueField: 'name')->orderBy(['id'])->toArray();
		$rankID = array_search($rankName, $rankNames, true) ?: TimeModeRankTable::RANK_15K;

		$this->set('users', Highscore::timeMode($categoryID, $rankID));
		$this->set('totalUsers', Highscore::activePlayerCount());
		$this->set('categoryIndex', $categoryID - 1);
		$this->set('rankName', $rankName);
		$this->set('rankNames', $rankNames);
		$this->set('playedRankIDs', Highscore::playedTimeModeRankIDs($categoryID));
	}

	#[HttpGet]
	public function leaderboard(): void
	{
		$this->set('_page', 'dailyHighscore');
		$this->set('_title', 'Tsumego Hero - Daily Highscore');
		$this->rememberHighscorePage(Constants::$HIGHSCORE_DAILY);
		$this->set('users', Highscore::daily());
		$this->set('totalUsers', Highscore::dailyPlayerCount());
	}

	//The Highscore item of the menu opens the table the signed in player looked at last
	//(AppController::highscoreLink). The tags and achievements tables have no page of their own there.
	private function rememberHighscorePage(int $highscorePageID): void
	{
		if (Auth::isLoggedIn() && (int) Auth::getUser()['highscore_page_id'] !== $highscorePageID)
			Auth::saveUserField('highscore_page_id', $highscorePageID);
	}

	//Redirect to the logged-in user's own profile page.
	#[HttpGet]
	public function profile(): Response
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		return $this->redirect('/users/view/' . Auth::getUserID());
	}

	#[HttpGet]
	public function meSolveHistory(): Response
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		return $this->redirect('/users/solveHistory/' . Auth::getUserID());
	}

	#[HttpGet]
	public function meContributions(): Response
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		return $this->redirect('/tags/user/' . Auth::getUserID());
	}

	#[HttpGet]
	public function view(int $id): mixed
	{
		$this->set('_page', 'user');

		// the own profile is the row the request signed in with
		$user = Auth::isLoggedIn() && Auth::getUserID() === $id ? Auth::getUser() : UserTable::row($id);
		if (!$user)
			return $this->redirect('/sets');
		$this->assertUserVisible($user);
		$userID = (int) $user['id'];
		$this->set('_title', 'Profile of ' . $user['display_name']);
		$this->set('og', UserProfile::openGraph($user));

		// only admins get to the profile of a deleted account, they see who it was
		if ($user['deleted'])
			$user['display_name'] = $user['deleted_name'] . ' ' . UserTable::DELETED_NAME;
		$this->set('user', $user);

		$this->set('dailyResults', UserProfile::dailyResults($userID));
		$this->set('timeModeRanks', UserProfile::timeModeRanks($userID));
		$this->set('timeGraph', UserProfile::timeModeRuns($userID));

		$progress = TsumegoTable::publicProblemProgress($userID);
		$this->set('solvedProblemCount', (int) $user['solved']);
		$this->set('tsumegoCount', TsumegoTable::problemCount());
		$this->set('publishedProblemCount', $progress['total']);
		$this->set('solvedPublishedCount', $progress['solved']);

		$this->set('latestAchievements', UserProfile::latestAchievements($userID));
		$this->set('achievementCounts', UserProfile::achievementCounts($userID));
		$this->set('achievementCount', $this->fetchTable('Achievement')->find()->count());

		// The section of the training shows only for a player who trained.
		$this->set('mistakeTraining', MistakeTraining::profile($userID, MistakeTraining::profileStart()));
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

	//An admin giving up the admin role, confirmed by the password. The profile opens once it is done.
	#[HttpGet]
	#[HttpPost]
	public function demote_admin()
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$admin = Auth::getUser();
		$isGoogleUser = UserTable::isGoogleUser($admin);
		$error = null;
		if ($this->input('demote') !== null)
		{
			if ($isGoogleUser ? !hash_equals(self::demotionToken((int) $admin['id']), (string) $this->input('demote')) : !password_verify((string) $this->input('demote'), $admin['password_hash']))
				$error = $isGoogleUser ? 'The form has expired, please submit it again.' : 'Password incorrect.';
			elseif (!($error = UserTable::changeRole(Auth::getUserID(), UserTable::ROLE_PLAYER)))
				return $this->redirect('/users/view/' . Auth::getUserID());
		}

		$this->set('error', $error);
		$this->set('admin', $admin);
		$this->set('demotionToken', $isGoogleUser ? self::demotionToken((int) $admin['id']) : null);
		return null;
	}

	//What the demotion form of a Google account carries instead of a password: the account has
	//none, and the cookie of the session is sent along by a form on any other site too, so only a
	//form this site rendered for the account can demote it.
	private static function demotionToken(int $userID): string
	{
		return hash_hmac('sha256', 'demote_admin ' . $userID, Configure::read('Security.salt'));
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
		$pageIndex = $this->query('page') !== null ? max(1, (int) $this->query('page')) : 1;
		$count = Util::query("SELECT COUNT(*) FROM tsumego_attempt where user_id = ?", [$userID])[0]['COUNT(*)'];
		$offset = ($pageIndex - 1) * $PAGE_SIZE;

		// The page of attempts is taken first, from the index of the player's attempts, so the
		// collection and the SGF of a problem are looked up for its 500 rows and not for every
		// attempt before it (2 s instead of 0.1 s on the last page of 30000 attempts).
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
	(SELECT * FROM tsumego_attempt WHERE user_id = ? ORDER BY created DESC, id DESC LIMIT " . $PAGE_SIZE . " OFFSET " . $offset . ") tsumego_attempt
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
ORDER BY tsumego_attempt.created DESC, tsumego_attempt.id DESC", [$userID, $userID]);

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

	#[HttpPost]
	public function playerColorPreference(): Response
	{
		return $this->saveChoice('player_color_setting', 'color',
			[UserTable::PLAYER_COLOR_SETTING_RANDOM, UserTable::PLAYER_COLOR_SETTING_ORIGINAL], UserTable::PLAYER_COLOR_SETTING_RANDOM);
	}

	#[HttpPost]
	public function boardOrientationPreference(): Response
	{
		return $this->saveChoice('board_orientation_setting', 'orientation',
			[UserTable::BOARD_ORIENTATION_SETTING_RANDOM, UserTable::BOARD_ORIENTATION_SETTING_ORIGINAL], UserTable::BOARD_ORIENTATION_SETTING_RANDOM);
	}

	#[HttpPost]
	public function dateFormatPreference(): Response
	{
		return $this->saveChoice('date_format_id', 'format',
			range(UserTable::DATE_FORMAT_ISO, UserTable::DATE_FORMAT_BROWSER), UserTable::DATE_FORMAT_ISO);
	}

	//Store the setting the player picked on the settings page; a value that is not one of the
	//choices stores the default.
	/** @param array<int> $choices */
	private function saveChoice(string $column, string $inputName, array $choices, int $default): Response
	{
		$this->Authorization->authorize('User', 'editPreferences');

		$value = (int) ($this->input($inputName) ?? $default);
		Auth::saveUserField($column, in_array($value, $choices, true) ? $value : $default);

		return $this->respondJson(['status' => 'ok']);
	}

	//Whether a new message is announced by an email, see MessageTable::send().
	#[HttpPost]
	public function emailOnNewMessagePreference(): Response
	{
		$this->Authorization->authorize('User', 'editPreferences');

		Auth::saveUserField('email_on_new_message', ($this->input('enabled') ?? '') === '1' ? 1 : 0);

		return $this->respondJson(['status' => 'ok']);
	}

	//Store the avatar a player picked. A choice that is not on offer for this
	//account (a Google picture it does not have) is ignored, the same way the other
	//preferences ignore a value they can not store.
	#[HttpPost]
	public function avatarPreference(): Response
	{
		$this->Authorization->authorize('User', 'editPreferences');

		$choice = (int) ($this->input('avatar') ?? UserTable::AVATAR_BOARD);
		if (array_key_exists($choice, UserTable::avatarChoices(Auth::getUser())))
			Auth::saveUserField('avatar_setting_id', $choice);

		return $this->respondJson(['status' => 'ok']);
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
			$file = $this->uploadedFile('avatar') ?? ($this->contentLength() > UploadedAvatar::maxBytes() ? ['error' => UPLOAD_ERR_INI_SIZE] : null);
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
			Log::error('Avatar upload of user ' . Auth::getUserID() . ': ' . $e->getMessage());
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
