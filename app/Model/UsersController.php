<?php
App::uses('CakeEmail', 'Network/Email');
class UsersController extends AppController{
	public $name = 'Users';
	public $pageTitle = "Users";
	public $helpers = array('Html', 'Form');
	
	public function resetpassword(){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'Tsumego Hero - Sign In';
		if(!empty($this->data)){
			$u = $this->User->findByEmail($this->data['User']['email']);
			if($u){
				$length = 20;
				$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
				$charactersLength = strlen($characters);
				$randomString = '';
				for ($i = 0; $i < $length; $i++) {
					$randomString .= $characters[rand(0, $charactersLength - 1)];
				}
				$u['User']['passwordreset'] = $randomString;
				$this->User->save($u);
				
				$Email = new CakeEmail();
				$Email->from(array('me@joschkazimdars.com' => 'https://tsumego-hero.com'));
				$Email->to($this->data['User']['email']);
				$Email->subject('Password reset for your Tsumego Hero account');
				$ans = 'Click the following button to reset your password. If you have not requested the password reset, then ignore this email. https://tsumego-hero.com/users/newpassword/'.$randomString;
				$Email->send($ans);
			}
			$this->set('sent', true);
		}else{
			$this->set('sent', false);
		}
	}
	
	public function newpassword($checksum=null){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'Tsumego Hero - Sign In';
		$valid = false;
		$done = false;
		if($checksum==null) $checksum=1;
		$u = $this->User->find('first', array('conditions' =>  array('passwordreset' => $checksum)));
		if(!empty($this->data)){
			$newPw = $this->tinkerEncode($this->data['User']['pw'], 1);
			$u['User']['pw'] = $newPw;
			$this->User->save($u);
			$done = true;
		}else{
			if($u!=null){
				$valid = true;
			}
		}
		$this->set('u', $u['User']['name']);
		$this->set('valid', $valid);
		$this->set('done', $done);
		$this->set('checksum', $checksum);
	}
	
	public function routine1(){//0:00
		$this->loadModel('DayRecord');
		$today = date('Y-m-d');
		$dateUser = $this->DayRecord->find('first', array('conditions' =>  array('date' => $today)));
		if(count($dateUser)==0){
			$this->uotd();
			$this->userRefresh();
			$this->deleteUserBoards();
			//$this->archive();
		}			
	}
	
	public function routine2(){//0:05
		$this->halfXP();
	}
	
	private function archive(){
		$ux = $this->User->find('all', array('limit' => 700, 'order' => 'created DESC'));
		$u = array();
		for($i=0; $i<count($ux); $i++){
			if($ux[$i]['User']['created'] < date('Y-m-d H:i:s', strtotime('-7 days'))) array_push($u, $ux[$i]);
		}
		for($i=0; $i<count($u); $i++){
			//$this->removePlayer($u[$i]['User']['id']);
		}
	}
	
	public function routine3(){//0:10
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$ux = $this->User->find('all', array('limit' => 400, 'order' => 'created DESC'));
		$u = array();
		$d1 = date('Y-m-d', strtotime('-7 days'));
		for($i=0; $i<count($ux); $i++){
			$date = new DateTime($ux[$i]['User']['created']);
			$date = $date->format('Y-m-d');
			if($date==$d1){
				$this->removePlayer($ux[$i]['User']['id']);
			}
		}
		$this->set('u', $d1);
	}
	
	public function routine4(){//0:15
		$this->routine3();
	}
	
	public function routine5(){//0:20
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		
		$users = $this->User->find('all', array('limit' => 100, 'order' => 'created DESC'));
		for($i=0; $i<count($users); $i++){
			$uid = $users[$i]['User']['id'];
			$ux = $this->User->findById($uid);
			$uts = $this->UserTsumego->find('all', array('conditions' =>  array('user_id' => $uid)));
			$solvedUts = array();
			for($j=0; $j<count($uts); $j++){
				if($uts[$j]['UserTsumego']['status']=='S' || $uts[$j]['UserTsumego']['status']=='W' || $uts[$j]['UserTsumego']['status']=='C'){
					array_push($solvedUts, $uts[$j]);
				}			
			}
			$uts = $this->OldUserTsumego->find('all', array('conditions' =>  array('user_id' => $uid)));
			$solvedUts2 = array();
			for($j=0; $j<count($uts); $j++){
				if($uts[$j]['OldUserTsumego']['status']=='S' || $uts[$j]['OldUserTsumego']['status']=='W' || $uts[$j]['OldUserTsumego']['status']=='C'){
					array_push($solvedUts2, $uts[$j]);
				}			
			}
			$ux['User']['solved'] = count($solvedUts) + count($solvedUts2);
			$this->User->save($ux);
		}
		
		$this->set('u', $users);
	}
	
	public function routine6(){
		$this->loadModel('Answer');
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$a = $this->Answer->findById(1);
		$u = $this->User->find('all', array('order' => 'id ASC', 'conditions' =>  array(
			'id >' => $a['Answer']['message'],
			'id <=' => $a['Answer']['dismissed']
		)));
		for($i=0; $i<count($u); $i++){
			$solvedUts = $this->UserTsumego->find('all', array('conditions' =>  array(
				'user_id' => $u[$i]['User']['id'],
				'OR' => array(
					array('status' => 'S'),
					array('status' => 'W'),
					array('status' => 'C')
				)
			)));
			$solvedUts2 = $this->OldUserTsumego->find('all', array('conditions' =>  array(
				'user_id' => $u[$i]['User']['id'],
				'OR' => array(
					array('status' => 'S'),
					array('status' => 'W'),
					array('status' => 'C')
				)
			)));
			$u[$i]['User']['solved'] = count($solvedUts)+count($solvedUts2);
			$this->User->save($u);
		}
		$uLast = $this->User->find('first', array('order' => 'id DESC'));
		if($uLast['User']['id']<$a['Answer']['message']){
			$a['Answer']['message'] = 0;
			$a['Answer']['dismissed'] = 300;
		}else{
			$a['Answer']['message'] += 300;
			$a['Answer']['dismissed'] += 300;
		}
		$this->Answer->save($a);
		$this->set('u', $u);
	}
	
	public function userstats($uid = null){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'USER STATS';
		$this->LoadModel('UserRecord');
		$this->LoadModel('Set');
		$this->LoadModel('Tsumego');
		if($uid == null){
			$ur = $this->UserRecord->find('all', array('limit' => 500, 'order' => 'created DESC'));
		}else{
			$ur = $this->UserRecord->find('all', array('limit' => 500, 'order' => 'created DESC', 'conditions' => array('user_id' => $uid)));
		}	
		 
		for($i=0; $i<count($ur); $i++){
			$u = $this->User->findById($ur[$i]['UserRecord']['user_id']);
			$ur[$i]['UserRecord']['user_name'] = $u['User']['name'];
			$t = $this->Tsumego->findById($ur[$i]['UserRecord']['tsumego_id']);
			$ur[$i]['UserRecord']['tsumego_num'] = $t['Tsumego']['num'];
			$ur[$i]['UserRecord']['tsumego_xp'] = $t['Tsumego']['difficulty']*10;
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$ur[$i]['UserRecord']['set_name'] = $s['Set']['title'];
		}
		
		$noIndex = false;
		if($uid != null) $noIndex = true;
		if(isset($this->params['url']['c'])) $this->set('count', 1);
		else $this->set('count', 0);
		$this->set('noIndex', $noIndex);
		$this->set('ur', $ur);
	}
	
	public function stats($p=null){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'PAGE STATS';
		$this->LoadModel('UserTsumego');
		$this->LoadModel('Comment');
		$this->LoadModel('User');
		$this->LoadModel('DayRecord');
		$this->LoadModel('Tsumego');
		$this->LoadModel('Set');
		
		$today = date('Y-m-d', strtotime('today'));
		
		if(isset($this->params['url']['c'])){
			$cx = $this->Comment->findById($this->params['url']['c']);	
			$cx['Comment']['status'] = $this->params['url']['s'];
			$this->Comment->save($cx);
		}
		
		$comments = $this->Comment->find('all', array('order' => 'created DESC'));
		$c1 = array();
		$c2 = array();
		$c3 = array();
		for($i=0; $i<count($comments); $i++){
			if(is_numeric($comments[$i]['Comment']['status'])){
				if($comments[$i]['Comment']['status']==0) array_push($c1, $comments[$i]);
			}
		}
		$comments = $c1;
		for($i=0; $i<count($comments); $i++){
			$t = $this->Tsumego->findById($comments[$i]['Comment']['tsumego_id']);
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			if($s['Set']['public']==1) array_push($c2, $comments[$i]);
			else array_push($c3, $comments[$i]);
		}
		if($p=='public'){
			$comments = $c2;
		}else if ($p=='sandbox'){
			$comments = $c3;
		}else if($p!=0 && is_numeric($p)){
			$comments = $this->Comment->find('all', array('order' => 'created DESC', 'conditions' => array('user_id' => $p)));
		}
		
		$todaysUsers = array();
		$activity = $this->User->find('all', array('order' => array('User.reuse3 DESC')));
		
		for($i=0; $i<count($comments); $i++){
			$userID = $comments[$i]['Comment']['user_id'];
			for($j=0; $j<count($activity); $j++){
				if($activity[$j]['User']['id']==$userID){
					$comments[$i]['Comment']['user_id'] = $activity[$j]['User']['id'];
					$comments[$i]['Comment']['user_name'] = $activity[$j]['User']['name'];
					$comments[$i]['Comment']['email'] = $activity[$j]['User']['email'];
					$t = $this->Tsumego->findById($comments[$i]['Comment']['tsumego_id']);
					$set = $this->Set->findById($t['Tsumego']['set_id']);
					$comments[$i]['Comment']['set'] = $set['Set']['title'];
					$comments[$i]['Comment']['set2'] = $set['Set']['title2'];
					$comments[$i]['Comment']['num'] = $t['Tsumego']['num'];
				}				
			}
		}
		$comments2 = array();
		for($i=0; $i<count($comments); $i++){
			if(is_numeric($comments[$i]['Comment']['status'])){
				if($comments[$i]['Comment']['status']==0) array_push($comments2, $comments[$i]);
			}
		}
		$comments = $comments2;
		
		for($i=0; $i<count($activity); $i++){
			$a = new DateTime($activity[$i]['User']['created']);
			if($a->format('Y-m-d')==$today) array_push($todaysUsers, $activity[$i]['User']);
		}
		
		$this->set('c1', count($c1));
		$this->set('c2', count($c2));
		$this->set('c3', count($c3));
		$this->set('page', $p);
		$this->set('u', $todaysUsers);
		$this->set('comments', $comments);
	}
	
	public function adminstats($p=null){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'ADMIN STATS';
		$this->LoadModel('UserTsumego');
		$this->LoadModel('Comment');
		$this->LoadModel('User');
		$this->LoadModel('DayRecord');
		$this->LoadModel('Tsumego');
		$this->LoadModel('Set');
		$this->LoadModel('AdminActivity');
		
		$aa = $this->AdminActivity->find('all', array('limit' => 300, 'order' => 'created DESC'));
		$aa1 = array();
		$aa2 = array();
		
		for($i=0; $i<count($aa); $i++){
			$at = $this->Tsumego->find('first', array('conditions' =>  array('id' => $aa[$i]['AdminActivity']['tsumego_id'])));
			$as = $this->Set->find('first', array('conditions' =>  array('id' => $at['Tsumego']['set_id'])));
			$au = $this->User->find('first', array('conditions' =>  array('id' => $aa[$i]['AdminActivity']['user_id'])));
			$aa[$i]['AdminActivity']['name'] = $au['User']['name'];
			$aa[$i]['AdminActivity']['tsumego'] = $as['Set']['title'].' '.$at['Tsumego']['num'];
			if(strpos($aa[$i]['AdminActivity']['answer'], '.sgf')) array_push($aa1, $aa[$i]);
			else array_push($aa2, $aa[$i]);
		}
		
		$this->set('aa', $aa);
		$this->set('aa1', $aa1);
		$this->set('aa2', $aa2);
	}
	
	public function removePlayer($id=null){
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$u = $this->User->findById($id);
		$u['User']['dbstorage'] = 2;
		$this->User->save($u);
		
		$uts = $this->UserTsumego->find('all', array('conditions' => array('user_id' => $id)));
		$outs = array();
		for($i=0; $i<count($uts); $i++){
			$outs[$i]['OldUserTsumego'] = $uts[$i]['UserTsumego'];
			$this->OldUserTsumego->save($outs[$i]);
			$this->UserTsumego->delete($uts[$i]['UserTsumego']['id']);
		}
	}
	
	public function addPlayer($id=null){
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$u = $this->User->findById($id);
		$u['User']['dbstorage'] = 1;
		$this->User->save($u);
		
		$outs = $this->OldUserTsumego->find('all', array('conditions' => array('user_id' => $id)));
		$uts = array();
		for($i=0; $i<count($outs); $i++){
			$uts[$i]['UserTsumego'] = $outs[$i]['OldUserTsumego'];
			$this->UserTsumego->save($uts[$i]);
			$this->OldUserTsumego->delete($outs[$i]['OldUserTsumego']['id']);
		}
	}
	
	public function login(){
		$this->loadModel('UserTsumego');
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'Tsumego Hero - Sign In';
		if(!empty($this->data)){
			$u = $this->User->findByName($this->data['User']['name']);
			if($u){
				if($this->validateLogin($this->data)){
					$this->LoadModel('Visit');
					$vs = $this->Visit->find('all', 
						array('order' => 'created',	'direction' => 'DESC', 'conditions' =>  array('user_id' => $u['User']['id']))
					);
					if($vs!=null) $_SESSION['lastVisit'] = $vs[count($vs)-1]['Visit']['tsumego_id'];
					for($i=1;$i<=54;$i++){
						if($u['User']['texture'.$i] == '1') $u['User']['texture'.$i] = 'checked';
						$_SESSION['texture'.$i] = $u['User']['texture'.$i];
					}
					$this->Session->setFlash(__('Login successful.', true));
					
					$isLoaded = $this->UserTsumego->find('first', array('conditions' => array('user_id' => $u['User']['id'])));
					if(count($isLoaded)==0) $_SESSION['redirect'] = 'loading';
					else $_SESSION['redirect'] = 'sets';
				}else{
					$this->Session->setFlash(__('Login incorrect.', true));
				}
			}else{
				$this->Session->setFlash(__('Login incorrect.', true));
			}
		}
	}
	
	public function loading(){
	}
	
	public function loading2($sum=null){
		if($sum=='8571380'){
			$this->addPlayer($_SESSION['loggedInUser']['User']['id']);
		}
	}
	
	public function add(){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'Tsumego Hero - Sign Up';
		if(!empty($this->data)){
			$userData = $this->data;
			$userData['User']['pw'] = $this->tinkerEncode($this->data['User']['pw'], 1);
			$userData['User']['pw2'] = $this->tinkerEncode($this->data['User']['pw2'], 1);
			
			if($this->data['User']['pw'] == $this->data['User']['pw2']){
				if(strlen($this->data['User']['pw'])<4){
					$userData['User']['pw'] = 'x';
					$userData['User']['pw2'] = 'x';
				}
			}
			
			$this->User->create();
			if($this->User->save($userData, true)){
				if($this->validateLogin($this->data)) $this->Session->setFlash(__('Registration successful.', true));
                else $this->Session->setFlash(__('Login incorrect.', true));
				//$this->redirect('/sets');
				$_SESSION['redirect'] = 'sets';
			}
		}
	}
	
	public function highscore(){
		$_SESSION['page'] = 'highscore';
		$_SESSION['title'] = 'Tsumego Hero - Highscore';
		
		$this->LoadModel('UserTsumego');
		$this->LoadModel('Tsumego');
		
		$tsumegos = $this->Tsumego->find('all');
		$tsumegoNum = count($tsumegos);
		
		if(isset($_SESSION['loggedInUser'])){
			$ux = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
			$solvedUts = $this->UserTsumego->find('all', array('conditions' =>  array(
				'user_id' => $_SESSION['loggedInUser']['User']['id'],
				'OR' => array(
					array('status' => 'S'),
					array('status' => 'W'),
					array('status' => 'C')
				)
			)));
			
			$ux['User']['solved'] = count($solvedUts);
			$this->User->save($ux);
		}
		
		
		$users = $this->User->find('all', array('limit' => 1000, 'order' => 'level DESC'));
	
		$userP = array();
		$stop = 1;
		
		for($i=0;$i<count($users);$i++){
			if($stop<=1000){
				$lvl = 1;
				$toplvl = $users[$i]['User']['level'];
				$startxp = 50;
				$sum = 0;
				$xpJump = 10;
				
				for($j=1; $j<$toplvl; $j++){
					if($j>=11) $xpJump = 25;
					if($j>=19) $xpJump = 50;
					if($j>=39) $xpJump = 100;
					if($j>=69) $xpJump = 150;
					if($j==99) $xpJump = 50000;
					if($j==100) $xpJump = 1150;
					if($j>=101) $xpJump = 0;
					$sum+=$startxp;
					$startxp+=$xpJump;
					
				}
				$sum += $users[$i]['User']['xp'];
				$users[$i]['User']['xpSum'] = $sum;
				
				$stop++;
			}
		}
		
		$UxpSum = array(); 
		$Uname = array();
		$Ulevel = array();
		$Uid = array();
		$Utype = array();
		$Usolved = array();
		$stop = 1;
		$anz = 0;
		for($i=0;$i<count($users);$i++){
			if($stop<=1000 && $anz<1000){
				array_push($UxpSum, $users[$i]['User']['xpSum']);
				if(strlen($users[$i]['User']['name'])>20) $users[$i]['User']['name'] = substr($users[$i]['User']['name'], 0, 20);
				array_push($Uname, $users[$i]['User']['name']);
				array_push($Ulevel, $users[$i]['User']['level']);
				array_push($Uid, $users[$i]['User']['id']);
				array_push($Utype, $users[$i]['User']['premium']);
				if($users[$i]['User']['solved']==null) $users[$i]['User']['solved']=0;
				array_push($Usolved, $users[$i]['User']['solved']);
				$anz++;
			}
		}
		
		array_multisort($UxpSum, $Uname, $Ulevel, $Uid, $Utype, $Usolved);
		
		$users2 = array();
		for($i=0; $i<count($UxpSum); $i++){
			$a = array();
			$a['xpSum'] = $UxpSum[$i];
			$a['name'] = $Uname[$i];
			$a['level'] = $Ulevel[$i];
			$a['id'] = $Uid[$i];
			$a['type'] = $Utype[$i];
			$a['solved'] = $Usolved[$i];
			array_push($users2, $a);
		}
	
		$this->set('users', $users2);
	}
	
	public function leaderboard(){
		$_SESSION['page'] = 'leaderboard';
		$_SESSION['title'] = 'Tsumego Hero - Leaderboard';
		
		$this->LoadModel('UserTsumego');
		$this->LoadModel('Tsumego');
		$this->LoadModel('DayRecord');
		
		$dayRecord = $this->DayRecord->find('all', array('limit' => 2, 'order' => 'id DESC'));
		$userYesterday = $this->User->findById($dayRecord[0]['DayRecord']['user_id']);
		
		$tsumegos = $this->Tsumego->find('all');
		$tsumegoNum = count($tsumegos);
		
		$activity = $this->User->find('all', array('order' => array('User.reuse3 DESC')));
		$this->set('a', $activity);
		for($i=0; $i<count($users); $i++){
			if($users[$i]['User']['name']=='noUser'){
				unset($users[$i]);
				break;
			}
		}
		$userP = array();
		$stop = 1;
		
		for($i=count($users); $i>=0; $i--){
				if($stop<=100){
					//$uts = $this->UserTsumego->find('all', array('conditions' =>  array('user_id' => $users[$i]['User']['id'])));
					$lvl = 1;
					$toplvl = $users[$i]['User']['level'];
					$startxp = 50;
					$sum = 0;
					$xpJump = 10;
					
					for($j=1; $j<$toplvl; $j++){
						if($j>=11) $xpJump = 25;
						if($j>=19) $xpJump = 50;
						if($j>=39) $xpJump = 100;
						if($j>=69) $xpJump = 150;
						if($j==99) $xpJump = 50000;
						if($j==100) $xpJump = 1150;
						if($j>=101) $xpJump = 0;
						$sum+=$startxp;
						$startxp+=$xpJump;
						
					}
					$sum += $users[$i]['User']['xp'];
					$users[$i]['User']['xpSum'] = $sum;
				
					$stop++;
				}
			}
			
			$UxpSum = array(); 
			$Uname = array();
			$Ulevel = array();
			$Uid = array();
			$stop = 1;
			$anz = 0;
			for($i=count($users); $i>=0; $i--){
				if($stop<=100 && $anz<100){
					array_push($UxpSum, $users[$i]['User']['xpSum']);
					array_push($Uname, $users[$i]['User']['name']);
					array_push($Ulevel, $users[$i]['User']['level']);
					array_push($Uid, $users[$i]['User']['id']);
					$anz++;
				}
			}
		
		array_multisort($UxpSum, $Uname, $Ulevel, $Uid);
		
		$users2 = array();
		for($i=0; $i<count($UxpSum); $i++){
			$a = array();
			$a['xpSum'] = $UxpSum[$i];
			$a['name'] = $Uname[$i];
			$a['level'] = $Ulevel[$i];
			$a['id'] = $Uid[$i];
			array_push($users2, $a);
		}
		
		$accessList = $this->User->find('all', array('conditions' =>  array('premium' => 2)));
		$access = array();
		for($i=0; $i<count($accessList); $i++){
			array_push($access, $accessList[$i]['User']['name']);
		}
		
		$todaysUsers = array();
		$today = date('Y-m-d', strtotime('today'));
		for($i=0; $i<count($activity); $i++){
			$a = new DateTime($activity[$i]['User']['created']);
			if($a->format('Y-m-d')==$today) array_push($todaysUsers, $activity[$i]['User']);
		}
		$this->set('uNum', count($todaysUsers));
		
		$this->set('users2', $users2);
		$this->set('users', $users2);
		$this->set('access', $access);
		$this->set('dayRecord', $userYesterday['User']['name']);
	}
	
	public function view($id = null){
		$_SESSION['page'] = 'user';
		if($_SESSION['loggedInUser']['User']['id']!=$id && $_SESSION['loggedInUser']['User']['id']!=72) $_SESSION['redirect'] = 'sets';
		
		$this->LoadModel('UserTsumego');
		$this->LoadModel('Tsumego');
		
		$user = $this->User->findById($id);
		$_SESSION['title'] = 'Profile of '.$user['User']['name'];
		
		if (!empty($this->data)){
			$this->User->create();
			$changeUser = $user;
			$changeUser['User']['email'] = $this->data['User']['email'];
			$this->set('data', $changeUser['User']['email']);
			$this->User->save($changeUser, true);
			$user = $this->User->findById($id);
		}
		
		$tsumegos = $this->Tsumego->find('all');
		
		$tsumegoDates = array();
		$invisibleSets = $this->getInvisibleSets();
		$dSets = $this->getDeletedSets();
		for($j=0; $j<count($tsumegos); $j++){
			if(!in_array($tsumegos[$j]['Tsumego']['set_id'], $invisibleSets) && !in_array($tsumegos[$j]['Tsumego']['set_id'], $dSets)) array_push($tsumegoDates, $tsumegos[$j]['Tsumego']['created']);
		}
		$tsumegoNum = count($tsumegoDates);
		
		
		$uts = $this->UserTsumego->find('all', array('conditions' =>  array('user_id' => $id)));
				
		$solvedUts = array();
		$solvedUts2 = array();
		for($j=0; $j<count($uts); $j++){
			if($uts[$j]['UserTsumego']['status']=='S' || $uts[$j]['UserTsumego']['status']=='W' || $uts[$j]['UserTsumego']['status']=='C'){
				array_push($solvedUts2, $uts[$j]);
				$date = new DateTime($uts[$j]['UserTsumego']['created']);
				$uts[$j]['UserTsumego']['created'] = $date->format('Y-m-d');
				$oldest = new DateTime(date('Y-m-d', strtotime('-30 days')));
				if($uts[$j]['UserTsumego']['created']>$oldest->format('Y-m-d')){
					array_push($solvedUts, $uts[$j]);
				}
			}			
		}
		
		$lvl = 1;
		$toplvl = $user['User']['level'];
		$startxp = 50;
		$sumx = 0;
		$xpJump = 10;
		
		for($i=1; $i<$toplvl; $i++){
			if($i>=11) $xpJump = 25;
			if($i>=19) $xpJump = 50;
			if($i>=39) $xpJump = 100;
			if($i>=69) $xpJump = 150;
			if($i>=99) $xpJump = 50000;
			if($i==100) $xpJump = 1150;
			if($i>=101) $xpJump = 0;
			$sumx+=$startxp;
			$startxp+=$xpJump;
			
		}
		$sumx += $user['User']['xp'];
		
		$ranks = $this->User->find('all', array('limit' => 1000, 'order' => 'level DESC'));
		$rank = 0;
		
		for($i=0;$i<count($ranks);$i++){
				$lvl = 1;
				$toplvl = $ranks[$i]['User']['level'];
				$startxp = 50;
				$SUM = 0;
				$xpJump = 10;
				
				for($j=1; $j<$toplvl; $j++){
					if($j>=11) $xpJump = 25;
					if($j>=19) $xpJump = 50;
					if($j>=39) $xpJump = 100;
					if($j>=69) $xpJump = 150;
					if($j==99) $xpJump = 50000;
					if($j==100) $xpJump = 1150;
					if($j>=101) $xpJump = 0;
					$SUM+=$startxp;
					$startxp+=$xpJump;
					
				}
				$SUM += $ranks[$i]['User']['xp'];
				$ranks[$i]['User']['xpSum'] = $SUM;
		}
		
		$UxpSum = array(); 
		$Uname = array();
		$Ulevel = array();
		$Uid = array();
		$Utype = array();
		$Usolved = array();
		
		for($i=0;$i<count($ranks);$i++){
			array_push($UxpSum, $ranks[$i]['User']['xpSum']);
			array_push($Uname, $ranks[$i]['User']['name']);
			array_push($Ulevel, $ranks[$i]['User']['level']);
			array_push($Uid, $ranks[$i]['User']['id']);
			array_push($Utype, $ranks[$i]['User']['premium']);
			array_push($Usolved, $ranks[$i]['User']['solved']);
		}
		
		array_multisort($UxpSum, $Uname, $Ulevel, $Uid, $Utype, $Usolved);
		
		$userPosition = 1000;
		
		$users2 = array();
		for($i=0; $i<count($UxpSum); $i++){
			$a = array();
			$a['xpSum'] = $UxpSum[$i];
			$a['name'] = $Uname[$i];
			$a['level'] = $Ulevel[$i];
			$a['id'] = $Uid[$i];
			$a['type'] = $Utype[$i];
			$a['solved'] = $Usolved[$i];
			array_push($users2, $a);
			if($Uid[$i]==$id) break;
			$userPosition--;
		}
		
		$tDates = array();
		for($j=0; $j<count($solvedUts); $j++){
			$date = new DateTime($solvedUts[$j]['UserTsumego']['created']);
			$solvedUts[$j]['UserTsumego']['created'] = $date->format('Y-m-d');
			array_push($tDates, $solvedUts[$j]['UserTsumego']['created']);
		}

		$graphData1 = array_count_values($tDates);
		$graph = array();
		$nextDay = '';
		$c = 0;
		
		$dt = new DateTime();
		$dt = $dt->format('Y-m-d');
		if(!isset($graphData1[$dt])){
			$graphData1[$dt] = 0.1;
		}
		
		$oldest = new DateTime(date('Y-m-d', strtotime('-30 days')));
		$oldest = $oldest->format('Y-m-d');
		
		$graphData2 = array();
		$counter = 0;
		while($oldest!=$dt){
			$graphData2[$counter]['date'] = $oldest;
			if(isset($graphData1[$oldest])) $graphData2[$counter]['num'] = $graphData1[$oldest];
			else $graphData2[$counter]['num'] = 0;
			
			$oldest = new DateTime($oldest);
			$oldest = $oldest->modify('+1 day');
			$oldest = $oldest->format('Y-m-d');
			$counter++;
		}
		
		$this->set('xpSum', $sumx);
		$this->set('rank', $userPosition);
		$this->set('uts', $solvedUts2);
		$this->set('graph', $graphData2);
		$this->set('user', $user);
		$this->set('tsumegos', $tsumegoDates);
		$this->set('tsumegoNum', $tsumegoNum);
		$this->set('solved', $user['User']['solved']);
	}
	
	public function donate($id = null){
		$_SESSION['page'] = 'home';
		$_SESSION['title'] = 'Tsumego Hero - Donate';
		$this->set('id', $id);
	}
	
	public function authors(){
		$_SESSION['page'] = 'home';
		$_SESSION['title'] = 'Tsumego Hero - Authors';
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$authors = $this->Tsumego->find('all', array('order' => 'created DESC', 'conditions' =>  array(
			'NOT' => array(
				'author' => array('Joschka Zimdars')
			)
		)));
		$set = $this->Set->find('all');
		$setMap = array();
		for($i=0; $i<count($set); $i++){
			$divider = ' ';
			$setMap[$set[$i]['Set']['id']] = $set[$i]['Set']['title'].$divider.$set[$i]['Set']['title2'];
		}
		
		$count = array();
		$count[0]['author'] = 'Innokentiy Zabirov';
		$count[0]['collections'] = 's: <b> Life & Death - Intermediate and Gokyo Shumyo</b>';
		$count[0]['count'] = 0;
		$count[1]['author'] = 'Alexandre Dinerchtein';
		$count[1]['collections'] = ': <b>Problems from Professional Games</b>';
		$count[1]['count'] = 0;
		$count[2]['author'] = 'David Ulbricht';
		$count[2]['collections'] = ': <b>Life & Death - Intermediate</b>';
		$count[2]['count'] = 0;
		$count[3]['author'] = 'Bradford Malbon';
		$count[3]['collections'] = 's: <b>Easy Life, Easy Kill</b>';
		$count[3]['count'] = 0;
		$count[4]['author'] = 'Ryan Smith';
		$count[4]['collections'] = 's: <b>Korean Problem Academy 1-4</b>';
		$count[4]['count'] = 0;
		
		
		
		
		
		for($i=0; $i<count($authors); $i++){
			if($authors[$i]['Tsumego']['set_id']!=120){
			$authors[$i]['Tsumego']['set'] = $setMap[$authors[$i]['Tsumego']['set_id']];
			$date = new DateTime($authors[$i]['Tsumego']['created']);
			$month = $date->format('m.');
			$tday = $date->format('d.');
			$tyear = $date->format('Y');
			if($tday[0]==0) $tday = substr($tday, -3);
			$authors[$i]['Tsumego']['created'] = $tday.$month.$tyear;
			
			if($authors[$i]['Tsumego']['author'] == $count[0]['author']) $count[0]['count']++;
			elseif($authors[$i]['Tsumego']['author'] == $count[1]['author']) $count[1]['count']++;
			elseif($authors[$i]['Tsumego']['author'] == $count[2]['author']) $count[2]['count']++;
			elseif($authors[$i]['Tsumego']['author'] == $count[3]['author']) $count[3]['count']++;
			elseif($authors[$i]['Tsumego']['author'] == $count[4]['author']) $count[4]['count']++;
			elseif($authors[$i]['Tsumego']['author'] == $count[5]['author']) $count[5]['count']++;
			elseif($authors[$i]['Tsumego']['author'] == $count[6]['author']) $count[6]['count']++;
			elseif($authors[$i]['Tsumego']['author'] == $count[7]['author']) $count[7]['count']++;
			}
		}
		$this->set('count', $count);
		$this->set('t', $authors);
	}
	
	public function success($id = null){
		$_SESSION['page'] = 'home';
		$_SESSION['title'] = 'Tsumego Hero - Success';
		
		$s = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
		$s['User']['reward'] = date('Y-m-d H:i:s');
		$this->User->create();
		$this->User->save($s);
		
		$Email = new CakeEmail();
		$Email->from(array('me@joschkazimdars.com' => 'https://tsumego-hero.com'));
		$Email->to('joschka.zimdars@googlemail.com');
		$Email->subject('Donation');
		if(isset($_SESSION['loggedInUser'])) $ans = $_SESSION['loggedInUser']['User']['name'].' '.$_SESSION['loggedInUser']['User']['email'];
		else $ans = 'no login';
		$Email->send($ans);
		$this->set('id', $id);
	}
	
	public function penalty($id = null){
		$_SESSION['page'] = 'home';
		$_SESSION['title'] = 'Tsumego Hero - Penalty';
		
		$p = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
		$p['User']['penalty'] = $p['User']['penalty']+1;
		$this->User->create();
		$this->User->save($p);
		
		$this->set('id', $id);
	}
	
	public function sets($id = null){
		$this->set('id', $id);
	}
	
	public function logout() {
		unset($_SESSION['loggedInUser']);
		//$this->redirect( '/sets' );
		$_SESSION['redirect'] = 'sets';
		$this->Session->setFlash(__('You have singed out.', true));
	}
	
	public function delete($id){
		$this->LoadModel('Comment');
		if ($this->request->is('get')) {
			throw new MethodNotAllowedException();
		}

		if ($this->Comment->delete($id)){
			$this->Flash->success(
				__('The post with id: %s has been deleted.', h($id))
			);
		} else {
			$this->Flash->error(
				__('The post with id: %s could not be deleted.', h($id))
			);
		}

		return $this->redirect(array('action' => '/stats'));
	}
	
	private function validateLogin($data){
		$u = $this->User->findByName($data['User']['name']);
		if($this->tinkerDecode($u['User']['pw'], 1) == $data['User']['pw']){
			$_SESSION['loggedInUser'] = $u;
			return true;
		}else{  
			return false;
		}
	}
	
	private function tinkerEncode($string,$key){
		 $j = 1.0;
		 $hash = '';
		 $key = sha1($key);
		 $strLen = strlen($string);
		 $keyLen = strlen($key);
		 for ($i = 0; $i < $strLen; $i++) {
			 $ordStr = ord(substr($string,$i,1));
			 if ($j == $keyLen) { $j = 0; }
			 $ordKey = ord(substr($key,$j,1));
			 $j++;
			 $hash .= strrev(base_convert(dechex($ordStr + $ordKey),16,36));
		 }
		 return $hash;
	}
	
	private function tinkerDecode($string,$key){
		 $j = 1.0;
		 $hash = '';
		 $key = sha1($key);
		 $strLen = strlen($string);
		 $keyLen = strlen($key);
		 for ($i = 0; $i < $strLen; $i+=2) {
			 $ordStr = hexdec(base_convert(strrev(substr($string,$i,2)),36,16));
			 if ($j == $keyLen) { $j = 0; }
			 $ordKey = ord(substr($key,$j,1));
			 $j++;
			 $hash .= chr($ordStr - $ordKey);
		 }
		 return $hash;
	}
	
	public function listplayers(){ //list players in ut database
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$ux = $this->User->find('all', array('limit' => 300, 'order' => 'created DESC'));
		$u = array();
		for($i=0; $i<count($ux); $i++){
			$uts1 = $this->UserTsumego->find('first', array('conditions' => array('user_id' => $ux[$i]['User']['id'])));
			$uts2 = $this->OldUserTsumego->find('first', array('conditions' => array('user_id' => $ux[$i]['User']['id'])));
			$date = new DateTime($ux[$i]['User']['created']);
			$date = $date->format('Y-m-d');
			$u[$i][0] = $ux[$i]['User']['id'];
			$u[$i][1] = $date;					
			if(count($uts1)==1 && count($uts2)==0) $u[$i][2] = 1;
			if(count($uts1)==0 && count($uts2)==1) $u[$i][2] = 2;
			if(count($uts1)==1 && count($uts2)==1) $u[$i][2] = 3;
			if(count($uts1)==0 && count($uts2)==0) $u[$i][2] = 4;
		}
		$this->set('u', $u);
	}
	
	public function test2(){
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		//$this->removePlayer(1091);
		
	}
	
	public function test(){
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$ux = $this->User->find('all', array('order' => 'created DESC'));
		$u = array();
		//$date1 = date('Y-m-d H:i:s', strtotime('-1 days')); 
		$date1 = date('Y-m-d H:i:s');
		$date2 = '2019-05-26 06:48:09';
		$end = false;
		for($i=0; $i<count($ux); $i++){
			if(!$end){
				
			}
		}
		//$this->removePlayer(136);
		$this->set('u', $u);
	}
	
	public function activeuts(){ //count active uts
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$ux = $this->User->find('all', array('order' => 'created DESC'));
		$u = array();
		for($i=0; $i<count($ux); $i++){
			if($ux[$i]['User']['dbstorage']==1){
				$uts = $this->UserTsumego->find('all', array('conditions' => array('user_id' => $ux[$i]['User']['id'])));
				array_push($u, count($uts));
			}
		}
		$this->set('u', $u);
	}
	
	public function cleanuts(){
		$this->loadModel('User');
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$this->loadModel('Answer');
		
		$dbToken = $this->Answer->findById(1);
		$start = $dbToken['Answer']['message'];
		$increment = 200;
		$dbToken['Answer']['message'] += $increment;
		$this->Answer->save($dbToken);
		
		$end = $start+$increment;
		$u = array();
		
		$all = $this->User->find('all', array('order' => 'id ASC'));
		for($i=$start; $i<$end; $i++){
			$uts = $this->UserTsumego->find('first', array('conditions' => array('user_id' => $all[$i]['User']['id'])));
			$outs = $this->OldUserTsumego->find('first', array('conditions' => array('user_id' => $all[$i]['User']['id'])));
			if(count($uts)!=0 && count($outs)==0) $all[$i]['User']['x'] = 1;
			else if(count($uts)==0 && count($outs)!=0) $all[$i]['User']['x'] = 2;
			else if(count($uts)==0 && count($outs)==0) $all[$i]['User']['x'] = 3;
			else if(count($uts)!=0 && count($outs)!=0) $all[$i]['User']['x'] = 4;
			
			if($all[$i]['User']['x'] == 4) $this->addPlayer($all[$i]['User']['id']);
			
			array_push($u, $all[$i]);
		}
		
		$this->set('u', $u);
	}
	
	public function cleanuts2(){
		$this->loadModel('User');
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$this->loadModel('Answer');
		
		$dbToken = $this->Answer->findById(1);
		$start = $dbToken['Answer']['message'];
		$increment = 10;
		$dbToken['Answer']['message'] += $increment;
		$this->Answer->save($dbToken);
		
		$end = $start+$increment;
		$u = array();
		
		$all = $this->User->find('all', array('order' => 'id ASC'));
		for($i=$start; $i<$end; $i++){
			/*
			$uts = $this->UserTsumego->find('all', array('conditions' => array('user_id' => $all[$i]['User']['id'])));
			$outs = $this->OldUserTsumego->find('all', array('conditions' => array('user_id' => $all[$i]['User']['id'])));
			
			$idMap = array();
			$status = array();
			
			for($i=0; $i<count($uts); $i++){
				array_push($idMap, $uts[$i]['UserTsumego']['tsumego_id']);
			}
			$result = array_unique(array_diff_assoc($idMap, array_unique($idMap)));
			if(count($result)==0) $all[$i]['User']['x'] = 'clean';
			else $all[$i]['User']['x'] = 'duplicates';
			*/
			
			array_push($u, $all[$i]);
		}
		
		$this->set('u', $u);
	}

	public function playerdb($id){ 
		$this->loadModel('Answer');
		$this->loadModel('UserTsumego');
		$this->loadModel('Tsumego');
		$this->loadModel('ModifiedTsumego');
		$ux = $this->User->findById($id);
		$ut = $this->UserTsumego->find('all', array('conditions' => array('user_id' => $id)));
		$uty = array();
		$keep = 0;
		$deleted = 1;
		$all = count($ut);
		for($i=0; $i<count($ut); $i++){
			array_push($uty, $ut[$i]['UserTsumego']['tsumego_id']);
		}
		for($i=0; $i<count($ut); $i++){
			//if($ut[$i]['UserTsumego']['tsumego_id']>10000){
			if(true){	
				$t = $this->Tsumego->findById($ut[$i]['UserTsumego']['tsumego_id']);
				if(
					$t['Tsumego']['set_id'] == 117 ||
					$t['Tsumego']['set_id'] == 104 ||
					$t['Tsumego']['set_id'] == 105 ||
					$t['Tsumego']['set_id'] == 90 ||
					$t['Tsumego']['set_id'] == 94 ||
					$t['Tsumego']['set_id'] == 101 ||
					$t['Tsumego']['set_id'] == 106 ||
					$t['Tsumego']['set_id'] == 107 ||
					$t['Tsumego']['set_id'] == 108 ||
					$t['Tsumego']['set_id'] == 67 ||
					$t['Tsumego']['set_id'] == 69 ||
					$t['Tsumego']['set_id'] == 81 ||
					$t['Tsumego']['set_id'] == 92 ||
					$t['Tsumego']['set_id'] == 50 ||
					$t['Tsumego']['set_id'] == 52 ||
					$t['Tsumego']['set_id'] == 53 ||
					$t['Tsumego']['set_id'] == 54 ||
					$t['Tsumego']['set_id'] == 41 ||
					$t['Tsumego']['set_id'] == 49 ||
					$t['Tsumego']['set_id'] == 65 ||
					$t['Tsumego']['set_id'] == 66 ||
					$t['Tsumego']['set_id'] == 109 ||
					$t['Tsumego']['set_id'] == 122 ||
					$t['Tsumego']['set_id'] == 124 ||
					$t['Tsumego']['set_id'] == 113 ||
					$t['Tsumego']['set_id'] == 115 ||
					$t['Tsumego']['set_id'] == 38 ||
					$t['Tsumego']['set_id'] == 42 ||
					$t['Tsumego']['set_id'] == 114 ||
					$t['Tsumego']['set_id'] == 11969 ||
					$t['Tsumego']['set_id'] == 29156 ||
					$t['Tsumego']['set_id'] == 31813 ||
					$t['Tsumego']['set_id'] == 33007 ||
					$t['Tsumego']['set_id'] == 71790 ||
					$t['Tsumego']['set_id'] == 74761 ||
					$t['Tsumego']['set_id'] == 81578 ||
					$t['Tsumego']['set_id'] == 88156
				){
					$keep++;
				}else{
					$this->UserTsumego->delete($ut[$i]['UserTsumego']['id']);
					$deleted++;
				}
			}
		}
		$a = array_unique(array_diff_assoc($uty, array_unique($uty)));
		$a = array_values($a);
		$b = array();
		
		$this->Answer->create();
		$answer = array();
		$answer['Answer']['user_id'] = $id;
		$answer['Answer']['message'] = '|';
		for($i=0; $i<count($a); $i++){
			$utd = $this->UserTsumego->find('all', array('conditions' => array('user_id' => $id, 'tsumego_id' => $a[$i])));
			$c = count($utd);
			$j = 0;
			$b[$i]['uid'] = $id;
			$b[$i]['tid'] = $a[$i];
			$b[$i]['count'] = 1;
			$answer['Answer']['message'] .= $b[$i]['tid'].'-';
			while($c>1){
				$this->UserTsumego->delete($utd[$j]['UserTsumego']['id']);
				$b[$i]['count']++;
				if($c==2) $answer['Answer']['message'] .= $b[$i]['count'].'|';
				$c--;
				$j++;
			}
		}
		$answer['Answer']['comment_id'] = $all;
		$answer['Answer']['dismissed'] = $keep;
		$this->Answer->save($answer);
		
		//$this->playerdb3();
		
		$this->set('a', $b);
		$this->set('uty', $uty);
	}
	public function playerdb2(){
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$ux = $this->User->find('all', array('order' => 'id ASC'));
		$this->set('ux', $ux);
	}
	public function playerdb3(){
		$this->loadModel('Answer');
		
		$dbToken = $this->Answer->findById(1);
		$start = $dbToken['Answer']['message'];
		$u = $this->User->find('all', array('order' => 'id ASC'));
		$dbToken['Answer']['message']++;
		$this->Answer->save($dbToken);
		if($start<10) $this->playerdb($u[$start]['User']['id']);
		
		$this->set('ut', $u);
		$this->set('out', $out);
	}
	public function playerdb4(){
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$ux = $this->User->find('all', array('order' => 'id ASC', 'conditions' =>  array(
			'id >' => 0,
			'id <=' => 100
		)));
		$this->set('ux', $ux);
	}
	public function playerdb5(){
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$this->loadModel('Answer');
		
		$dbToken = $this->Answer->findById(1);
		$start = $dbToken['Answer']['message'];
		$increment = 100;
		$dbToken['Answer']['message'] += $increment;
		$this->Answer->save($dbToken);
		
		$ux = $this->User->find('all', array('order' => 'id ASC', 'conditions' =>  array(
			'id >' => $start,
			'id <=' => $start+$increment
		)));
		$u = array();
		for($i=0; $i<count($ux); $i++){
			$ut = $this->UserTsumego->find('all', array('conditions' => array(
				'user_id' => $ux[$i]['User']['id'],
				'OR' => array(
					array('status' => 'S'),
					array('status' => 'W'),
					array('status' => 'C')
				)
			)));
			$out = $this->OldUserTsumego->find('all', array('conditions' => array(
				'user_id' => $ux[$i]['User']['id'],
				'OR' => array(
					array('status' => 'S'),
					array('status' => 'W'),
					array('status' => 'C')
				)
			)));
			$c = array();
			$c['id'] = $ux[$i]['User']['id'];
			$c['name'] = $ux[$i]['User']['name'];
			$c['old'] = $ux[$i]['User']['solved'];
			$u['User']['solved'] = count($ut)+count($out);
			$c['new'] = $ux[$i]['User']['solved'];
			$this->User->save($u);
			
			array_push($u, $c);
		}
		
		$this->set('c', $u);
	}
	
	public function removeDuplicates(){
	}
	public function removeOldEntries($id){
		$this->loadModel('UserTsumego');
		$this->loadModel('OldUserTsumego');
		$ux = $this->User->find('all', array('order' => 'id ASC'));
		$this->set('ux', $ux);
	}
	
}
?>
