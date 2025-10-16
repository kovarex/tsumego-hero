<?php
class TsumegoRatingAttemptsController extends AppController {

	public function index($trid=null){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'TSUMEGO RECORDS';
		if($trid == null){
			$trs = $this->TsumegoRatingAttempt->find('all', array('limit' => 500, 'order' => 'created DESC'));
		}else{
			$trs = $this->TsumegoRatingAttempt->find('all', array('limit' => 500, 'order' => 'created DESC', 'conditions' => array('user_id' => $trid)));
		}
		$this->set('trs', $trs);
		$this->set('trs2', $trs2);
		$this->set('x', '');
		if($trid!=null) $this->set('x', '<a href="/tsumego_records/"><< back to overview</a>');
    }
	
	public function json($type=null){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'TSUMEGO RECORDS';
		$this->LoadModel('TsumegoAttempt');
		$this->LoadModel('User');
		$this->LoadModel('Tsumego');
		$this->LoadModel('Set');
		$this->LoadModel('SetConnection');
		
		if($type==0){
			$trs = $this->TsumegoRatingAttempt->find('all', array('limit' => 12000, 'order' => 'created DESC', 'conditions' =>  array(
				'tsumego_id >' => 17000
			)));
		
			$header = array('user_id', 'user_elo', 'user_ip', 'user_country', 'user_country_code', 'tsumego_id', 'tsumego_elo', 'tsumego_set', 'status', 'seconds', 'created');
			
			$posts = array();
			for($i=0; $i<count($trs); $i++){
				$user = $this->User->findById($trs[$i]['TsumegoRatingAttempt']['user_id']);
				$tsumego = $this->Tsumego->findById($trs[$i]['TsumegoRatingAttempt']['tsumego_id']);
				$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $tsumego['Tsumego']['id'])));
				$tsumego['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
				$set = $this->Set->findById($tsumego['Tsumego']['set_id']);
				$values = array();
				$hash = substr($user['User']['ip'], 0, 1);
				for($j=0; $j<count($header); $j++){
					$a=array();
					if($header[$j]=='user_ip'){
						$a['name'] = $header[$j];
						$a['value'] = $user['User']['ip'];
					}elseif($header[$j]=='user_country'){
						$a['name'] = $header[$j];
						$a['value'] = $user['User']['location'];
					}elseif($header[$j]=='user_country_code'){
						$a['name'] = $header[$j];
						$a['value'] = $user['User']['location'];
					}elseif($header[$j]=='tsumego_set'){
						$a['name'] = $header[$j];
						$a['value'] = $set['Set']['title'];
					}else{
						$a['name'] = $header[$j];
						$a['value'] = $trs[$i]['TsumegoRatingAttempt'][$header[$j]];
					}
					array_push($values, $a);
				}
				/*
				$values['user_id'] = $trs[$i]['TsumegoRatingAttempt']['user_id'];
				$values['user_elo'] = $trs[$i]['TsumegoRatingAttempt']['user_elo'];
				$values['user_ip'] = $user['User']['ip'];
				$values['user_country'] = 'Germany';
				$values['user_country_code'] = 'DEU';
				$values['tsumego_id'] = $trs[$i]['TsumegoRatingAttempt']['tsumego_id'];
				$values['tsumego_elo'] = $trs[$i]['TsumegoRatingAttempt']['tsumego_elo'];
				$values['tsumego_set'] = $set['Set']['title'];
				$values['status'] = $trs[$i]['TsumegoRatingAttempt']['status'];
				$values['seconds'] = $trs[$i]['TsumegoRatingAttempt']['seconds'];
				$values['created'] = $trs[$i]['TsumegoRatingAttempt']['created'];
				*/
				$posts[$i] = array('id'=> $trs[$i]['TsumegoRatingAttempt']['id']);
				$posts[$i]['values'] = $values;
			}
			
			$response = $posts;
			$fp = fopen('files/tsumego-hero-user-activities.json', 'w');
			fwrite($fp, json_encode($response));
			fclose($fp);

		}else{
			$trs = $this->TsumegoRatingAttempt->find('all', array('order' => 'created DESC'));
		}
		
		
		$this->set('trs', $trs);
    }
	
	public function csv($type=null){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'TSUMEGO RECORDS';
		$this->LoadModel('TsumegoAttempt');
		$this->LoadModel('User');
		
		if($type==0){
			$trs = $this->TsumegoRatingAttempt->find('all', array('limit' => 1000, 'order' => 'created DESC'));
		
			$csv = array();
			$header = array('id', 'user_id', 'user_elo', 'user_deviation', 'tsumego_id', 'tsumego_elo', 'tsumego_deviation', 'status', 'seconds', 'sequence', 'recent', 'created');
			array_push($csv, $header);
			for($i=0; $i<count($trs); $i++){
				array_push($csv, $trs[$i]['TsumegoRatingAttempt']);
			}
			
			$file = fopen("files/tsumego-hero-user-activities.csv","w");

			foreach ($csv as $line){
			  fputcsv($file, $line);
			}

			fclose($file);
		}elseif($type==1){
			$trs = $this->TsumegoRatingAttempt->find('all', array('order' => 'created DESC'));
		
			$csv = array();
			$header = array('id', 'user_id', 'user_elo', 'user_deviation', 'tsumego_id', 'tsumego_elo', 'tsumego_deviation', 'status', 'seconds', 'sequence', 'recent', 'created');
			array_push($csv, $header);
			for($i=0; $i<count($trs); $i++){
				array_push($csv, $trs[$i]['TsumegoRatingAttempt']);
			}
			
			$file = fopen("files/tsumego-hero-user-activities.csv","w");

			foreach ($csv as $line){
			  fputcsv($file, $line);
			}

			fclose($file);
		}elseif($type==2){
			$trs = $this->TsumegoAttempt->find('all', array('limit' => 1000, 'order' => 'created DESC'));
		
			$csv = array();
			$header = array('id', 'user_id', 'tsumego_id', 'level', 'xp', 'gain', 'status', 'seconds', 'created');
			array_push($csv, $header);
			for($i=0; $i<count($trs); $i++){
				array_push($csv, $trs[$i]['TsumegoAttempt']);
			}
			
			$file = fopen("files/tsumego-hero-user-activities.csv","w");

			foreach ($csv as $line){
			  fputcsv($file, $line);
			}

			fclose($file);
		}elseif($type==3){
			$trs = $this->TsumegoAttempt->find('all', array('limit' => 200000, 'order' => 'created DESC'));
		
			$csv = array();
			$header = array('id', 'user_id', 'tsumego_id', 'level', 'xp', 'gain', 'status', 'seconds', 'created');
			array_push($csv, $header);
			for($i=0; $i<count($trs); $i++){
				array_push($csv, $trs[$i]['TsumegoAttempt']);
			}
			
			$file = fopen("files/tsumego-hero-user-activities.csv","w");

			foreach ($csv as $line){
			  fputcsv($file, $line);
			}

			fclose($file);
		}elseif($type==4){
			$u = $this->User->find('all');
		
			$csv = array();
			$header = array('id', 'ip');
			array_push($csv, $header);
			for($i=0; $i<count($u); $i++){
				$a = array();
				array_push($a, $u[$i]['User']['id']);
				array_push($a, $u[$i]['User']['ip']);
				if($u[$i]['User']['ip']!=null) array_push($csv, $a);
			}
			
			$file = fopen("files/tsumego-hero-user-activities.csv","w");

			foreach ($csv as $line){
			  fputcsv($file, $line);
			}

			fclose($file);
		}else{
			$trs = $this->TsumegoRatingAttempt->find('all', array('order' => 'created DESC'));
		}
		
		
		$this->set('trs', $trs);
    }
	
	public function user($trid){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'History of '.$_SESSION['loggedInUser']['User']['name'];
		$this->LoadModel('Set');
		$this->LoadModel('Tsumego');
		$this->LoadModel('SetConnection');
		$this->LoadModel('TsumegoAttempt');
		if($_SESSION['loggedInUser']['User']['id']!=$trid && $_SESSION['loggedInUser']['User']['id']!=72) $_SESSION['redirect'] = 'sets';
		
		$trs = $this->TsumegoAttempt->find('all', array('limit' => 200, 'order' => 'created DESC', 'conditions' => array(
			'user_id' => $_SESSION['loggedInUser']['User']['id'],
			'mode' => 2
		)));
		/*
		$trsx = $this->TsumegoAttempt->find('all', array('limit' => 10, 'order' => 'created DESC', 'conditions' => array(
			'user_id' => $_SESSION['loggedInUser']['User']['id']
		)));
		echo '<pre>'; print_r($trsx); echo '</pre>';
		*/
		for($i=0; $i<count($trs); $i++){
			if($trs[$i]['TsumegoAttempt']['solved']==1)
				$trs[$i]['TsumegoAttempt']['status'] = '<b style="color:#0cbb0c;">Solved</b>';
			else
				$trs[$i]['TsumegoAttempt']['status'] = '<b style="color:#e03c4b;">Failed</b>';
			$t = $this->Tsumego->findById($trs[$i]['TsumegoAttempt']['tsumego_id']);
			$trs[$i]['TsumegoAttempt']['tsumego_elo'] = $t['Tsumego']['elo_rating_mode'];
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$trs[$i]['TsumegoAttempt']['title'] = '<a target="_blank" href="/tsumegos/play/'.$trs[$i]['TsumegoAttempt']['tsumego_id'].'?mode=1">'.$s['Set']['title'].' '
			.$s['Set']['title2'].' - '.$t['Tsumego']['num'].'</a>';
			
			$date = new DateTime($trs[$i]['TsumegoAttempt']['created']);
			$month = date("F", strtotime($trs[$i]['TsumegoAttempt']['created']));
			$tday = $date->format('d. ');
			$tyear = $date->format('Y');
			$tClock = $date->format('H:i');
			if($tday[0]==0) $tday = substr($tday, -3);
			$trs[$i]['TsumegoAttempt']['created'] = $tClock.' | '.$tday.$month.' '.$tyear;
			$seconds = $trs[$i]['TsumegoAttempt']['seconds']%60;
			$minutes = floor($trs[$i]['TsumegoAttempt']['seconds']/60);
			$hours = floor($trs[$i]['TsumegoAttempt']['seconds']/3600);
			$hours2 = $hours;
			while($hours2>0){
				$minutes-=60;
				$hours2--;
			}
			
			if($minutes==0 && $hours==0) $minutes = '';
			else $minutes .= 'm ';
			if($hours==0) $hours = '';
			else $hours .= 'h ';
			$trs[$i]['TsumegoAttempt']['seconds'] = $hours.$minutes.$seconds.'s';
			
		}
		
		$u = $this->User->findById($trid);
		$this->set('uname', $u['User']['name']);
		$this->set('trs', $trs);
    }
	
}




