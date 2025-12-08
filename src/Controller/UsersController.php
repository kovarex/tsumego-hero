<?php

App::uses('CakeEmail', 'Network/Email');
App::uses('Constants', 'Utility');
App::uses('SgfParser', 'Utility');
App::uses('AdminActivityLogger', 'Utility');
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

	public function publish(): void
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
	public function routine22() //achievement highscore
	{
		$aNum = count($this->Achievement->find('all') ?: []);
		$as = $this->AchievementStatus->find('all');
		$as2 = [];

		$asCount = count($as);
		for ($i = 0; $i < $asCount; $i++)
			if ($as[$i]['AchievementStatus']['achievement_id'] != 46)
				array_push($as2, $as[$i]['AchievementStatus']['user_id']);
			else
			{
				$as46counter = $as[$i]['AchievementStatus']['value'];
				while ($as46counter > 0)
				{
					array_push($as2, $as[$i]['AchievementStatus']['user_id']);
					$as46counter--;
				}
			}
		$as3 = array_count_values($as2);
		$uaNum = [];
		$uaId = [];
		foreach ($as3 as $key => $value)
		{
			$u = $this->User->findById($key);
			$u['User']['name'] = $this->checkPicture($u);
			array_push($uaNum, $value);
			array_push($uaId, $u['User']['id']);
		}
		array_multisort($uaNum, $uaId);

		$toJson = [];
		$toJson['uaNum'] = $uaNum;
		$toJson['uaId'] = $uaId;
		$toJson['aNum'] = $aNum;

		file_put_contents('json/achievement_highscore.json', json_encode($toJson));
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
	 * @param string|int|null $p Page parameter
	 * @return void
	 */
	public function stats($p = null)
	{
		$this->set('_page', 'user');
		$this->set('_title', 'PAGE STATS');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Comment');
		$this->loadModel('User');
		$this->loadModel('DayRecord');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('AdminActivity');
		$this->loadModel('SetConnection');

		$today = date('Y-m-d', strtotime('today'));

		if (isset($this->params['url']['c']))
		{
			$cx = $this->Comment->findById($this->params['url']['c']);
			$cx['Comment']['status'] = $this->params['url']['s'];
			$this->Comment->save($cx);
		}

		$comments = $this->Comment->find('all', ['order' => 'created DESC']);
		$c1 = [];
		$c2 = [];
		$c3 = [];
		$commentsCount = count($comments);
		for ($i = 0; $i < $commentsCount; $i++)
			if (is_numeric($comments[$i]['Comment']['status']))
				if ($comments[$i]['Comment']['status'] == 0)
					array_push($c1, $comments[$i]);
		$comments = $c1;
		$commentsCount = count($comments);
		for ($i = 0; $i < $commentsCount; $i++)
		{
			$t = $this->Tsumego->findById($comments[$i]['Comment']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			if ($s['Set']['public'] == 1)
				array_push($c2, $comments[$i]);
			else
				array_push($c3, $comments[$i]);
		}
		if ($p == 'public')
			$comments = $c2;
		elseif ($p == 'sandbox')
			$comments = $c3;
		elseif ($p != 0 && is_numeric($p))
			$comments = $this->Comment->find('all', ['order' => 'created DESC', 'conditions' => ['user_id' => $p]]);

		$todaysUsers = [];
		$activity = $this->User->find('all', ['order' => ['User.reuse3 DESC']]);

		$commentsCount = count($comments);
		for ($i = 0; $i < $commentsCount; $i++)
		{
			$userID = $comments[$i]['Comment']['user_id'];
			$activityCount = count($activity);
			for ($j = 0; $j < $activityCount; $j++)
				if ($activity[$j]['User']['id'] == $userID)
				{
					$comments[$i]['Comment']['user_id'] = $activity[$j]['User']['id'];
					$comments[$i]['Comment']['user_name'] = $activity[$j]['User']['name'];
					$comments[$i]['Comment']['email'] = $activity[$j]['User']['email'];
					$t = $this->Tsumego->findById($comments[$i]['Comment']['tsumego_id']);
					$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
					$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
					$set = $this->Set->findById($t['Tsumego']['set_id']);
					$comments[$i]['Comment']['set'] = $set['Set']['title'];
					$comments[$i]['Comment']['set2'] = $set['Set']['title2'];
					$comments[$i]['Comment']['num'] = $t['Tsumego']['num'];
				}
		}
		$comments2 = [];
		$commentsCount = count($comments);
		for ($i = 0; $i < $commentsCount; $i++)
			if (is_numeric($comments[$i]['Comment']['status']))
				if ($comments[$i]['Comment']['status'] == 0)
					array_push($comments2, $comments[$i]);
		$comments = $comments2;

		$activityCount = count($activity);
		for ($i = 0; $i < $activityCount; $i++)
		{
			$a = new DateTime($activity[$i]['User']['created']);
			if ($a->format('Y-m-d') == $today)
				array_push($todaysUsers, $activity[$i]['User']);
		}

		$aa = $this->AdminActivity->find('all', ['limit' => 100, 'order' => 'created DESC', 'conditions' => ['user_id' => 2781]]);

		$this->set('c1', count($c1));
		$this->set('c2', count($c2));
		$this->set('c3', count($c3));
		$this->set('page', $p);
		$this->set('u', $todaysUsers);
		$this->set('comments', $comments);
		$this->set('aa', $aa);
	}

	/**
	 * @return void
	 */
	public function uservisits()
	{
		$this->set('_page', 'set');
		$this->set('_title', 'User Visits');
		$this->loadModel('Answer');

		$ans = $this->Answer->find('all', ['order' => 'created DESC']);
		$a = [];
		$ansCount = count($ans);
		for ($i = 0; $i < $ansCount; $i++)
		{
			$a[$i]['date'] = $ans[$i]['Answer']['created'];
			$a[$i]['num'] = $ans[$i]['Answer']['dismissed'];
			$a[$i]['y'] = date('Y', strtotime($ans[$i]['Answer']['created']));
			$a[$i]['m'] = date('m', strtotime($ans[$i]['Answer']['created']));
			$a[$i]['d'] = date('d', strtotime($ans[$i]['Answer']['created']));
		}
		array_pop($a);
		$aNew = [];
		$aNew['date'] = '2020-01-28 07:15:05';
		$aNew['num'] = 259;
		$aNew['y'] = 2020;
		$aNew['m'] = '01';
		$aNew['d'] = '28';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2020-01-21 07:15:05';
		$aNew['num'] = 223;
		$aNew['y'] = 2020;
		$aNew['m'] = '01';
		$aNew['d'] = '21';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-10-31 07:15:05';
		$aNew['num'] = 187;
		$aNew['y'] = 2019;
		$aNew['m'] = '10';
		$aNew['d'] = '31';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-05-15 07:15:05';
		$aNew['num'] = 171;
		$aNew['y'] = 2019;
		$aNew['m'] = '05';
		$aNew['d'] = '15';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-03-24 07:15:05';
		$aNew['num'] = 163;
		$aNew['y'] = 2019;
		$aNew['m'] = '03';
		$aNew['d'] = '24';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-03-15 07:15:05';
		$aNew['num'] = 141;
		$aNew['y'] = 2019;
		$aNew['m'] = '03';
		$aNew['d'] = '15';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-03-13 07:15:05';
		$aNew['num'] = 134;
		$aNew['y'] = 2019;
		$aNew['m'] = '03';
		$aNew['d'] = '13';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-03-12 07:15:05';
		$aNew['num'] = 121;
		$aNew['y'] = 2019;
		$aNew['m'] = '03';
		$aNew['d'] = '12';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-03-07 07:15:05';
		$aNew['num'] = 106;
		$aNew['y'] = 2019;
		$aNew['m'] = '03';
		$aNew['d'] = '07';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-02-28 07:15:05';
		$aNew['num'] = 103;
		$aNew['y'] = 2019;
		$aNew['m'] = '02';
		$aNew['d'] = '28';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-02-22 07:15:05';
		$aNew['num'] = 85;
		$aNew['y'] = 2019;
		$aNew['m'] = '02';
		$aNew['d'] = '22';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-02-19 07:15:05';
		$aNew['num'] = 82;
		$aNew['y'] = 2019;
		$aNew['m'] = '02';
		$aNew['d'] = '19';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-02-18 07:15:05';
		$aNew['num'] = 75;
		$aNew['y'] = 2019;
		$aNew['m'] = '02';
		$aNew['d'] = '18';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-02-15 07:15:05';
		$aNew['num'] = 73;
		$aNew['y'] = 2019;
		$aNew['m'] = '02';
		$aNew['d'] = '15';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-02-14 07:15:05';
		$aNew['num'] = 72;
		$aNew['y'] = 2019;
		$aNew['m'] = '02';
		$aNew['d'] = '14';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-02-11 07:15:05';
		$aNew['num'] = 54;
		$aNew['y'] = 2019;
		$aNew['m'] = '02';
		$aNew['d'] = '11';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-02-06 07:15:05';
		$aNew['num'] = 53;
		$aNew['y'] = 2019;
		$aNew['m'] = '02';
		$aNew['d'] = '06';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-02-04 07:15:05';
		$aNew['num'] = 47;
		$aNew['y'] = 2019;
		$aNew['m'] = '02';
		$aNew['d'] = '04';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-01-31 07:15:05';
		$aNew['num'] = 48;
		$aNew['y'] = 2019;
		$aNew['m'] = '01';
		$aNew['d'] = '31';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-01-27 07:15:05';
		$aNew['num'] = 37;
		$aNew['y'] = 2019;
		$aNew['m'] = '01';
		$aNew['d'] = '27';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-01-22 07:15:05';
		$aNew['num'] = 31;
		$aNew['y'] = 2019;
		$aNew['m'] = '01';
		$aNew['d'] = '22';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-01-19 07:15:05';
		$aNew['num'] = 33;
		$aNew['y'] = 2019;
		$aNew['m'] = '01';
		$aNew['d'] = '19';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-01-16 07:15:05';
		$aNew['num'] = 32;
		$aNew['y'] = 2019;
		$aNew['m'] = '01';
		$aNew['d'] = '16';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-01-09 07:15:05';
		$aNew['num'] = 26;
		$aNew['y'] = 2019;
		$aNew['m'] = '01';
		$aNew['d'] = '09';
		array_push($a, $aNew);
		$aNew = [];
		$aNew['date'] = '2019-01-07 07:15:05';
		$aNew['num'] = 20;
		$aNew['y'] = 2019;
		$aNew['m'] = '01';
		$aNew['d'] = '07';
		array_push($a, $aNew);

		$this->set('a', $a);
	}

	/**
	 * @return void
	 */
	public function duplicates()
	{
		$this->set('_page', 'sandbox');
		$this->set('_title', 'Merge Duplicates');
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Set');
		$this->loadModel('AdminActivity');
		$this->loadModel('SetConnection');
		$this->loadModel('Sgf');
		$this->loadModel('Duplicate');
		$this->loadModel('Comment');

		$idMap = [];
		$idMap2 = [];
		$marks = [];
		$aMessage = null;
		$errSet = '';
		$errNotNull = '';
		$tooltipSgfs = [];
		$tooltipInfo = [];
		$tooltipBoardSize = [];
		//$sc1 = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => 3537, 'set_id' => 71790)));
		//$sc2 = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => 25984, 'set_id' => 190)));
		//$sc2['SetConnection']['tsumego_id'] = 25984;
		//$this->SetConnection->save($sc2);

		if (isset($this->params['url']['remove']))
		{
			$remove = $this->Tsumego->findById($this->params['url']['remove']);
			if ($remove)
			{
				$remove['Tsumego']['duplicate'] = 0;
				$this->Tsumego->save($remove);
			}
		}
		if (isset($this->params['url']['removeDuplicate']))
		{
			$remove = $this->Tsumego->findById($this->params['url']['removeDuplicate']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $remove['Tsumego']['id']]]);
			$remove['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			if (!empty($remove) && $remove['Tsumego']['duplicate'] > 9)
			{
				$r1 = $this->Tsumego->findById($remove['Tsumego']['duplicate']);
				$r2 = $this->Tsumego->find('all', ['conditions' => ['duplicate' => $remove['Tsumego']['duplicate']]]);
				array_push($r2, $r1);
				if (count($r2) == 2)
				{
					$r2Count = count($r2);
					for ($i = 0; $i < $r2Count; $i++)
					{
						$r2[$i]['Tsumego']['duplicate'] = 0;
						$this->Tsumego->save($r2[$i]);
					}
				}
				elseif (count($r2) > 2)
				{
					$remove['Tsumego']['duplicate'] = 0;
					$this->Tsumego->save($remove);
				}
				AdminActivityLogger::log(AdminActivityType::DUPLICATE_REMOVE, $this->params['url']['removeDuplicate']);
			}
			else
				$aMessage = 'You can\'t remove the main duplicate.';
		}
		if (isset($this->params['url']['main']) && isset($this->params['url']['duplicates']))
		{
			$newDuplicates = explode('-', $this->params['url']['duplicates']);
			$newD = [];
			$newDmain = [];
			$checkSc = $this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $this->params['url']['main']]]);
			$errSet = '';
			$errNotNull = '';
			if (count($checkSc) <= 1)
				$validSc = true;
			else
			{
				$validSc = false;
				$errNotNull = 'Already set as duplicate.';
			}
			$newD0check = [];
			$newDuplicatesCount = count($newDuplicates);
			for ($i = 0; $i < $newDuplicatesCount; $i++)
			{
				$newD0 = $this->Tsumego->findById($newDuplicates[$i]);
				$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $newD0['Tsumego']['id']]]);
				$newD0['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
				array_push($newD0check, $newD0['Tsumego']['set_id']);
			}
			$newD0check = array_count_values($newD0check);
			foreach ($newD0check as $key => $value)
				if ($value > 1)
				{
					$validSc = false;
					$errSet = 'You can\'t link duplicates in the same collection.';
				}

			if ($validSc)
			{
				$newDuplicatesCount = count($newDuplicates);
				for ($i = 0; $i < $newDuplicatesCount; $i++)
				{
					$newD = $this->Tsumego->findById($newDuplicates[$i]);
					$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $newD['Tsumego']['id']]]);
					$newD['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
					if ($newD['Tsumego']['id'] == $this->params['url']['main'])
					{
						$newDmain = $newD;
						$newD['Tsumego']['duplicate'] = $this->params['url']['main'];
						$this->Tsumego->save($newD);
					}
					else
					{
						$comments = $this->Comment->find('all', ['conditions' => ['tsumego_id' => $newD['Tsumego']['id']]]);
						$commentsCount = count($comments);
						for ($j = 0; $j < $commentsCount; $j++)
							$this->Comment->delete($comments[$j]['Comment']['id']);
						$this->Tsumego->delete($newD['Tsumego']['id']);
					}
					$this->SetConnection->delete($scT['SetConnection']['id']);
					$setC = [];
					$setC['SetConnection']['tsumego_id'] = $this->params['url']['main'];
					$setC['SetConnection']['set_id'] = $newD['Tsumego']['set_id'];
					$setC['SetConnection']['num'] = $newD['Tsumego']['num'];
					$this->SetConnection->create();
					$this->SetConnection->save($setC);
					$dupDel = $this->Duplicate->find('all', ['conditions' => ['tsumego_id' => $newDuplicates[$i]]]);
					$dupDelCount = count($dupDel);
					for ($j = 0; $j < $dupDelCount; $j++)
						$this->Duplicate->delete($dupDel[$j]['Duplicate']['id']);
				}
				AdminActivityLogger::log(AdminActivityType::DUPLICATE_GROUP_CREATE, $this->params['url']['main']);
			}
		}
		if (!empty($this->data['Mark']))
		{
			$mark = $this->Tsumego->findById($this->data['Mark']['tsumego_id']);
			if (!empty($mark) && $mark['Tsumego']['duplicate'] == 0)
			{
				$mark['Tsumego']['duplicate'] = -1;
				$this->Tsumego->save($mark);
			}
		}
		if (!empty($this->data['Mark2']))
		{
			$mark = $this->Tsumego->findById($this->data['Mark2']['tsumego_id']);
			$group = $this->Tsumego->findById($this->data['Mark2']['group_id']);

			if ($mark != null && $mark['Tsumego']['duplicate'] == 0 && $group != null)
			{
				$scTx = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $mark['Tsumego']['id']]]);
				$scTx['SetConnection']['tsumego_id'] = $this->data['Mark2']['group_id'];
				$this->SetConnection->save($scTx);
				$comments = $this->Comment->find('all', ['conditions' => ['tsumego_id' => $mark['Tsumego']['id']]]);
				$commentsCount = count($comments);
				for ($j = 0; $j < $commentsCount; $j++)
					$this->Comment->delete($comments[$j]['Comment']['id']);
				$this->Tsumego->delete($mark['Tsumego']['id']);
			}
		}

		$marks = $this->Tsumego->find('all', ['conditions' => ['duplicate' => -1]]);
		$marksCount = count($marks);
		for ($i = 0; $i < $marksCount; $i++)
			array_push($idMap2, $marks[$i]['Tsumego']['id']);
		$uts2 = $this->TsumegoStatus->find('all', ['conditions' => ['tsumego_id' => $idMap2, 'user_id' => Auth::getUserID()]]);
		$counter2 = 0;
		$markTooltipSgfs = [];
		$markTooltipInfo = [];
		$markTooltipBoardSize = [];
		$marksCount = count($marks);
		for ($i = 0; $i < $marksCount; $i++)
		{
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $marks[$i]['Tsumego']['id']]]);
			$marks[$i]['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($marks[$i]['Tsumego']['set_id']);
			$marks[$i]['Tsumego']['title'] = $s['Set']['title'] . ' - ' . $marks[$i]['Tsumego']['num'];
			$marks[$i]['Tsumego']['status'] = $uts2[$counter2]['TsumegoStatus']['status'];
			$counter2++;
		}

		$setConnections = $this->SetConnection->find('all');
		$scCount = [];
		$scCount2 = [];
		foreach ($setConnections as $setConnection)
			$scCount[] = $setConnection['SetConnection']['tsumego_id'];
		$scCount = array_count_values($scCount);
		foreach ($scCount as $key => $value)
			if ($value > 1)
				$scCount2[] = $key;

		$duplicates1 = [];

		$showAll = false;

		if (isset($this->params['url']['load']))
		{
			$showAll = true;
			$counter = 0;
			$scCount2Count = count($scCount2);
			for ($i = 0; $i < $scCount2Count; $i++)
			{
				$duplicates1[$i] = [];
				foreach ($setConnections as $setConnection)
					if ($setConnection['SetConnection']['tsumego_id'] == $scCount2[$i])
					{
						$scT1 = $this->Tsumego->findById($setConnection['SetConnection']['tsumego_id']);
						$scT1['Tsumego']['num'] = $setConnection['SetConnection']['num'];
						$scT1['Tsumego']['set_id'] = $setConnection['SetConnection']['set_id'];
						$scT1['Tsumego']['status'] = 'N';
						array_push($duplicates1[$i], $scT1);
						array_push($idMap, $scT1['Tsumego']['id']);
					}
			}

			$uts = $this->TsumegoStatus->find('all', ['conditions' => ['tsumego_id' => $idMap, 'user_id' => Auth::getUserID()]]);
			$tooltipSgfs = [];
			$tooltipInfo = [];
			$tooltipBoardSize = [];
			$duplicates1Count = count($duplicates1);
			for ($i = 0; $i < $duplicates1Count; $i++)
			{
				$tooltipSgfs[$i] = [];
				$tooltipInfo[$i] = [];
				$tooltipBoardSize[$i] = [];
				$duplicates1Count = count($duplicates1[$i]);
				for ($j = 0; $j < $duplicates1Count; $j++)
				{
					$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $duplicates1[$i][$j]['Tsumego']['id'], 'set_id' => $duplicates1[$i][$j]['Tsumego']['set_id']]]);
					$duplicates1[$i][$j]['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
					$s = $this->Set->findById($duplicates1[$i][$j]['Tsumego']['set_id']);
					if ($s != null)
					{
						$duplicates1[$i][$j]['Tsumego']['title'] = $s['Set']['title'] . ' - ' . $duplicates1[$i][$j]['Tsumego']['num'];
						$duplicates1[$i][$j]['Tsumego']['duplicateLink'] = '?sid=' . $duplicates1[$i][$j]['Tsumego']['set_id'];
						$utsCount = count($uts);
						for ($k = 0; $k < $utsCount; $k++)
							if ($uts[$k]['TsumegoStatus']['tsumego_id'] == $duplicates1[$i][$j]['Tsumego']['id'])
								$duplicates1[$i][$j]['Tsumego']['status'] = $uts[$k]['TsumegoStatus']['status'];
					}
				}
			}

		}

		$this->set('showAll', $showAll);
		$this->set('d', $duplicates1);
		$this->set('d', $duplicates1);
		$this->set('marks', $marks);
		$this->set('aMessage', $aMessage);
		$this->set('tooltipSgfs', $tooltipSgfs);
		$this->set('tooltipInfo', $tooltipInfo);
		$this->set('tooltipBoardSize', $tooltipBoardSize);
		$this->set('markTooltipSgfs', $markTooltipSgfs);
		$this->set('markTooltipInfo', $markTooltipInfo);
		$this->set('markTooltipBoardSize', $markTooltipBoardSize);
		$this->set('errSet', $errSet);
		$this->set('errNotNull', $errNotNull);
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

	/**
	 * @param string|int|null $p Page parameter
	 * @return void
	 */
	public function adminstats($p = null)
	{
		$this->set('_page', 'user');
		$this->set('_title', 'Admin Panel');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Comment');
		$this->loadModel('User');
		$this->loadModel('DayRecord');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('AdminActivity');
		$this->loadModel('SetConnection');
		$this->loadModel('Tag');
		$this->loadModel('TagConnection');
		$this->loadModel('Sgf');
		$this->loadModel('UserContribution');
		$this->loadModel('Reject');

		if (Auth::isAdmin())
		{
			if (isset($this->params['url']['accept']) && isset($this->params['url']['tag_id']))
				if (md5((string) Auth::getUserID()) == $this->params['url']['hash'])
				{

					$tagsToApprove = explode('-', $_COOKIE['tagList']);
					$tagsToApproveCount = count($tagsToApprove);
					for ($i = 1; $i < $tagsToApproveCount; $i++)
					{
						$tagToApprove = $this->TagConnection->findById(substr($tagsToApprove[$i], 1));
						if ($tagToApprove != null && $tagToApprove['TagConnection']['approved'] != 1)
						{
							AppController::handleContribution(Auth::getUserID(), 'reviewed');
							if (substr($tagsToApprove[$i], 0, 1) == 'a')
							{
								$tagToApprove['TagConnection']['approved'] = '1';
								$this->TagConnection->save($tagToApprove);
								AppController::handleContribution($tagToApprove['TagConnection']['user_id'], 'added_tag');
							}
							else
							{
								$reject = [];
								$reject['Reject']['tsumego_id'] = $tagToApprove['TagConnection']['tsumego_id'];
								$reject['Reject']['user_id'] = $tagToApprove['TagConnection']['user_id'];
								$reject['Reject']['type'] = 'tag';
								$tagNameId = $this->Tag->findById($tagToApprove['TagConnection']['tag_id']);
								$reject['Reject']['text'] = $tagNameId['Tag']['name'];
								$this->Reject->create();
								$this->Reject->save($reject);
								$this->TagConnection->delete($tagToApprove['TagConnection']['id']);
							}
						}
					}

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

					$proposalsToApprove = explode('-', $_COOKIE['proposalList']);
					$proposalsToApproveCount = count($proposalsToApprove);
					for ($i = 1; $i < $proposalsToApproveCount; $i++)
					{
						$proposalToApprove = $this->Sgf->findById(substr($proposalsToApprove[$i], 1));
						$firstSgf = $this->Sgf->find('first', ['order' => 'id ASC', 'conditions' => ['tsumego_id' => $proposalToApprove['Sgf']['tsumego_id']]]);
						if ($proposalToApprove != null && $proposalToApprove['Sgf']['id'] == $firstSgf['Sgf']['id'])
						{
							AppController::handleContribution(Auth::getUserID(), 'reviewed');
							if (substr($proposalsToApprove[$i], 0, 1) == 'a')
							{
								$recentSgf = $this->Sgf->find('first', ['order' => 'id DESC', 'conditions' => ['tsumego_id' => $proposalToApprove['Sgf']['tsumego_id']]]);
								$this->Sgf->save($proposalToApprove);
								AppController::handleContribution($proposalToApprove['Sgf']['user_id'], 'made_proposal');
							}
							else
							{
								$reject = [];
								$reject['Reject']['user_id'] = $proposalToApprove['Sgf']['user_id'];
								$reject['Reject']['tsumego_id'] = $proposalToApprove['Sgf']['tsumego_id'];
								$reject['Reject']['type'] = 'proposal';
								$this->Reject->create();
								$this->Reject->save($reject);
								$this->Sgf->delete($proposalToApprove['Sgf']['id']);
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
		$tagsPage = isset($this->params['url']['tags_page']) ? max(1, (int) $this->params['url']['tags_page']) : 1;
		$tagNamesPage = isset($this->params['url']['tagnames_page']) ? max(1, (int) $this->params['url']['tagnames_page']) : 1;
		$proposalsPage = isset($this->params['url']['proposals_page']) ? max(1, (int) $this->params['url']['proposals_page']) : 1;
		$activityPage = isset($this->params['url']['activity_page']) ? max(1, (int) $this->params['url']['activity_page']) : 1;
		$commentsPage = isset($this->params['url']['comments_page']) ? max(1, (int) $this->params['url']['comments_page']) : 1;

		$tagsOffset = ($tagsPage - 1) * $perPage;
		$tagNamesOffset = ($tagNamesPage - 1) * $perPage;
		$proposalsOffset = ($proposalsPage - 1) * $perPage;
		$activityOffset = ($activityPage - 1) * $perPage;
		$commentsOffset = ($commentsPage - 1) * $perPage;

		// Get total counts
		$tagsTotal = $this->TagConnection->find('count', ['conditions' => ['approved' => 0]]);
		$tagNamesTotal = $this->Tag->find('count', ['conditions' => ['approved' => 0]]);

		// Fetch paginated data
		$tagConnections = $this->TagConnection->find('all', [
			'conditions' => ['approved' => 0],
			'limit' => $perPage,
			'offset' => $tagsOffset,
			'order' => 'created DESC'
		]);
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

		$tsIds = [];
		$tagTsumegos = [];
		$tagsCount = count($tagConnections);
		for ($i = 0; $i < $tagsCount; $i++)
		{
			$at = $this->Tsumego->find('first', ['conditions' => ['id' => $tagConnections[$i]['TagConnection']['tsumego_id']]]);
			array_push($tsIds, $at['Tsumego']['id']);
			array_push($tagTsumegos, $at);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $at['Tsumego']['id']]]);
			$as = $this->Set->find('first', ['conditions' => ['id' => $scT['SetConnection']['set_id']]]);
			$au = $this->User->findById($tagConnections[$i]['TagConnection']['user_id']);
			$tagConnections[$i]['TagConnection']['name'] = $tKeys[$tagConnections[$i]['TagConnection']['tag_id']];
			$tagConnections[$i]['TagConnection']['tsumego'] = $as['Set']['title'] . ' - ' . $at['Tsumego']['num'];
			$tagConnections[$i]['TagConnection']['user'] = $this->checkPicture($au);
		}
		$tagNamesCount = count($tags);
		for ($i = 0; $i < $tagNamesCount; $i++)
		{
			$au = $this->User->findById($tags[$i]['Tag']['user_id']);
			$tags[$i]['Tag']['user'] = $this->checkPicture($au);
		}

		// Find first SGF (minimum id) for each tsumego
		$firstSgfIds = $this->Sgf->query("
			SELECT MIN(id) as min_id
			FROM sgf
			GROUP BY tsumego_id
			ORDER BY min_id DESC
			LIMIT $perPage OFFSET $proposalsOffset
		");
		$firstIds = [];
		foreach ($firstSgfIds as $row)
			$firstIds[] = $row[0]['min_id'];

		// Get total count of proposals
		$proposalsTotal = $this->Sgf->query("
			SELECT COUNT(DISTINCT tsumego_id) as total
			FROM sgf
		")[0][0]['total'];

		$approveSgfs = $this->Sgf->find('all', ['conditions' => ['id' => $firstIds]]);
		$sgfTsumegos = [];
		$latestVersionTsumegos = [];
		$approveSgfsCount = count($approveSgfs);
		for ($i = 0; $i < $approveSgfsCount; $i++)
		{
			$at = $this->Tsumego->find('first', ['conditions' => ['id' => $approveSgfs[$i]['Sgf']['tsumego_id']]]);
			array_push($latestVersionTsumegos, $this->Sgf->find('first', ['order' => 'id DESC', 'conditions' => ['tsumego_id' => $at['Tsumego']['id']]]));
			array_push($sgfTsumegos, $at);
			array_push($tsIds, $at['Tsumego']['id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $at['Tsumego']['id']]]);
			$as = $this->Set->find('first', ['conditions' => ['id' => $scT['SetConnection']['set_id']]]);
			$au = $this->User->findById($approveSgfs[$i]['Sgf']['user_id']);
			$approveSgfs[$i]['Sgf']['tsumego'] = $as['Set']['title'] . ' - ' . $at['Tsumego']['num'];
			$approveSgfs[$i]['Sgf']['user'] = $this->checkPicture($au);
		}
		$uts = $this->TsumegoStatus->find('all', [
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'tsumego_id' => $tsIds,
			],
		]);

		$tsMap = [];
		$utsCount = count($uts);
		for ($i = 0; $i < $utsCount; $i++)
			$tsMap[$uts[$i]['TsumegoStatus']['tsumego_id']] = $uts[$i]['TsumegoStatus']['status'];

		$tooltipSgfs = [];
		$tooltipInfo = [];
		$tooltipBoardSize = [];
		$tagTsumegosCount = count($tagTsumegos);
		for ($i = 0; $i < $tagTsumegosCount; $i++)
			$tagTsumegos[$i]['Tsumego']['status'] = $tsMap[$tagTsumegos[$i]['Tsumego']['id']];
		$tooltipSgfs2 = [];
		$tooltipInfo2 = [];
		$tooltipBoardSize2 = [];
		$sgfTsumegosCount = count($sgfTsumegos);
		for ($i = 0; $i < $sgfTsumegosCount; $i++)
			$sgfTsumegos[$i]['Tsumego']['status'] = $tsMap[$sgfTsumegos[$i]['Tsumego']['id']];

		$u = $this->User->find('all', ['conditions' => ['isAdmin >' => 0]]);
		$uArray = [];
		$uCount = count($u);
		for ($i = 0; $i < $uCount; $i++)
			array_push($uArray, $u[$i]['User']['id']);

		// Get total count of admin activities
		$activityTotal = $this->AdminActivity->find('count');

		$aa = $this->AdminActivity->find('all', [
			'limit' => $perPage,
			'offset' => $activityOffset,
			'order' => 'AdminActivity.created DESC',
			'contain' => ['AdminActivityType']
		]);
		$aa2 = [];
		$b1 = [];

		// Separate arrays for activities and comments
		$adminActivities = [];
		$adminActivities['tsumego_id'] = [];
		$adminActivities['tsumego'] = [];
		$adminActivities['created'] = [];
		$adminActivities['name'] = [];
		$adminActivities['type'] = [];
		$adminActivities['old_value'] = [];
		$adminActivities['new_value'] = [];

		$adminComments = [];
		$adminComments['tsumego_id'] = [];
		$adminComments['tsumego'] = [];
		$adminComments['created'] = [];
		$adminComments['name'] = [];
		$adminComments['message'] = [];

		$aaCount = count($aa);
		for ($i = 0; $i < $aaCount; $i++)
		{
			// Get user info
			$au = $this->User->find('first', ['conditions' => ['id' => $aa[$i]['AdminActivity']['user_id']]]);
			$aa[$i]['AdminActivity']['name'] = $au['User']['name'];
			$aa[$i]['AdminActivity']['isAdmin'] = $au['User']['isAdmin'];

			// Handle set-level activities (set_id is populated, tsumego_id is NULL)
			if ($aa[$i]['AdminActivity']['set_id'] !== null && $aa[$i]['AdminActivity']['set_id'] > 0)
			{
				$as = $this->Set->find('first', ['conditions' => ['id' => $aa[$i]['AdminActivity']['set_id']]]);
				$aa[$i]['AdminActivity']['tsumego'] = $as['Set']['title'] . ' (Set-wide)';
				$aa[$i]['AdminActivity']['tsumego_id'] = 0; // Placeholder for display
			}
			// Handle problem-level activities (tsumego_id is populated)
			elseif ($aa[$i]['AdminActivity']['tsumego_id'] !== null && $aa[$i]['AdminActivity']['tsumego_id'] > 0)
			{
				$at = $this->Tsumego->find('first', ['conditions' => ['id' => $aa[$i]['AdminActivity']['tsumego_id']]]);
				$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $at['Tsumego']['id']]]);
				$as = $this->Set->find('first', ['conditions' => ['id' => $scT['SetConnection']['set_id']]]);
				$aa[$i]['AdminActivity']['tsumego'] = $as['Set']['title'] . ' - ' . $scT['SetConnection']['num'];
			}
			else
			{
				// General admin activity not tied to specific tsumego or set
				$aa[$i]['AdminActivity']['tsumego'] = 'General Admin Activity';
				$aa[$i]['AdminActivity']['tsumego_id'] = 0;
			}
			// Get readable type name from enum table (names are Title Case: 'Description Edit' etc.)
			$readableType = $aa[$i]['AdminActivityType']['name'];

			array_push($aa2, $aa[$i]);
			array_push($adminActivities['tsumego_id'], $aa[$i]['AdminActivity']['tsumego_id']);
			array_push($adminActivities['tsumego'], $aa[$i]['AdminActivity']['tsumego']);
			array_push($adminActivities['created'], $aa[$i]['AdminActivity']['created']);
			array_push($adminActivities['name'], $aa[$i]['AdminActivity']['name']);
			array_push($adminActivities['type'], $readableType);
			array_push($adminActivities['old_value'], $aa[$i]['AdminActivity']['old_value']);
			array_push($adminActivities['new_value'], $aa[$i]['AdminActivity']['new_value']);
		}

		// Get total count of admin comments
		$commentsTotal = $this->Comment->find('count', [
			'conditions' => [
				'user_id' => $uArray,
				'NOT' => [
					'status' => [99],
				],
			],
		]);

		// Find paginated comments from admin users
		$comments = $this->Comment->find('all', [
			'order' => 'created DESC',
			'limit' => $perPage,
			'offset' => $commentsOffset,
			'conditions' => [
				'user_id' => $uArray,
				'NOT' => [
					'status' => [99],
				],
			],
		]);
		$commentsCount = count($comments);
		for ($i = 0; $i < $commentsCount; $i++)
		{
			$at = $this->Tsumego->find('first', ['conditions' => ['id' => $comments[$i]['Comment']['tsumego_id']]]);
			if (empty($at)) continue; // Skip if tsumego doesn't exist

			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $at['Tsumego']['id']]]);
			$at['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$as = $this->Set->find('first', ['conditions' => ['id' => $at['Tsumego']['set_id']]]);
			$au = $this->User->find('first', ['conditions' => ['id' => $comments[$i]['Comment']['user_id']]]);
			array_push($adminComments['tsumego_id'], $comments[$i]['Comment']['tsumego_id']);
			array_push($adminComments['tsumego'], $as['Set']['title'] . ' - ' . $at['Tsumego']['num']);
			array_push($adminComments['created'], $comments[$i]['Comment']['created']);
			array_push($adminComments['name'], $au['User']['name']);
			array_push($adminComments['message'], $comments[$i]['Comment']['message']);
		}

		$requestDeletion = $this->User->find('all', ['conditions' => ['dbstorage' => 1111]]);

		$this->set('requestDeletion', $requestDeletion);
		$this->set('aa', $aa);
		$this->set('aa2', $aa2);
		$this->set('adminActivities', $adminActivities);
		$this->set('adminComments', $adminComments);
		$this->set('tags', $tagConnections);
		$this->set('tagNames', $tags);
		$this->set('tagTsumegos', $tagTsumegos);
		$this->set('approveSgfs', $approveSgfs);
		$this->set('sgfTsumegos', $sgfTsumegos);
		$this->set('latestVersionTsumegos', $latestVersionTsumegos);

		// Pagination data
		$this->set('tagsPage', $tagsPage);
		$this->set('tagsTotal', $tagsTotal);
		$this->set('tagsPagesTotal', ceil($tagsTotal / $perPage));
		$this->set('tagNamesPage', $tagNamesPage);
		$this->set('tagNamesTotal', $tagNamesTotal);
		$this->set('tagNamesPagesTotal', ceil($tagNamesTotal / $perPage));
		$this->set('proposalsPage', $proposalsPage);
		$this->set('proposalsTotal', $proposalsTotal);
		$this->set('proposalsPagesTotal', ceil($proposalsTotal / $perPage));
		$this->set('activityPage', $activityPage);
		$this->set('activityTotal', $activityTotal);
		$this->set('activityPagesTotal', ceil($activityTotal / $perPage));
		$this->set('commentsPage', $commentsPage);
		$this->set('commentsTotal', $commentsTotal);
		$this->set('commentsPagesTotal', ceil($commentsTotal / $perPage));
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
		$this->loadModel('UserContribution');

		$list = [];
		$uc = $this->UserContribution->find('all', ['limit' => 100, 'order' => 'score DESC']);
		$ucCount = count($uc);
		for ($i = 0; $i < $ucCount; $i++)
		{
			$x = [];
			$x['id'] = $uc[$i]['UserContribution']['user_id'];
			$user = $this->User->findById($uc[$i]['UserContribution']['user_id']);
			if ($user && isset($user['User']))
			{
				$x['name'] = $this->checkPicture($user);
				$x['score'] = $uc[$i]['UserContribution']['score'];
				$x['added_tag'] = $uc[$i]['UserContribution']['added_tag'];
				$x['created_tag'] = $uc[$i]['UserContribution']['created_tag'];
				$x['made_proposal'] = $uc[$i]['UserContribution']['made_proposal'];
				$x['reviewed'] = $uc[$i]['UserContribution']['reviewed'];
				array_push($list, $x);
			}
		}
		$this->set('a', $list);
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

	/**
	 * @return void
	 */
	public function achievements()
	{
		$this->set('_page', 'achievementHighscore');
		$this->set('_title', 'Tsumego Hero - Achievements Highscore');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Tsumego');
		$this->loadModel('AchievementStatus');
		$this->loadModel('Achievement');
		$this->loadModel('User');

		if (Auth::isLoggedIn())
		{
			$ux = $this->User->findById(Auth::getUserID());
			$ux['User']['lastHighscore'] = 2;
			$this->User->save($ux);
		}
		$json = json_decode(file_get_contents('json/achievement_highscore.json'), true);
		$jsonUaIdCount = count($json['uaId']);
		for ($i = $jsonUaIdCount - 1; $i >= $jsonUaIdCount - 100; $i--)
		{
			$u = $this->User->findById($json['uaId'][$i]);
			if ($u && isset($u['User']['name']))
				$json['uaId'][$i] = $u['User']['name'];
		}

		$this->set('uaNum', $json['uaNum']);
		$this->set('uName', $json['uaId']);
		$this->set('aNum', $json['aNum']);
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

		$users = ClassRegistry::init('User')->query('SELECT user.name, user.external_id, user.picture, user.daily_xp, user.daily_solved FROM user WHERE daily_xp > 0 ORDER BY daily_xp DESC');
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

		$tsumegos = $this->SetConnection->find('all');
		if (!$tsumegos)
			$tsumegos = [];
		$uts = $this->TsumegoStatus->find('all', ['order' => 'updated DESC', 'conditions' => ['user_id' => $id]]);
		if (!$uts)
			$uts = [];
		$tsumegoDates = [];

		$setKeys = [];
		$setArray = $this->Set->find('all', ['conditions' => ['public' => 1]]);
		if (!$setArray)
			$setArray = [];
		$setArrayCount = count($setArray);
		for ($i = 0; $i < $setArrayCount; $i++)
			$setKeys[$setArray[$i]['Set']['id']] = $setArray[$i]['Set']['id'];

		$tsumegosCount = count($tsumegos);
		for ($j = 0; $j < $tsumegosCount; $j++)
			if (isset($setKeys[$tsumegos[$j]['SetConnection']['set_id']]))
				array_push($tsumegoDates, $tsumegos[$j]);
		$tsumegoNum = count($tsumegoDates);
		$solvedUts = [];
		$lastYear = date('Y-m-d', strtotime('-1 year'));

		$tsumegoStatusToRestCount = 0;

		$utsCount = count($uts);
		for ($j = 0; $j < $utsCount; $j++)
		{
			$date = new DateTime($uts[$j]['TsumegoStatus']['created']);
			$uts[$j]['TsumegoStatus']['created'] = $date->format('Y-m-d');
			if ($uts[$j]['TsumegoStatus']['status'] == 'S' || $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C')
			{
				$oldest = new DateTime(date('Y-m-d', strtotime('-30 days')));
				if ($uts[$j]['TsumegoStatus']['created'] > $oldest->format('Y-m-d'))
					array_push($solvedUts, $uts[$j]);
			}
			if ($uts[$j]['TsumegoStatus']['created'] < $lastYear)
				$tsumegoStatusToRestCount++;
		}

		$oldest = new DateTime(date('Y-m-d', strtotime('-183 days')));
		$oldest = $oldest->format('Y-m-d');
		$ta = $this->TsumegoAttempt->find('all', [
			'order' => 'created DESC',
			'conditions' => ['user_id' => $id, 'created >' => $oldest]]);

		$taBefore = '';
		$graph = [];
		$ta2 = [];

		$ta2['date'] = [];
		$ta2['rating'] = [];

		$taCount = count($ta);
		for ($i = 0; $i < $taCount; $i++)
		{
			if ($ta[$i]['TsumegoAttempt']['user_rating'] != null)
			{
				$ta2['date'][] = $ta[$i]['TsumegoAttempt']['created'];
				$ta2['rating'][] = $ta[$i]['TsumegoAttempt']['user_rating'];
			}

			$ta[$i]['TsumegoAttempt']['created'] = new DateTime(date($ta[$i]['TsumegoAttempt']['created']));
			$ta[$i]['TsumegoAttempt']['created'] = $ta[$i]['TsumegoAttempt']['created']->format('Y-m-d');
			if (!isset($graph[$ta[$i]['TsumegoAttempt']['created']]))
			{
				$graph[$ta[$i]['TsumegoAttempt']['created']] = [];
				$graph[$ta[$i]['TsumegoAttempt']['created']]['s'] = 0;
				$graph[$ta[$i]['TsumegoAttempt']['created']]['f'] = 0;
			}
			$graph[$ta[$i]['TsumegoAttempt']['created']][$ta[$i]['TsumegoAttempt']['solved'] == 1 ? 's' : 'f']++;
		}

		$timeGraph = [];
		$ro = $this->TimeModeSession->find('all', [
			'order' => 'time_mode_rank_id ASC',
			'conditions' => [
				'user_id' => $id,
			],
		]);

		$highestRo = '15k';
		$roCount = count($ro);
		for ($i = 0; $i < $roCount; $i++)
		{
			$highestRo = $this->getHighestRo($ro[$i]['TimeModeSession']['TimeModeAttempt'], $highestRo);
			if (isset($timeGraph[$ro[$i]['TimeModeSession']['TimeModeAttempt']][$ro[$i]['TimeModeSession']['status']]))
				$timeGraph[$ro[$i]['TimeModeSession']['TimeModeAttempt']][$ro[$i]['TimeModeSession']['status']]++;
			else
				$timeGraph[$ro[$i]['TimeModeSession']['TimeModeAttempt']][$ro[$i]['TimeModeSession']['status']] = 1;
		}
		$timeGraph = $this->formatTimegraph($timeGraph);

		$percentSolved = Util::getPercentButAvoid100UntilComplete($user['User']['solved'], $tsumegoNum);

		$deletedTsumegoStatusCount = 0;
		$canResetOldTsumegoStatuses = $percentSolved >= Constants::$MINIMUM_PERCENT_OF_TSUMEGOS_TO_BE_SOLVED_BEFORE_RESET_IS_ALLOWED;
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

				$percentSolved = Util::getPercentButAvoid100UntilComplete($user['User']['solved'], $tsumegoNum);
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

		$achievementUpdate1 = $this->checkLevelAchievements();
		$achievementUpdate2 = $this->checkProblemNumberAchievements();
		$achievementUpdate = array_merge(
			$achievementUpdate1 ?: [],
			$achievementUpdate2 ?: []
		);

		if (count($achievementUpdate) > 0)
			$this->updateXP($id, $achievementUpdate);
		$aNum = $this->AchievementStatus->find('all', ['conditions' => ['user_id' => $id]]);
		$asx = $this->AchievementStatus->find('first', ['conditions' => ['user_id' => $id, 'achievement_id' => 46]]);
		$aNumx = count($aNum);
		if ($asx != null)
			$aNumx = $aNumx + $asx['AchievementStatus']['value'] - 1;

		$countGraph = 160 + count($graph) * 25;
		$countTimeGraph = 160 + count($timeGraph) * 25;

		$user['User']['name'] = $this->checkPicture($user['User']);

		$aCount = $this->Achievement->find('all');

		$this->set('ta2', $ta2);
		$this->set('graph', $graph);
		$this->set('countGraph', $countGraph);
		$this->set('timeGraph', $timeGraph);
		$this->set('countTimeGraph', $countTimeGraph);
		$this->set('timeModeRuns', count($ro));
		$this->set('user', $user);
		$this->set('tsumegoNum', $tsumegoNum);
		$this->set('percentSolved', $percentSolved);
		$this->set('deletedTsumegoStatusCount', $deletedTsumegoStatusCount);
		$this->set('tsumegoStatusToRestCount', $tsumegoStatusToRestCount);
		$this->set('allUts', $uts);
		$this->set('as', $as);
		$this->set('achievementUpdate', $achievementUpdate);
		$this->set('highestRo', $highestRo);
		$this->set('aNum', $aNumx);
		$this->set('aCount', $aCount);
		$this->set('canResetOldTsumegoStatuses', $canResetOldTsumegoStatuses);
	}

	private function formatTimegraph($graph)
	{
		$g = [];
		$g['15k'] = 0;
		$g['14k'] = 0;
		$g['13k'] = 0;
		$g['12k'] = 0;
		$g['11k'] = 0;
		$g['10k'] = 0;
		$g['9k'] = 0;
		$g['8k'] = 0;
		$g['7k'] = 0;
		$g['6k'] = 0;
		$g['5k'] = 0;
		$g['4k'] = 0;
		$g['3k'] = 0;
		$g['2k'] = 0;
		$g['1k'] = 0;
		$g['1d'] = 0;
		$g['2d'] = 0;
		$g['3d'] = 0;
		$g['4d'] = 0;
		$g['5d'] = 0;
		foreach ($graph as $key => $value)
			$g[$key] = $value;
		$g2 = [];
		foreach ($g as $key => $value)
			if ($g[$key] != 0)
				$g2[$key] = $value;

		return $g2;
	}

	private function getHighestRo($new, $old)
	{
		$newNum = 23;
		$oldNum = 23;
		$a = [];
		$a[0] = '9d';
		$a[1] = '8d';
		$a[2] = '7d';
		$a[3] = '6d';
		$a[4] = '5d';
		$a[5] = '4d';
		$a[6] = '3d';
		$a[7] = '2d';
		$a[8] = '1d';
		$a[9] = '1k';
		$a[10] = '2k';
		$a[11] = '3k';
		$a[12] = '4k';
		$a[13] = '5k';
		$a[14] = '6k';
		$a[15] = '7k';
		$a[16] = '8k';
		$a[17] = '9k';
		$a[18] = '10k';
		$a[19] = '11k';
		$a[20] = '12k';
		$a[21] = '13k';
		$a[22] = '14k';
		$a[23] = '15k';
		$aCount = count($a);
		for ($i = 0; $i < $aCount; $i++)
		{
			if ($a[$i] == $new)
				$newNum = $i;
			if ($a[$i] == $old)
				$oldNum = $i;
		}
		if ($newNum < $oldNum)
			return $new;

		return $old;
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
		$this->loadModel('Comment');
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

	public function delete($id)
	{
		$this->loadModel('Comment');
		if ($this->request->is('get'))
			throw new MethodNotAllowedException();

		if ($this->Comment->delete($id))
			CookieFlash::set(__('The post with id: %s has been deleted.', h($id)), 'success');
		else
			CookieFlash::set(__('The post with id: %s could not be deleted.', h($id)), 'error');

		return $this->redirect(['action' => '/stats']);
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
	public function overview1()
	{
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('Comment');

		$test = $this->Comment->find('all', [
			'order' => 'created DESC',
			'conditions' => [
				'status' => 0,
			],
		]);

		$comments = $this->Comment->find('all', [
			'order' => 'created DESC',
			'conditions' => [
				[
					['NOT' => ['user_id' => 0]],
					['NOT' => ['status' => 99]],
				],
			],
		]);
		$comments2 = [];
		$monthBack = date('Y-m-d', strtotime('-10 years'));
		$commentsCount = count($comments);
		for ($i = 0; $i < $commentsCount; $i++)
		{
			$u = $this->User->findById($comments[$i]['Comment']['user_id']);
			$comments[$i]['Comment']['user'] = $u['User']['name'];
			$t = $this->Tsumego->findById($comments[$i]['Comment']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$comments[$i]['Comment']['tsumego'] = $s['Set']['title'] . ' ' . $t['Tsumego']['num'];

			$date = new DateTime($comments[$i]['Comment']['created']);

			$comments[$i]['Comment']['created2'] = $date->format('Y-m-d');

			if ($comments[$i]['Comment']['created2'] > $monthBack && $comments[$i]['Comment']['user_id'] != 0)
				array_push($comments2, $comments[$i]);
		}
		$comments = $comments2;

		$users = [];
		$adminIds = [];

		$commentsCount = count($comments);
		for ($i = 0; $i < $commentsCount; $i++)
		{
			array_push($users, $comments[$i]['Comment']['user']);
			array_push($adminIds, $comments[$i]['Comment']['admin_id']);
		}
		$adminIds = array_count_values($adminIds);

		$users = array_count_values($users);
		$uValue = [];
		$uName = [];
		foreach ($users as $key => $value)
		{
			array_push($uValue, $value);
			array_push($uName, $key);
		}
		array_multisort($uValue, $uName);

		$u2 = [];
		$u2['name'] = [];
		$u2['value'] = [];
		$uNameCount = count($uName);
		for ($i = $uNameCount - 1; $i >= 0; $i--)
		{
			array_push($u2['name'], $uName[$i]);
			array_push($u2['value'], $uValue[$i]);
		}
		$this->set('users', $users);
		$this->set('u2', $u2);
		$this->set('comments', $comments);
	}

	/**
	 * @return void
	 */
	public function purge()
	{
		$this->loadModel('Purge');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Set');
		$this->loadModel('Answer');
		$this->loadModel('Schedule');
		$this->loadModel('PurgeList');
		$this->loadModel('Tsumego');

		$p = 0;
		$pl = $this->PurgeList->find('all', ['order' => 'id DESC', 'limit' => 3]);

		if (isset($this->data['Schedule']))
		{
			$schedule = [];
			$st = $this->Tsumego->find('first', ['conditions' => ['set_id' => $this->data['Schedule']['set_id_from'], 'num' => $this->data['Schedule']['num']]]);
			$schedule['Schedule']['tsumego_id'] = $st['Tsumego']['id'];
			$schedule['Schedule']['set_id'] = $this->data['Schedule']['set_id_to'];
			$schedule['Schedule']['date'] = $this->data['Schedule']['date'];
			if (is_numeric($this->data['Schedule']['num']))
				if ($this->data['Schedule']['num'] > 0)
					$this->Schedule->save($schedule);
		}

		if (isset($this->params['url']['p']))
			if ($this->params['url']['p'] == 1)
			{
				$p = $this->Purge->find('all');
				$pCount = count($p);
				for ($i = 0; $i < $pCount; $i++)
					if ($p[$i]['Purge']['id'] != 1 && $p[$i]['Purge']['id'] != 2 && $p[$i]['Purge']['id'] != 3)
						$this->Purge->delete($p[$i]['Purge']['id']);
					else
					{
						$p[$i]['Purge']['user_id'] = 1;
						$this->Purge->save($p[$i]);
					}
				$purgeList = [];
				$purgeList['start'] = date('Y-m-d H:i:s');
				$purgeList['empty_uts'] = 'in progress...';
				$purgeList['purge'] = 'in progress...';
				$purgeList['count'] = 'in progress...';
				$purgeList['archive'] = 'in progress...';
				$purgeList['tsumego_scores'] = 'in progress...';
				$purgeList['set_scores'] = 'in progress...';
				$this->PurgeList->create();
				$this->PurgeList->save($purgeList);
				$p = 1;
			}
		$s = $this->Set->find('all', ['conditions' => ['public' => 1]]);
		$de = $this->Set->find('all', ['conditions' => ['public' => -1]]);
		$in = $this->Set->find('all', ['conditions' => ['public' => 0]]);

		$a = [];
		$inCount = count($in);
		for ($i = 0; $i < $inCount; $i++)
			array_push($a, $in[$i]['Set']['id']);
		$in = $a;

		$a = [];
		$deCount = count($de);
		for ($i = 0; $i < $deCount; $i++)
			array_push($a, $de[$i]['Set']['id']);
		$de = $a;

		$ans = $this->Answer->find('all', ['limit' => 100, 'order' => 'created DESC']);
		$s = $this->Schedule->find('all', ['limit' => 100, 'order' => 'date DESC']);

		$this->set('ans', $ans);
		$this->set('s', $s);
		$this->set('p', $p);
		$this->set('pl', $pl);
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
}
