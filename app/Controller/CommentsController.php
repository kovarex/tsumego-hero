<?php
class CommentsController extends AppController{
  public function index(){
		$this->LoadModel('Tsumego');
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('Set');
		$this->LoadModel('User');
		$this->LoadModel('Sgf');
		$this->LoadModel('SetConnection');
		$_SESSION['title'] = 'Tsumego Hero - Discuss';
		$_SESSION['page'] = 'discuss';
		$c = array();
		$setsWithPremium = array();
		$index = 0;
		$counter = 0;
		$reverseOrder = false;
		$lastEntry = true;
		$firstPage = false;
		$paramcommentid = 0;
		$paramdirection = 0;
		$paramindex = 0;
		$paramyourcommentid = 0;
		$paramyourdirection = 0;
		$paramyourindex = 0;
		$unresolvedSet = 'true';
		if(!isset($this->params['url']['unresolved'])){ $unresolved = 'false'; $unresolvedSet = 'false'; }
		else $unresolved = $this->params['url']['unresolved'];
		if(!isset($this->params['url']['filter'])) $filter1 = 'true';
		else $filter1 = $this->params['url']['filter'];
		
		if(!isset($_SESSION['loggedInUser']['User']['id'])
			|| isset($_SESSION['loggedInUser']['User']['id']) && $_SESSION['loggedInUser']['User']['premium']<1
		)
			$hasPremium = false;
		else
			$hasPremium = true;
		$swp = $this->Set->find('all', array('conditions' => array('premium' => 1)));
		for($i=0;$i<count($swp);$i++)
			array_push($setsWithPremium, $swp[$i]['Set']['id']);

		if($filter1=='true'){
			$userTsumegos = $this->TsumegoStatus->find('all', array('conditions' =>  array(
				'user_id' => $_SESSION['loggedInUser']['User']['id'], 
				'OR' => array(
					array('status' => 'S'),
					array('status' => 'C'),
					array('status' => 'W')
				)
			)));
		}else{
			$userTsumegos = $this->TsumegoStatus->find('all', array('conditions' =>  array(
				'user_id' => $_SESSION['loggedInUser']['User']['id']
			)));
		}
		
		$keyList = array();
		$keyListStatus = array();
		for($i=0; $i<count($userTsumegos); $i++){
			$keyList[$i] = $userTsumegos[$i]['TsumegoStatus']['tsumego_id'];
			$keyListStatus[$i] = $userTsumegos[$i]['TsumegoStatus']['status'];
		}
		if(!isset($this->params['url']['comment-id'])){
			if($unresolved=='false'){
				$comments = $this->Comment->find('all', array('limit' => 500, 'order' => 'created DESC', 'conditions' => array(
					'NOT' => array('status' => 99)
				)));
			}elseif($unresolved=='true'){
				$comments = $this->Comment->find('all', array(
					'limit' => 500, 
					'order' => 'created DESC', 
					'conditions' =>  array(
						'status' => 0,
						array(
							'NOT' => array('user_id' => 0)
						)
					)
				));
				$filter1 = 'falsex';
			}
			$firstPage = true;
		}else{
			$paramcommentid = $this->params['url']['comment-id'];
			$paramdirection = $this->params['url']['direction'];
			$paramindex = $this->params['url']['index'];
			if($unresolved=='false'){
				if($this->params['url']['direction']=='next'){
					$comments = $this->Comment->find('all', array('limit' => 500, 'order' => 'created DESC','conditions' => array(
						'Comment.id <' => $this->params['url']['comment-id'],
						'NOT' => array('status' =>99)
					)));
				}else if(($this->params['url']['direction']=='prev')){
					$comments = $this->Comment->find('all', array('limit' => 500, 'order' => 'created ASC','conditions' => array(
						'Comment.id >' => $this->params['url']['comment-id'],
						'NOT' => array('status' =>99)
					)));
					$reverseOrder = true;
				}
			}elseif($unresolved=='true'){
				if($this->params['url']['direction']=='next'){
					$comments = $this->Comment->find('all', array('limit' => 500, 'order' => 'created DESC','conditions' => array(
						'Comment.id <' => $this->params['url']['comment-id'],
						'Comment.status ' => 0
					)));
				}else if(($this->params['url']['direction']=='prev')){
					$comments = $this->Comment->find('all', array('limit' => 500, 'order' => 'created ASC','conditions' => array(
						'Comment.id >' => $this->params['url']['comment-id'],
						'Comment.status ' => 0
					)));
					$reverseOrder = true;
				}
			}
			$index = $this->params['url']['index'];
		}
		if($filter1=='true'){
			$commentsBuffer = array();
			for($i=0; $i<count($comments); $i++){
				$t = $this->Tsumego->findById($comments[$i]['Comment']['tsumego_id']);
				$premiumLock = false;
				if(!$hasPremium)
					if(in_array($t['Tsumego']['set_id'], $setsWithPremium))
						$premiumLock = true;
				if($t!=null && !$premiumLock){
					if(in_array($t['Tsumego']['id'], $keyList)){
						if($counter<11){
							if(!in_array($t['Tsumego']['id'], $keyList)) $solved = 0;
							else{
								if($keyListStatus[array_search($t['Tsumego']['id'], $keyList)]=='S' || 
								   $keyListStatus[array_search($t['Tsumego']['id'], $keyList)]=='C' || 
								   $keyListStatus[array_search($t['Tsumego']['id'], $keyList)]=='W') 
								   $solved = 1;
								else $solved = 0;
							}					
							$u = $this->User->findById($comments[$i]['Comment']['user_id']);
							if($comments[$i]['Comment']['set_id']==null)
								$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
							else
								$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'], 'set_id' => $comments[$i]['Comment']['set_id'])));
							$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
							$s = $this->Set->findById($t['Tsumego']['set_id']);
							$counter++;
							$comments[$i]['Comment']['counter'] = $counter+$index;
							$comments[$i]['Comment']['user_name'] = $this->checkPicture($u);
							$comments[$i]['Comment']['set'] = $s['Set']['title'];
							$comments[$i]['Comment']['set2'] = $s['Set']['title2'];
							$comments[$i]['Comment']['num'] = $scT['SetConnection']['num'];
							
							if(!in_array($t['Tsumego']['id'], $keyList)) $comments[$i]['Comment']['user_tsumego'] = 'N';
							else $comments[$i]['Comment']['user_tsumego'] = $keyListStatus[array_search($t['Tsumego']['id'], $keyList)];
							$comments[$i]['Comment']['solved'] = $solved;
							if($comments[$i]['Comment']['admin_id']!=null){
								$au = $this->User->findById($comments[$i]['Comment']['admin_id']);
								if($au['User']['name']=='Morty') $au['User']['name'] = 'Admin';
								$comments[$i]['Comment']['admin_name'] = $au['User']['name'];
							}
							
							$date = new DateTime($comments[$i]['Comment']['created']);
							$month = date("F", strtotime($comments[$i]['Comment']['created']));
							$tday = $date->format('d. ');
							$tyear = $date->format('Y');
							$tClock = $date->format('H:i');
							if($tday[0]==0) $tday = substr($tday, -3);
							$comments[$i]['Comment']['created'] = $tday.$month.' '.$tyear.'<br>'.$tClock;
							array_push($c, $comments[$i]);
						}
					}
				}
			}
		}else{
			$commentsBuffer = array();
			for($i=0; $i<count($comments); $i++){
				if($counter<11){
					$t = $this->Tsumego->findById($comments[$i]['Comment']['tsumego_id']);
					$premiumLock = false;
					if(!$hasPremium)
						if(in_array($t['Tsumego']['set_id'], $setsWithPremium))
							$premiumLock = true;
					if(!$premiumLock){
						if(!in_array($t['Tsumego']['id'], $keyList)) $solved = 0;
						else{
							if($keyListStatus[array_search($t['Tsumego']['id'], $keyList)]=='S' || 
								$keyListStatus[array_search($t['Tsumego']['id'], $keyList)]=='C' || 
								$keyListStatus[array_search($t['Tsumego']['id'], $keyList)]=='W') 
								$solved = 1;
							else $solved = 0;
						}
						$u = $this->User->findById($comments[$i]['Comment']['user_id']);
						if($comments[$i]['Comment']['set_id']==null)
							$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
						else
							$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'], 'set_id' => $comments[$i]['Comment']['set_id'])));
						$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
						$s = $this->Set->findById($t['Tsumego']['set_id']);
						$counter++;
						$comments[$i]['Comment']['counter'] = $counter+$index;
						$comments[$i]['Comment']['user_name'] = $this->checkPicture($u);
						$comments[$i]['Comment']['set'] = $s['Set']['title'];
						$comments[$i]['Comment']['set2'] = $s['Set']['title2'];
						$comments[$i]['Comment']['num'] = $scT['SetConnection']['num'];
						if(!in_array($t['Tsumego']['id'], $keyList)) $comments[$i]['Comment']['user_tsumego'] = 'N';
						else $comments[$i]['Comment']['user_tsumego'] = $keyListStatus[array_search($t['Tsumego']['id'], $keyList)];
						$comments[$i]['Comment']['solved'] = $solved;
						if($comments[$i]['Comment']['admin_id']!=null){
							$au = $this->User->findById($comments[$i]['Comment']['admin_id']);
							if($au['User']['name']=='Morty') $au['User']['name'] = 'Admin';
							$comments[$i]['Comment']['admin_name'] = $au['User']['name'];
						}
						$date = new DateTime($comments[$i]['Comment']['created']);
						$month = date("F", strtotime($comments[$i]['Comment']['created']));
						$tday = $date->format('d. ');
						$tyear = $date->format('Y');
						$tClock = $date->format('H:i');
						if($tday[0]==0) $tday = substr($tday, -3);
						$comments[$i]['Comment']['created'] = $tday.$month.' '.$tyear.'<br>'.$tClock;
						array_push($c, $comments[$i]);
					}
				}
			}
		}
		
		if($reverseOrder){
			$c = array_reverse($c);
			$counter = 0;
			for($i=0; $i<count($c); $i++){
				$counter++;
				$c[$i]['Comment']['counter'] = $counter+$index;
			}
		}			
		///////////////////////////////////
		$yourc = array();
		$yourindex = 0;
		$yourcounter = 0;
		$yourreverseOrder = false;
		$yourlastEntry = true;
		$yourfirstPage = false;
		$yourComments = array();
		if(!isset($this->params['url']['your-comment-id'])){
			$yourComments = $this->Comment->find('all', array('limit' => 500, 'order' => 'created DESC', 'conditions' => array(
				'user_id' => $_SESSION['loggedInUser']['User']['id']
			)));
			$yourfirstPage = true;
		}else{
			$paramyourcommentid = $this->params['url']['your-comment-id'];
			$paramyourdirection = $this->params['url']['your-direction'];
			$paramyourindex = $this->params['url']['your-index'];
			if($this->params['url']['your-direction']=='next'){
				$yourComments = $this->Comment->find('all', array('limit' => 500, 'order' => 'created DESC','conditions' => array(
					'Comment.id <' => $this->params['url']['your-comment-id'],
					'user_id' => $_SESSION['loggedInUser']['User']['id']
				)));
			}else if(($this->params['url']['your-direction']=='prev')){
				$yourComments = $this->Comment->find('all', array('limit' => 500, 'order' => 'created ASC','conditions' => array(
					'Comment.id >' => $this->params['url']['your-comment-id'],
					'user_id' => $_SESSION['loggedInUser']['User']['id']
				)));
				$yourreverseOrder = true;
			}
			$yourindex = $this->params['url']['your-index'];
		}
		
		for($i=0; $i<count($yourComments); $i++){
			if($yourcounter<11){
				$t = $this->Tsumego->findById($yourComments[$i]['Comment']['tsumego_id']);
				if(!isset($t['Tsumego']['id'])) $t['Tsumego']['id'] = 0;
				if(in_array($t['Tsumego']['id'], $keyList)){
					$u = $this->User->findById($yourComments[$i]['Comment']['user_id']);
					if($yourComments[$i]['Comment']['set_id']==null)
						$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
					else
						$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'], 'set_id' => $yourComments[$i]['Comment']['set_id'])));
					$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
					$s = $this->Set->findById($t['Tsumego']['set_id']);
					$yourcounter++;
					$yourComments[$i]['Comment']['counter'] = $yourcounter+$yourindex;
					$yourComments[$i]['Comment']['user_name'] = $this->checkPicture($u);
					$yourComments[$i]['Comment']['set'] = $s['Set']['title'];
					$yourComments[$i]['Comment']['set2'] = $s['Set']['title2'];
					$yourComments[$i]['Comment']['num'] = $scT['SetConnection']['num'];
					$yourComments[$i]['Comment']['user_tsumego'] = $keyListStatus[array_search($t['Tsumego']['id'], $keyList)];
					if($yourComments[$i]['Comment']['admin_id']!=null){
						$au = $this->User->findById($yourComments[$i]['Comment']['admin_id']);
						if($au['User']['name']=='Morty') $au['User']['name'] = 'Admin';
						$yourComments[$i]['Comment']['admin_name'] = $au['User']['name'];
					}
						$date = new DateTime($yourComments[$i]['Comment']['created']);
						$month = date("F", strtotime($yourComments[$i]['Comment']['created']));
						$tday = $date->format('d. ');
						$tyear = $date->format('Y');
						$tClock = $date->format('H:i');
						if($tday[0]==0) $tday = substr($tday, -3);
						$yourComments[$i]['Comment']['created'] = $tday.$month.' '.$tyear.'<br>'.$tClock;
					
					array_push($yourc, $yourComments[$i]);
				}
			}
			if(strpos($yourComments[$i]['Comment']['message'], '<a href="/files/ul1/') === false)
				$yourComments[$i]['Comment']['message'] = htmlspecialchars($yourComments[$i]['Comment']['message']);
		}
		if($yourreverseOrder){
			$yourc = array_reverse($yourc);
			$yourcounter = 0;
			for($i=0; $i<count($yourc); $i++){
				$yourcounter++;
				$yourc[$i]['Comment']['counter'] = $yourcounter+$yourindex;
			}
		}
		
		$yourComments2 = array();
		for($i=0; $i<count($yourComments); $i++){
			if($counter<10){
				$u = $this->User->findById($yourComments[$i]['Comment']['user_id']);
				$t = $this->Tsumego->findById($yourComments[$i]['Comment']['tsumego_id']);
				if($yourComments[$i]['Comment']['set_id']==null)
					$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
				else
					$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'], 'set_id' => $yourComments[$i]['Comment']['set_id'])));
				$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
				$s = $this->Set->findById($t['Tsumego']['set_id']);
				$counter++;
				$yourComments[$i]['Comment']['counter'] = $counter;
				$yourComments[$i]['Comment']['user_name'] = $this->checkPicture($u);
				$yourComments[$i]['Comment']['set'] = $s['Set']['title'];
				$yourComments[$i]['Comment']['set2'] = $s['Set']['title2'];
				$yourComments[$i]['Comment']['num'] = $scT['SetConnection']['num'];
				if($s!=null){
					array_push($yourComments2, $yourComments[$i]);
				}
			}
		}
		
		if(isset($this->params['url']['filter'])) $this->set('filter1', $filter1);
		if($filter1=='falsex') $this->set('filter1', 'false');
		if($unresolvedSet == 'true'){
			$this->set('unresolved', $unresolved);
			$this->set('comments3', count($this->Comment->find('all', array('order' => 'created DESC', 'conditions' =>  array('status' => 0,
			array(
				'NOT' => array('user_id' => 0)
			))))));
		}
		
		$currentPositionPlaceholder = '<img src="/img/positionIcon1.png" class="positionIcon1" style="cursor:context-menu;">';
		
		$tooltipSgfs = array();
		$tooltipInfo = array();
		$tooltipBoardSize = array();
		$tooltipSgfs2 = array();
		$tooltipInfo2 = array();
		$tooltipBoardSize2 = array();
		for($i=0; $i<count($c); $i++){
			if(strpos($c[$i]['Comment']['message'], '<a href="/files/ul1/') === false)
				$c[$i]['Comment']['message'] = htmlspecialchars($c[$i]['Comment']['message']);
			$c[$i]['Comment']['message'] = str_replace('[current position]', $currentPositionPlaceholder, $c[$i]['Comment']['message']);
			
			$tBuffer = $this->Tsumego->findById($c[$i]['Comment']['tsumego_id']);
			$tts = $this->Sgf->find('all', array('limit' => 1, 'order' => 'version DESC', 'conditions' => array('tsumego_id' => $tBuffer['Tsumego']['id'])));
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			array_push($tooltipSgfs, $tArr[0]);
			array_push($tooltipInfo, $tArr[2]);
			array_push($tooltipBoardSize, $tArr[3]);
		}
		for($i=0; $i<count($yourc); $i++){
			$yourc[$i]['Comment']['message'] = str_replace('[current position]', $currentPositionPlaceholder, $yourc[$i]['Comment']['message']);
			
			$tBuffer = $this->Tsumego->findById($yourc[$i]['Comment']['tsumego_id']);
			$tts2 = $this->Sgf->find('all', array('limit' => 1, 'order' => 'version DESC', 'conditions' => array('tsumego_id' => $tBuffer['Tsumego']['id'])));
			$tArr2 = $this->processSGF($tts2[0]['Sgf']['sgf']);
			$tooltipSgfs2[$i] = $tArr2[0];
			$tooltipInfo2[$i] = $tArr2[2];
			array_push($tooltipBoardSize2, $tArr2[3]);
		}
		$admins = $this->User->find('all', array('conditions' => array('isAdmin' => 1))); 

		if($_SESSION['loggedInUser']['User']['isAdmin'] > 0){
			$uc = $this->Comment->find('all', array('conditions' => array(
				'user_id' => 0,
				'tsumego_id' => 0
			)));
			for($i=0; $i<count($uc); $i++)
				$this->Comment->delete($uc[$i]['Comment']['id']);
		}

		$this->set('admins', $admins);
		$this->set('paramindex', $paramindex);
		$this->set('paramdirection', $paramdirection);
		$this->set('paramcommentid', $paramcommentid);
		$this->set('paramyourindex', $paramyourindex);
		$this->set('paramyourdirection', $paramyourdirection);
		$this->set('paramyourcommentid', $paramyourcommentid);
		$this->set('index', $index);
		$this->set('yourindex', $yourindex);
		$this->set('comments', $c);
		$this->set('yourComments', $yourc);
		$this->set('userTsumegos', $keyList);
		$this->set('firstPage', $firstPage);
		$this->set('yourfirstPage', $yourfirstPage);
		$this->set('tooltipSgfs', $tooltipSgfs);
		$this->set('tooltipInfo', $tooltipInfo);
		$this->set('tooltipSgfs2', $tooltipSgfs2);
		$this->set('tooltipInfo2', $tooltipInfo2);
		$this->set('tooltipBoardSize', $tooltipBoardSize);
		$this->set('tooltipBoardSize2', $tooltipBoardSize2);
  }

	public function remove($id){
		$token = true;
		if($_SESSION['loggedInUser']['User']['isAdmin']<1)
			$token = false;
		else if($this->params['url']['token']!=md5($id))
			$token = false;
		if($token){
			$this->Comment->delete($id);
		}
		$this->set('token', $token);
	}
}

?>


