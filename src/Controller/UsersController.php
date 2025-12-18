<?php

App::uses('CakeEmail', 'Network/Email');
App::uses('Constants', 'Utility');
App::uses('SgfParser', 'Utility');
App::uses('AdminActivityLogger', 'Utility');
App::uses('TagConnectionProposalsRenderer', 'Utility');
App::uses('AdminActivityRenderer', 'Utility');
App::uses('AdminActivityType', 'Model');
App::uses('CookieFlash', 'Utility');

class UsersController extends AppController
{
	public $name = 'Users';

	public $pageTitle = 'Users';

	public $helpers = ['Html', 'Form'];

	/**
	 * Shows graph of the rating evolution of the given tsumego
	 * @param string|int|null $id Tsumego ID
	 * @return void
	 */
	public function tsumego_rating_graph($id = null)
	{
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');

		$t = $this->Tsumego->findById($id);
		$sc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
		if (!$sc)
			throw new NotFoundException('SetConnection not found');
		$s = $this->Set->findById($sc['SetConnection']['set_id']);
		if (!$s)
			throw new NotFoundException('Set not found');
		$name = $s['Set']['title'] . ' - ' . $sc['SetConnection']['num'];
		$tsumegoAttempts = $this->TsumegoAttempt->find('all', [
			'order' => 'created ASC',
			'conditions' => [
				'tsumego_id' => $id,
				'NOT' => ['tsumego_rating' => 0],
			],
		]);
		$this->set('rating', $t['Tsumego']['rating']);
		$this->set('name', $name);
		$this->set('tsumegoAttempts', $tsumegoAttempts);
		$this->set('id', $id);
	}

	// shows the publish schedule
	public function showPublishSchedule(): void
	{
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('Schedule');
		$this->loadModel('SetConnection');

		$p = $this->Schedule->find('all', ['order' => 'date ASC', 'conditions' => ['published' => 0]]);

		$pCount = count($p);
		for ($i = 0; $i < $pCount; $i++)
		{
			$t = $this->Tsumego->findById($p[$i]['Schedule']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$p[$i]['Schedule']['num'] = $scT['SetConnection']['num'];
			$p[$i]['Schedule']['set'] = $s['Set']['title'] . ' ' . $s['Set']['title2'] . ' ';
		}
		$this->set('p', $p);
	}

	/**
	 * @return void
	 */
	public function resetpassword()
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Sign In');
		$this->set('sent', !empty($this->data));
		if (empty($this->data))
			return;

		$user = $this->User->findByEmail($this->data['User']['email']);
		if (!$user)
			return;
		$randomString = Util::generateRandomString(20);
		$user['User']['passwordreset'] = $randomString;
		$this->User->save($user);

		$email = $this->_getEmailer();
		$email->from(['me@tsumego.com' => 'https://tsumego.com']);
		$email->to($this->data['User']['email']);
		$email->subject('Password reset for your Tsumego Hero account');
		$email->send('Click the following button to reset your password. If you have not requested the password reset,
then ignore this email. https://' . $_SERVER['HTTP_HOST'] . '/users/newpassword/' . $randomString);
	}

	public function _getEmailer()
	{
		return new CakeEmail();
	}

	// @param string|null $checksum Password reset checksum
	public function newpassword($checksum = null): mixed
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Sign In');
		$done = false;
		if ($checksum == null)
			$checksum = 1;
		$user = $this->User->find('first', ['conditions' => ['passwordreset' => $checksum]]);
		$valid = ($user != null);
		if (!$user)
			return null;

		if ($this->data['User']['password'])
		{
			$user['User']['passwordreset'] = null;
			$user['User']['password_hash'] = password_hash($this->data['User']['password'], PASSWORD_DEFAULT);
			$this->User->save($user);
			CookieFlash::set("Password changed", 'success');
			return $this->redirect("/users/login");
		}

		$this->set('valid', $valid);
		$this->set('done', $done);
		$this->set('checksum', $checksum);
		return null;
	}

	/**
	 * @return void
	 */
	public function routine0() //23:55 signed in users today
	{
		$this->loadModel('Answer');

		$activity = $this->User->find('all', ['order' => ['User.reuse3 DESC']]);
		$todaysUsers = [];
		$today = date('Y-m-d', strtotime('today'));
		$activityCount = count($activity);
		for ($i = 0; $i < $activityCount; $i++)
		{
			$a = new DateTime($activity[$i]['User']['created']);
			if ($a->format('Y-m-d') == $today)
				array_push($todaysUsers, $activity[$i]['User']);
		}

		$token = [];
		$this->Answer->create();
		$token['Answer']['dismissed'] = count($todaysUsers);
		$token['Answer']['created'] = date('Y-m-d H:i:s');
		$this->Answer->save($token);

		$this->set('u', count($todaysUsers));
	}

	/**
	 * @return void
	 */
	public function routine6() //0:30 update user solved field
	{
		$this->loadModel('Answer');
		$this->loadModel('TsumegoStatus');
		$a = $this->Answer->findById(1);
		$uLast = $this->User->find('first', ['order' => 'id DESC']);
		if ($uLast['User']['id'] < $a['Answer']['message'])
		{
			$a['Answer']['message'] = 0;
			$a['Answer']['dismissed'] = 300;
		}
		else
		{
			$a['Answer']['message'] += 300;
			$a['Answer']['dismissed'] += 300;
		}
		$this->Answer->save($a);
	}

	/**
	 * @param string|int|null $uid User ID
	 * @return void
	 */
	public function userstats($uid = null)
	{
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
			$ur[$i]['TsumegoAttempt']['user_name'] = $u['User']['name'];
			$ur[$i]['TsumegoAttempt']['level'] = $u['User']['level'];
			$t = $this->Tsumego->findById($ur[$i]['TsumegoAttempt']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
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
	 * @param string|int|null $sid Set ID
	 * @return void
	 */
	public function userstats3($sid = null)
	{
		$this->set('_page', 'user');
		$this->set('_title', 'USER STATS');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');

		$ts = TsumegoUtil::collectTsumegosFromSet($sid);
		$ids = [];
		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++)
			array_push($ids, $ts[$i]['Tsumego']['id']);

		if ($sid == null)
			$ur = $this->TsumegoAttempt->find('all', ['limit' => 500, 'order' => 'created DESC']);
		else
			$ur = $this->TsumegoAttempt->find('all', ['order' => 'updated DESC', 'conditions' => ['tsumego_id' => $ids]]);

		$urCount = count($ur);
		for ($i = 0; $i < $urCount; $i++)
		{
			$u = $this->User->findById($ur[$i]['TsumegoAttempt']['user_id']);
			$ur[$i]['TsumegoAttempt']['user_name'] = $u['User']['name'];
			$t = $this->Tsumego->findById($ur[$i]['TsumegoAttempt']['tsumego_id']);
			$ur[$i]['TsumegoAttempt']['tsumego_num'] = $t['Tsumego']['num'];
			$ur[$i]['TsumegoAttempt']['tsumego_xp'] = $t['Tsumego']['difficulty'];
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
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
	public function uploads()
	{
		$this->set('_page', 'set');
		$this->set('_title', 'Uploads');
		$this->loadModel('Sgf');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');

		$s = $this->Sgf->find('all', [
			'limit' => 250,
			'order' => 'created DESC',
		]);

		$sCount = count($s);
		for ($i = 0; $i < $sCount; $i++)
		{
			$s[$i]['Sgf']['sgf'] = str_replace("\r", '', $s[$i]['Sgf']['sgf']);
			$s[$i]['Sgf']['sgf'] = str_replace("\n", '"+"\n"+"', $s[$i]['Sgf']['sgf']);

			$u = $this->User->findById($s[$i]['Sgf']['user_id']);
			$s[$i]['Sgf']['user'] = $u['User']['name'];
			$t = $this->Tsumego->findById($s[$i]['Sgf']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$set = $this->Set->findById($t['Tsumego']['set_id']);
			$s[$i]['Sgf']['title'] = $set['Set']['title'] . ' ' . $set['Set']['title2'] . ' #' . $t['Tsumego']['num'];
			$s[$i]['Sgf']['num'] = $t['Tsumego']['num'];

			$s[$i]['Sgf']['delete'] = false;
			$sDiff = $this->Sgf->find('all', ['order' => 'id DESC', 'limit' => 2, 'conditions' => ['tsumego_id' => $s[$i]['Sgf']['tsumego_id']]]);
			$s[$i]['Sgf']['diff'] = $sDiff[1]['Sgf']['id'];
		}
		$this->set('s', $s);
	}

	public function adminstats(): void
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Admin Panel');
		$this->loadModel('User');
		$this->loadModel('DayRecord');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('Tag');

		if (Auth::isAdmin())
		{
			if (isset($this->params['url']['accept']) && isset($this->params['url']['tag_id']))
				if (md5((string) Auth::getUserID()) == $this->params['url']['hash'])
				{
					$tagNamesToApprove = explode('-', $_COOKIE['tagNameList']);
					$tagNamesToApproveCount = count($tagNamesToApprove);
					for ($i = 1; $i < $tagNamesToApproveCount; $i++)
					{
						$tagNameToApprove = $this->Tag->findById(substr($tagNamesToApprove[$i], 1));
						if ($tagNameToApprove != null && $tagNameToApprove['Tag']['approved'] != 1)
						{
							AppController::handleContribution(Auth::getUserID(), 'reviewed');
							if (substr($tagNamesToApprove[$i], 0, 1) == 'a')
							{
								$tagNameToApprove['Tag']['approved'] = '1';
								$this->Tag->save($tagNameToApprove);
								AppController::handleContribution($tagNameToApprove['Tag']['user_id'], 'created_tag');
							}
							else
							{
								$reject = [];
								$reject['Reject']['user_id'] = $tagNameToApprove['Tag']['user_id'];
								$reject['Reject']['type'] = 'tag name';
								$reject['Reject']['text'] = $tagNameToApprove['Tag']['name'];
								$this->Reject->create();
								$this->Reject->save($reject);
								$this->Tag->delete($tagNameToApprove['Tag']['id']);
							}
						}
					}
				}

			if (isset($this->params['url']['delete']) && isset($this->params['url']['hash']))
			{
				$toDelete = $this->User->findById($this->params['url']['delete'] / 1111);
				$del1 = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => $toDelete['User']['id']]]);
				$del2 = $this->TsumegoAttempt->find('all', ['conditions' => ['user_id' => $toDelete['User']['id']]]);
				if (md5($toDelete['User']['name']) == $this->params['url']['hash'])
				{
					foreach ($del1 as $item)
						$this->TsumegoStatus->delete($item['TsumegoStatus']['id']);
					foreach ($del2 as $item)
						$this->TsumegoAttempt->delete($item['TsumegoAttempt']['id']);
					$this->User->delete($toDelete['User']['id']);
					echo '<pre>';
					print_r('Deleted user ' . $toDelete['User']['name']);
					echo '</pre>';
				}
			}
		}

		// Pagination setup
		$perPage = 100;
		$tagNamesPage = isset($this->params['url']['tagnames_page']) ? max(1, (int) $this->params['url']['tagnames_page']) : 1;

		$tagNamesOffset = ($tagNamesPage - 1) * $perPage;

		// Get total counts
		$this->set('tagNamesTotal', $this->Tag->find('count', ['conditions' => ['approved' => 0]]));

		$tags = $this->Tag->find('all', [
			'conditions' => ['approved' => 0],
			'limit' => $perPage,
			'offset' => $tagNamesOffset,
			'order' => 'created DESC'
		]);

		$tagsByKey = $this->Tag->find('all');
		$tKeys = [];
		$tagsByKeyCount = count($tagsByKey);
		for ($i = 0; $i < $tagsByKeyCount; $i++)
			$tKeys[$tagsByKey[$i]['Tag']['id']] = $tagsByKey[$i]['Tag']['name'];

		$tagTsumegos = [];

		$tagNamesCount = count($tags);
		for ($i = 0; $i < $tagNamesCount; $i++)
		{
			$au = $this->User->findById($tags[$i]['Tag']['user_id']);
			$tags[$i]['Tag']['user'] = $this->checkPicture($au);
		}

		$requestDeletion = $this->User->find('all', ['conditions' => ['dbstorage' => 1111]]);

		$this->set('requestDeletion', $requestDeletion);
		$this->set('tagNames', $tags);
		$this->set('tagTsumegos', $tagTsumegos);

		// Pagination data
		$this->set('sgfProposalsRenderer', new SGFProposalsRenderer($this->params['url']));
		$this->set('adminActivityRenderer', new AdminActivityRenderer($this->params['url']));
		$this->set('tagConnectionProposalsRenderer', new TagConnectionProposalsRenderer($this->params['url']));
	}

	private function getUserFromNameOrEmail()
	{
		$input = $this->data['username'];
		if (empty($input))
			return null;
		if ($user = $this->User->findByName($input))
			return $user;
		if ($user = $this->User->findByEmail($input))
			return $user;
		return null;
	}

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

		if (!$this->data['username'])
			return null;
		$user = $this->getUserFromNameOrEmail();
		if (!$user)
		{
			CookieFlash::set('Unknown user', 'error');
			return null;
		}

		if (!$this->validateLogin($this->data, $user))
		{
			CookieFlash::set('Incorrect password', 'error');
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

	public function add()
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Tsumego Hero - Sign Up');

		// Prepare signed redirect URL for Google Sign-In state (fully stateless)
		$redirectUrl = '/sets/';
		$signature = $this->signRedirectUrl($redirectUrl);
		$this->set('redirectUrl', $redirectUrl);
		$this->set('redirectSignature', $signature);

		if (empty($this->data))
			return;

		if ($this->data['User']['password1'] != $this->data['User']['password2'])
		{
			CookieFlash::set('passwords don\'t match', 'error');
			return;
		}

		$userData = $this->data;
		$userData['User']['password_hash'] = password_hash($this->data['User']['password1'], PASSWORD_DEFAULT);
		$userData['User']['name'] = $this->data['User']['name'];
		$userData['User']['email'] = $this->data['User']['email'];

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

		$user = ClassRegistry::init('User')->find('first', ['conditions' => ['name' => $this->data['User']['name']]]);
		if (!$user)
			die("New user created, but it is not possible to load it.");
		CookieFlash::set(__('Registration successful.'), 'success');
		return $this->redirect(['controller' => 'sets', 'action' => 'index']);
	}

	/**
	 * @return void
	 */
	public function highscore()
	{
		$this->set('_page', 'levelHighscore');
		$this->set('_title', 'Tsumego Hero - Highscore');

		$this->loadModel('Tsumego');
		$this->loadModel('Activate');

		$activate = false;
		if (Auth::isLoggedIn())
			$activate = $this->Activate->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]);

		$users = $this->User->find('all', ['limit' => 1000, 'order' => 'level DESC, xp DESC']);
		$this->set('users', $users);
		$this->set('activate', $activate);
	}

	public function rating(): void
	{
		$this->set('_page', 'ratingHighscore');
		$this->set('_title', 'Tsumego Hero - Rating');

		$this->loadModel('TsumegoStatus');
		$this->loadModel('Tsumego');
		if (Auth::isLoggedIn())
		{
			$ux = $this->User->findById(Auth::getUserID());
			$ux['User']['lastHighscore'] = 2;
			$this->User->save($ux);
		}

		$users = $this->User->find('all', ['limit' => 1000, 'order' => 'rating DESC']);
		$this->set('users', $users);
	}

	/**
	 * @return void
	 */
	public function added_tags()
	{
		$this->set('_page', 'timeHighscore');
		$this->set('_title', 'Tsumego Hero - Added Tags');

		$this->set('tagContributors', Util::query("
SELECT
	user.id as user_id,
	user.name as user_name,
	user.external_id as user_external_id,
	user.picture as user_picture,
	user.rating as user_rating,
	count(*) AS tag_count
FROM
	tag_connection
	JOIN user on tag_connection.user_id = user.id
WHERE tag_connection.approved = true
GROUP BY user_id
ORDER BY tag_count DESC
LIMIT 100"));
	}

	/**
	 * @return void
	 */
	public function rewards()
	{
		$this->loadModel('UserContribution');
		$uc = $this->UserContribution->find('first', ['conditions' => ['user_id' => Auth::getUserID()]]);

		if (isset($this->params['url']['action']) && isset($this->params['url']['token']))
			if (md5('level') == $this->params['url']['action'])
			{
				if (md5($uc['UserContribution']['score']) == $this->params['url']['token'])
				{
					$uc['UserContribution']['reward1'] = 1;
					$this->UserContribution->save($uc);
					Auth::getUser()['level'] += 1;
					Auth::saveUser();
					$this->set('refresh', 'refresh');
				}
			}
			elseif (md5('TimeModeAttempt') == $this->params['url']['action'])
			{
				if (md5($uc['UserContribution']['score']) == $this->params['url']['token'])
				{
					$uc['UserContribution']['reward2'] = 1;
					$this->UserContribution->save($uc);
					Auth::getUser()['rating'] += 100;
					Auth::saveUser();
					$this->set('refresh', 'refresh');
				}
			}
			elseif (md5('heropower') == $this->params['url']['action'])
			{
				if (md5($uc['UserContribution']['score']) == $this->params['url']['token'])
				{
					$uc['UserContribution']['reward3'] = 1;
					$this->UserContribution->save($uc);
				}
			}
			elseif (md5('premium') == $this->params['url']['action'])
				if (md5($uc['UserContribution']['score']) == $this->params['url']['token'])
					if (!Auth::hasPremium())
					{
						Auth::getUser()['premium'] = 1;
						Auth::saveUser();
					}
		$reachedGoals = floor($uc['UserContribution']['score'] / 30);
		$goals = [];
		$goals[0] = $reachedGoals >= 1;
		$goals[1] = $reachedGoals >= 2;
		$goals[2] = $reachedGoals >= 3;
		$goals[3] = $reachedGoals >= 4;
		$goalsColor = [];
		$goalsCount = count($goals);
		for ($i = 0; $i < $goalsCount; $i++)
			if ($goals[$i])
				$goalsColor[$i] = '#e9cc2c';
			else
				$goalsColor[$i] = 'black';

		$this->set('goals', $goals);
		$this->set('goalsColor', $goalsColor);
		$this->set('uc', $uc);
	}

	public function achievements(): void
	{
		$this->set('_page', 'achievementHighscore');
		$this->set('_title', 'Tsumego Hero - Achievements Highscore');

		if (Auth::isLoggedIn())
		{
			$ux = $this->User->findById(Auth::getUserID());
			$ux['User']['lastHighscore'] = 2;
			$this->User->save($ux);
		}

		$this->set('users', Util::query("
SELECT
    user.id AS id,
    user.name AS name,
    user.rating AS rating,
    user.picture AS picture,
    user.external_id AS external_id,
    SUM(achievement_status.value) AS achievement_score
FROM user
JOIN achievement_status
    ON achievement_status.user_id = user.id
GROUP BY user.id
ORDER BY achievement_score DESC
LIMIT 100;"));
	}

	/**
	 * @return void
	 */
	public function highscore3()
	{
		$this->set('_page', 'timeHighscore');
		$this->set('_title', 'Tsumego Hero - Time Highscore');

		$this->loadModel('TsumegoStatus');
		$this->loadModel('Tsumego');
		$this->loadModel('TimeModeSession');
		$currentRank = '';
		$params1 = '';
		$params2 = '';

		if (Auth::isLoggedIn())
		{
			$ux = $this->User->findById(Auth::getUserID());
			$ux['User']['lastHighscore'] = 2;
			$this->User->save($ux);
		}

		if (isset($this->params['url']['category']))
		{
			$ro = $this->TimeModeSession->find('all', [
				'order' => 'points DESC',
				'conditions' => [
					'mode' => $this->params['url']['category'],
					'TimeModeAttempt' => $this->params['url']['TimeModeAttempt'],
				],
			]);
			$currentRank = $this->params['url']['TimeModeAttempt'];
			$params1 = $this->params['url']['category'];
			$params2 = $this->params['url']['TimeModeAttempt'];
		}
		else
		{
			if (Auth::isLoggedIn())
				$lastModex = Auth::getUser()['lastMode'] - 1;
			else
				$lastModex = 2;

			$params1 = $lastModex;
			$params2 = '15k';
			$currentRank = $params2;
			$ro = $this->TimeModeSession->find('all', [
				'order' => 'points DESC',
				'conditions' => [
					'mode' => $params1,
					'TimeModeAttempt' => $params2,
				],
			]);
		}
		$roAll = [];
		$roAll['user'] = [];
		$roAll['picture'] = [];
		$roAll['points'] = [];
		$roAll['result'] = [];

		$roCount = count($ro);
		for ($i = 0; $i < $roCount; $i++)
		{
			$us = $this->User->findById($ro[$i]['TimeModeSession']['user_id']);
			$alreadyIn = false;
			$roAllCount = count($roAll['user']);
			for ($j = 0; $j < $roAllCount; $j++)
				if ($roAll['user'][$j] == $us['User']['name'])
					$alreadyIn = true;
			if (!$alreadyIn)
			{
				array_push($roAll['user'], $us['User']['name']);
				array_push($roAll['picture'], $us['User']['picture']);
				array_push($roAll['points'], $ro[$i]['TimeModeSession']['points']);
				array_push($roAll['result'], $ro[$i]['TimeModeSession']['status']);
			}
		}

		$modes = [];
		$modes[0] = [];
		$modes[1] = [];
		$modes[2] = [];
		for ($i = 0; $i < 3; $i++)
		{
			$rank = 15;
			$j = 0;
			while ($rank > -5)
			{
				$kd = 'k';
				$rank2 = $rank;
				if ($rank >= 1)
					$kd = 'k';
				else
				{
					$rank2 = ($rank - 1) * (-1);
					$kd = 'd';
				}
				$modes[$i][$j] = $rank2 . $kd;
				$rank--;
				$j++;
			}
		}
		$modes2 = [];
		$modes2[0] = [];
		$modes2[1] = [];
		$modes2[2] = [];
		for ($i = 0; $i < 3; $i++)
		{
			$rank = 15;
			$j = 0;
			while ($rank > -5)
			{
				$kd = 'k';
				$rank2 = $rank;
				if ($rank >= 1)
					$kd = 'k';
				else
				{
					$rank2 = ($rank - 1) * (-1);
					$kd = 'd';
				}
				$modes2[$i][$j] = $rank2 . $kd;
				$rank--;
				$j++;
			}
		}

		$modesCount = count($modes);
		for ($i = 0; $i < $modesCount; $i++)
		{
			$modesCount = count($modes[$i]);
			for ($j = 0; $j < $modesCount; $j++)
			{
				$mx = $this->TimeModeSession->find('first', [
					'conditions' => [
						'TimeModeAttempt' => $modes[$i][$j],
						'mode' => $i,
					],
				]);
				if ($mx)
					$modes[$i][$j] = 1;
			}
		}

		if (Auth::isLoggedIn())
		{
			$ux = $this->User->findById(Auth::getUserID());
			$ux['User']['lastHighscore'] = 4;
			$this->User->save($ux);
		}

		$this->set('roAll', $roAll);
		$this->set('TimeModeAttempt', $currentRank);
		$this->set('params1', $params1);
		$this->set('params2', $params2);
		$this->set('modes', $modes);
		$this->set('modes2', $modes2);
	}

	/**
	 * @return void
	 */
	public function leaderboard()
	{
		$this->set('_page', 'dailyHighscore');
		$this->set('_title', 'Tsumego Hero - Daily Highscore');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Tsumego');
		$this->loadModel('DayRecord');

		$adminsList = $this->User->find('all', ['order' => 'id ASC', 'conditions' => ['isAdmin >' => 0]]) ?: [];
		$admins = [];
		foreach ($adminsList as $admin)
			$admins [] = $admin['User']['name'];
		$dayRecord = $this->DayRecord->find('all', ['limit' => 2, 'order' => 'id DESC']);
		$userYesterdayName = 'Unknown';
		if (count($dayRecord) > 0 && isset($dayRecord[0]['DayRecord']['user_id']))
		{
			$userYesterday = $this->User->findById($dayRecord[0]['DayRecord']['user_id']);
			if ($userYesterday && isset($userYesterday['User']['name']))
				$userYesterdayName = $userYesterday['User']['name'];
		}

		$users = ClassRegistry::init('User')->query('SELECT user.id, user.name, user.rating, user.external_id, user.picture, user.daily_xp, user.daily_solved FROM user WHERE daily_xp > 0 ORDER BY daily_xp DESC');
		$exportedUsers = [];
		foreach ($users as $user)
			$exportedUsers [] = $user['user'];

		$this->set('leaderboard', $exportedUsers);
		$this->set('uNum', "TODO");
		$this->set('admins', $admins);
		$this->set('dayRecord', $userYesterdayName);
	}

	/**
	 * @param string|int|null $id
	 * @return void
	 */
	public function view($id = null)
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
		$this->set('_title', 'Profile of ' . $user['User']['name']);

		// user edit
		// TODO: should be its own action
		if ($id == Auth::getUserID())
		{
			if (!empty($this->data))
				if (isset($this->data['User']['email']))
				{
					Auth::getUser()['email'] = $this->data['User']['email'];
					Auth::saveUser();
					$this->set('data', $this->data['User']['email']);
				}
			if (isset($this->params['url']['undo']))
				if ($this->params['url']['undo'] / 1111 == $id)
				{
					Auth::getUser()['dbstorage'] = 1;
					Auth::saveUser();
				}
		}

		$tsumegoStatusToRestCount = Util::query("
SELECT
	COUNT(*) AS total
FROM tsumego_status
WHERE
	user_id = ? AND
	tsumego_status.status IN ('S', 'C', 'W') AND
	tsumego_status.updated <= NOW() - INTERVAL 1 YEAR;", [$id])[0]['total'];

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

		$deletedTsumegoStatusCount = 0;
		$tsumegoCount = TsumegoFilters::empty()->calculateCount();
		$canResetOldTsumegoStatuses = Util::getPercent($user['User']['solved'], $tsumegoCount) >= Constants::$MINIMUM_PERCENT_OF_TSUMEGOS_TO_BE_SOLVED_BEFORE_RESET_IS_ALLOWED;
		if (isset($this->params['url']['delete-uts']))
			if ($this->params['url']['delete-uts'] == 'true' && $canResetOldTsumegoStatuses)
			{
				$utsCount = count($uts);
				for ($j = 0; $j < $utsCount; $j++)
					if ($uts[$j]['TsumegoStatus']['created'] < $lastYear)
					{
						$this->TsumegoStatus->delete($uts[$j]['TsumegoStatus']['id']);
						$deletedTsumegoStatusCount++;
					}
				$utx = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => $id]]);
				$correctCounter = 0;
				$utxCount = count($utx);
				for ($j = 0; $j < $utxCount; $j++)
					if ($utx[$j]['TsumegoStatus']['status'] == 'S' || $utx[$j]['TsumegoStatus']['status'] == 'W' || $utx[$j]['TsumegoStatus']['status'] == 'C')
						$correctCounter++;

				$user['User']['solved'] = $correctCounter;
				$user['User']['dbstorage'] = 99;
				$this->User->save($user);
			}

		$asCount = count($as);
		for ($i = 0; $i < $asCount; $i++)
		{
			$as[$i]['AchievementStatus']['a_title'] = $ach[$as[$i]['AchievementStatus']['achievement_id'] - 1]['Achievement']['name'];
			$as[$i]['AchievementStatus']['a_description'] = $ach[$as[$i]['AchievementStatus']['achievement_id'] - 1]['Achievement']['description'];
			$as[$i]['AchievementStatus']['a_image'] = $ach[$as[$i]['AchievementStatus']['achievement_id'] - 1]['Achievement']['image'];
			$as[$i]['AchievementStatus']['a_color'] = $ach[$as[$i]['AchievementStatus']['achievement_id'] - 1]['Achievement']['color'];
			$as[$i]['AchievementStatus']['a_id'] = $ach[$as[$i]['AchievementStatus']['achievement_id'] - 1]['Achievement']['id'];
			$as[$i]['AchievementStatus']['a_xp'] = $ach[$as[$i]['AchievementStatus']['achievement_id'] - 1]['Achievement']['xp'];
		}

		$aNum = $this->AchievementStatus->find('all', ['conditions' => ['user_id' => $id]]);
		$asx = $this->AchievementStatus->find('first', ['conditions' => ['user_id' => $id, 'achievement_id' => 46]]);
		$aNumx = count($aNum);
		if ($asx != null)
			$aNumx = $aNumx + $asx['AchievementStatus']['value'] - 1;

		$user['User']['name'] = $this->checkPicture($user['User']);

		$aCount = $this->Achievement->find('all');

		$this->set('dailyResults', $dailyResults);
		$this->set('user', $user);
		$this->set('tsumegoCount', $tsumegoCount);
		$this->set('deletedTsumegoStatusCount', $deletedTsumegoStatusCount);
		$this->set('tsumegoStatusToRestCount', $tsumegoStatusToRestCount);
		$this->set('as', $as);
		$this->set('aNum', $aNumx);
		$this->set('aCount', $aCount);
		$this->set('canResetOldTsumegoStatuses', $canResetOldTsumegoStatuses);
	}

	/**
	 * @param string|int|null $id Donation ID
	 * @return void
	 */
	public function donate($id = null)
	{
		$this->set('_page', 'home');
		$this->set('_title', 'Tsumego Hero - Upgrade');

		$overallCounter = 0;
		$sandboxSets = $this->Set->find('all', ['conditions' => ['public' => 0]]);
		$sandboxSetsCount = count($sandboxSets);
		for ($i = 0; $i < $sandboxSetsCount; $i++)
		{
			$ts = TsumegoUtil::collectTsumegosFromSet($sandboxSets[$i]['Set']['id']);
			$overallCounter += count($ts);
		}

		$setsWithPremium = [];
		$tsumegosWithPremium = [];
		$swp = $this->Set->find('all', ['conditions' => ['premium' => 1]]);
		$swpCount = count($swp);
		for ($i = 0; $i < $swpCount; $i++)
		{
			array_push($setsWithPremium, $swp[$i]['Set']['id']);
			$twp = TsumegoUtil::collectTsumegosFromSet($swp[$i]['Set']['id']);
			$twpCount = count($twp);
			for ($j = 0; $j < $twpCount; $j++)
				array_push($tsumegosWithPremium, $twp[$j]);
		}

		$this->set('id', $id);
		$this->set('overallCounter', $overallCounter);
		$this->set('premiumSets', $swp);
		$this->set('premiumTsumegos', count($tsumegosWithPremium));
	}

	/**
	 * @return void
	 */
	public function authors()
	{
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
		$count[0]['collections'] = 's: <a href="/sets/view/41">Life & Death - Intermediate</a> and <a href="/sets/view/122">Gokyo Shumyo 1-4</a>';
		$count[0]['count'] = 0;
		$count[1]['author'] = 'Alexandre Dinerchtein';
		$count[1]['collections'] = ': <a href="/sets/view/109">Problems from Professional Games</a>';
		$count[1]['count'] = 0;
		$count[2]['author'] = 'David Ulbricht';
		$count[2]['collections'] = ': <a href="/sets/view/41">Life & Death - Intermediate</a>';
		$count[2]['count'] = 0;
		$count[3]['author'] = 'Bradford Malbon';
		$count[3]['collections'] = 's: <a href="/sets/view/104">Easy Life</a> and <a href="/sets/view/105">Easy Kill</a>';
		$count[3]['count'] = 0;
		$count[4]['author'] = 'Ryan Smith';
		$count[4]['collections'] = 's: <a href="/sets/view/67">Korean Problem Academy 1-4</a>';
		$count[4]['count'] = 0;
		$count[5]['author'] = 'Fupfv';
		$count[5]['collections'] = ': <a href="/sets/view/139">Gokyo Shumyo 4</a>';
		$count[5]['count'] = 0;
		$count[6]['author'] = 'саша черных';
		$count[6]['collections'] = ': <a href="/sets/view/137">Tsumego Master</a>';
		$count[6]['count'] = 0;
		$count[7]['author'] = 'Timo Kreuzer';
		$count[7]['collections'] = ': <a href="/sets/view/137">Tsumego Master</a>';
		$count[7]['count'] = 0;
		$count[8]['author'] = 'David Mitchell';
		$count[8]['collections'] = ': <a href="/sets/view/143">Diabolical</a>';
		$count[8]['count'] = 10;
		$count[9]['author'] = 'Omicron';
		$count[9]['collections'] = ': <a href="/sets/view/145">Tesujis in Real Board Positions</a>';
		$count[9]['count'] = 0;
		$count[10]['author'] = 'Sadaharu';
		$count[10]['collections'] = ': <a href="/sets/view/146">Tsumego of Fortitude</a>, <a href="/sets/view/166">Secret Tsumego from Hong Dojo</a>, <a href="/sets/view/158">Beautiful Tsumego</a> and more.';
		$count[10]['count'] = 0;
		$count[11]['author'] = 'Jérôme Hubert';
		$count[11]['collections'] = ': <a href="/sets/view/150">Kanzufu</a> and more.';
		$count[11]['count'] = 0;
		$count[12]['author'] = 'Kaan Malçok';
		$count[12]['collections'] = ': <a href="/sets/view/163">Xuanxuan Qijing</a>';
		$count[12]['count'] = 0;

		$this->set('count', $count);
		$this->set('t', $authors);
	}

	/**
	 * @param string|int|null $id Success ID
	 * @return void
	 */
	public function success($id = null)
	{
		$this->set('_page', 'home');
		$this->set('_title', 'Tsumego Hero - Success');

		$s = $this->User->findById(Auth::getUserID());
		Auth::getUser()['reward'] = date('Y-m-d H:i:s');
		Auth::getUser()['premium'] = 1;
		Auth::saveUser();

		$Email = new CakeEmail();
		$Email->from(['me@joschkazimdars.com' => 'https://tsumego-hero.com']);
		$Email->to('joschka.zimdars@googlemail.com');
		$Email->subject('Upgrade');
		if (Auth::isLoggedIn())
			$ans = Auth::getUser()['name'] . ' ' . Auth::getUser()['email'];
		else
			$ans = 'no login';
		$Email->send($ans);
		if (Auth::isLoggedIn())
		{
			$Email = new CakeEmail();
			$Email->from(['me@joschkazimdars.com' => 'https://tsumego-hero.com']);
			$Email->to(Auth::getUser()['email']);
			$Email->subject('Tsumego Hero');
			$ans = '
Hello ' . Auth::getUser()['name'] . ',

Thank you!. Your account should be upgraded automatically.

--
Best Regards
Joschka Zimdars';
			$Email->send($ans);
		}
		$this->set('id', $id);
	}

	/**
	 * @param string|int|null $id Penalty ID
	 * @return void
	 */
	public function penalty($id = null)
	{
		$this->set('_page', 'home');
		$this->set('_title', 'Tsumego Hero - Penalty');
		Auth::getUser()['penalty'] = Auth::getUser()['penalty'] + 1;
		Auth::saveUser();
		$this->set('id', $id);
	}

	/**
	 * @param string|int|null $id Set ID
	 * @return void
	 */
	public function sets($id = null)
	{
		$this->set('id', $id);
	}

	/**
	 * @return void
	 */
	public function logout()
	{
		Auth::logout();
	}

	private function validateLogin($data, $user): bool
	{
		if (!$user)
			return false;
		return password_verify($data['password'], $user['User']['password_hash']);
	}

	/**
	 * @return CakeResponse|null
	 */
	public function googlesignin()
	{
		$name = '';
		$email = '';
		$picture = '';
		$id_token = $_POST['credential'];
		$client_id = '986748597524-05gdpjqrfop96k6haga9gvj1f61sji6v.apps.googleusercontent.com';
		$token_info = file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . $id_token);
		$token_data = json_decode($token_info, true);
		if (isset($token_data['aud']) && $token_data['aud'] == $client_id)
		{
			$name = $token_data['name'];
			$email = $token_data['email'];
			$picture = $token_data['picture'];
		}
		else
			echo 'Invalid token';
		$externalId = 'g__' . $token_data['sub'];
		$u = $this->User->find('first', ['conditions' => ['external_id' => $externalId]]);
		if ($u == null)
		{
			$imageUrl = $picture;
			$imageContent = file_get_contents($imageUrl);

			$userData = [];
			$userData['User']['name'] = 'g__' . $name;
			$userData['User']['email'] = 'g__' . $email;
			$userData['User']['password_hash'] = 'not used';
			$userData['User']['external_id'] = $externalId;

			if ($imageContent === false)
				$userData['User']['picture'] = 'default.png';
			else
			{
				$userData['User']['picture'] = $externalId . '.png';
				file_put_contents('img/google/' . $externalId . '.png', $imageContent);
			}
			$this->User->create();
			$this->User->save($userData, true);
			$u = $this->User->find('first', ['conditions' => ['external_id' => $externalId]]);
		}
		$this->signIn($u);

		// Get redirect URL from state parameter (set via data-state in the button)
		// Google Sign-In provides built-in CSRF protection via g_csrf_token cookie
		// We use HMAC signature to prevent redirect URL tampering (stateless)
		$redirect = '/sets/';
		$stateJson = $_POST['state'] ?? null;
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

	/**
	 * @return void
	 */
	public function delete_account()
	{
		$redirect = false;
		$status = '';

		if (!empty($this->data))
			if (isset($this->data['User']['delete']))
				if (password_verify($this->data['User']['delete'], Auth::getUser()['password_hash']))
				{
					Auth::getUser()['dbstorage'] = 1111;
					Auth::saveUser();
					$redirect = true;
				}
				else
					$status = '<p style="color:#d63a49">Password incorrect.</p>';
		Auth::getUser()['name'] = $this->checkPicture(Auth::getUser());

		$this->set('redirect', $redirect);
		$this->set('status', $status);
		$this->set('u', Auth::getUser());
	}

	/**
	 * @return void
	 */
	public function demote_admin()
	{
		$redirect = false;
		$status = '';
		if (!Auth::isLoggedIn())
			return;

		if (!empty($this->data))
			if (isset($this->data['User']['demote']))
				if (password_verify($this->data['User']['demote'], Auth::getUser()['password_hash']))
				{
					Auth::getUser()['isAdmin'] = 0;
					Auth::saveUser();
					$redirect = true;
				}
				else
					$status = '<p style="color:#d63a49">Password incorrect.</p>';
		Auth::getUser()['name'] = $this->checkPicture(Auth::getUser());

		$this->set('redirect', $redirect);
		$this->set('status', $status);
		$this->set('u', Auth::getUser());
	}

	public function solveHistory($userID)
	{
		$PAGE_SIZE = 500;
		$pageIndex = isset($this->params->query['page']) ? max(1, (int) $this->params->query['page']) : 1;
		$count = Util::query("SELECT COUNT(*) FROM tsumego_attempt where user_id = ?", [$userID])[0]['COUNT(*)'];
		$offset = ($pageIndex - 1) * $PAGE_SIZE;

		$attempts = Util::query("
SELECT
	set.title AS set_title,
	tsumego_attempt.tsumego_id AS tsumego_id,
	set_connection.id AS set_connection_id,
	set_connection.num AS num,
	tsumego_status.status AS status,
	tsumego_attempt.created AS created,
	tsumego_attempt.gain AS xp_gain,
	tsumego_attempt.solved AS solved,
	tsumego_attempt.misplays AS misplays,
	tsumego_attempt.user_rating AS user_rating
FROM
	tsumego_attempt
	JOIN set_connection ON set_connection.tsumego_id = tsumego_attempt.tsumego_id
	LEFT JOIN tsumego_status ON tsumego_status.user_id = ? AND tsumego_status.tsumego_id = tsumego_attempt.tsumego_id
	JOIN `set` ON set_connection.set_id = `set`.id
WHERE
	tsumego_attempt.user_id=?
ORDER BY created DESC
LIMIT " . $PAGE_SIZE . "
OFFSET " . $offset, [$userID, $userID]);

		$this->set('_page', 'solveHistory');
		$this->set('_title', 'Solve history');
		$this->set('count', $count);
		$this->set('pageIndex', $pageIndex);
		$this->set('PAGE_SIZE', $PAGE_SIZE);
		$this->set('attempts', $attempts);
	}

	public function acceptSGFProposal($sgfID)
	{
		if (!Auth::isAdmin())
			return $this->redirect('/sets');

		$proposalToApprove = ClassRegistry::init('Sgf')->findById($sgfID);
		if (!$proposalToApprove)
		{
			CookieFlash::set('Sgf proposal doesn\'t exist.', 'fail');
			return $this->redirect('/users/adminstats');
		}

		$proposalToApprove = $proposalToApprove['Sgf'];
		if ($proposalToApprove['accepted'] != 0)
		{
			CookieFlash::set('Sgf proposal was already accepted.', 'fail');
			return $this->redirect('/users/adminstats');
		}
		$proposalToApprove['accepted'] = 1;
		ClassRegistry::init('Sgf')->save($proposalToApprove);

		AppController::handleContribution(Auth::getUserID(), 'reviewed');
		AppController::handleContribution($proposalToApprove['user_id'], 'made_proposal');
		CookieFlash::set('Sgf proposal accepted', 'success');
		return $this->redirect('/users/adminstats');
	}

	public function rejectSGFProposal($sgfID)
	{
		if (!Auth::isAdmin())
			return $this->redirect('/sets');

		$proposalToReject = ClassRegistry::init('Sgf')->findById($sgfID);
		if (!$proposalToReject)
		{
			CookieFlash::set('Sgf proposal doesn\'t exist.', 'fail');
			return $this->redirect('/users/adminstats');
		}

		$proposalToReject = $proposalToReject['Sgf'];

		if ($proposalToReject['accepted'] != 0)
		{
			CookieFlash::set('Sgf proposal was already accepted.', 'fail');
			return $this->redirect('/users/adminstats');
			;
		}

		$reject = [];
		$reject['user_id'] = $proposalToReject['user_id'];
		$reject['tsumego_id'] = $proposalToReject['tsumego_id'];
		$reject['type'] = 'proposal';
		ClassRegistry::init('Reject')->create();
		ClassRegistry::init('Reject')->save($reject);
		ClassRegistry::init('Sgf')->delete($proposalToReject['id']);

		CookieFlash::set('Sgf proposal rejected', 'success');
		return $this->redirect('/users/adminstats');
	}

	public function acceptTagConnectionProposal($tagConnectionID)
	{
		if (!Auth::isAdmin())
			return $this->redirect('/sets');

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
		AppController::handleContribution(Auth::getUserID(), 'reviewed');
		AppController::handleContribution($proposalToApprove['user_id'], 'added_tag');
		return $this->redirect('/users/adminstats');
	}

	public function rejectTagConnectionProposal($tagConnectionID)
	{
		if (!Auth::isAdmin())
			return $this->redirect('/sets');

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

		$reject = [];
		$reject['user_id'] = $proposalToReject['user_id'];
		$reject['tsumego_id'] = $proposalToReject['tsumego_id'];
		$reject['type'] = 'tag';
		$tagName = ClassRegistry::init('Tag')->findById($proposalToReject['tag_id'])['Tag'];
		$reject['type'] = $tagName['name'];

		$tag = ClassRegistry::init('Tag')->findById($proposalToReject['tag_id'])['Tag'];
		AdminActivityLogger::log(AdminActivityType::REJECT_TAG, $proposalToReject['tsumego_id'], null, $tag['name'], null);

		ClassRegistry::init('Reject')->create();
		ClassRegistry::init('Reject')->save($reject);
		ClassRegistry::init('TagConnection')->delete($proposalToReject['id']);

		CookieFlash::set('Tag proposal rejected', 'success');
		return $this->redirect('/users/adminstats');
	}
}
