<?php
App::uses('CakeEmail', 'Network/Email');
class UsersController extends AppController{
	public $name = 'Users';
	public $pageTitle = "Users";
	public $helpers = array('Html', 'Form');
	
	public function playerdb5(){
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Answer');
		$this->loadModel('Purge');
		$this->loadModel('Set');
		$this->loadModel('TsumegoRatingAttempt');
		$this->loadModel('Rank');
		$this->loadModel('RankOverview');
		$this->loadModel('Comment');
		$this->loadModel('Schedule');
		$this->loadModel('Sgf');
		$this->loadModel('SetConnection');
		$this->loadModel('Duplicate');
		$this->loadModel('PublishDate');
		$this->loadModel('TagName');
		/*
		$setFrom = 259;
		$setTo = 263;
		$step = 10;
		$numFrom = 111;
		$startDay = 1;
		$endDay = 20;
		$month = '09';
		
		$numTo = $numFrom + $step - 1;
		while($startDay <= $endDay){
			$sc = $this->SetConnection->find('all', array('order' => 'num ASC', 'conditions' => array(
				'set_id' => $setFrom,
				'num >=' => $numFrom,
				'num <=' => $numTo
			)));
			for($i=0; $i<count($sc); $i++){
				if($startDay>9) $digit = '';
				else $digit = '0';
				$s = array();
				$s['Schedule']['published'] = '0';
				$s['Schedule']['date'] = '2025-'.$month.'-'.$digit.$startDay;
				$s['Schedule']['set_id'] = $setTo;
				$s['Schedule']['tsumego_id'] = $sc[$i]['SetConnection']['tsumego_id'];
				$this->Schedule->create();
				$this->Schedule->save($s);
			}
			echo '<pre>'; print_r($startDay); echo '</pre>';
			$numFrom += $step;
			$numTo += $step;
			$startDay += 1;
		}
		
		////////////////

		$t = $this->Tsumego->find('all', array('conditions' => array(
			'set_id' => 210,
			'num <=' => 20
		)));

		for($i=0; $i<count($t); $i++){
			$tag = array();
			$tag['Tag']['tsumego_id'] = $t[$i]['Tsumego']['id'];
			$tag['Tag']['user_id'] = 72;
			$tag['Tag']['tag_name_id'] = 10;
			$tag['Tag']['approved'] = 1;
			$this->Tag->create();
			$this->Tag->save($tag);
		}

		echo '<pre>'; print_r(count($t)); echo '</pre>';
		//
		$st = $this->Tsumego->find('all', array('conditions' => array(
			'id >=' => 1,
			'id <=' => 10000
		)));
		$counter=0;

		for($i=0;$i<count($st);$i++){
			if(strpos(' '.$st[$i]['Tsumego']['description'], 'b ')){
				$st[$i]['Tsumego']['description'] = str_replace('b ', '[b] ', $st[$i]['Tsumego']['description']);
				$this->Tsumego->save($st[$i]);
				$counter++;
			}
		}
		echo '<pre>'; print_r($counter); echo '</pre>';
		echo '<pre>'; print_r(' | '); echo '</pre>';
		echo '<pre>'; print_r(count($st)); echo '</pre>';
		//
		$tn = $this->TagName->find('all');
		for($i=0;$i<count($tn);$i++){
			$tn[$i]['TagName']['color'] = rand(0,24);
			$this->TagName->save($tn[$i]);
		}

		$test = $this->findTsumegoSet(50);
		echo '<pre>'; print_r(count($test)); echo '</pre>';

		$ts = $this->Tsumego->find('all', array('order' => 'id ASC', 'conditions' => array(
			'set_id' => 50
		)));
		echo '<pre>'; print_r(count($ts)); echo '</pre>';

		$u = $this->Tsumego->find('all', array('conditions' => array(
			'NOT' => array('set_id' => null)
		)));
		echo '<pre>'; print_r(count($u)); echo '</pre>';
		$u = $this->Tsumego->find('all', array('conditions' => array(
			'public' => 1
		)));
		echo '<pre>'; print_r(count($u)); echo '</pre>';
		$ux = $this->Tsumego->find('all', array('conditions' => array(
			'public' => 1,
			'set_id' => null
		)));
		echo '<pre>'; print_r(count($ux)); echo '</pre>';
	
		$sc = $this->SetConnection->find('all', array('order' => 'num ASC', 'conditions' => array(
			'set_id' => 194,
			'num >=' => 1,
			'num <=' => 10
		)));
		
		for($i=0; $i<count($sc); $i++){
			$s = array();
			$s['Schedule']['published'] = '0';
			$s['Schedule']['date'] = '2024-03-25';
			$s['Schedule']['set_id'] = '228';
			$s['Schedule']['tsumego_id'] = $sc[$i]['SetConnection']['tsumego_id'];
			$this->Schedule->create();
			$this->Schedule->save($s);
		}
		
		$sc = $this->SetConnection->find('all', array('order' => 'num ASC', 'conditions' => array(
			'set_id' => 213,
			'num >=' => 201,
			'num <=' => 210
		)));
		
		for($i=0; $i<count($sc); $i++){
			$s = array();
			$s['Schedule']['published'] = '0';
			$s['Schedule']['date'] = '2024-03-21';
			$s['Schedule']['set_id'] = '227';
			$s['Schedule']['tsumego_id'] = $sc[$i]['SetConnection']['tsumego_id'];
			$this->Schedule->create();
			$this->Schedule->save($s);
		}
		
		for($i=0; $i<count($ts); $i++){
			//$this->setTsumegoElo($ts[$i]['Tsumego']['id']);
			$this->setTsumegoElo($ts[$i]);
		}
		
		$u = $this->User->find('all', array('conditions' => array(
			'id >=' => 10000,
			'id <=' => 20000
		)));
		
		for($i=0; $i<count($u); $i++){
			if($u[$i]['User']['elo_rating_mode']!=100){
				$u[$i]['User']['elo_rating_mode'] = 100;
				$u[$i]['User']['rd'] = 200;
				$u[$i]['User']['solved2'] = 0;
				$this->User->save($u[$i]);
			}
		}
		
		$ts = $this->Tsumego->find('all', array('conditions' => array(
			'id >=' => 29634,
			'id <=' => 29643
		)));
		
		for($i=0; $i<count($ts); $i++){
			$sc['SetConnection']['set_id'] = 216;
			$sc['SetConnection']['tsumego_id'] = $ts[$i]['Tsumego']['id'];
			$sc['SetConnection']['num'] = $ts[$i]['Tsumego']['num'];
			$this->SetConnection->create();
			$this->SetConnection->save($sc);
		}
		
		$comments = $this->Comment->find('all');
		$c = array();
		for($i=0; $i<count($comments); $i++){
			array_push($c, $comments[$i]['Comment']['user_id']);
		}
		$c = array_count_values($c);
		$this->set('c', $c);
		
		echo '<pre>'; print_r(count($comments)); echo '</pre>';
		echo '<pre>'; print_r(count($c)); echo '</pre>';
		*/
		
		
		//$this->SetConnection->save($sc);
		//$s = $this->Tsumego->find('all', array('conditions' => array('id >' => 14000)));
		//echo '<pre>'; print_r(count($s)); echo '</pre>';
		/*
		for($j=0; $j<count($s); $j++){
			//$this->SetConnection->create();
			$sc = array();
			$sc['SetConnection']['tsumego_id'] = $s[$j]['Tsumego']['id'];
			$sc['SetConnection']['set_id'] = $s[$j]['Tsumego']['set_id'];
			$sc['SetConnection']['num'] = $s[$j]['Tsumego']['num'];
			//$this->SetConnection->save($sc);
		}
		
		$ts1 = $this->TsumegoStatus->find('all', array('conditions' => array('user_id' => 5080)));
		$correctCounter = 0;
		$ts2 = array();
		for($j=0; $j<count($ts1); $j++){
			if($ts1[$j]['TsumegoStatus']['status']=='S' || $ts1[$j]['TsumegoStatus']['status']=='W' || $ts1[$j]['TsumegoStatus']['status']=='C'){
				$correctCounter++;
				
			}
			array_push($ts2, $ts1[$j]['TsumegoStatus']['tsumego_id']);			
		}
		echo '<pre>'; print_r($correctCounter); echo '</pre>';
		echo '<pre>'; print_r(array_count_values($ts2)); echo '</pre>';
		$t1['Tsumego']['duplicate'] = 2;
		$t2['Tsumego']['duplicate'] = $t1['Tsumego']['id'];
		$t3['Tsumego']['duplicate'] = $t1['Tsumego']['id'];
		$this->Tsumego->save($t1);
		$this->Tsumego->save($t2);
		$this->Tsumego->save($t3);
		*/
		/*
		2270 stephalamy@gmail.com
		441  marioaliandoe@gmail.com
		7732 semelis@gmail.com
		
		$c = $this->Comment->find('all', array('order' => 'created DESC', 'conditions' => array(
			'created >' => '2023-10-00 07:58:47',
			'NOT' => array(
				'user_id' => 33,
			)
		)));
		$cc = array();
		for($i=0; $i<count($c); $i++){
			$u = $this->User->findById($c[$i]['Comment']['user_id']);
			array_push($cc, $u['User']['name']);
		}
		echo '<pre>'; print_r(array_count_values($cc)); echo '</pre>'; 
		
		$ts = $this->Tsumego->find('all', array('conditions' => array('set_id' => 42)));
		$sgfs = array();
		for($i=0; $i<count($ts); $i++){
			//$this->Tsumego->delete($ts[$i]['Tsumego']['id']);
			array_push($sgfs, $this->Sgf->find('first', array('conditions' => array('tsumego_id' => $ts[$i]['Tsumego']['id']))));
		}
		
		for($i=0; $i<count($sgfs); $i++){
			$this->Sgf->delete($sgfs[$i]['Sgf']['id']);
		}
		echo '<pre>'; print_r(count($ts)); echo '</pre>'; 
		echo '<pre>'; print_r(count($sgfs)); echo '</pre>'; 
		
		$ts = $this->Tsumego->find('all', array('order' => 'num ASC', 'conditions' => array(
			'set_id' => 185,
			'num >=' => 531,
			'num <=' => 540
		)));
		
		for($i=0; $i<count($ts); $i++){
			$s = array();
			$s['Schedule']['published'] = '0';
			$s['Schedule']['date'] = '2023-11-05';
			$s['Schedule']['set_id'] = '198';
			$s['Schedule']['tsumego_id'] = $ts[$i]['Tsumego']['id'];
			$this->Schedule->create();
			$this->Schedule->save($s);
		}*/

		$this->set('t', $t);
		$this->set('u', $u);
		$this->set('ou', $ou);
		$this->set('ouc', $ouc);
		$this->set('tr', $tr);
		$this->set('ut', $ut);
		$this->set('out', $out);
		$this->set('ux', $ux);
	}

	public function deleteoldattempts(){
		$this->loadModel('TsumegoAttempt');
		$ta = $this->TsumegoAttempt->find('all', array('limit' => 5000, 'order' => 'created ASC'));
		echo '<pre>'; print_r($ta[0]['TsumegoAttempt']['created']); echo '</pre>';
		
		for($i=0; $i<count($ta); $i++)
			$this->TsumegoAttempt->delete($ta[$i]['TsumegoAttempt']['id']);
		
		$this->set('x', '2023-08-01 00:00:00');
		$this->set('date', $ta[0]['TsumegoAttempt']['created']);
	}
	
	public function rank_list(){
		$this->loadModel('Tsumego');
		
		$ts = $this->Tsumego->find('all', array('conditions' => array(
			'id >=' => 20000,
			'id <=' => 30000
		)));
		for($i=0; $i<count($ts); $i++){
			$this->set_elo($ts[$i]['Tsumego']['id']);
		}
	}
	
	private function set_elo($tid){
		$this->loadModel('Tsumego');
		$t = $this->Tsumego->findById($tid);
		$rank = $this->getTsumegoRankx($t['Tsumego']['userWin']);
		$tMax = $this->getTsumegoRankMax($t['Tsumego']['userWin']);
		$tVal = $this->getTsumegoRankVal($t['Tsumego']['userWin'], $tMax);
		$p = $tVal/$tMax;
		$newElo = $this->getTsumegoElo($rank, $p);
		$adjustElo = $this->adjustElo($newElo);
		$t['Tsumego']['elo_rating_mode'] = $adjustElo;
		$t['Tsumego']['difficulty'] = $this->convertEloToXp($t['Tsumego']['elo_rating_mode']);
		$this->Tsumego->save($t);
	}
	
	public function rank_single(){
		$this->loadModel('Tsumego');
		$a = array();
		$a['c'] = array();
		$a['rank'] = array();
		$a['rank2'] = array();
		$a['rank3'] = array();
		$a['elo'] = array();
		$a['elo2'] = array();
		$counter = 0;
		while($counter<=100){
			array_push($a['c'], $counter);
			$rank = $this->getTsumegoRankx($counter);
			array_push($a['rank'], $rank);
			
			$tMax = $this->getTsumegoRankMax($counter);
			array_push($a['rank2'], $tMax);
			
			$tVal = $this->getTsumegoRankVal($counter, $tMax);
			$p = $tVal/$tMax;
			array_push($a['rank3'], $p);
			
			$newElo = $this->getTsumegoElo($rank, $p);
			array_push($a['elo'], $newElo);
			
			$adjustElo = $this->adjustElo($newElo);
			array_push($a['elo2'], $adjustElo);
			
			$counter+=.5;
		}
		echo '<table>';
		for($i=0; $i<count($a['c']); $i++){
			echo '<tr>';
			echo '<td>'.$a['c'][$i].'</td><td>'.$a['rank'][$i].'</td><td>'.$a['rank3'][$i].'</td><td>'.$a['elo'][$i].'</td><td>'.$a['elo2'][$i].'</td>';
			echo '</tr>';
		}
		echo '</table>';
	}

	private function transferCollection($from, $to){
		$sc = $this->SetConnection->find('all', array('order' => 'num ASC', 'conditions' => array('set_id' => $from)));
		$tsIds = array();
		for($i=0; $i<count($sc); $i++){
			array_push($tsIds, $sc[$i]['SetConnection']['tsumego_id']);
			$sc[$i]['SetConnection']['set_id'] = $to;
			$this->SetConnection->save($sc[$i]);
		}
		$ts = $this->Tsumego->find('all', array('conditions' => array('id' => $tsIds)));
		for($i=0; $i<count($ts); $i++){
			$ts[$i]['Tsumego']['set_id'] = $to;
			$this->Tsumego->save($ts[$i]);
		}
		$this->Set->delete($from);
	}
	
	public function adjusttsumego(){
		$this->loadModel('Tsumego');
		$ts = $this->Tsumego->find('all', array('order' => 'elo_rating_mode ASC'));
		echo '<pre>'; print_r(count($ts)); echo '</pre>';
		echo '<table>';
		for($i=0; $i<count($ts); $i++)
			echo '<tr><td>'.$ts[$i]['Tsumego']['id'].'</td><td>'.$ts[$i]['Tsumego']['difficulty']
			.'</td><td>'.$ts[$i]['Tsumego']['userWin'].'</td><td>'.$ts[$i]['Tsumego']['elo_rating_mode'].'</td></tr>';
		echo '</table>';
	}
	
	public function tsumego_rating($id=null){
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		
		$t = $this->Tsumego->findById($id);
		$sc = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $id)));
		$s = $this->Set->findById($sc['SetConnection']['set_id']);
		$name = $s['Set']['title'].' - '.$t['Tsumego']['num'];
		$ta = $this->TsumegoAttempt->find('all', array('order' => 'created ASC', 'conditions' => array(
			'tsumego_id' => $id,
			'NOT' => array(
				'tsumego_elo' => 0
			)
		)));
		$this->set('rating', $t['Tsumego']['elo_rating_mode']);
		$this->set('name', $name);
		$this->set('ta', $ta);
		$this->set('id', $id);
	}
	
	//scan for glitches
	public function test1a($x=null){
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		$ts = $this->Tsumego->find('all', array('order' => 'id ASC'));
		$id = $ts[$x]['Tsumego']['id'];
		$ta = $this->TsumegoAttempt->find('all', array('order' => 'created ASC', 'conditions' => array(
			'tsumego_id' => $id,
			'NOT' => array(
				'tsumego_elo' => 0
			)
		)));
		$change = $ta[count($ta)-1]['TsumegoAttempt']['tsumego_elo'] - $ta[0]['TsumegoAttempt']['tsumego_elo'];
		$t = $this->Tsumego->findById($id);
		$t['Tsumego']['rd'] = $change;
		$this->Tsumego->save($t);
		$p = $x.'/'.count($ts);
		echo '<pre>'; print_r($p); echo '</pre>';
		echo '<pre>'; print_r($id); echo '</pre>';
		echo '<pre>'; print_r($change); echo '</pre>';
		$this->set('next', $x+1);
		$this->set('finish', count($ts)-1);
	}
	
	//fix glitched problems
	public function test1b($id=null){
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		
		$t = $this->Tsumego->find('all', array('conditions' => array(
			'rd <' => -500
		)));
		for($i=0; $i<count($t); $i++){
			$ta = $this->TsumegoAttempt->find('all', array('limit' => 2, 'order' => 'created ASC', 'conditions' => array(
				'tsumego_id' => $t[$i]['Tsumego']['id'],
				'NOT' => array(
					'tsumego_elo' => 0
				)
			)));
			$t[$i]['Tsumego']['rd'] = 0;
			$t[$i]['Tsumego']['elo_rating_mode'] = $ta[0]['TsumegoAttempt']['tsumego_elo'];
			$this->Tsumego->save($t[$i]);
			echo '<pre>'; print_r('saved '.$t[$i]['Tsumego']['id']); echo '</pre>';
		}
		echo '<pre>'; print_r(count($t)); echo '</pre>';
		echo '<pre>'; print_r($ta[0]['TsumegoAttempt']['tsumego_elo']); echo '</pre>';
	}
	
	//no author
	public function test1c($id=null){
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		
		$t = $this->Tsumego->find('all', array('conditions' => array(
			'author' => ''
		)));
		/*
		for($i=0; $i<count($t); $i++){
			$ta = $this->TsumegoAttempt->find('all', array('limit' => 2, 'order' => 'created ASC', 'conditions' => array(
				'tsumego_id' => $t[$i]['Tsumego']['id'],
				'NOT' => array(
					'tsumego_elo' => 0
				)
			)));
			$t[$i]['Tsumego']['rd'] = 0;
			$t[$i]['Tsumego']['elo_rating_mode'] = $ta[0]['TsumegoAttempt']['tsumego_elo'];
			$this->Tsumego->save($t[$i]);
			echo '<pre>'; print_r('saved '.$t[$i]['Tsumego']['id']); echo '</pre>';
		}
		*/
		echo '<pre>'; print_r(count($t)); echo '</pre>';
		echo '<pre>'; print_r($t); echo '</pre>';
	}
	
	//list ratings
	public function test1d(){
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('SetConnection');
		$this->loadModel('Set');
		
		$ts = $this->Tsumego->find('all', array('order' => 'elo_rating_mode ASC'));
		
		$x1min = 2200;
		$x1max = 2673;
		$x2min = 2200;
		$x2max = 2900;
		
		for($i=0;$i<count($ts);$i++){
			$sc = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $ts[$i]['Tsumego']['id'])));
			$s = $this->Set->findById($sc['SetConnection']['set_id']);
			$ts[$i]['Tsumego']['public'] = $s['Set']['public'];
			$ts[$i]['Tsumego']['rank'] = $this->getTsumegoRank($ts[$i]['Tsumego']['elo_rating_mode']);
			$ts[$i]['Tsumego']['shift'] = round($x2min + (($x2max - $x2min) / ($x1max - $x1min)) * ($ts[$i]['Tsumego']['elo_rating_mode'] - $x1min));
			$ts[$i]['Tsumego']['rank2'] = $this->getTsumegoRank(round($x2min + (($x2max - $x2min) / ($x1max - $x1min)) * ($ts[$i]['Tsumego']['elo_rating_mode'] - $x1min)));
		}
		$this->set('ts', $ts);
	}
	
	public function map19k(){
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('SetConnection');
		$this->loadModel('Set');
		
		$ts = $this->Tsumego->find('all', array('order' => 'elo_rating_mode ASC', 'conditions' => array(
			'elo_rating_mode >' => 500,
			'elo_rating_mode <' => 1200
		)));
		
		$x1min = 500;
		$x1max = 1200;
		$x2min = 100;
		$x2max = 1200;
		
		for($i=0;$i<count($ts);$i++){
			$sc = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $ts[$i]['Tsumego']['id'])));
			$s = $this->Set->findById($sc['SetConnection']['set_id']);
			$ts[$i]['Tsumego']['public'] = $s['Set']['public'];
			$ts[$i]['Tsumego']['rank'] = $this->getTsumegoRank($ts[$i]['Tsumego']['elo_rating_mode']);
			$ts[$i]['Tsumego']['shift'] = round($x2min + (($x2max - $x2min) / ($x1max - $x1min)) * ($ts[$i]['Tsumego']['elo_rating_mode'] - $x1min));
			$ts[$i]['Tsumego']['rank2'] = $this->getTsumegoRank(round($x2min + (($x2max - $x2min) / ($x1max - $x1min)) * ($ts[$i]['Tsumego']['elo_rating_mode'] - $x1min)));
			if($ts[$i]['Tsumego']['public']==1){
				$ts[$i]['Tsumego']['elo_rating_mode'] = $ts[$i]['Tsumego']['shift'];
				//$this->Tsumego->save($ts[$i]);
			}
		}
		$this->set('ts', $ts);
	}
	
	public function map8d(){
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('SetConnection');
		$this->loadModel('Set');
		
		$ts = $this->Tsumego->find('all', array('order' => 'elo_rating_mode ASC', 'conditions' => array(
			'elo_rating_mode >=' => 2200
		)));
		
		$x1min = 2200;
		$x1max = 2673;
		$x2min = 2200;
		$x2max = 2900;
		
		for($i=0;$i<count($ts);$i++){
			$sc = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $ts[$i]['Tsumego']['id'])));
			$s = $this->Set->findById($sc['SetConnection']['set_id']);
			$ts[$i]['Tsumego']['public'] = $s['Set']['public'];
			$ts[$i]['Tsumego']['rank'] = $this->getTsumegoRank($ts[$i]['Tsumego']['elo_rating_mode']);
			$ts[$i]['Tsumego']['shift'] = round($x2min + (($x2max - $x2min) / ($x1max - $x1min)) * ($ts[$i]['Tsumego']['elo_rating_mode'] - $x1min));
			$ts[$i]['Tsumego']['rank2'] = $this->getTsumegoRank(round($x2min + (($x2max - $x2min) / ($x1max - $x1min)) * ($ts[$i]['Tsumego']['elo_rating_mode'] - $x1min)));
			if($ts[$i]['Tsumego']['public']==1){
				$ts[$i]['Tsumego']['elo_rating_mode'] = $ts[$i]['Tsumego']['shift'];
				//$this->Tsumego->save($ts[$i]);
			}
		}
		$this->set('ts', $ts);
	}
	
	public function test1e(){
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('SetConnection');
		$this->loadModel('Set');
		
		$x1min = 500;
		$x1max = 1200;
		$x2min = 100;
		$x2max = 1200;
		
		$x = 700;
		
		$result = $x2min + (($x2max - $x2min) / ($x1max - $x1min)) * ($x - $x1min);
		echo $result;
	}
	
	//list tsumego variations
	public function test2(){
		$this->loadModel('Tsumego');
		
		$ts = $this->Tsumego->find('all', array('order' => 'rd ASC'));
		$more = array();
		$less = array();
		
		for($i=0; $i<count($ts); $i++){
			if($ts[$i]['Tsumego']['rd']>0)
				array_push($more, $ts[$i]['Tsumego']['rd']);
			if($ts[$i]['Tsumego']['rd']<0)
				array_push($less, $ts[$i]['Tsumego']['rd']);
		}
		echo '<pre>'; print_r(count($less)); echo '</pre>';
		echo '<pre>'; print_r(count($more)); echo '</pre>';
		
		$this->set('ts', $ts);
	}
	
	public function adjusttsumego2(){
		$ts = array();
		$min = 600;
		$max = 2600;
		
		$scale = $max-$min;
		$step = $scale/100;
		$x = array();
		for($i=0; $i<=100; $i++){
			$x[$i] = $min+$step*$i;
		}
		
		for($i=0; $i<=100; $i++){
			$a = array();
			$a['elo'] = $x[$i];
			$a['xp'] = round(pow($a['elo']/100, 1.55)-6);
			array_push($ts, $a);
		}
			
		echo '<table>';
		for($i=0; $i<count($ts); $i++)
			echo '<tr><td>'.$ts[$i]['elo'].'</td><td>'.$ts[$i]['xp'].'</td></tr>';
		echo '</table>';
	}
	
	private function publishDates(){
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');
		$this->loadModel('Set');
		$this->loadModel('PublishDate');
		$ts = $this->Tsumego->find('all', array('order' => 'created ASC'));
		for($i=0; $i<count($ts); $i++){
			$pdsc = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $ts[$i]['Tsumego']['id'])));
			$pds = $this->Set->findById($pdsc['SetConnection']['set_id']);
			if($pds['Set']['public']==-1){
				$pd['PublishDate']['tsumego_id'] = $ts[$i]['Tsumego']['id'];
				$pd['PublishDate']['date'] = $ts[$i]['Tsumego']['created'];
				$this->PublishDate->create();
				$this->PublishDate->save($pd);
			}
		}
	}
	
	private function fillSetConnection(){
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');
		
		$ts = $this->Tsumego->find('all', array('conditions' => array('id >=' => 18000)));
		for($j=0; $j<count($ts); $j++){
			$scx = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $ts[$j]['Tsumego']['id'], 'set_id' => $ts[$j]['Tsumego']['set_id'])));
			if($scx==null){
				$sc = array();
				$sc['SetConnection']['tsumego_id'] = $ts[$j]['Tsumego']['id'];
				$sc['SetConnection']['set_id'] = $ts[$j]['Tsumego']['set_id'];
				$sc['SetConnection']['num'] = $ts[$j]['Tsumego']['num'];
				$this->SetConnection->create();
				$this->SetConnection->save($sc);
			}
		}
	}
	
	public function publish(){
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('Schedule');
		$this->loadModel('SetConnection');
		
		$p = $this->Schedule->find('all', array('order' => 'date ASC', 'conditions' => array('published' => 0)));
		
		for($i=0; $i<count($p); $i++){
			$t = $this->Tsumego->findById($p[$i]['Schedule']['tsumego_id']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$p[$i]['Schedule']['num'] = $t['Tsumego']['num'];
			$p[$i]['Schedule']['set'] = $s['Set']['title'].' '.$s['Set']['title2'].' ';
		}
		$this->set('p', $p);
	}
	
	public function empty_uts(){
		$this->loadModel('TsumegoStatus');
		$this->loadModel('PurgeList');
		$pl = $this->PurgeList->find('first', array('order' => 'id DESC'));
		$pl['PurgeList']['empty_uts'] = date('Y-m-d H:i:s');
		$this->PurgeList->save($pl);
		
		$ut = $this->TsumegoStatus->find('all', array('limit' => 10000, 'conditions' =>  array('user_id' => 33)));
		for($i=0; $i<count($ut); $i++){
			$this->TsumegoStatus->delete($ut[$i]['TsumegoStatus']['id']);
		}
		$this->set('ut', count($ut));
	}
	
	public function best_tsumego(){
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('TsumegoRatingAttempt');
		$this->loadModel('Schedule');
		$this->loadModel('Tsumego');
		
		$tsumegoOfTheDay1 = $this->TsumegoRatingAttempt->find('all', array('limit' => 10000, 'order' => 'created DESC', 'conditions' => array('status' => 'S')));
		$tsumegoOfTheDay2 = $this->TsumegoAttempt->find('all', array('limit' => 30000, 'order' => 'created DESC', 'conditions' => array('gain >=' => 40)));
		
		$date = date('Y-m-d', strtotime('yesterday'));
		$s = $this->Schedule->find('all', array('conditions' =>  array('date' => $date)));
		
		$t = $this->Tsumego->find('all');
		
		$this->set('ut', $tsumegoOfTheDay1);
		$this->set('out', $tsumegoOfTheDay2);
		$this->set('s', $s);
		$this->set('t', $t);
	}
	
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
				$ans = 'Click the following button to reset your password. If you have not requested the password reset, 
then ignore this email. https://tsumego-hero.com/users/newpassword/'.$randomString;
				$Email->send($ans);
			}
			$this->set('sent', true);
		}else{
			$this->set('sent', false);
		}
		/*
		if($_SESSION['loggedInUser']['User']['id']==72){
			$Email = new CakeEmail();
			$Email->from(array('me@joschkazimdars.com' => 'http://joschkazimdars.com'));
			$Email->to('support@my-evh.de');
			$Email->subject('Mitgliedsnummer u. Aufnahmedatum');
			$ans = 'Hallo,
			
die me@joschkazimdars.com ist eine Weiterleitungs-Email. Von der kann ich keine Mails schicken. Ich hab gerade ein PHP-Script geschrieben, das den Mail-Header manipuliert, sodass es aussieht, als hätte ich von der Mail gesendet. Falls ihr noch Hilfe im Web-Team braucht, vielleicht ergibt sich ja noch etwas, ich bin studierter Informatiker. Also ich bin am Freitag eh im EVH-Chat.

-- 
Viele Grüße
Joschka Zimdars';
			$Email->send($ans);
			$this->set('sent', true);
		}
		*/
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
	
	public function routine0(){//23:55 signed in users today
		$this->loadModel('Answer');
		
		$activity = $this->User->find('all', array('order' => array('User.reuse3 DESC')));
		$todaysUsers = array();
		$today = date('Y-m-d', strtotime('today'));
		for($i=0; $i<count($activity); $i++){
			$a = new DateTime($activity[$i]['User']['created']);
			if($a->format('Y-m-d')==$today) array_push($todaysUsers, $activity[$i]['User']);
		}
		
		$token = array();
		$this->Answer->create();
		$token['Answer']['dismissed'] = count($todaysUsers);
		$token['Answer']['created'] = date('Y-m-d H:i:s');
		$this->Answer->save($token);
		
		$this->set('u', count($todaysUsers));
	}
	
	public function routine1(){//0:00 uotd
		$this->loadModel('DayRecord');
		$today = date('Y-m-d');
		$dateUser = $this->DayRecord->find('first', array('conditions' =>  array('date' => $today)));
		if(count($dateUser)==0){
			$this->uotd();
			$this->deleteUserBoards();
		}			
	}
	
	public function routine2(){//0:02 halfXP
		$this->halfXP();
	}
	
	public function routine3(){//0:04 t_glicko
		$this->loadModel('User');
		$this->loadModel('TsumegoRatingAttempt');
		$ux = $this->User->find('all', array('limit' => 1000, 'order' => 'created DESC'));
		
		for($i=0; $i<count($ux); $i++){
			$trs = $this->TsumegoRatingAttempt->find('all', array('order' => 'created DESC', 'conditions' => array('user_id' => $ux[$i]['User']['id'])));
			$activeToday = false;
			$d1 = date('Y-m-d', strtotime('yesterday'));
			
			for($j=0; $j<count($trs); $j++){
				$date = new DateTime($trs[$j]['TsumegoRatingAttempt']['created']);
				$date = $date->format('Y-m-d');
				$trs[$j]['TsumegoRatingAttempt']['created'] = $date;
				if($date==$d1){
					$activeToday = true;
					break;
				}
			}
			if($ux[$i]['User']['rd']<90){//125
				$ux[$i]['User']['rd'] += 35;
				$this->User->save($ux[$i]);
			}
			/*
			if(!$activeToday && $ux[$i]['User']['rd']<250){
				$ux[$i]['User']['rd'] += 60;
				$this->User->save($ux[$i]);
			}
			*/
		}
		
		$this->set('activeToday', $activeToday);
		$this->set('trs', $trs);
	}
	
	public function routine11(){//0:05 userRefresh
		$this->userRefresh(1);
	}
	public function routine12(){//0:06 userRefresh
		$this->userRefresh(2);
	}
	public function routine13(){//0:07 userRefresh
		$this->userRefresh(3);
	}
	public function routine14(){//0:08 userRefresh
		$this->userRefresh(4);
	}
	public function routine15(){//0:09 userRefresh
		$this->userRefresh(5);
	}
	public function routine16(){//0:10 userRefresh
		$this->userRefresh(6);
	}
	public function routine17(){//0:11 userRefresh
		$this->userRefresh(7);
	}
	public function routine18(){//0:12 userRefresh
		$this->userRefresh(8);
	}
	public function routine19(){//0:13 userRefresh
		$this->userRefresh(9);
	}
	public function routine110(){//0:14 userRefresh
		$this->userRefresh(10);
	}
	public function routine111(){//0:15 userRefresh
		$this->userRefresh(11);
	}
	public function routine112(){//0:16 userRefresh
		$this->userRefresh(12);
	}
	public function routine20(){//popular tags
		$tags = $this->Tag->find('all', array('conditions' => array('approved' => 1)));
		$tagCount = array();
		for($i=0; $i<count($tags); $i++)
			array_push($tagCount, $tags[$i]['Tag']['tag_name_id']);

		$tagCount = array_count_values($tagCount);
		$tagId = array();
		$tagNum = array();
		foreach($tagCount as $key => $value){
			array_push($tagId, $key);
			array_push($tagNum, $value);
		}
		array_multisort($tagNum, $tagId);
		$array = array();
		for($i=count($tagId)-1; $i>=0; $i--){
			$a = array();
			$a['id'] = $tagId[$i];
			$a['num'] = $tagNum[$i];
			array_push($array, $a);
		}
		file_put_contents('json/popular_tags.json', json_encode($array));
	}
	public function routine21(){//level highscore
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
		$rand = rand(0,9);
		echo $rand;
		for($i=0;$i<count($users);$i++){
			if($stop<=1000 && $anz<1000){
				array_push($UxpSum, $users[$i]['User']['xpSum']);
				if(strlen($users[$i]['User']['name'])>20) $users[$i]['User']['name'] = substr($users[$i]['User']['name'], 0, 20);
				array_push($Uname, $this->checkPicture($users[$i]));
				array_push($Ulevel, $users[$i]['User']['level']);
				array_push($Uid, $users[$i]['User']['id']);
				array_push($Utype, $users[$i]['User']['premium']);
				if($users[$i]['User']['solved']==null) $users[$i]['User']['solved']=0;
				if(($i+$rand)%9 == 0)
					array_push($Usolved, $this->saveSolvedNumber($users[$i]['User']['id']));
				else
					array_push($Usolved, $users[$i]['User']['solved']);
				$anz++;
			}
		}
		array_multisort($UxpSum, $Uname, $Ulevel, $Uid, $Utype, $Usolved);
		$users2 = array();
		for($i=0; $i<count($UxpSum); $i++){
			$a = array();
			$a['xpSum'] = $UxpSum[$i];
			$a['name'] = utf8_encode($Uname[$i]);
			$a['level'] = $Ulevel[$i];
			$a['id'] = $Uid[$i];
			$a['type'] = $Utype[$i];
			$a['solved'] = $Usolved[$i];
			array_push($users2, $a);
		}
		file_put_contents('json/level_highscore.json', json_encode($users2));
	}

	public function routine22(){//achievement highscore
		$aNum = count($this->Achievement->find('all'));
		$as = $this->AchievementStatus->find('all');
		$as2 = array();

		for($i=0; $i<count($as); $i++){
			if($as[$i]['AchievementStatus']['achievement_id']!=46){
				array_push($as2, $as[$i]['AchievementStatus']['user_id']);
			}else{
				$as46counter = $as[$i]['AchievementStatus']['value'];
				while($as46counter>0){
					array_push($as2, $as[$i]['AchievementStatus']['user_id']);
					$as46counter--;
				}
			}
		}
		$as3 = array_count_values($as2);
		$uaNum = array();
		$uaId = array();
		foreach ($as3 as $key => $value){
			$u = $this->User->findById($key);
			$u['User']['name'] = $this->checkPicture($u);
			array_push($uaNum, $value);
			array_push($uaId, $u['User']['id']);
		}
		array_multisort($uaNum, $uaId);
		
		$toJson = array();
		$toJson['uaNum'] = $uaNum;
		$toJson['uaId'] = $uaId;
		$toJson['aNum'] = $aNum;
	
		file_put_contents('json/achievement_highscore.json', json_encode($toJson));
	}

	public function routine23(){//daily highscore
		$activity = $this->User->find('all', array('order' => array('User.reuse3 DESC')));
		$todaysUsers = array();
		$today = date('Y-m-d', strtotime('today'));
		for($i=0; $i<count($activity); $i++){
			$activity[$i]['User']['name'] =  utf8_encode($activity[$i]['User']['name']);
			$a = new DateTime($activity[$i]['User']['created']);
			if($a->format('Y-m-d')==$today) array_push($todaysUsers, $activity[$i]['User']);
		}
		file_put_contents('json/daily_highscore.json', json_encode($todaysUsers));
	}

	public function routine24(){//time mode overview
		$sets = $this->Set->find('all', array('conditions' => array('public' => 1)));
		$tsumegos = array();
		for($i=0;$i<count($sets);$i++){
			$tx = $this->findTsumegoSet($sets[$i]['Set']['id']);
			for($j=0;$j<count($tx);$j++){
				array_push($tsumegos, $tx[$j]);
			}
		}
		$rx = array();
		array_push($rx, '15k');
		array_push($rx, '14k');
		array_push($rx, '13k');
		array_push($rx, '12k');
		array_push($rx, '11k');
		array_push($rx, '10k');
		array_push($rx, '9k');
		array_push($rx, '8k');
		array_push($rx, '7k');
		array_push($rx, '6k');
		array_push($rx, '5k');
		array_push($rx, '4k');
		array_push($rx, '3k');
		array_push($rx, '2k');
		array_push($rx, '1k');
		array_push($rx, '1d');
		array_push($rx, '2d');
		array_push($rx, '3d');
		array_push($rx, '4d');
		array_push($rx, '5d');
		$rxx = array();
		for($i=0;$i<count($rx);$i++)
			$rxx[$rx[$i]] = array();
		for($i=0;$i<count($tsumegos);$i++){
			if($tsumegos[$i]['Tsumego']['elo_rating_mode']>=2500) array_push($rxx['5d'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=2400) array_push($rxx['4d'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=2300) array_push($rxx['3d'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=2200) array_push($rxx['2d'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=2100) array_push($rxx['1d'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=2000) array_push($rxx['1k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=1900) array_push($rxx['2k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=1800) array_push($rxx['3k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=1700) array_push($rxx['4k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=1600) array_push($rxx['5k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=1500) array_push($rxx['6k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=1400) array_push($rxx['7k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=1300) array_push($rxx['8k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=1200) array_push($rxx['9k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=1100) array_push($rxx['10k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=1000) array_push($rxx['11k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=900) array_push($rxx['12k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=800) array_push($rxx['13k'], $tsumegos[$i]);
			elseif($tsumegos[$i]['Tsumego']['elo_rating_mode']>=700) array_push($rxx['14k'], $tsumegos[$i]);
			else array_push($rxx['15k'], $tsumegos[$i]);
		}
		$rxxCount = array();
		array_push($rxxCount, count($rxx['15k']));
		array_push($rxxCount, count($rxx['14k']));
		array_push($rxxCount, count($rxx['13k']));
		array_push($rxxCount, count($rxx['12k']));
		array_push($rxxCount, count($rxx['11k']));
		array_push($rxxCount, count($rxx['10k']));
		array_push($rxxCount, count($rxx['9k']));
		array_push($rxxCount, count($rxx['8k']));
		array_push($rxxCount, count($rxx['7k']));
		array_push($rxxCount, count($rxx['6k']));
		array_push($rxxCount, count($rxx['5k']));
		array_push($rxxCount, count($rxx['4k']));
		array_push($rxxCount, count($rxx['3k']));
		array_push($rxxCount, count($rxx['2k']));
		array_push($rxxCount, count($rxx['1k']));
		array_push($rxxCount, count($rxx['1d']));
		array_push($rxxCount, count($rxx['2d']));
		array_push($rxxCount, count($rxx['3d']));
		array_push($rxxCount, count($rxx['4d']));
		array_push($rxxCount, count($rxx['5d']));

		file_put_contents('json/time_mode_overview.json', json_encode($rxxCount));
	}

	public function routine25(){//tsumego public and set_id
		$sets = $this->Set->find('all', array('conditions' => array('public' => 1)));
		for($i=0; $i<count($sets); $i++){
			$ts = $this->findTsumegoSet($sets[$i]['Set']['id']);
			for($j=0; $j<count($ts); $j++){
				$save = false;
				if($ts[$j]['Tsumego']['public']!=1){
					$ts[$j]['Tsumego']['public'] = 1;
					$save = true;
				}
				if($ts[$j]['Tsumego']['set_id']==null){
					$ts[$j]['Tsumego']['set_id'] = $sets[$i]['Set']['id'];
					$save = true;
				}
				if($save)
					$this->Tsumego->save($ts[$j]);
			}
		}
	}

	public function refresh_dates($filter=null){//0:17 refresh rest (routine999)
		if($filter==1){
			$u = $this->User->find('all', array('conditions' =>  array(
				'NOT' => array('lastRefresh' => date('Y-m-d'))
			)));
		}elseif($filter==2){
			$u = $this->User->find('all', array('conditions' =>  array(
				'NOT' => array('lastRefresh' => date('Y-m-d'))
			)));
			for($i=0; $i<count($u); $i++){
				$this->User->create();
				$u[$i]['User']['reuse2'] = 0;//#
				$u[$i]['User']['reuse3'] = 0;//xp
				$u[$i]['User']['reuse4'] = 0;//daily maximum
				$u[$i]['User']['damage'] = 0;
				$u[$i]['User']['sprint'] = 1;
				$u[$i]['User']['intuition'] = 1;
				$u[$i]['User']['rejuvenation'] = 1;
				$u[$i]['User']['refinement'] = 1;
				$u[$i]['User']['usedSprint'] = 0;
				$u[$i]['User']['usedRejuvenation'] = 0;
				$u[$i]['User']['usedRefinement'] = 0;
				$u[$i]['User']['readingTrial'] = 30;
				$u[$i]['User']['potion'] = 0;
				$u[$i]['User']['promoted'] += 1;
				$u[$i]['User']['lastRefresh'] = date('Y-m-d');
				$this->User->save($u[$i]);
			}
		}else{
			$u = $this->User->find('all', array('limit' => 50, 'order' => 'lastRefresh ASC'));
		}
		$this->set('u', $u);
	}
	
	public function routine4x(){//0:20 remove inactive players 2
		$this->loadModel('TsumegoStatus');
		$ux = $this->User->find('all', array('limit' => 1000, 'order' => 'created DESC'));
		$u = array();
		$d1 = date('Y-m-d', strtotime('-7 days'));
		for($i=0; $i<count($ux); $i++){
			$date = new DateTime($ux[$i]['User']['created']);
			$date = $date->format('Y-m-d');
			if($date==$d1){
			}
		}
		$this->set('u', $d1);
	}
	
	public function routine5(){//0:25 update user solved field
		$this->loadModel('TsumegoStatus');
		
		$users = $this->User->find('all', array('limit' => 100, 'order' => 'created DESC'));
		for($i=0; $i<count($users); $i++){
			$uid = $users[$i]['User']['id'];
			$ux = $this->User->findById($uid);
			$uts = $this->TsumegoStatus->find('all', array('conditions' =>  array('user_id' => $uid)));
			$solvedUts = array();
			for($j=0; $j<count($uts); $j++){
				if($uts[$j]['TsumegoStatus']['status']=='S' || $uts[$j]['TsumegoStatus']['status']=='W' || $uts[$j]['TsumegoStatus']['status']=='C'){
					array_push($solvedUts, $uts[$j]);
				}			
			}
			/*$uts = $this->OldTsumegoStatus->find('all', array('conditions' =>  array('user_id' => $uid)));
			$solvedUts2 = array();
			for($j=0; $j<count($uts); $j++){
				if($uts[$j]['OldTsumegoStatus']['status']=='S' || $uts[$j]['OldTsumegoStatus']['status']=='W' || $uts[$j]['OldTsumegoStatus']['status']=='C'){
					array_push($solvedUts2, $uts[$j]);
				}			
			}*/
			$ux['User']['solved'] = count($solvedUts);
			$this->User->save($ux);
		}
		
		$this->set('u', $users);
	}
	
	public function routine6(){//0:30 update user solved field
		$this->loadModel('Answer');
		$this->loadModel('TsumegoStatus');
		$a = $this->Answer->findById(1);
		$u = $this->User->find('all', array('order' => 'id ASC', 'conditions' =>  array(
			'id >' => $a['Answer']['message'],
			'id <=' => $a['Answer']['dismissed']
		)));
		for($i=0; $i<count($u); $i++){
			$solvedUts = $this->TsumegoStatus->find('all', array('conditions' =>  array(
				'user_id' => $u[$i]['User']['id'],
				'OR' => array(
					array('status' => 'S'),
					array('status' => 'W'),
					array('status' => 'C')
				)
			)));
			$u[$i]['User']['solved'] = count($solvedUts);
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
		$this->LoadModel('TsumegoAttempt');
		$this->LoadModel('Set');
		$this->LoadModel('Tsumego');
		$this->LoadModel('SetConnection');
		if($uid == null){
			$ur = $this->TsumegoAttempt->find('all', array('limit' => 500, 'order' => 'created DESC'));
		}elseif($uid==99){
			$ur = $this->TsumegoAttempt->find('all', array('order' => 'created DESC', 'conditions' =>  array(
				'tsumego_id >=' => 19752,
				'tsumego_id <=' => 19761
			)));
		}else{
			$ur = $this->TsumegoAttempt->find('all', array('limit' => 500, 'order' => 'created DESC', 'conditions' => array('user_id' => $uid)));
		}	
		 
		for($i=0; $i<count($ur); $i++){
			$u = $this->User->findById($ur[$i]['TsumegoAttempt']['user_id']);
			$ur[$i]['TsumegoAttempt']['user_name'] = $u['User']['name'];
			$ur[$i]['TsumegoAttempt']['level'] = $u['User']['level'];
			$t = $this->Tsumego->findById($ur[$i]['TsumegoAttempt']['tsumego_id']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$ur[$i]['TsumegoAttempt']['tsumego_num'] = $t['Tsumego']['num'];
			$ur[$i]['TsumegoAttempt']['tsumego_xp'] = $t['Tsumego']['difficulty'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$ur[$i]['TsumegoAttempt']['set_name'] = $s['Set']['title'];
		}
		
		$noIndex = false;
		if($uid != null) $noIndex = true;
		if(isset($this->params['url']['c'])) $this->set('count', 1);
		else $this->set('count', 0);
		$this->set('noIndex', $noIndex);
		$this->set('ur', $ur);
		$this->set('uid', $uid);
	}
	
	public function userstats2($uid = null){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'USER STATS';
		$this->LoadModel('TsumegoAttempt');
		$this->LoadModel('Set');
		$this->LoadModel('Tsumego');
		$this->LoadModel('SetConnection');
		if($uid == null){
			$ur = $this->TsumegoAttempt->find('all', array('limit' => 500, 'order' => 'created DESC'));
		}else{
			$ur = $this->TsumegoAttempt->find('all', array('order' => 'created DESC', 'conditions' => array('user_id' => $uid)));
		}	
		
		$performance = array();
		$performance['p10'] = 0;
		$performance['p10S'] = 0;
		$performance['p10F'] = 0;
		$performance['p20'] = 0;
		$performance['p20S'] = 0;
		$performance['p20F'] = 0;
		$performance['p30'] = 0;
		$performance['p30S'] = 0;
		$performance['p30F'] = 0;
		$performance['p40'] = 0;
		$performance['p40S'] = 0;
		$performance['p40F'] = 0;
		$performance['p50'] = 0;
		$performance['p50S'] = 0;
		$performance['p50F'] = 0;
		$performance['p60'] = 0;
		$performance['p60S'] = 0;
		$performance['p60F'] = 0;
		$performance['p70'] = 0;
		$performance['p70S'] = 0;
		$performance['p70F'] = 0;
		$performance['p80'] = 0;
		$performance['p80S'] = 0;
		$performance['p80F'] = 0;
		$performance['p90'] = 0;
		$performance['p90S'] = 0;
		$performance['p90F'] = 0;
		$performance['pX'] = 0;
		
		for($i=0; $i<count($ur); $i++){
			$u = $this->User->findById($ur[$i]['TsumegoAttempt']['user_id']);
			$ur[$i]['TsumegoAttempt']['user_name'] = $u['User']['name'];
			$t = $this->Tsumego->findById($ur[$i]['TsumegoAttempt']['tsumego_id']);
			$ur[$i]['TsumegoAttempt']['tsumego_num'] = $t['Tsumego']['num'];
			$ur[$i]['TsumegoAttempt']['tsumego_xp'] = $t['Tsumego']['difficulty']*10;
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$ur[$i]['TsumegoAttempt']['set_name'] = $s['Set']['title'];
			
			if($ur[$i]['TsumegoAttempt']['solved']!='S' && $ur[$i]['TsumegoAttempt']['solved']!='F') $performance['pX']++;
			
			if($ur[$i]['TsumegoAttempt']['tsumego_xp']==10){
				if($ur[$i]['TsumegoAttempt']['solved']=='S') $performance['p10S']++;
				elseif($ur[$i]['TsumegoAttempt']['solved']=='F') $performance['p10F']++;
				$performance['p10']++;
			}elseif($ur[$i]['TsumegoAttempt']['tsumego_xp']==20){
				if($ur[$i]['TsumegoAttempt']['solved']=='S') $performance['p20S']++;
				elseif($ur[$i]['TsumegoAttempt']['solved']=='F') $performance['p20F']++;
				$performance['p20']++;
			}elseif($ur[$i]['TsumegoAttempt']['tsumego_xp']==30){
				if($ur[$i]['TsumegoAttempt']['solved']=='S') $performance['p30S']++;
				elseif($ur[$i]['TsumegoAttempt']['solved']=='F') $performance['p30F']++;
				$performance['p30']++;
			}elseif($ur[$i]['TsumegoAttempt']['tsumego_xp']==40){
				if($ur[$i]['TsumegoAttempt']['solved']=='S') $performance['p40S']++;
				elseif($ur[$i]['TsumegoAttempt']['solved']=='F') $performance['p40F']++;
				$performance['p40']++;
			}elseif($ur[$i]['TsumegoAttempt']['tsumego_xp']==50){
				if($ur[$i]['TsumegoAttempt']['solved']=='S') $performance['p50S']++;
				elseif($ur[$i]['TsumegoAttempt']['solved']=='F') $performance['p50F']++;
				$performance['p50']++;
			}elseif($ur[$i]['TsumegoAttempt']['tsumego_xp']==60){
				if($ur[$i]['TsumegoAttempt']['solved']=='S') $performance['p60S']++;
				elseif($ur[$i]['TsumegoAttempt']['solved']=='F') $performance['p60F']++;
				$performance['p60']++;
			}elseif($ur[$i]['TsumegoAttempt']['tsumego_xp']==70){
				if($ur[$i]['TsumegoAttempt']['solved']=='S') $performance['p70S']++;
				elseif($ur[$i]['TsumegoAttempt']['solved']=='F') $performance['p70F']++;
				$performance['p70']++;
			}elseif($ur[$i]['TsumegoAttempt']['tsumego_xp']==80){
				if($ur[$i]['TsumegoAttempt']['solved']=='S') $performance['p80S']++;
				elseif($ur[$i]['TsumegoAttempt']['solved']=='F') $performance['p80F']++;
				$performance['p80']++;
			}elseif($ur[$i]['TsumegoAttempt']['tsumego_xp']==90){
				if($ur[$i]['TsumegoAttempt']['solved']=='S') $performance['p90S']++;
				elseif($ur[$i]['TsumegoAttempt']['solved']=='F') $performance['p90F']++;
				$performance['p90']++;
			}
		}
		
		$noIndex = false;
		if($uid != null) $noIndex = true;
		
		$this->set('noIndex', $noIndex);
		$this->set('ur', $ur);
		$this->set('uid', $uid);
		$this->set('performance', $performance);
	}
	
	public function userstats3($sid = null){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'USER STATS';
		$this->LoadModel('TsumegoAttempt');
		$this->LoadModel('Set');
		$this->LoadModel('Tsumego');
		$this->LoadModel('SetConnection');
		
		$ts = $this->findTsumegoSet($sid);
		$ids = array();
		for($i=0; $i<count($ts); $i++){
			array_push($ids, $ts[$i]['Tsumego']['id']);
		}
		
		if($sid == null){
			$ur = $this->TsumegoAttempt->find('all', array('limit' => 500, 'order' => 'created DESC'));
		}else{
			$ur = $this->TsumegoAttempt->find('all', array('order' => 'created DESC', 'conditions' => array('tsumego_id' => $ids)));
		}	
		 
		for($i=0; $i<count($ur); $i++){
			$u = $this->User->findById($ur[$i]['TsumegoAttempt']['user_id']);
			$ur[$i]['TsumegoAttempt']['user_name'] = $u['User']['name'];
			$t = $this->Tsumego->findById($ur[$i]['TsumegoAttempt']['tsumego_id']);
			$ur[$i]['TsumegoAttempt']['tsumego_num'] = $t['Tsumego']['num'];
			$ur[$i]['TsumegoAttempt']['tsumego_xp'] = $t['Tsumego']['difficulty'];
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$ur[$i]['TsumegoAttempt']['set_name'] = $s['Set']['title'];
		}
		
		$noIndex = false;
		if($uid != null) $noIndex = true;
		if(isset($this->params['url']['c'])) $this->set('count', 1);
		else $this->set('count', 0);
		$this->set('noIndex', $noIndex);
		$this->set('ur', $ur);
		$this->set('uid', $uid);
	}
	
	public function stats($p=null){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'PAGE STATS';
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('Comment');
		$this->LoadModel('User');
		$this->LoadModel('DayRecord');
		$this->LoadModel('Tsumego');
		$this->LoadModel('Set');
		$this->LoadModel('AdminActivity');
		$this->LoadModel('SetConnection');
		
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
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
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
					$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
					$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
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
		
		$aa = $this->AdminActivity->find('all', array('limit' => 100, 'order' => 'created DESC', 'conditions' => array('user_id' => 2781)));
		
		$this->set('c1', count($c1));
		$this->set('c2', count($c2));
		$this->set('c3', count($c3));
		$this->set('page', $p);
		$this->set('u', $todaysUsers);
		$this->set('comments', $comments);
		$this->set('aa', $aa);
	}
	
	public function uservisits(){
		$_SESSION['page'] = 'set';
		$_SESSION['title'] = 'User Visits';
		$this->loadModel('Answer');
		
		$ans = $this->Answer->find('all', array('order' => 'created DESC'));
		$a = array();
		for($i=0; $i<count($ans); $i++){
			$a[$i]['date'] = $ans[$i]['Answer']['created'];
			$a[$i]['num'] = $ans[$i]['Answer']['dismissed'];
			$a[$i]['y'] = date('Y', strtotime($ans[$i]['Answer']['created']));
			$a[$i]['m'] = date('m', strtotime($ans[$i]['Answer']['created']));
			$a[$i]['d'] = date('d', strtotime($ans[$i]['Answer']['created']));
		}
		array_pop($a);
		$aNew = array();$aNew['date'] = '2020-01-28 07:15:05';$aNew['num'] = 259;$aNew['y'] = 2020;$aNew['m'] = '01';$aNew['d'] = '28';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2020-01-21 07:15:05';$aNew['num'] = 223;$aNew['y'] = 2020;$aNew['m'] = '01';$aNew['d'] = '21';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-10-31 07:15:05';$aNew['num'] = 187;$aNew['y'] = 2019;$aNew['m'] = '10';$aNew['d'] = '31';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-05-15 07:15:05';$aNew['num'] = 171;$aNew['y'] = 2019;$aNew['m'] = '05';$aNew['d'] = '15';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-03-24 07:15:05';$aNew['num'] = 163;$aNew['y'] = 2019;$aNew['m'] = '03';$aNew['d'] = '24';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-03-15 07:15:05';$aNew['num'] = 141;$aNew['y'] = 2019;$aNew['m'] = '03';$aNew['d'] = '15';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-03-13 07:15:05';$aNew['num'] = 134;$aNew['y'] = 2019;$aNew['m'] = '03';$aNew['d'] = '13';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-03-12 07:15:05';$aNew['num'] = 121;$aNew['y'] = 2019;$aNew['m'] = '03';$aNew['d'] = '12';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-03-07 07:15:05';$aNew['num'] = 106;$aNew['y'] = 2019;$aNew['m'] = '03';$aNew['d'] = '07';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-02-28 07:15:05';$aNew['num'] = 103;$aNew['y'] = 2019;$aNew['m'] = '02';$aNew['d'] = '28';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-02-22 07:15:05';$aNew['num'] = 85;$aNew['y'] = 2019;$aNew['m'] = '02';$aNew['d'] = '22';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-02-19 07:15:05';$aNew['num'] = 82;$aNew['y'] = 2019;$aNew['m'] = '02';$aNew['d'] = '19';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-02-18 07:15:05';$aNew['num'] = 75;$aNew['y'] = 2019;$aNew['m'] = '02';$aNew['d'] = '18';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-02-15 07:15:05';$aNew['num'] = 73;$aNew['y'] = 2019;$aNew['m'] = '02';$aNew['d'] = '15';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-02-14 07:15:05';$aNew['num'] = 72;$aNew['y'] = 2019;$aNew['m'] = '02';$aNew['d'] = '14';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-02-11 07:15:05';$aNew['num'] = 54;$aNew['y'] = 2019;$aNew['m'] = '02';$aNew['d'] = '11';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-02-06 07:15:05';$aNew['num'] = 53;$aNew['y'] = 2019;$aNew['m'] = '02';$aNew['d'] = '06';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-02-04 07:15:05';$aNew['num'] = 47;$aNew['y'] = 2019;$aNew['m'] = '02';$aNew['d'] = '04';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-01-31 07:15:05';$aNew['num'] = 48;$aNew['y'] = 2019;$aNew['m'] = '01';$aNew['d'] = '31';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-01-27 07:15:05';$aNew['num'] = 37;$aNew['y'] = 2019;$aNew['m'] = '01';$aNew['d'] = '27';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-01-22 07:15:05';$aNew['num'] = 31;$aNew['y'] = 2019;$aNew['m'] = '01';$aNew['d'] = '22';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-01-19 07:15:05';$aNew['num'] = 33;$aNew['y'] = 2019;$aNew['m'] = '01';$aNew['d'] = '19';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-01-16 07:15:05';$aNew['num'] = 32;$aNew['y'] = 2019;$aNew['m'] = '01';$aNew['d'] = '16';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-01-09 07:15:05';$aNew['num'] = 26;$aNew['y'] = 2019;$aNew['m'] = '01';$aNew['d'] = '09';array_push($a, $aNew);
		$aNew = array();$aNew['date'] = '2019-01-07 07:15:05';$aNew['num'] = 20;$aNew['y'] = 2019;$aNew['m'] = '01';$aNew['d'] = '07';array_push($a, $aNew);

		$this->set('a', $a);
	}
	
	public function duplicates(){
		$_SESSION['page'] = 'sandbox';
		$_SESSION['title'] = 'Merge Duplicates';
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Set');
		$this->loadModel('AdminActivity');
		$this->loadModel('SetConnection');
		$this->loadModel('Sgf');
		$this->loadModel('Duplicate');
		$this->loadModel('Comment');
		
		$idMap = array();
		$idMap2 = array();
		$marks = array();
		$aMessage = null;
		$errSet = '';
		$errNotNull = '';
		//$sc1 = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => 3537, 'set_id' => 71790)));
		//$sc2 = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => 25984, 'set_id' => 190)));
		//$sc2['SetConnection']['tsumego_id'] = 25984;
		//$this->SetConnection->save($sc2);
		
		//echo '<pre>'; print_r($sc1); echo '</pre>';
		//echo '<pre>'; print_r($sc2); echo '</pre>';
		
		if(isset($this->params['url']['remove'])){
			$remove = $this->Tsumego->findById($this->params['url']['remove']);
			if(!empty($remove)){
				$remove['Tsumego']['duplicate'] = 0;
				$this->Tsumego->save($remove);
			}
		}
		if(isset($this->params['url']['removeDuplicate'])){
			$remove = $this->Tsumego->findById($this->params['url']['removeDuplicate']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $remove['Tsumego']['id'])));
			$remove['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			if(!empty($remove) && $remove['Tsumego']['duplicate']>9){
				$r1 = $this->Tsumego->findById($remove['Tsumego']['duplicate']);
				$r2 = $this->Tsumego->find('all', array('conditions' => array('duplicate' => $remove['Tsumego']['duplicate'])));
				array_push($r2, $r1);
				if(count($r2)==2){
					for($i=0; $i<count($r2); $i++){
						$r2[$i]['Tsumego']['duplicate'] = 0;
						$this->Tsumego->save($r2[$i]);
					}
				}else if(count($r2)>2){
					$remove['Tsumego']['duplicate'] = 0;
					$this->Tsumego->save($remove);
				}
				$sx = $this->Set->findById($remove['Tsumego']['set_id']);
				$title = $sx['Set']['title'].' - '.$remove['Tsumego']['num'];
				$adminActivity = array();
				$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
				$adminActivity['AdminActivity']['tsumego_id'] = $this->params['url']['removeDuplicate'];
				$adminActivity['AdminActivity']['file'] = 'settings';
				$adminActivity['AdminActivity']['answer'] = 'Removed duplicate: '.$title;
				$this->AdminActivity->save($adminActivity);
			}else{
				$aMessage = 'You can\'t remove the main duplicate.';
			}
		}
		if(isset($this->params['url']['main']) && isset($this->params['url']['duplicates'])){
			$newDuplicates = explode('-', $this->params['url']['duplicates']);
			$newD = array();
			$newDmain = array();
			$checkSc = $this->SetConnection->find('all', array('conditions' => array('tsumego_id' => $this->params['url']['main'])));
			$errSet = '';
			$errNotNull = '';
			if(count($checkSc)<=1){
				$validSc = true;
			}else{
				$validSc = false;
				$errNotNull = 'Already set as duplicate.';
			}
			$newD0check = array();
			for($i=0; $i<count($newDuplicates); $i++){
				$newD0 = $this->Tsumego->findById($newDuplicates[$i]);
				$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $newD0['Tsumego']['id'])));
				$newD0['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
				array_push($newD0check, $newD0['Tsumego']['set_id']);
			}
			$newD0check = array_count_values($newD0check);
			foreach($newD0check as $key => $value)
				if($value>1){
					$validSc = false;
					$errSet = 'You can\'t link duplicates in the same collection.';
				}
			
			if($validSc){
				for($i=0; $i<count($newDuplicates); $i++){
					$newD = $this->Tsumego->findById($newDuplicates[$i]);
					$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $newD['Tsumego']['id'])));
					$newD['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
					if($newD['Tsumego']['id']==$this->params['url']['main']){
						$newDmain = $newD;
						$newD['Tsumego']['duplicate'] = $this->params['url']['main'];
						$this->Tsumego->save($newD);
					}else{
						$comments = $this->Comment->find('all', array('conditions' => array('tsumego_id' => $newD['Tsumego']['id'])));
						for($j=0; $j<count($comments); $j++)
							$this->Comment->delete($comments[$j]['Comment']['id']);
						$this->Tsumego->delete($newD['Tsumego']['id']);
					}
					$this->SetConnection->delete($scT['SetConnection']['id']);
					$setC = array();
					$setC['SetConnection']['tsumego_id'] = $this->params['url']['main'];
					$setC['SetConnection']['set_id'] = $newD['Tsumego']['set_id'];
					$setC['SetConnection']['num'] = $newD['Tsumego']['num'];
					$this->SetConnection->create();
					$this->SetConnection->save($setC);
					$dupDel = $this->Duplicate->find('all', array('conditions' => array('tsumego_id' => $newDuplicates[$i])));
					for($j=0; $j<count($dupDel); $j++)
						$this->Duplicate->delete($dupDel[$j]['Duplicate']['id']);
				}
				$sx = $this->Set->findById($newDmain['Tsumego']['set_id']);
				$title = $sx['Set']['title'].' - '.$newDmain['Tsumego']['num'];
				$adminActivity = array();
				$adminActivity['AdminActivity']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
				$adminActivity['AdminActivity']['tsumego_id'] = $this->params['url']['main'];
				$adminActivity['AdminActivity']['file'] = 'settings';
				$adminActivity['AdminActivity']['answer'] = 'Created duplicate group: '.$title;
				$this->AdminActivity->save($adminActivity);
			}
		}
		if(!empty($this->data['Mark'])){
			$mark = $this->Tsumego->findById($this->data['Mark']['tsumego_id']);
			if(!empty($mark) && $mark['Tsumego']['duplicate']==0){
				$mark['Tsumego']['duplicate'] = -1;
				$this->Tsumego->save($mark);
			}
		}
		if(!empty($this->data['Mark2'])){
			$mark = $this->Tsumego->findById($this->data['Mark2']['tsumego_id']);
			$group = $this->Tsumego->findById($this->data['Mark2']['group_id']);
			
			if($mark!=null && $mark['Tsumego']['duplicate']==0 && $group!=null){
				$scTx = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $mark['Tsumego']['id'])));
				$scTx['SetConnection']['tsumego_id'] = $this->data['Mark2']['group_id'];
				$this->SetConnection->save($scTx);
				$comments = $this->Comment->find('all', array('conditions' => array('tsumego_id' => $mark['Tsumego']['id'])));
				for($j=0; $j<count($comments); $j++)
					$this->Comment->delete($comments[$j]['Comment']['id']);
				$this->Tsumego->delete($mark['Tsumego']['id']);
			}
		}
		
		$marks = $this->Tsumego->find('all', array('conditions' => array('duplicate' => -1)));
		for($i=0; $i<count($marks); $i++)
			array_push($idMap2, $marks[$i]['Tsumego']['id']);
		$uts2 = $this->TsumegoStatus->find('all', array('conditions' => array('tsumego_id'=>$idMap2, 'user_id'=>$_SESSION['loggedInUser']['User']['id'])));
		$counter2 = 0;
		$markTooltipSgfs = array();
		$markTooltipInfo = array();
		$markTooltipBoardSize = array();
		for($i=0; $i<count($marks); $i++){
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $marks[$i]['Tsumego']['id'])));
			$marks[$i]['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($marks[$i]['Tsumego']['set_id']);
			$marks[$i]['Tsumego']['title'] = $s['Set']['title'].' - '.$marks[$i]['Tsumego']['num'];
			$marks[$i]['Tsumego']['status'] = $uts2[$counter2]['TsumegoStatus']['status'];
			$tts = $this->Sgf->find('all', array('limit' => 1, 'order' => 'version DESC', 'conditions' => array('tsumego_id' => $marks[$i]['Tsumego']['id'])));
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			$markTooltipSgfs[$i] = $tArr[0];
			$markTooltipInfo[$i] = $tArr[2];
			$markTooltipBoardSize[$i] = $tArr[3];
			$counter2++;
		}
		
		$sc = $this->SetConnection->find('all');
		$scCount = array();
		$scCount2 = array();
		for($i=0; $i<count($sc); $i++)
			array_push($scCount, $sc[$i]['SetConnection']['tsumego_id']);
		$scCount = array_count_values($scCount);
		foreach($scCount as $key => $value){
			if($value>1)
				array_push($scCount2, $key);
		}
		
		$duplicates1 = array();
		
		$showAll = false;
		
		if(isset($this->params['url']['load'])){
			$showAll = true;
			$counter = 0;
			for($i=0; $i<count($scCount2); $i++){
				$duplicates1[$i] = array();
				for($j=0; $j<count($sc); $j++){
					if($sc[$j]['SetConnection']['tsumego_id']==$scCount2[$i]){
						$scT1 = $this->Tsumego->findById($sc[$j]['SetConnection']['tsumego_id']);
						$scT1['Tsumego']['num'] = $sc[$j]['SetConnection']['num'];
						$scT1['Tsumego']['set_id'] = $sc[$j]['SetConnection']['set_id'];
						$scT1['Tsumego']['status'] = 'N';
						array_push($duplicates1[$i], $scT1);
						array_push($idMap, $scT1['Tsumego']['id']);
					}
				}
			}
			
			$uts = $this->TsumegoStatus->find('all', array('conditions' => array('tsumego_id'=>$idMap, 'user_id'=>$_SESSION['loggedInUser']['User']['id'])));
			$tooltipSgfs = array();
			$tooltipInfo = array();
			$tooltipBoardSize = array();
			for($i=0; $i<count($duplicates1); $i++){
				$tooltipSgfs[$i] = array();
				$tooltipInfo[$i] = array();
				$tooltipBoardSize[$i] = array();
				for($j=0; $j<count($duplicates1[$i]); $j++){
					$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $duplicates1[$i][$j]['Tsumego']['id'], 'set_id' => $duplicates1[$i][$j]['Tsumego']['set_id'])));
					$duplicates1[$i][$j]['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
					$s = $this->Set->findById($duplicates1[$i][$j]['Tsumego']['set_id']);
					if($s!=null){
						$duplicates1[$i][$j]['Tsumego']['title'] = $s['Set']['title'].' - '.$duplicates1[$i][$j]['Tsumego']['num'];
						$duplicates1[$i][$j]['Tsumego']['duplicateLink'] = '?sid='.$duplicates1[$i][$j]['Tsumego']['set_id'];
						for($k=0; $k<count($uts); $k++)
							if($uts[$k]['TsumegoStatus']['tsumego_id'] == $duplicates1[$i][$j]['Tsumego']['id'])
								$duplicates1[$i][$j]['Tsumego']['status'] = $uts[$k]['TsumegoStatus']['status'];
						$tts = $this->Sgf->find('all', array('limit' => 1, 'order' => 'version DESC', 'conditions' => array('tsumego_id' => $duplicates1[$i][$j]['Tsumego']['id'])));
						$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
						$tooltipSgfs[$i][$j] = $tArr[0];
						$tooltipInfo[$i][$j] = $tArr[2];
						$tooltipBoardSize[$i][$j] = $tArr[3];
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
	
	public function uploads(){
		$_SESSION['page'] = 'set';
		$_SESSION['title'] = 'Uploads';
		$this->LoadModel('Sgf');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');
		
		$s = $this->Sgf->find('all', array('limit' => 250, 'order' => 'created DESC', 'conditions' =>  array(
			'NOT' => array('user_id' => 33, 'version' => 0)
		)));
		
		for($i=0; $i<count($s); $i++){
			$s[$i]['Sgf']['sgf'] = str_replace("\r", '', $s[$i]['Sgf']['sgf']);
			$s[$i]['Sgf']['sgf'] = str_replace("\n", '"+"\n"+"', $s[$i]['Sgf']['sgf']);
			
			$u = $this->User->findById($s[$i]['Sgf']['user_id']);
			$s[$i]['Sgf']['user'] = $u['User']['name'];
			$t = $this->Tsumego->findById($s[$i]['Sgf']['tsumego_id']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$set = $this->Set->findById($t['Tsumego']['set_id']);
			$s[$i]['Sgf']['title'] = $set['Set']['title'].' '.$set['Set']['title2'].' #'.$t['Tsumego']['num'];
			$s[$i]['Sgf']['num'] = $t['Tsumego']['num'];
			
			$s[$i]['Sgf']['delete'] = false;
			$sDiff = $this->Sgf->find('all', array('order' => 'version DESC','limit' => 2,'conditions' => array('tsumego_id' => $s[$i]['Sgf']['tsumego_id'])));			
			$s[$i]['Sgf']['diff'] = $sDiff[1]['Sgf']['id'];
		}
		$this->set('s', $s);
	}
	
	public function adminstats($p=null){
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'Admin Panel';
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('TsumegoAttempts');
		$this->LoadModel('TsumegoRatingAttempts');
		$this->LoadModel('Comment');
		$this->LoadModel('User');
		$this->LoadModel('DayRecord');
		$this->LoadModel('Tsumego');
		$this->LoadModel('Set');
		$this->LoadModel('AdminActivity');
		$this->LoadModel('SetConnection');
		$this->LoadModel('Tag');
		$this->LoadModel('TagName');
		$this->LoadModel('Sgf');
		$this->LoadModel('UserContribution');
		$this->LoadModel('Reject');

		if($_SESSION['loggedInUser']['User']['isAdmin']>0){
			if(isset($this->params['url']['accept']) && isset($this->params['url']['tag_id'])){
				if(md5($_SESSION['loggedInUser']['User']['id']) == $this->params['url']['hash']){

					$tagsToApprove = explode("-",$_COOKIE['tagList']);
					for($i=1; $i<count($tagsToApprove); $i++){
						$tagToApprove = $this->Tag->findById(substr($tagsToApprove[$i], 1));
						if($tagToApprove!=null && $tagToApprove['Tag']['approved']!=1){
							$this->handleContribution($_SESSION['loggedInUser']['User']['id'], 'reviewed');
							if(substr($tagsToApprove[$i], 0,1) == 'a'){
								$tagToApprove['Tag']['approved'] = '1';
								$this->Tag->save($tagToApprove);
								$this->handleContribution($tagToApprove['Tag']['user_id'], 'added_tag');
							}else{
								$reject = array();
								$reject['Reject']['tsumego_id'] = $tagToApprove['Tag']['tsumego_id'];
								$reject['Reject']['user_id'] = $tagToApprove['Tag']['user_id'];
								$reject['Reject']['type'] = 'tag';
								$tagNameId = $this->TagName->findById($tagToApprove['Tag']['tag_name_id']);
								$reject['Reject']['text'] = $tagNameId['TagName']['name'];
								$this->Reject->create();
								$this->Reject->save($reject);
								$this->Tag->delete($tagToApprove['Tag']['id']);
							}
						}
					}

					$tagNamesToApprove = explode("-",$_COOKIE['tagNameList']);
					for($i=1; $i<count($tagNamesToApprove); $i++){
						$tagNameToApprove = $this->TagName->findById(substr($tagNamesToApprove[$i], 1));
						if($tagNameToApprove!=null && $tagNameToApprove['TagName']['approved']!=1){
							$this->handleContribution($_SESSION['loggedInUser']['User']['id'], 'reviewed');
							if(substr($tagNamesToApprove[$i], 0,1) == 'a'){
								$tagNameToApprove['TagName']['approved'] = '1';
								$this->TagName->save($tagNameToApprove);
								$this->handleContribution($tagNameToApprove['TagName']['user_id'], 'created_tag');
							}else{
								$reject = array();
								$reject['Reject']['user_id'] = $tagNameToApprove['TagName']['user_id'];
								$reject['Reject']['type'] = 'tag name';
								$reject['Reject']['text'] = $tagNameToApprove['TagName']['name'];
								$this->Reject->create();
								$this->Reject->save($reject);
								$this->TagName->delete($tagNameToApprove['TagName']['id']);
							}
						}
					}

					$proposalsToApprove = explode("-",$_COOKIE['proposalList']);
					for($i=1; $i<count($proposalsToApprove); $i++){
						$proposalToApprove =  $this->Sgf->findById(substr($proposalsToApprove[$i], 1));
						if($proposalToApprove!=null && $proposalToApprove['Sgf']['version']==0){
							$this->handleContribution($_SESSION['loggedInUser']['User']['id'], 'reviewed');
							if(substr($proposalsToApprove[$i], 0,1) == 'a'){
								$recentSgf = $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' =>  array('tsumego_id' => $proposalToApprove['Sgf']['tsumego_id'])));
								$proposalToApprove['Sgf']['version'] = $this->createNewVersionNumber($recentSgf, 0);
								$this->Sgf->save($proposalToApprove);
								$this->handleContribution($proposalToApprove['Sgf']['user_id'], 'made_proposal');
							}else{
								$reject = array();
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
			}
			
			if(isset($this->params['url']['delete']) && isset($this->params['url']['hash'])){
				$toDelete = $this->User->findById($this->params['url']['delete']/1111);
				$del1 = $this->TsumegoStatus->find('all', array('conditions' => array('user_id' => $toDelete['User']['id'])));
				$del2 = $this->TsumegoAttempts->find('all', array('conditions' => array('user_id' => $toDelete['User']['id'])));
				$del3 = $this->TsumegoRatingAttempts->find('all', array('conditions' => array('user_id' => $toDelete['User']['id'])));
				if(md5($toDelete['User']['name']) == $this->params['url']['hash']){
					for($i=0; $i<count($del1); $i++)
						$this->TsumegoStatus->delete($del1[$i]['TsumegoStatus']['id']);
					for($i=0; $i<count($del2); $i++)
						$this->TsumegoAttempts->delete($del2[$i]['TsumegoAttempts']['id']);
					for($i=0; $i<count($del3); $i++)
						$this->TsumegoRatingAttempts->delete($del3[$i]['TsumegoRatingAttempts']['id']);
					$this->User->delete($toDelete['User']['id']);
					echo '<pre>'; print_r('Deleted user '.$toDelete['User']['name']); echo '</pre>';
				}
			}
		}

		$tags = $this->Tag->find('all', array('conditions' => array('approved' => 0)));
		$tagNames = $this->TagName->find('all', array('conditions' => array('approved' => 0)));
		$tagsByKey = $this->TagName->find('all');
		$tKeys = array();
		for($i=0; $i<count($tagsByKey); $i++)
			$tKeys[$tagsByKey[$i]['TagName']['id']] = $tagsByKey[$i]['TagName']['name'];
		
		$tsIds = array();
		$tagTsumegos = array();
		for($i=0; $i<count($tags); $i++){
			$at = $this->Tsumego->find('first', array('conditions' => array('id' => $tags[$i]['Tag']['tsumego_id'])));
			array_push($tsIds, $at['Tsumego']['id']);
			array_push($tagTsumegos, $at);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $at['Tsumego']['id'])));
			$as = $this->Set->find('first', array('conditions' => array('id' => $scT['SetConnection']['set_id'])));
			$au = $this->User->findById($tags[$i]['Tag']['user_id']);
			$tags[$i]['Tag']['name'] = $tKeys[$tags[$i]['Tag']['tag_name_id']];
			$tags[$i]['Tag']['tsumego'] = $as['Set']['title'].' - '.$at['Tsumego']['num'];
			$tags[$i]['Tag']['user'] = $this->checkPicture($au);
		}
		for($i=0; $i<count($tagNames); $i++){
			$au = $this->User->findById($tagNames[$i]['TagName']['user_id']);
			$tagNames[$i]['TagName']['user'] = $this->checkPicture($au);
		}

		$approveSgfs = $this->Sgf->find('all', array('conditions' => array('version' => 0)));
		$sgfTsumegos = array();
		$latestVersionTsumegos = array();
		for($i=0; $i<count($approveSgfs); $i++){
			$at = $this->Tsumego->find('first', array('conditions' => array('id' => $approveSgfs[$i]['Sgf']['tsumego_id'])));
			array_push($latestVersionTsumegos, $this->Sgf->find('first', array('order' => 'version DESC', 'conditions' =>  array('tsumego_id' => $at['Tsumego']['id']))));
			array_push($sgfTsumegos, $at);
			array_push($tsIds, $at['Tsumego']['id']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $at['Tsumego']['id'])));
			$as = $this->Set->find('first', array('conditions' => array('id' => $scT['SetConnection']['set_id'])));
			$au = $this->User->findById($approveSgfs[$i]['Sgf']['user_id']);
			$approveSgfs[$i]['Sgf']['tsumego'] = $as['Set']['title'].' - '.$at['Tsumego']['num'];
			$approveSgfs[$i]['Sgf']['user'] = $this->checkPicture($au);
		}
		$uts = $this->TsumegoStatus->find('all', array('conditions' =>  array(
			'user_id' => $_SESSION['loggedInUser']['User']['id'],
			'tsumego_id' => $tsIds
		)));

		$tsMap = array();
		for($i=0; $i<count($uts); $i++)
			$tsMap[$uts[$i]['TsumegoStatus']['tsumego_id']] = $uts[$i]['TsumegoStatus']['status'];
		
		$tooltipSgfs = array();
		$tooltipInfo = array();
		$tooltipBoardSize = array();
		for($i=0; $i<count($tagTsumegos); $i++){
			$tagTsumegos[$i]['Tsumego']['status'] = $tsMap[$tagTsumegos[$i]['Tsumego']['id']];
			$tts = $this->Sgf->find('all', array('limit' => 1, 'order' => 'version DESC', 'conditions' => array('tsumego_id' => $tagTsumegos[$i]['Tsumego']['id'])));
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			array_push($tooltipSgfs, $tArr[0]);
			array_push($tooltipInfo, $tArr[2]);
			array_push($tooltipBoardSize, $tArr[3]);
		}
		$tooltipSgfs2 = array();
		$tooltipInfo2 = array();
		$tooltipBoardSize2 = array();
		for($i=0; $i<count($sgfTsumegos); $i++){
			$sgfTsumegos[$i]['Tsumego']['status'] = $tsMap[$sgfTsumegos[$i]['Tsumego']['id']];
			$tts = $this->Sgf->find('all', array('limit' => 1, 'order' => 'version DESC', 'conditions' => array('tsumego_id' => $sgfTsumegos[$i]['Tsumego']['id'])));
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			array_push($tooltipSgfs2, $tArr[0]);
			array_push($tooltipInfo2, $tArr[2]);
			array_push($tooltipBoardSize2, $tArr[3]);
		}
		
		$u = $this->User->find('all', array('conditions' => array('isAdmin >' => 0)));
		$uArray = array();
		for($i=0; $i<count($u); $i++)
			array_push($uArray, $u[$i]['User']['id']);
		
		$aa = $this->AdminActivity->find('all', array('limit' => 100, 'order' => 'created DESC'));
		$aa2 = array();
		$b1 = array();
		$ca = array();
		$ca['tsumego_id'] = array();
		$ca['tsumego'] = array();
		$ca['created'] = array();
		$ca['name'] = array();
		$ca['answer'] = array();
		$ca['type'] = array();
		for($i=0; $i<count($aa); $i++){
			$at = $this->Tsumego->find('first', array('conditions' => array('id' => $aa[$i]['AdminActivity']['tsumego_id'])));
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $at['Tsumego']['id'])));
			$at['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$as = $this->Set->find('first', array('conditions' => array('id' => $at['Tsumego']['set_id'])));
			$au = $this->User->find('first', array('conditions' => array('id' => $aa[$i]['AdminActivity']['user_id'])));
			$aa[$i]['AdminActivity']['name'] = $au['User']['name'];
			$aa[$i]['AdminActivity']['isAdmin'] = $au['User']['isAdmin'];
			$aa[$i]['AdminActivity']['tsumego'] = $as['Set']['title'].' - '.$at['Tsumego']['num'];
			if($aa[$i]['AdminActivity']['answer']==96) $aa[$i]['AdminActivity']['answer'] = 'Approved.';
			if($aa[$i]['AdminActivity']['answer']==97) $aa[$i]['AdminActivity']['answer'] = 'No answer necessary.';
			if($aa[$i]['AdminActivity']['answer']==98) $aa[$i]['AdminActivity']['answer'] = 'Can\'t resolve this.';
			if($aa[$i]['AdminActivity']['answer']==99) $aa[$i]['AdminActivity']['answer'] = 'Deleted.';
			if(!strpos($aa[$i]['AdminActivity']['answer'], '.sgf')){
				array_push($aa2, $aa[$i]);
				array_push($ca['tsumego_id'], $aa[$i]['AdminActivity']['tsumego_id']);
				array_push($ca['tsumego'], $aa[$i]['AdminActivity']['tsumego']);
				array_push($ca['created'], $aa[$i]['AdminActivity']['created']);
				array_push($ca['name'], $aa[$i]['AdminActivity']['name']);
				array_push($ca['answer'], $aa[$i]['AdminActivity']['answer']);
				array_push($ca['type'], 'Answer');
			}else{
				if($aa[$i]['AdminActivity']['isAdmin'] > 0){
					array_push($aa2, $aa[$i]);
					array_push($ca['tsumego_id'], $aa[$i]['AdminActivity']['tsumego_id']);
					array_push($ca['tsumego'], $aa[$i]['AdminActivity']['tsumego']);
					array_push($ca['created'], $aa[$i]['AdminActivity']['created']);
					array_push($ca['name'], $aa[$i]['AdminActivity']['name']);
					array_push($ca['answer'], $aa[$i]['AdminActivity']['answer']);
					array_push($ca['type'], 'Upload');
				}
			}
		}
		$adminComments = $this->Comment->find('all', array('order' => 'created DESC', 'conditions' => array(
			'created >' => $aa[count($aa)-1]['AdminActivity']['created'],
			'user_id' => $uArray,
			'NOT' => array(
				'status' => array(99)
			)
		)));
		for($i=0; $i<count($adminComments); $i++){
			$at = $this->Tsumego->find('first', array('conditions' => array('id' => $adminComments[$i]['Comment']['tsumego_id'])));
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $at['Tsumego']['id'])));$at['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$as = $this->Set->find('first', array('conditions' => array('id' => $at['Tsumego']['set_id'])));
			$au = $this->User->find('first', array('conditions' => array('id' => $adminComments[$i]['Comment']['user_id'])));
			array_push($ca['tsumego_id'], $adminComments[$i]['Comment']['tsumego_id']);
			array_push($ca['tsumego'], $as['Set']['title'].' - '.$at['Tsumego']['num']);
			array_push($ca['created'], $adminComments[$i]['Comment']['created']);
			array_push($ca['name'], $au['User']['name']);
			array_push($ca['answer'], $adminComments[$i]['Comment']['message']);
			array_push($ca['type'], 'Comment');
		}
		for($i=0; $i<count($ca['tsumego_id']); $i++)
			array_multisort($ca['created'], $ca['tsumego_id'], $ca['tsumego'], $ca['name'], $ca['answer'], $ca['type']);
		
		$requestDeletion = $this->User->find('all', array('conditions' => array('dbstorage' => 1111)));
		
		$this->set('requestDeletion', $requestDeletion);
		$this->set('aa', $aa);
		$this->set('aa2', $aa2);
		$this->set('ca', $ca);
		$this->set('tags', $tags);
		$this->set('tagNames', $tagNames);
		$this->set('tagTsumegos', $tagTsumegos);
		$this->set('tooltipSgfs', $tooltipSgfs);
		$this->set('tooltipInfo', $tooltipInfo);
		$this->set('tooltipBoardSize', $tooltipBoardSize);
		$this->set('tooltipSgfs2', $tooltipSgfs2);
		$this->set('tooltipInfo2', $tooltipInfo2);
		$this->set('tooltipBoardSize2', $tooltipBoardSize2);
		$this->set('approveSgfs', $approveSgfs);
		$this->set('sgfTsumegos', $sgfTsumegos);
		$this->set('latestVersionTsumegos', $latestVersionTsumegos);
	}
	
	public function login(){
		$this->loadModel('TsumegoStatus');
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'Tsumego Hero - Sign In';
		
		$clearSession = true;
		if(!empty($this->data)){
			$clearSession = false;
			$u = $this->User->findByName($this->data['User']['name']);
			if($u){
				if($this->validateLogin($this->data)){
					$this->signIn($u);
					$this->Session->setFlash(__('Login successful.', true));
					$isLoaded = $this->TsumegoStatus->find('first', array('conditions' => array('user_id' => $u['User']['id'])));
					$_SESSION['redirect'] = 'sets';
				}else{
					$this->Session->setFlash(__('Login incorrect.', true));
				}
			}else{
				$this->Session->setFlash(__('Login incorrect.', true));
			}
		}else{
			$clearSession = true;
		}
		$this->set('clearSession', $clearSession);
	}
	
	public function login2(){
		$this->loadModel('TsumegoStatus');
		$_SESSION['page'] = 'user';
		$_SESSION['title'] = 'Tsumego Hero - Sign In';
		if(!empty($this->data)){
			$u = $this->User->findByEmail($this->data['User']['email']);
			if($u){
				if($this->validateLogin2($this->data)){
					$this->signIn($u);
					$this->Session->setFlash(__('Login successful.', true));
					$isLoaded = $this->TsumegoStatus->find('first', array('conditions' => array('user_id' => $u['User']['id'])));
					$_SESSION['redirect'] = 'sets';
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
				$_SESSION['redirect'] = 'sets';
			}
		}
	}

	public function highscore(){
		$_SESSION['page'] = 'levelHighscore';
		$_SESSION['title'] = 'Tsumego Hero - Highscore';
		
		$this->LoadModel('Tsumego');
		$this->LoadModel('Activate');
		
		$this->saveSolvedNumber($_SESSION['loggedInUser']['User']['id']);
	
		$activate = false;
		if(isset($_SESSION['loggedInUser']))
			$activate = $this->Activate->find('first', array('conditions' =>  array('user_id' => $_SESSION['loggedInUser']['User']['id'])));
		
		$json = json_decode(file_get_contents('json/level_highscore.json'), true);
		
		$uAll = $this->User->find('all', array('limit' => 1250, 'order' => 'level DESC'));
		$uMap = array();
		for($i=0; $i<count($uAll); $i++)
			$uMap[$uAll[$i]['User']['id']] = $uAll[$i]['User']['name'];
		for($i=0; $i<count($json); $i++)
			$json[$i]['name'] = $uMap[$json[$i]['id']];
		
		$this->set('users', $json);
		$this->set('activate', $activate);
	}
	
	public function rating(){
		$_SESSION['page'] = 'ratingHighscore';
		$_SESSION['title'] = 'Tsumego Hero - Rating';
		
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('Tsumego');
		if(isset($_SESSION['loggedInUser'])){
			$ux = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
			$ux['User']['lastHighscore'] = 2;
			$this->User->save($ux);
		}
		
		$users = $this->User->find('all', array('limit' => 1000, 'order' => 'elo_rating_mode DESC', 'conditions' =>  array(
			'NOT' => array('id' => array(33, 34, 35))
		)));
	
		$this->set('users', $users);
	}

	public function added_tags(){
		$_SESSION['page'] = 'timeHighscore';
		$_SESSION['title'] = 'Tsumego Hero - Added Tags';
		$this->LoadModel('UserContribution');

		$list = array();
		$uc = $this->UserContribution->find('all', array('limit' => 100, 'order' => 'score DESC'));
		for($i=0; $i<count($uc); $i++){
			$x = array();
			$x['id'] = $uc[$i]['UserContribution']['user_id'];
			$x['name'] =  $this->checkPicture($this->User->findById($uc[$i]['UserContribution']['user_id']));
			$x['score'] = $uc[$i]['UserContribution']['score'];
			$x['added_tag'] = $uc[$i]['UserContribution']['added_tag'];
			$x['created_tag'] = $uc[$i]['UserContribution']['created_tag'];
			$x['made_proposal'] = $uc[$i]['UserContribution']['made_proposal'];
			$x['reviewed'] = $uc[$i]['UserContribution']['reviewed'];
			array_push($list, $x);
		}
		$this->set('a', $list);
	}

	public function rewards(){
		$this->LoadModel('UserContribution');
		$uc = $this->UserContribution->find('first', array('conditions' => array('user_id' => $_SESSION['loggedInUser']['User']['id'])));

		if(isset($this->params['url']['action']) && isset($this->params['url']['token'])){
			if(md5('level') == $this->params['url']['action']){
				if(md5($uc['UserContribution']['score']) == $this->params['url']['token']){
					$uc['UserContribution']['reward1'] = 1;
					$this->UserContribution->save($uc);
					$u = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
					$u['User']['level'] += 1;
					$u['User']['nextlvl'] += $this->getXPJump($u['User']['level']);
					$u['User']['health'] = $this->getHealth($u['User']['level']);
					$_SESSION['loggedInUser']['User']['level'] = $u['User']['level'];
					$_SESSION['loggedInUser']['User']['nextlvl'] = $u['User']['nextlvl'];
					$_SESSION['loggedInUser']['User']['health'] = $u['User']['health'];
					$this->User->save($u);
					$u = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
					$_SESSION['loggedInUser'] = $u;
					$this->set('refresh', 'refresh');
				}
			}else if(md5('rank') == $this->params['url']['action']){
				if(md5($uc['UserContribution']['score']) == $this->params['url']['token']){
					$uc['UserContribution']['reward2'] = 1;
					$this->UserContribution->save($uc);
					$u = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
					$u['User']['elo_rating_mode'] += 100;
					$_SESSION['loggedInUser']['User']['elo_rating_mode'] = $u['User']['elo_rating_mode'];
					$this->User->save($u);
					$u = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
					$_SESSION['loggedInUser'] = $u;
					$this->set('refresh', 'refresh');
				}
			}else if(md5('heropower') == $this->params['url']['action']){
				if(md5($uc['UserContribution']['score']) == $this->params['url']['token']){
					$uc['UserContribution']['reward3'] = 1;
					$this->UserContribution->save($uc);
				}
			}else if(md5('premium') == $this->params['url']['action']){
				if(md5($uc['UserContribution']['score']) == $this->params['url']['token']){
					if($_SESSION['loggedInUser']['User']['preium'] == 0){
						$u = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
						$u['User']['premium'] = 1;
						$_SESSION['loggedInUser']['User']['premium'] = $u['User']['premium'];
						$this->User->save($u);
						$u = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
						$_SESSION['loggedInUser'] = $u;
					}
				}
			}
		}
		$reachedGoals = floor($uc['UserContribution']['score']/30);
		$goals = array();
		$goals[0] = $reachedGoals >= 1;
		$goals[1] = $reachedGoals >= 2;
		$goals[2] = $reachedGoals >= 3;
		$goals[3] = $reachedGoals >= 4;
		$goalsColor = array();
		for($i=0; $i<count($goals); $i++){
			if($goals[$i]) $goalsColor[$i] = '#e9cc2c';
			else $goalsColor[$i] = 'black';
		}
		//echo '<pre>'; print_r($goals); echo '</pre>';
		$this->set('goals', $goals);
		$this->set('goalsColor', $goalsColor);
		$this->set('uc', $uc);
	}
	
	public function achievements(){
		$_SESSION['page'] = 'achievementHighscore';
		$_SESSION['title'] = 'Tsumego Hero - Achievements Highscore';
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('Tsumego');
		$this->LoadModel('AchievementStatus');
		$this->LoadModel('Achievement');
		$this->LoadModel('User');
		
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			$ux = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
			$ux['User']['lastHighscore'] = 2;
			$this->User->save($ux);
		}
		$json = json_decode(file_get_contents('json/achievement_highscore.json'), true);

		for($i=count($json['uaId'])-1; $i>=count($json['uaId'])-100; $i--){
			$u = $this->User->findById($json['uaId'][$i]);
			$json['uaId'][$i] = $u['User']['name'];
		}

		$this->set('uaNum', $json['uaNum']);
		$this->set('uName', $json['uaId']);
		$this->set('aNum', $json['aNum']);
	}
	
	public function highscore3(){
		$_SESSION['page'] = 'timeHighscore';
		$_SESSION['title'] = 'Tsumego Hero - Time Highscore';
		
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('Tsumego');
		$this->LoadModel('RankOverview');
		$currentRank = '';
		$params1 = '';
		$params2 = '';
		
		if(isset($_SESSION['loggedInUser'])){
			$ux = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
			$ux['User']['lastHighscore'] = 2;
			$this->User->save($ux);
		}
		
		if(isset($this->params['url']['category'])){
			$ro = $this->RankOverview->find('all', array('order' => 'points DESC', 'conditions' => array(
				'mode' => $this->params['url']['category'],
				'rank' => $this->params['url']['rank']
			)));
			$currentRank = $this->params['url']['rank'];
			$params1 = $this->params['url']['category'];
			$params2 = $this->params['url']['rank'];
		}else{
			if(isset($_SESSION['loggedInUser'])){
				$lastModex = $_SESSION['loggedInUser']['User']['lastMode']-1;
			 }else{
				$lastModex = 2;
			}
			
			$params1 = $lastModex;
			$params2 = '15k';
			$currentRank = $params2;
			$ro = $this->RankOverview->find('all', array('order' => 'points DESC', 'conditions' => array(
				'mode' => $params1,
				'rank' => $params2
			)));
			//echo '<pre>'; print_r($ro); echo '</pre>'; 
			//echo '<pre>'; print_r($_SESSION['loggedInUser']['User']['lastMode']); echo '</pre>'; 
		}
		$roAll = array();
		$roAll['user'] = array();
		$roAll['picture'] = array();
		$roAll['points'] = array();
		$roAll['result'] = array();
		
		for($i=0; $i<count($ro); $i++){
			$us = $this->User->findById($ro[$i]['RankOverview']['user_id']);
			$alreadyIn = false;
			for($j=0; $j<count($roAll['user']); $j++){
				if($roAll['user'][$j]==$us['User']['name']) $alreadyIn = true;
			}
			if(!$alreadyIn){
				array_push($roAll['user'], $us['User']['name']);
				array_push($roAll['picture'], $us['User']['picture']);
				array_push($roAll['points'], $ro[$i]['RankOverview']['points']);
				array_push($roAll['result'], $ro[$i]['RankOverview']['status']);
			}
		}
		
		$modes = array();
		$modes[0] = array();
		$modes[1] = array();
		$modes[2] = array();
		for($i=0;$i<3;$i++){
			$rank = 15;
			$j=0;
			while($rank>-5){
				$kd = 'k';
				$rank2 = $rank;
				if($rank>=1) $kd = 'k';
				else{ 
					$rank2 = ($rank-1)*(-1);
					$kd = 'd';
				}
				$modes[$i][$j] = $rank2.$kd;
				$rank--;
				$j++;
			}
		}
		$modes2 = array();
		$modes2[0] = array();
		$modes2[1] = array();
		$modes2[2] = array();
		for($i=0;$i<3;$i++){
			$rank = 15;
			$j=0;
			while($rank>-5){
				$kd = 'k';
				$rank2 = $rank;
				if($rank>=1) $kd = 'k';
				else{ 
					$rank2 = ($rank-1)*(-1);
					$kd = 'd';
				}
				$modes2[$i][$j] = $rank2.$kd;
				$rank--;
				$j++;
			}
		}
		
		for($i=0;$i<count($modes);$i++){
			for($j=0;$j<count($modes[$i]);$j++){
				$mx = $this->RankOverview->find('first', array('conditions' => array(
					'rank' => $modes[$i][$j],
					'mode' => $i
				)));
				if(!empty($mx)) $modes[$i][$j] = 1;
			}
		}
		
		if(isset($_SESSION['loggedInUser'])){
			$ux = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
			$ux['User']['lastHighscore'] = 4;
			$this->User->save($ux);
		}
		
		//echo '<pre>'; print_r($modes); echo '</pre>'; 
		//echo '<pre>'; print_r($modes2); echo '</pre>'; 
		$this->set('roAll', $roAll);
		$this->set('rank', $currentRank);
		$this->set('params1', $params1);
		$this->set('params2', $params2);
		$this->set('modes', $modes);
		$this->set('modes2', $modes2);
	}
	
	public function leaderboard(){
		$_SESSION['page'] = 'dailyHighscore';
		$_SESSION['title'] = 'Tsumego Hero - Daily Highscore';
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('Tsumego');
		$this->LoadModel('DayRecord');

		$adminsList = $this->User->find('all', array('order' => 'id ASC', 'conditions' =>  array('isAdmin >' => 0)));
		$admins = array();
		for($i=0; $i<count($adminsList); $i++){
			array_push($admins, $adminsList[$i]['User']['name']);
		}
		$dayRecord = $this->DayRecord->find('all', array('limit' => 2, 'order' => 'id DESC'));
		$userYesterday = $this->User->findById($dayRecord[0]['DayRecord']['user_id']);
		if(isset($_SESSION['loggedInUser'])){
			$ux = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
			$ux['User']['lastHighscore'] = 3;
			$this->User->save($ux);
		}
		$json = json_decode(file_get_contents('json/daily_highscore.json'), true);
		for($i=0; $i<count($json); $i++){
			$u = $this->User->findById($json[$i]['id']);
			$json[$i]['name'] = $u['User']['name'];
		}

		$this->set('a', $json);
		$this->set('uNum', count($json));
		$this->set('admins', $admins);
		$this->set('dayRecord', $userYesterday['User']['name']);
	}
	
	public function view($id=null){
		$_SESSION['page'] = 'user';
		$this->LoadModel('TsumegoStatus');
		$this->LoadModel('TsumegoAttempt');
		$this->LoadModel('Tsumego');
		$this->LoadModel('Set');
		$this->LoadModel('Achievement');
		$this->LoadModel('AchievementStatus');
		$this->LoadModel('SetConnection');
		$this->LoadModel('RankOverview');
		$hideEmail = false;
		
		$solvedUts2 = $this->saveSolvedNumber($_SESSION['loggedInUser']['User']['id']);
		
		$as = $this->AchievementStatus->find('all', array('limit' => 12, 'order' => 'created DESC', 'conditions' => array('user_id' => $_SESSION['loggedInUser']['User']['id'])));
		$ach = $this->Achievement->find('all');		
		
		$user = $this->User->findById($id);
		$_SESSION['title'] = 'Profile of '.$user['User']['name'];
		
		if($_SESSION['loggedInUser']['User']['id']!=$id && $_SESSION['loggedInUser']['User']['id']!=72){
			$_SESSION['redirect'] = 'sets';
			$user['User']['email'] = '';
			$hideEmail = true;
		}
		if(!empty($this->data)){
			if(isset($this->data['User']['email'])){
				$changeUser = $user;
				$changeUser['User']['email'] = $this->data['User']['email'];
				$this->set('data', $changeUser['User']['email']);
				$this->User->save($changeUser, true);
				$user = $this->User->findById($id);
			}
		}
		if(isset($this->params['url']['undo'])){
			if($this->params['url']['undo']/1111 == $id){
				$user['User']['dbstorage'] = 1;
				$_SESSION['loggedInUser']['User']['dbstorage'] = $user['User']['dbstorage'];
				$this->User->save($user);
				$user = $this->User->findById($id);
			}
		}
		
		$tsumegos = $this->SetConnection->find('all');
		$uts = $this->TsumegoStatus->find('all', array('order' => 'created DESC', 'conditions' =>  array('user_id' => $id)));
		$tsumegoDates = array();
		
		$setKeys = array();
		$setArray = $this->Set->find('all', array('conditions' => array('public' => 1)));
		for($i=0; $i<count($setArray); $i++)
			$setKeys[$setArray[$i]['Set']['id']] = $setArray[$i]['Set']['id'];
		
		$scs = array();
		for($j=0; $j<count($tsumegos); $j++){
			if(isset($setKeys[$tsumegos[$j]['SetConnection']['set_id']])){
				array_push($tsumegoDates, $tsumegos[$j]);
			}
		}
		$tsumegoNum = count($tsumegoDates);
		$solvedUts = array();
		$lastYear = date('Y-m-d', strtotime('-1 year'));
		$dNum = 0;
		
		for($j=0; $j<count($uts); $j++){
			$date = new DateTime($uts[$j]['TsumegoStatus']['created']);
			$uts[$j]['TsumegoStatus']['created'] = $date->format('Y-m-d');
			if($uts[$j]['TsumegoStatus']['status']=='S' || $uts[$j]['TsumegoStatus']['status']=='W' || $uts[$j]['TsumegoStatus']['status']=='C'){
				$oldest = new DateTime(date('Y-m-d', strtotime('-30 days')));
				if($uts[$j]['TsumegoStatus']['created']>$oldest->format('Y-m-d'))
					array_push($solvedUts, $uts[$j]);
			}	
			if($uts[$j]['TsumegoStatus']['created']<$lastYear)
				$dNum++;
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
		
		$oldest = new DateTime(date('Y-m-d', strtotime('-30 days')));
		$oldest = $oldest->format('Y-m-d');
		$ta = $this->TsumegoAttempt->find('all', array('limit' => 400, 'order' => 'created DESC', 'conditions' => array(
			'user_id' => $_SESSION['loggedInUser']['User']['id']
		)));
		
		$taBefore = '';
		$graph = array();
		$highestElo = 0;
		$ta2['date'] = array();
		$ta2['elo'] = array();
		
		for($i=0;$i<count($ta);$i++){
			if($ta[$i]['TsumegoAttempt']['elo']!=null){
				if($ta[$i]['TsumegoAttempt']['elo']>$highestElo)
					$highestElo = $taBefore=$ta[$i]['TsumegoAttempt']['elo'];
				array_push($ta2['date'], $ta[$i]['TsumegoAttempt']['created']);
				array_push($ta2['elo'], $ta[$i]['TsumegoAttempt']['elo']);
			}
			if($ta[$i]['TsumegoAttempt']['mode']==1 || $ta[$i]['TsumegoAttempt']['mode']==0){
				$ta[$i]['TsumegoAttempt']['created'] = new DateTime(date($ta[$i]['TsumegoAttempt']['created']));
				$ta[$i]['TsumegoAttempt']['created'] = $ta[$i]['TsumegoAttempt']['created']->format('Y-m-d');
				if($ta[$i]['TsumegoAttempt']['created']>=$oldest){
					if($taBefore==$ta[$i]['TsumegoAttempt']['created']){
						if($ta[$i]['TsumegoAttempt']['solved']==1)
							$graph[$ta[$i]['TsumegoAttempt']['created']]['s']++;
						else
							$graph[$ta[$i]['TsumegoAttempt']['created']]['f']++;
						$testCounter++;
					}else{
						$graph[$ta[$i]['TsumegoAttempt']['created']] = array();
						if($ta[$i]['TsumegoAttempt']['solved']==1){
							$graph[$ta[$i]['TsumegoAttempt']['created']]['s'] = 1;
							$graph[$ta[$i]['TsumegoAttempt']['created']]['f'] = 0;
						}else{
							$graph[$ta[$i]['TsumegoAttempt']['created']]['s'] = 0;
							$graph[$ta[$i]['TsumegoAttempt']['created']]['f'] = 1;
						}
						$taBefore=$ta[$i]['TsumegoAttempt']['created'];
					}
				}
			}
		}
		
		$eloRank = $this->getTsumegoRank($_SESSION['loggedInUser']['User']['elo_rating_mode']);
		$highestEloRank = $this->getTsumegoRank($highestElo);
		
		if($highestElo<$user['User']['elo_rating_mode'])
			$highestElo = $user['User']['elo_rating_mode'];
		
		$timeGraph = array();
		$ro = $this->RankOverview->find('all', array('order' => 'rank ASC', 'conditions' => array(
			'user_id' => $_SESSION['loggedInUser']['User']['id']
		)));
		$highestRo = '15k';
		for($i=0;$i<count($ro);$i++){
			$highestRo = $this->getHighestRo($ro[$i]['RankOverview']['rank'], $highestRo);
			if(isset($timeGraph[$ro[$i]['RankOverview']['rank']][$ro[$i]['RankOverview']['status']]))
				$timeGraph[$ro[$i]['RankOverview']['rank']][$ro[$i]['RankOverview']['status']]++;
			else
				$timeGraph[$ro[$i]['RankOverview']['rank']][$ro[$i]['RankOverview']['status']] = 1;
		}
		$timeGraph = $this->formatTimegraph($timeGraph);
		
		$p = $user['User']['solved']/$tsumegoNum*100;
		$p = round($p);
		if($p==100 && $user['User']['solved']<$tsumegoNum)
			$p = 99;
		if($p>100)
			$p = 100;
		
		$deletedProblems = 1;
		if(isset($this->params['url']['delete-uts'])){
			if($this->params['url']['delete-uts']=='true' && $p>=75){
				for($j=0; $j<count($uts); $j++){
					if($uts[$j]['TsumegoStatus']['created']<$lastYear) $this->TsumegoStatus->delete($uts[$j]['TsumegoStatus']['id']);
				}
				$deletedProblems = 2;
				$utx = $this->TsumegoStatus->find('all', array('conditions' => array('user_id' => $id)));
				$correctCounter = 0;
				for($j=0; $j<count($utx); $j++){
					if($utx[$j]['TsumegoStatus']['status']=='S' || $utx[$j]['TsumegoStatus']['status']=='W' || $utx[$j]['TsumegoStatus']['status']=='C'){
						$correctCounter++;
					}	
				}
				
				$user['User']['solved'] = $correctCounter;
				$user['User']['dbstorage'] = 99;
				$this->User->save($user);
				
				$p = $user['User']['solved']/$tsumegoNum*100;
				$p = round($p);
				if($p==100 && $user['User']['solved']<$tsumegoNum) $p = 99;
				if($p>100) $p = 100;
			}
		}
		
		if($_SESSION['loggedInUser']['User']['id']!=$id)
			$deletedProblems = 3;
		
		for($i=0; $i<count($as); $i++){
			$as[$i]['AchievementStatus']['a_title'] = $ach[$as[$i]['AchievementStatus']['achievement_id']-1]['Achievement']['name'];
			$as[$i]['AchievementStatus']['a_description'] = $ach[$as[$i]['AchievementStatus']['achievement_id']-1]['Achievement']['description'];
			$as[$i]['AchievementStatus']['a_image'] = $ach[$as[$i]['AchievementStatus']['achievement_id']-1]['Achievement']['image'];
			$as[$i]['AchievementStatus']['a_color'] = $ach[$as[$i]['AchievementStatus']['achievement_id']-1]['Achievement']['color'];
			$as[$i]['AchievementStatus']['a_id'] = $ach[$as[$i]['AchievementStatus']['achievement_id']-1]['Achievement']['id'];
			$as[$i]['AchievementStatus']['a_xp'] = $ach[$as[$i]['AchievementStatus']['achievement_id']-1]['Achievement']['xp'];
		}
		
		$achievementUpdate = array();
		$achievementUpdate1 = $this->checkLevelAchievements();
		$achievementUpdate2 = $this->checkProblemNumberAchievements();
		$achievementUpdate = array_merge($achievementUpdate1, $achievementUpdate2);
		
		if(count($achievementUpdate)>0) $this->updateXP($_SESSION['loggedInUser']['User']['id'], $achievementUpdate);
		$aNum = $this->AchievementStatus->find('all', array('conditions' => array('user_id' => $_SESSION['loggedInUser']['User']['id'])));
		$asx = $this->AchievementStatus->find('first', array('conditions' => array('user_id' => $id, 'achievement_id' => 46)));
		$aNumx = count($aNum);
		if($asx!=null)
			$aNumx = $aNumx + $asx['AchievementStatus']['value'] - 1;
		
		$countGraph = 160+count($graph)*25;
		$countTimeGraph = 160+count($timeGraph)*25;
		
		$user['User']['name'] = $this->checkPicture($user);

		if(substr($_SESSION['loggedInUser']['User']['email'],0,3)=='g__' && $_SESSION['loggedInUser']['User']['external_id']!=null)
			$user['User']['email'] = substr($_SESSION['loggedInUser']['User']['email'],3);

		$aCount = $this->Achievement->find('all');

		$this->set('ta2', $ta2);
		$this->set('xpSum', $sumx);
		$this->set('graph', $graph);
		$this->set('countGraph', $countGraph);
		$this->set('timeGraph', $timeGraph);
		$this->set('countTimeGraph', $countTimeGraph);
		$this->set('timeModeRuns', count($ro));
		$this->set('user', $user);
		$this->set('tsumegoNum', $tsumegoNum);
		$this->set('solved', $user['User']['solved']);
		$this->set('p', $p);
		$this->set('dNum', $dNum);
		$this->set('allUts', $uts);
		$this->set('deletedProblems', $deletedProblems);
		$this->set('hideEmail', $hideEmail);
		$this->set('as', $as);
		$this->set('achievementUpdate', $achievementUpdate);
		$this->set('solvedUts2', $solvedUts2);
		$this->set('highestElo', $highestElo);
		$this->set('highestEloRank', $highestEloRank);
		$this->set('eloRank', $eloRank);
		$this->set('highestRo', $highestRo);
		$this->set('aNum', $aNumx);
		$this->set('aCount', $aCount);
	}
	
	private function formatTimegraph($graph){
		$g = array();
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
		$g2 = array();
		foreach ($g as $key => $value)	
			if($g[$key]!=0)
				$g2[$key] = $value;
		return $g2;
	}
	
	private function getHighestRo($new, $old){
		$newNum = 23;
		$oldNum = 23;
		$a = array();
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
		for($i=0;$i<count($a);$i++){
			if($a[$i]==$new)
				$newNum = $i;
			if($a[$i]==$old)
				$oldNum = $i;
		}
		if($newNum<$oldNum)
			return $new;
		return $old;
	}
	
	public function donate($id = null){
		$_SESSION['page'] = 'home';
		$_SESSION['title'] = 'Tsumego Hero - Upgrade';

		$overallCounter = 0;
		$sandboxSets = $this->Set->find('all', array('conditions' => array('public' => 0)));
		for($i=0; $i<count($sandboxSets); $i++){
			$ts = $this->findTsumegoSet($sandboxSets[$i]['Set']['id']);
			$overallCounter += count($ts);
		}

		$setsWithPremium = array();
		$tsumegosWithPremium = array();
		$swp = $this->Set->find('all', array('conditions' => array('premium' => 1)));
		for($i=0;$i<count($swp);$i++){
			array_push($setsWithPremium, $swp[$i]['Set']['id']);
			$twp = $this->findTsumegoSet($swp[$i]['Set']['id']);
			for($j=0;$j<count($twp);$j++)
				array_push($tsumegosWithPremium, $twp[$j]);
		}

		$this->set('id', $id);
		$this->set('overallCounter', $overallCounter);
		$this->set('premiumSets', $swp);
		$this->set('premiumTsumegos', count($tsumegosWithPremium));
	}
	
	public function authors(){
		$this->LoadModel('Comment');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		
		$_SESSION['page'] = 'about';
		$_SESSION['title'] = 'Tsumego Hero - About';
		
		$authors = $this->Tsumego->find('all', array('order' => 'created DESC', 'conditions' =>  array(
			'NOT' => array(
				'author' => array('Joschka Zimdars')
			)
		)));
		$set = $this->Set->find('all');
		$setMap = array();
		$setMap2 = array();
		for($i=0; $i<count($set); $i++){
			$divider = ' ';
			$setMap[$set[$i]['Set']['id']] = $set[$i]['Set']['title'].$divider.$set[$i]['Set']['title2'];
			$setMap2[$set[$i]['Set']['id']] = $set[$i]['Set']['public'];
		}
		
		$count = array();
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
	
	public function success($id = null){
		$_SESSION['page'] = 'home';
		$_SESSION['title'] = 'Tsumego Hero - Success';
		
		$s = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
		$s['User']['reward'] = date('Y-m-d H:i:s');
		$s['User']['premium'] = 1;
		$this->User->create();
		$this->User->save($s);
		
		$Email = new CakeEmail();
		$Email->from(array('me@joschkazimdars.com' => 'https://tsumego-hero.com'));
		$Email->to('joschka.zimdars@googlemail.com');
		$Email->subject('Upgrade');
		if(isset($_SESSION['loggedInUser']['User']['id'])) $ans = $_SESSION['loggedInUser']['User']['name'].' '.$_SESSION['loggedInUser']['User']['email'];
		else $ans = 'no login';
		$Email->send($ans);
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			$Email = new CakeEmail();
			$Email->from(array('me@joschkazimdars.com' => 'https://tsumego-hero.com'));
			$Email->to($_SESSION['loggedInUser']['User']['email']);
			$Email->subject('Tsumego Hero');
			$ans = '
Hello '.$_SESSION['loggedInUser']['User']['name'].',
			
Thank you!. Your account should be upgraded automatically. 

-- 
Best Regards
Joschka Zimdars';
			$Email->send($ans);
		}
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
	
	public function logout(){
		unset($_SESSION['loggedInUser']);
		//$this->redirect( '/sets' );
		//$_SESSION['redirect'] = 'sets';
		//$this->Session->setFlash(__('You have singed out.', true));
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
	
	private function validateLogin2($data){
		$u = $this->User->findByEmail($data['User']['email']);
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
		$this->loadModel('TsumegoStatus');
		$ux = $this->User->find('all', array('limit' => 300, 'order' => 'created DESC'));
		$u = array();
		for($i=0; $i<count($ux); $i++){
			$uts1 = $this->TsumegoStatus->find('first', array('conditions' => array('user_id' => $ux[$i]['User']['id'])));
			$date = new DateTime($ux[$i]['User']['created']);
			$date = $date->format('Y-m-d');
			$u[$i][0] = $ux[$i]['User']['id'];
			$u[$i][1] = $date;					
		}
		$this->set('u', $u);
	}
	
	//visualization and set difficulty
	public function tsumego_score(){
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('PurgeList');
		$this->loadModel('SetConnection');
		
		$pl = $this->PurgeList->find('first', array('order' => 'id DESC'));
		$pl['PurgeList']['set_scores'] = date('Y-m-d H:i:s');
		$this->PurgeList->save($pl);
		
		$sets = $this->Set->find('all', array('conditions' => array('public' => 1)));
		$xxx = array();
		for($i=0; $i<count($sets); $i++){
			$xxx[$i] = array();
			$tsx = $this->findTsumegoSet($sets[$i]['Set']['id']);
			
			for($j=0; $j<count($tsx); $j++){
				$tsx[$j]['Tsumego']['set'] = $sets[$i]['Set']['title'];
				$tsx[$j]['Tsumego']['setid'] = $sets[$i]['Set']['id'];
				$tsx[$j]['Tsumego']['multiplier'] = $sets[$i]['Set']['multiplier'];
				array_push($xxx[$i], $tsx[$j]);
			}
		}
		$newTs2 = array();
		$newTs3 = array();
		$jc = 0;
		
		$avg = array();
		$avg[10] = 83.139212328767;
		$avg[20] = 73.936962406015;
		$avg[30] = 63.582480620155;
		$avg[40] = 57.21416496945;
		$avg[50] = 52.61221;
		$avg[60] = 44.959153094463;
		$avg[70] = 36.729862258953;
		$avg[80] = 31.031111111111;
		$avg[90] = 24.950833333333;
		
		$setPercent = array();
		$setCount = array();
		$setDifficulty = array();
		for($i=0; $i<count($xxx); $i++){
			$jc = 0;
			$sp = 0;
			$sc = 0;
			for($k=0; $k<count($xxx[$i]); $k++){
				$distance = array();
				
				$sp += $xxx[$i][$k]['Tsumego']['elo_rating_mode'];
				$sc ++;
				
				for($l=0; $l<9; $l++){
					$xp = ($l+1)*10;
					$distance[$l] = $xxx[$i][$k]['Tsumego']['userWin'] - $avg[$xp];
					if($distance[$l]<0) $distance[$l]*=-1;
				}
				$lowest = 100;
				$pos = 0;
				for($j=0; $j<count($distance); $j++){
					if($distance[$j]<$lowest){
						$pos = $j;
						$lowest = $distance[$j];
					}
				}
				
				$newTs3['id'][$i][$jc] = $xxx[$i][$k]['Tsumego']['id'];
				$newTs3['count'][$i][$jc] = $xxx[$i][$k]['Tsumego']['userLoss'];
				$newTs3['percent'][$i][$jc] = $xxx[$i][$k]['Tsumego']['userWin'];
				$newTs3['set'][$i][$jc] = $xxx[$i][$k]['Tsumego']['set'];
				$newTs3['setid'][$i][$jc] = $xxx[$i][$k]['Tsumego']['setid'];
				$newTs3['num'][$i][$jc] = $xxx[$i][$k]['Tsumego']['num'];
				$newTs3['xp'][$i][$jc] = $xxx[$i][$k]['Tsumego']['part_increment'];
				$newTs3['newxp'][$i][$jc] = ($pos+1)*10;
				$newTs3['multiplier'][$i][$jc] = $xxx[$i][$k]['Tsumego']['multiplier'];
				$newTs3['multiplied'][$i][$jc] = ceil($xxx[$i][$k]['Tsumego']['multiplier']*$newTs3['newxp'][$i][$jc]);
				
				
				/*
				if($newTs3['setid'][$i][$jc]==104){
					$tsu = $this->Tsumego->findById($newTs3['id'][$i][$jc]);
					$tsu['Tsumego']['difficulty'] = $newTs3['multiplied'][$i][$jc];
					$this->Tsumego->save($tsu);
				}
				*/
				$jc++;
			}
			$setCount[$i] = $sc;
			$setPercent[$i] = round($sp/$sc,2);
			//echo '<pre>'; print_r($setPercent); echo '</pre>';
			$distance = array();
			for($l=0; $l<9; $l++){
				$xp = ($l+1)*10;
				$distance[$l] = $setPercent[$i] - $avg[$xp];
				if($distance[$l]<0) $distance[$l]*=-1;
			}
			$lowest = 100;
			$pos = 0;
			for($j=0; $j<count($distance); $j++){
				if($distance[$j]<$lowest){
					$pos = $j;
					$lowest = $distance[$j];
				}
			}
			
			$setDifficulty[$i] = round($setPercent[$i]);
			//$setDifficulty[$i] = $pos+1;
		}
		
		for($i=0; $i<count($newTs3['id']); $i++){
			array_multisort($newTs3['num'][$i], $newTs3['set'][$i], $newTs3['percent'][$i], $newTs3['id'][$i], $newTs3['xp'][$i], $newTs3['newxp'][$i], $newTs3['count'][$i], $newTs3['multiplier'][$i], $newTs3['multiplied'][$i], $newTs3['setid'][$i]);
		}
		
		for($i=0; $i<count($setDifficulty); $i++){
			//echo $newTs3['setid'][$i][0].':'.$setDifficulty[$i].'<br>';
			$s = $this->Set->findById($newTs3['setid'][$i][0]);
			$s['Set']['difficulty'] = $setDifficulty[$i];
			$this->Set->save($s);
		}
		
		$this->set('t', $t);
		$this->set('newTs3', $newTs3);
		$this->set('setPercent', $setPercent);
		$this->set('setCount', $setCount);
		$this->set('setDifficulty', $setDifficulty);
		$this->set('xxx', $xxx);
		$this->set('ur', $ur);
		$this->set('ratio', $ratio);
		$this->set('from', $from);
		$this->set('to', $to);
		$this->set('sets', $sets);
		$this->set('params', $this->params['url']['t']);
	}
	
	//percentages 0-100
	public function tsumego_score2(){
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$avg = array();
		$count= array();
		$ts2 = array();
		$tsx = array();
		$ts = $this->Tsumego->find('all', array('order' => 'userWin ASC'));
		for($i=0; $i<count($ts); $i++){
			$s = $this->Set->findById($ts[$i]['Tsumego']['set_id']);
			if($s['Set']['public']==1 && $ts[$i]['Tsumego']['userWin']!=0){
				$ts[$i]['Tsumego']['userWin'] = round($ts[$i]['Tsumego']['userWin']);
				array_push($ts2, $ts[$i]);
				array_push($tsx, $ts[$i]['Tsumego']['userWin']);
			}
		}
		
		$this->set('ts', $ts2);
		$this->set('tsx', $tsx);
	}
	
	//single score without set
	public function single_tsumego_score(){
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Tsumego');
		
		$ur = $this->TsumegoAttempt->find('all', array('order' => 'created DESC', 'limit' => 1000, 'conditions' =>  array('tsumego_id' => $this->params['url']['t'])));
		$t = $this->Tsumego->findById($this->params['url']['t']);
		
		$ratio = array();
		$ratio['s'] = 0;
		$ratio['f'] = 0;
		for($i=0; $i<count($ur); $i++){
			if($ur[$i]['TsumegoAttempt']['solved'] == 'S' || $ur[$i]['TsumegoAttempt']['solved'] == 1) $ratio['s']++;
			elseif($ur[$i]['TsumegoAttempt']['solved'] == 'F' || $ur[$i]['TsumegoAttempt']['solved'] == 0) $ratio['f']++;
		}
		$ratio['count'] = $ratio['s']+$ratio['f'];
		
		$ratio['percent'] = $ratio['s']/$ratio['count'];
		$ratio['percent']*=100;
		$ratio['percent'] = round($ratio['percent'],2);
		
		$this->set('t', $t);
		$this->set('ur', $ur);
		$this->set('ratio', $ratio);
	}
	
	//find average percentages of 10 to 90 xp 
	//look if no outliers
	public function avg_tsumego_score(){
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');
		$avg = array();
		$count= array();
		for($h=10; $h<=90; $h+=10){
			$ts = $this->Tsumego->find('all', array('conditions' =>  array('difficulty' => $h)));
			$ts2 = array();
			$counter = 0;
			$sum = 0;
		
			for($i=0; $i<count($ts); $i++){
				$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $ts[$i]['Tsumego']['id'])));$ts[$i]['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

				$s = $this->Set->findById($ts[$i]['Tsumego']['set_id']);
				
				if($s['Set']['public']==1 && $ts[$i]['Tsumego']['userWin']!=0){
					$counter++;
					$sum+=$ts[$i]['Tsumego']['userWin'];
					array_push($ts2, $ts[$i]);
				}
			}
			$avg[$h] = $sum/$counter;
			$count[$h] = $counter;
		}
		
		$tsx= array();
		$tsCount = $this->Tsumego->find('all', array('order' => 'difficulty ASC'));
		for($i=0; $i<count($tsCount); $i++){
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $tsCount[$i]['Tsumego']['id'])));$tsCount[$i]['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$s = $this->Set->findById($tsCount[$i]['Tsumego']['set_id']);
			if($s['Set']['public']==1){
				array_push($tsx, $tsCount[$i]['Tsumego']['difficulty']);
			}
		}
		
		/*
		$avg = array();
		$avg[10] = 83.139212328767;
		$avg[20] = 73.936962406015;
		$avg[30] = 63.582480620155;
		$avg[40] = 57.21416496945;
		$avg[50] = 52.61221;
		$avg[60] = 44.959153094463;
		$avg[70] = 36.729862258953;
		$avg[80] = 31.031111111111;
		$avg[90] = 24.950833333333;
		
		[10] => 87.382505764796
		[20] => 73.129444444444
		[30] => 64.567987288136
		[40] => 56.862570224719
		[50] => 52.17781420765
		[60] => 45.272835820896
		[70] => 37.944619771863
		[80] => 30.989898989899
		[90] => 21.294197002141
		*/
		
		$x1 = $this->Tsumego->find('all', array('order' => 'difficulty ASC', 'conditions' =>  array(
			'difficulty >' => 103
		)));
		/*
		for($i=0; $i<count($x1); $i++){
			$s = $this->Set->findById($x1[$i]['Tsumego']['set_id']);
			if($s['Set']['public']==1){
				array_push($xx, $x1[$i]);
			}
		}
		*/
		$this->set('ts', $ts2);
		$this->set('tsx', $tsx);
		$this->set('avg', $avg);
		$this->set('count', $count);
		$this->set('x1', $x1);
	}
	
	//score by avg distance
	public function set_single_tsumego_score(){
		$this->loadModel('Tsumego');
		
		$avg = array();
		$avg[10] = 87.382505764796;
		$avg[20] = 73.129444444444;
		$avg[30] = 64.567987288136;
		$avg[40] = 56.862570224719;
		$avg[50] = 52.194473324213;
		$avg[60] = 45.272835820896;
		$avg[70] = 37.944619771863;
		$avg[80] = 31.759309210526;
		$avg[90] = 21.345405982906;
		
		$t = $this->Tsumego->findById(549);
		$distance = array();
		$lowest = 100;
		$pos = 0;
		for($i=10; $i<=90; $i+=10){
			$distance[$i] = $t['Tsumego']['userWin'] - $avg[$i];
			if($distance[$i]<0) $distance[$i]*=-1;
			if($distance[$i]<$lowest){
				$pos = $i;
				$lowest = $distance[$i];
			}
		}
		$t['Tsumego']['difficulty'] = $pos;
		$this->Tsumego->save($t);
		
		$this->set('ts', $ts2);
		$this->set('ts3', $ts3);
	}
	
	//all in one
	public function set_full_tsumego_scores(){
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Tsumego');
		$this->loadModel('PurgeList');
		$this->loadModel('Set');
		$this->loadModel('Sgf');
		
		$pl = $this->PurgeList->find('first', array('order' => 'id DESC'));
		$pl['PurgeList']['tsumego_scores'] = date('Y-m-d H:i:s');
		$this->PurgeList->save($pl);
		
		$from = $this->params['url']['t'];
		$to = $this->params['url']['t'] + 10;
		
		$hightestT = $this->Tsumego->find('first', array('order' => 'id DESC'));
		$hightestT++;
		$ts = $this->Tsumego->find('all', array('order' => 'id ASC', 'conditions' =>  array(
			'id >=' => $from,
			'id <' => $to
		)));
		$ts2 = $ts;
		
		for($i=0; $i<count($ts); $i++){
			/*
			$set = $this->Set->findById($ts[$i]['Tsumego']['set_id']);
			$sgf = array();
			$sgf['Sgf']['sgf'] = file_get_contents('6473k339312/'.$set['Set']['folder'].'/'.$ts[$i]['Tsumego']['num'].'.sgf');
			$sgf['Sgf']['user_id'] = 33;
			$sgf['Sgf']['tsumego_id'] = $ts[$i]['Tsumego']['id'];
			$sgf['Sgf']['version'] = 1.0;
			$this->Sgf->create();
			$this->Sgf->save($sgf);
			*/
			$ur = $this->TsumegoAttempt->find('all', array('order' => 'created DESC', 'limit' => 1000, 'conditions' =>  array('tsumego_id' => $ts[$i]['Tsumego']['id'])));
			$ratio = array();
			$ratio['s'] = 0;
			$ratio['f'] = 0;
			for($j=0; $j<count($ur); $j++){
				if($ur[$j]['TsumegoAttempt']['solved'] == 'S' || $ur[$j]['TsumegoAttempt']['solved'] == 1) $ratio['s']++;
				elseif($ur[$j]['TsumegoAttempt']['solved'] == 'F' || $ur[$j]['TsumegoAttempt']['solved'] == 0) $ratio['f']++;
			}
			$ts[$i]['Tsumego']['solved'] = $ratio['s'];
			$ts[$i]['Tsumego']['failed'] = $ratio['f'];
			$count = $ts[$i]['Tsumego']['solved']+$ts[$i]['Tsumego']['failed'];
			$percent = $ts[$i]['Tsumego']['solved']/$count;
			$percent*=100;
			$percent = round($percent,2);
			$ts[$i]['Tsumego']['userWin'] = $percent;
			$ts[$i]['Tsumego']['userLoss'] = $count;
			
			$newXp = 110-round($ts[$i]['Tsumego']['userWin']);
			$ts[$i]['Tsumego']['difficultyOld'] = $ts[$i]['Tsumego']['difficulty'];
			$ts[$i]['Tsumego']['difficulty'] = $newXp;
			
			if($percent>=1 && $percent<=23) $ts[$i]['Tsumego']['elo_rating_mode'] = 2500;//$tRank='5d';
			elseif($percent<=26) $ts[$i]['Tsumego']['elo_rating_mode'] = 2400;//$tRank='4d';
			elseif($percent<=29) $ts[$i]['Tsumego']['elo_rating_mode'] = 2300;//$tRank='3d';
			elseif($percent<=32) $ts[$i]['Tsumego']['elo_rating_mode'] = 2200;//$tRank='2d';
			elseif($percent<=35) $ts[$i]['Tsumego']['elo_rating_mode'] = 2100;//$tRank='1d';
			elseif($percent<=38) $ts[$i]['Tsumego']['elo_rating_mode'] = 2000;//$tRank='1k';
			elseif($percent<=42) $ts[$i]['Tsumego']['elo_rating_mode'] = 1900;//$tRank='2k';
			elseif($percent<=46) $ts[$i]['Tsumego']['elo_rating_mode'] = 1800;//$tRank='3k';
			elseif($percent<=50) $ts[$i]['Tsumego']['elo_rating_mode'] = 1700;//$tRank='4k';
			elseif($percent<=55) $ts[$i]['Tsumego']['elo_rating_mode'] = 1600;//$tRank='5k';
			elseif($percent<=60) $ts[$i]['Tsumego']['elo_rating_mode'] = 1500;//$tRank='6k';
			elseif($percent<=65) $ts[$i]['Tsumego']['elo_rating_mode'] = 1400;//$tRank='7k';
			elseif($percent<=70) $ts[$i]['Tsumego']['elo_rating_mode'] = 1300;//$tRank='8k';
			elseif($percent<=75) $ts[$i]['Tsumego']['elo_rating_mode'] = 1200;//$tRank='9k';
			elseif($percent<=80) $ts[$i]['Tsumego']['elo_rating_mode'] = 1100;//$tRank='10k';
			elseif($percent<=85) $ts[$i]['Tsumego']['elo_rating_mode'] = 1000;//$tRank='11k';
			else $ts[$i]['Tsumego']['elo_rating_mode'] = 900;
			
			$this->Tsumego->save($ts[$i]);
		}
		$this->set('ts', $ts);
		$this->set('ts2', $ts2);
		$this->set('ur', $ur);
		$this->set('from', $from);
		$this->set('to', $to);
		$this->set('hightestT', $hightestT);
		$this->set('params', $this->params['url']['t']);
	}
	//set solved, failed
	//users/set_tsumego_scores?t=0
	public function set_tsumego_scores(){
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Tsumego');
		
		$from = $this->params['url']['t'];
		$to = $this->params['url']['t'] + 10;
		
		
		$ts = $this->Tsumego->find('all', array('order' => 'id ASC', 'conditions' =>  array(
			'id >' => $from,
			'id <=' => $to
		)));
		
		for($i=0; $i<count($ts); $i++){
			$ur = $this->TsumegoAttempt->find('all', array('order' => 'created DESC', 'limit' => 1000, 'conditions' =>  array('tsumego_id' => $ts[$i]['Tsumego']['id'])));
			$ratio = array();
			$ratio['s'] = 0;
			$ratio['f'] = 0;
			for($j=0; $j<count($ur); $j++){
				if($ur[$j]['TsumegoAttempt']['status'] == 'S' || $ur[$j]['TsumegoAttempt']['solved'] == 1) $ratio['s']++;
				elseif($ur[$j]['TsumegoAttempt']['status'] == 'F' || $ur[$j]['TsumegoAttempt']['solved'] == 0) $ratio['f']++;
			}
			$ts[$i]['Tsumego']['solved'] = $ratio['s'];
			$ts[$i]['Tsumego']['failed'] = $ratio['f'];
			$this->Tsumego->save($ts[$i]);
		}
		$t = $ts[count($ts)-1];
		$this->set('t', $t);
		$this->set('ts', $ts);
		$this->set('ur', $ur);
		$this->set('from', $from);
		$this->set('to', $to);
		$this->set('params', $this->params['url']['t']);
	}
	
	//set userWin(%), userLoss(count)
	public function set_tsumego_scores2(){
		$this->loadModel('Tsumego');
		
		$ts = $this->Tsumego->find('all');
		
		for($i=0; $i<count($ts); $i++){
			$count = $ts[$i]['Tsumego']['solved']+$ts[$i]['Tsumego']['failed'];
			$percent = $ts[$i]['Tsumego']['solved']/$count;
			$percent*=100;
			$percent = round($percent,2);
			$ts[$i]['Tsumego']['userWin'] = $percent;
			$ts[$i]['Tsumego']['userLoss'] = $count;
			$this->Tsumego->save($ts[$i]);
		}
	}
	
	//distance to avg, save closest
	public function set_tsumego_scores3(){
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');
		
		$avg = array();
		$avg[10] = 87.382505764796;
		$avg[20] = 73.129444444444;
		$avg[30] = 64.567987288136;
		$avg[40] = 56.862570224719;
		$avg[50] = 52.194473324213;
		$avg[60] = 45.272835820896;
		$avg[70] = 37.944619771863;
		$avg[80] = 31.759309210526;
		$avg[90] = 21.345405982906;
		
		$ts2 = array();
		$ts = $this->Tsumego->find('all');
		for($i=0; $i<count($ts); $i++){
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $ts[$i]['Tsumego']['id'])));$ts[$i]['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$s = $this->Set->findById($ts[$i]['Tsumego']['set_id']);
			if($s['Set']['public']==1 && $ts[$i]['Tsumego']['userWin']!=0){
				array_push($ts2, $ts[$i]);
			}
		}
		
		for($i=0; $i<count($ts2); $i++){
			$distance = array();
			$lowest = 100;
			$pos = 0;
			for($j=10; $j<=90; $j+=10){
				$distance[$j] = $ts2[$i]['Tsumego']['userWin'] - $avg[$j];
				if($distance[$j]<0) $distance[$j]*=-1;
				if($distance[$j]<$lowest){
					$pos = $j;
					$lowest = $distance[$j];
				}
			}
			$ts2[$i]['Tsumego']['difficulty'] = $pos;
			$this->Tsumego->save($ts2[$i]);
		}
		

		$this->set('distance', $distance);
		$this->set('avg', $avg);
		$this->set('pos', $pos);
		$this->set('lowest', $lowest);
		$this->set('ts2', $ts2);
	}
	
	public function activeuts(){ //count active uts
		$this->loadModel('TsumegoStatus');
		$ux = $this->User->find('all', array('order' => 'created DESC'));
		$u = array();
		for($i=0; $i<count($ux); $i++){
			if($ux[$i]['User']['dbstorage']==1){
				$uts = $this->TsumegoStatus->find('all', array('conditions' => array('user_id' => $ux[$i]['User']['id'])));
				array_push($u, count($uts));
			}
		}
		$this->set('u', $u);
	}
	
	public function cleanuts(){
		$this->loadModel('User');
		$this->loadModel('TsumegoStatus');
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
			$uts = $this->TsumegoStatus->find('first', array('conditions' => array('user_id' => $all[$i]['User']['id'])));
			array_push($u, $all[$i]);
		}
		
		$this->set('u', $u);
	}
	
	public function cleanuts2(){
		$this->loadModel('User');
		$this->loadModel('TsumegoStatus');
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
			$uts = $this->TsumegoStatus->find('all', array('conditions' => array('user_id' => $all[$i]['User']['id'])));
			$outs = $this->OldTsumegoStatus->find('all', array('conditions' => array('user_id' => $all[$i]['User']['id'])));
			
			$idMap = array();
			$status = array();
			
			for($i=0; $i<count($uts); $i++){
				array_push($idMap, $uts[$i]['TsumegoStatus']['tsumego_id']);
			}
			$result = array_unique(array_diff_assoc($idMap, array_unique($idMap)));
			if(count($result)==0) $all[$i]['User']['x'] = 'clean';
			else $all[$i]['User']['x'] = 'duplicates';
			*/
			
			array_push($u, $all[$i]);
		}
		
		$this->set('u', $u);
	}
	
	private function ratingMatch2($d){
		if($d==1){
			return 900;
		}elseif($d==2){
			return 1200;
		}elseif($d==3){
			return 1500;
		}elseif($d==4){	
			return 1750;
		}elseif($d==5){
			return 1950;
		}elseif($d==6){
			return 2150;
		}elseif($d==7){
			return 2350;
		}elseif($d==8){
			return 2500;
		}elseif($d==9){
			return 2600;
		}
		return 1750;
	}
	
	public function playerdb6(){ //update solved
		$this->loadModel('TsumegoStatus');
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
			$ut = $this->TsumegoStatus->find('all', array('conditions' => array(
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
			$u['User']['solved'] = count($ut);
			$c['new'] = $ux[$i]['User']['solved'];
			$this->User->save($u);
			
			array_push($u, $c);
		}
		
		$this->set('c', $u);
	}
	
	public function purgesingle($id){
		$this->loadModel('Purge');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('SetConnection');
		$ux = $this->User->findById($id);
		$ut = $this->TsumegoStatus->find('all', array('conditions' => array('user_id' => $id)));
		$st2 = $this->Set->find('all', array('conditions' => array('public' => 1)));
		$uty = array();
		$keep = 0;
		$deleted = 0;
		$all = count($ut);
		for($i=0; $i<count($ut); $i++){
			array_push($uty, $ut[$i]['TsumegoStatus']['tsumego_id']);
		}
		for($i=0; $i<count($ut); $i++){
			$t = $this->Tsumego->findById($ut[$i]['TsumegoStatus']['tsumego_id']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$isMain = false;
			for($j=0; $j<count($st2); $j++){
				if($t['Tsumego']['set_id']==$st2[$j]['Set']['id']) $isMain = true;
			}
			if($isMain)	$keep++;
			else{
				//$this->TsumegoStatus->delete($ut[$i]['TsumegoStatus']['id']);
				$deleted++;
			}	
		}
		$a = array_unique(array_diff_assoc($uty, array_unique($uty)));
		$a = array_values($a);
		$b = array();
		
		$this->Purge->create();
		$answer = array();
		$answer['Purge']['user_id'] = $id;
		$answer['Purge']['duplicates'] = '|';
		for($i=0; $i<count($a); $i++){
			$utd = $this->TsumegoStatus->find('all', array('conditions' => array('user_id' => $id, 'tsumego_id' => $a[$i])));
			$c = count($utd);
			$j = 0;
			$b[$i]['uid'] = $id;
			$b[$i]['tid'] = $a[$i];
			$b[$i]['count'] = 1;
			$answer['Purge']['duplicates'] .= $b[$i]['tid'].'-';
			while($c>1){
				$this->TsumegoStatus->delete($utd[$j]['TsumegoStatus']['id']);
				$b[$i]['count']++;
				if($c==2) $answer['Purge']['duplicates'] .= $b[$i]['count'].'|';
				$c--;
				$j++;
			}
		}
		$answer['Purge']['pre'] = $all;
		$answer['Purge']['after'] = $keep;
		$this->Purge->save($answer);
		
		$this->set('a', $b);
		$this->set('uty', $uty);
	}
	
	private function countsingle($id){
		$this->loadModel('Purge');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$u = $this->User->findById($id);
		$ut = $this->TsumegoStatus->find('all', array('conditions' => array('user_id' => $id)));
		$correctCounter1 = 0;
		for($j=0; $j<count($ut); $j++){
			if($ut[$j]['TsumegoStatus']['status']=='S' || $ut[$j]['TsumegoStatus']['status']=='W' || $ut[$j]['TsumegoStatus']['status']=='C'){
				$correctCounter1++;
			}	
		}
		$sum = $correctCounter1;
		$u['User']['solved'] = $sum;
		//$u['User']['elo_rating_mode'] = 100;
		$u['User']['readingTrial'] = 30;
		//$u['User']['mode'] = 1;
		$u['User']['health'] = $this->getHealth($u['User']['level']);
		$this->Purge->create();
		$p = array();
		$p['Purge']['user_id'] = $id;
		$p['Purge']['duplicates'] = '$'.$sum;
		$this->Purge->save($p);
		$this->User->save($u);
	}
	
	public function archivesingle($id){
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Purge');
		$ux = $this->User->findById($id);
		$p = array();
		$p['Purge']['user_id'] = $id;
		
		if($ux==null){
			$ux['User']['d2'] = 'null';
		}else{
			$d1 = date('Y-m-d', strtotime('-7 days'));
			$date = new DateTime($ux['User']['created']);
			$date = $date->format('Y-m-d');
			$ux['User']['d1'] = $date;
			if($date < $d1){
				$ux['User']['d2'] = 'archive';
				$c = count($this->TsumegoStatus->find('all', array('conditions' => array('user_id' => $ux['User']['id']))));
				if($c==0) $c = '';
				$p['Purge']['duplicates'] = '-'.$c;
			}else{
				$ux['User']['d2'] = 'ok';
				$p['Purge']['duplicates'] = '+';
			}
		}
		$this->Purge->create();
		$this->Purge->save($p);
		$this->set('ux', $ux);
	}
	
	public function purgelist(){
		$this->loadModel('Purge');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('PurgeList');
		
		$pl = $this->PurgeList->find('first', array('order' => 'id DESC'));
		$pl['PurgeList']['purge'] = date('Y-m-d H:i:s');
		$this->PurgeList->save($pl);
		
		$dbToken = $this->Purge->findById(3);
		$start = $dbToken['Purge']['user_id'];
		$u = $this->User->find('all', array('order' => 'id ASC'));
		$uCount = count($u)+50;
		if(isset($u[$start]['User']['id'])) $this->purgesingle($u[$start]['User']['id']);
		$dbToken['Purge']['user_id']++;
		$this->Purge->save($dbToken);
		if($start<$uCount) $this->set('stop', 'f');
		else $this->set('stop', 't');
		$this->set('x', $u[$start]['User']['id']);
		$this->set('s', $start);
		$this->set('u', $u[$start]);
		$this->set('uCount', $uCount);
	}
	
	public function countlist(){
		$this->loadModel('Purge');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('PurgeList');
		
		$pl = $this->PurgeList->find('first', array('order' => 'id DESC'));
		$pl['PurgeList']['count'] = date('Y-m-d H:i:s');
		$this->PurgeList->save($pl);
		
		$dbToken = $this->Purge->findById(2);
		$start = $dbToken['Purge']['user_id'];
		$u = $this->User->find('all', array('order' => 'id ASC'));
		$uCount = count($u)+50;
		if(isset($u[$start]['User']['id'])) $this->countsingle($u[$start]['User']['id']);
		$dbToken['Purge']['user_id']++;
		$this->Purge->save($dbToken);
		if($start<$uCount) $this->set('stop', 'f');
		else $this->set('stop', 't');
		$this->set('s', $start);
		$this->set('u', $u[$start]);
		$this->set('ux', $ux);
		$this->set('uCount', $uCount);
	}
	
	public function archivelist(){
		$this->loadModel('Purge');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('PurgeList');
		
		$pl = $this->PurgeList->find('first', array('order' => 'id DESC'));
		$pl['PurgeList']['archive'] = date('Y-m-d H:i:s');
		$this->PurgeList->save($pl);
		
		$dbToken = $this->Purge->findById(1);
		$start = $dbToken['Purge']['user_id'];
		$u = $this->User->find('all', array('order' => 'id ASC'));
		$uCount = count($u)+50;
		if(isset($u[$start]['User']['id'])) $this->archivesingle($u[$start]['User']['id']);
		$dbToken['Purge']['user_id']++;
		$this->Purge->save($dbToken);
		if($start<$uCount) $this->set('stop', 'f');
		else $this->set('stop', 't');
		$this->set('s', $start);
		$this->set('u', $u[$start]);
		$this->set('ux', $ux);
		$this->set('uCount', $uCount);
	}
	
	public function likesview(){
		$this->loadModel('Purge');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Set');
		$this->loadModel('Answer');
		$this->loadModel('Schedule');
		$this->loadModel('PurgeList');
		$this->loadModel('Tsumego');
		$this->loadModel('Reputation');
		$this->loadModel('SetConnection');
		
		$repPos = $this->Reputation->find('all', array('conditions' => array('value' => 1)));
		$repPos2 = array();
		$repPos3 = array();
		for($i=0; $i<count($repPos); $i++){
			$tx = $this->Tsumego->findById($repPos[$i]['Reputation']['tsumego_id']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $tx['Tsumego']['id'])));$tx['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$repPos[$i]['Reputation']['set_id'] = $tx['Tsumego']['set_id'];
			array_push($repPos2, $repPos[$i]['Reputation']['set_id']);
		}
		$repPos3 = array_count_values($repPos2);
		ksort($repPos3);
		
		$repNeg = $this->Reputation->find('all', array('conditions' => array('value' => -1)));
		$repNeg2 = array();
		$repNeg3 = array();
		for($i=0; $i<count($repNeg); $i++){
			$tx = $this->Tsumego->findById($repNeg[$i]['Reputation']['tsumego_id']);
			$repNeg[$i]['Reputation']['set_id'] = $tx['Tsumego']['set_id'];
			array_push($repNeg2, $repNeg[$i]['Reputation']['set_id']);
		}
		$repNeg3 = array_count_values($repNeg2);
		ksort($repNeg3);
		
		$repSets = array();
		$ii = 0;
		foreach ($repPos3 as $key => $value){
			$repSets[$ii] = array();
			$repSets[$ii]['set_id'] = $key;
			$repSets[$ii]['pos'] = $value;
			$repSets[$ii]['neg'] = 0;
			$ii++;
		}
		
		//echo '<pre>'; print_r($repPos3); echo '</pre>'; 
		//echo '<pre>'; print_r($repNeg3); echo '</pre>'; 
		
		foreach ($repNeg3 as $key => $value){
			$found = false;
			for($i=0; $i<count($repSets); $i++){
				if($repSets[$i]['set_id']==$key){
					$repSets[$i]['neg'] = $value;
					$found = true;
				}
			}
			if($found==false){
				$ax = array();
				$ax['set_id'] = $key;
				$ax['pos'] = 0;
				$ax['neg'] = $value;
				array_push($repSets, $ax);
			}
		}
		
		$as = array();
		for($i=0; $i<count($repSets); $i++){
			$sx = $this->Set->findById($repSets[$i]['set_id']);
			$repSets[$i]['set_name'] = $sx['Set']['title'].' '.$sx['Set']['title2'];
			array_push($as, $repSets[$i]['set_id']);
		}
		sort($as);
		
		$all = $this->Reputation->find('all', array('order' => 'created DESC'));
		for($i=0; $i<count($all); $i++){
			$allT = $this->Tsumego->findById($all[$i]['Reputation']['tsumego_id']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $allT['Tsumego']['id'])));$allT['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$all[$i]['Reputation']['num'] = $allT['Tsumego']['num'];
			
			$allS = $this->Set->findById($allT['Tsumego']['set_id']);
			$all[$i]['Reputation']['name'] = $allS['Set']['title'];
			
			$allU = $this->User->findById($all[$i]['Reputation']['user_id']);
			$all[$i]['Reputation']['user'] = $allU['User']['name'];
			
			if($all[$i]['Reputation']['value']==1) $all[$i]['Reputation']['value'] = 'like';
			else $all[$i]['Reputation']['value'] = 'dislike';
		}
		
		$this->set('all', $all);
		$this->set('repSets', $repSets);
		$this->set('as', $as);
		$this->set('repPos', $repPos);
		$this->set('repPos2', $repPos2);
		$this->set('repPos3', $repPos3);
		$this->set('repNeg', $repNeg);
		$this->set('repNeg2', $repNeg2);
		$this->set('repNeg3', $repNeg3);
	}
	
	public function i($id=null){
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('Reputation');
		$this->loadModel('SetConnection');
		
		$s = $this->Set->findById($id);
		$a = array();
		
		$r = $this->Reputation->find('all', array('order' => 'created DESC'));
		for($i=0;$i<count($r);$i++){
			$t = $this->Tsumego->findById($r[$i]['Reputation']['tsumego_id']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			if($t['Tsumego']['set_id']==$id) array_push($a, $r[$i]);
		}
		$likes = 0;
		$dislikes = 0;
		
		for($i=0;$i<count($a);$i++){
			if($a[$i]['Reputation']['value']==1) $likes++;
			else $dislikes++;
			
			$u = $this->User->findById($a[$i]['Reputation']['user_id']);
			$a[$i]['Reputation']['user'] = $u['User']['name'];
			
			$t = $this->Tsumego->findById($a[$i]['Reputation']['tsumego_id']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$a[$i]['Reputation']['tsumego'] = $s['Set']['title'].' '.$t['Tsumego']['num'];
			$a[$i]['Reputation']['set_id'] = $t['Tsumego']['set_id'];
		}
		$this->set('a', $a);
		$this->set('id', $id);
		$this->set('s', $s);
		$this->set('likes', $likes);
		$this->set('dislikes', $dislikes);
	}
	
	public function googlesignin(){
		$name = '';
		$email = '';
		$picture = '';
		$id_token = $_POST['credential'];
		$client_id = '842499094931-nt12l2fehajo4k7f39bb44fsjl0l4h6u.apps.googleusercontent.com';
		$token_info = file_get_contents("https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token);
		$token_data = json_decode($token_info, true);
		if (isset($token_data['aud']) && $token_data['aud'] == $client_id){
			$name = $token_data['name'];
			$email = $token_data['email'];
			$picture = $token_data['picture'];
		}else{
			echo "Invalid token";
		}
		$externalId = 'g__'.$token_data['sub'];
		$u = $this->User->find('first', array('conditions' => array('external_id' => $externalId)));
		if($u==null){
			$imageUrl = $picture;
			$imageContent = file_get_contents($imageUrl);
			
			$userData = array();
			$userData['User']['name'] = 'g__'.$name;
			$userData['User']['email'] = 'g__'.$email;
			$userData['User']['pw'] = 'k4y284t2w4v264z2a4t2h464h4x2m5x2t4v2';
			$userData['User']['pw2'] = 'k4y284t2w4v264z2a4t2h464h4x2m5x2t4v2';
			$userData['User']['external_id'] = $externalId;

			if ($imageContent === FALSE) {
				$userData['User']['picture'] = 'default.png';
			}else{
				$userData['User']['picture'] = $externalId.'.png';
				file_put_contents('img/google/'.$externalId.'.png', $imageContent);
			}
			$this->User->create();
			$this->User->save($userData, true);
			$u = $this->User->find('first', array('conditions' => array('external_id' => $externalId)));
		}
		$this->signIn($u);
		$_SESSION['redirect'] = 'sets';

		$this->set('name', $name);
		$this->set('email', $email);
		$this->set('picture', $picture);
	}

	public function fbsignin($id=null){
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
				// Get the access token from the request
				$input = json_decode(file_get_contents('php://input'), true);
				$accessToken = $input['accessToken'];

				// Your app credentials
				$app_id = '866506025665869';
				$app_secret = '6f7fd195f177db9fe30205fc52dba785';

				// Exchange the short-lived token for a long-lived one (optional)
				$url = "https://graph.facebook.com/oauth/access_token?"
						. "grant_type=fb_exchange_token&"
						. "client_id={$app_id}&"
						. "client_secret={$app_secret}&"
						. "fb_exchange_token={$accessToken}";

				$response = file_get_contents($url);
				$responseData = json_decode($response, true);

				// Get user info from Facebook
				$url = "https://graph.facebook.com/me?fields=id,name,email&access_token=" . $responseData['access_token'];
				$userInfo = file_get_contents($url);
				$userInfoData = json_decode($userInfo, true);

				// Handle login logic here, such as checking if the user exists in your database
				// and creating a session.

				echo json_encode($userInfoData); // Return user info as JSON
		} else {
				echo json_encode(['error' => 'Invalid request']);
		}
	}
	
	public function overview1(){
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('Comment');
		
		$test = $this->Comment->find('all', array(
			'order' => 'created DESC', 
			'conditions' =>  array(
				'status' => 0,
			)
		));
		
		
		//echo '<pre>'; print_r($test); echo '</pre>';
		
		$comments = $this->Comment->find('all', array(
			'order' => 'created DESC', 
			'conditions' =>  array(
				array(
					'NOT' => array('user_id' => 0),
					'NOT' => array('status' => 99)
				)
			)
		));
		$comments2 = array();
		$monthBack = date('Y-m-d', strtotime("-10 years"));
		for($i=0;$i<count($comments);$i++){
			$u = $this->User->findById($comments[$i]['Comment']['user_id']);
			$comments[$i]['Comment']['user'] = $u['User']['name'];
			$t = $this->Tsumego->findById($comments[$i]['Comment']['tsumego_id']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$comments[$i]['Comment']['tsumego'] = $s['Set']['title'].' '.$t['Tsumego']['num'];
			
			$date = new DateTime($comments[$i]['Comment']['created']);
			
			
			
			$comments[$i]['Comment']['created2'] = $date->format('Y-m-d');
			
			if($comments[$i]['Comment']['created2']>$monthBack && $comments[$i]['Comment']['user_id']!=0) array_push($comments2, $comments[$i]);
		}	
		$comments = $comments2;
		
		$users = array();
		$adminIds = array();
		
		for($i=0;$i<count($comments);$i++){
			array_push($users, $comments[$i]['Comment']['user']);
			array_push($adminIds, $comments[$i]['Comment']['admin_id']);
		}
		$adminIds = array_count_values($adminIds);
		
		$users = array_count_values($users);
		$uValue = array();
		$uName = array();
		foreach($users as $key=>$value){
			array_push($uValue, $value);
			array_push($uName, $key);
		}
		array_multisort($uValue, $uName);
		
		$u2['name'] = array();
		$u2['value'] = array();
		for($i=count($uName)-1;$i>=0;$i--){
			array_push($u2['name'], $uName[$i]);
			array_push($u2['value'], $uValue[$i]);
		}
		$this->set('users', $users);
		$this->set('u2', $u2);
		$this->set('comments', $comments);
	}
	
	public function purge(){
		$this->loadModel('Purge');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Set');
		$this->loadModel('Answer');
		$this->loadModel('Schedule');
		$this->loadModel('PurgeList');
		$this->loadModel('Tsumego');
		$this->loadModel('Reputation');
		
		$p = 0;
		$pl = $this->PurgeList->find('all', array('order' => 'id DESC', 'limit' => 3));
		
		if(isset($this->data['Schedule'])){
			$schedule = array();
			$st = $this->Tsumego->find('first', array('conditions' =>  array('set_id' => $this->data['Schedule']['set_id_from'], 'num' => $this->data['Schedule']['num'])));
			$schedule['Schedule']['tsumego_id'] = $st['Tsumego']['id'];
			$schedule['Schedule']['set_id'] = $this->data['Schedule']['set_id_to'];
			$schedule['Schedule']['date'] =  $this->data['Schedule']['date'];
			if(is_numeric($this->data['Schedule']['num'])){
				if($this->data['Schedule']['num']>0){
					$this->Schedule->save($schedule);
				}
			}
		}
		
		if(isset($this->params['url']['p'])){
			if($this->params['url']['p']==1){
				$p = $this->Purge->find('all');
				for($i=0; $i<count($p); $i++){
					if($p[$i]['Purge']['id']!=1 && $p[$i]['Purge']['id']!=2 && $p[$i]['Purge']['id']!=3){
						$this->Purge->delete($p[$i]['Purge']['id']);
					}else{
						$p[$i]['Purge']['user_id'] = 1;
						$this->Purge->save($p[$i]);
					}
				}
				$purgeList = array();
				$purgeList['start'] = date('Y-m-d H:i:s');
				$purgeList['empty_uts'] = 'in progress...';
				$purgeList['purge'] = 'in progress...';
				$purgeList['count'] = 'in progress...';
				$purgeList['archive'] = 'in progress...';
				$purgeList['tsumego_scores'] = 'in progress...';
				$purgeList['set_scores'] = 'in progress...';
				$this->PurgeList->create();
				$this->PurgeList->save($purgeList);
				$p=1;
			}
		}
		$s = $this->Set->find('all', array('conditions' => array('public' => 1)));
		$de = $this->Set->find('all', array('conditions' => array('public' => -1)));
		$in = $this->Set->find('all', array('conditions' => array('public' => 0)));
		
		$a = array();
		for($i=0; $i<count($in); $i++){
			array_push($a, $in[$i]['Set']['id']);
		}
		$in = $a;
		
		$a = array();
		for($i=0; $i<count($de); $i++){
			array_push($a, $de[$i]['Set']['id']);
		}
		$de = $a;
		
		$t = $this->getTsumegoOfTheDay();
		
		$ans = $this->Answer->find('all', array('limit' => 100, 'order' => 'created DESC'));
		$s = $this->Schedule->find('all', array('limit' => 100, 'order' => 'date DESC'));
		
		
		$this->set('t', $t);
		$this->set('ans', $ans);
		$this->set('s', $s);
		$this->set('p', $p);
		$this->set('pl', $pl);
	}

	public function delete_account(){
		$u = null;
		$redirect = false;
		$status = '';
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			$u = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
		}

		if(!empty($this->data)){
			if(isset($this->data['User']['delete'])){
				if($u['User']['pw'] == $this->tinkerEncode($this->data['User']['delete'], 1)){
					$u['User']['dbstorage'] = 1111;
					$_SESSION['loggedInUser']['User']['dbstorage'] = $u['User']['dbstorage'];
					$this->User->save($u);
					$redirect = true;
				}else{
					$status = '<p style="color:#d63a49">Password incorrect.</p>';
				}
			}
		}
		$u['User']['name'] = $this->checkPicture($u);

		$this->set('redirect', $redirect);
		$this->set('status', $status);
		$this->set('u', $u);
	}

	public function demote_admin(){
		$u = null;
		$redirect = false;
		$status = '';
		if(isset($_SESSION['loggedInUser']['User']['id'])){
			$u = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
		}

		if(!empty($this->data)){
			if(isset($this->data['User']['demote'])){
				if($u['User']['pw'] == $this->tinkerEncode($this->data['User']['demote'], 1)){
					$u['User']['isAdmin'] = 0;
					$_SESSION['loggedInUser']['User']['isAdmin'] = 0;
					$this->User->save($u);
					$redirect = true;
				}else{
					$status = '<p style="color:#d63a49">Password incorrect.</p>';
				}
			}
		}
		$u['User']['name'] = $this->checkPicture($u);

		$this->set('redirect', $redirect);
		$this->set('status', $status);
		$this->set('u', $u);
	}
	
	public function set_score(){
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		
		$sets = $this->Set->find('all', array('conditions' => array('public' => 1)));
		//$sets = $this->Set->find('all', array('conditions' => array('id' => 74761)));
		$ts = array();
		$xxx = array();
		for($i=0; $i<count($sets); $i++){
			$xxx[$i] = array();
			//$tsx = $this->Tsumego->find('all', array('conditions' =>  array('set_id' => $sets[$i]['Set']['id'])));
			$tsx = $this->findTsumegoSet($sets[$i]['Set']['id']);
			for($j=0; $j<count($tsx); $j++){
				$tsx[$j]['Tsumego']['set'] = $sets[$i]['Set']['title'];
				$tsx[$j]['Tsumego']['setid'] = $sets[$i]['Set']['id'];
				$tsx[$j]['Tsumego']['multiplier'] = $sets[$i]['Set']['multiplier'];
				array_push($ts, $tsx[$j]);
				array_push($xxx[$i], $tsx[$j]);
			}
		}
		$newTs2 = array();
		$newTs3 = array();
		$jc = 0;
		
		$avg = array();
		$avg[10] = 83.139212328767;
		$avg[20] = 73.936962406015;
		$avg[30] = 63.582480620155;
		$avg[40] = 57.21416496945;
		$avg[50] = 52.61221;
		$avg[60] = 44.959153094463;
		$avg[70] = 36.729862258953;
		$avg[80] = 31.031111111111;
		$avg[90] = 24.950833333333;
		
		$setPercent = array();
		$setCount = array();
		$setDifficulty = array();
		for($i=0; $i<count($xxx); $i++){
			$jc = 0;
			$sp = 0;
			$sc = 0;
			for($k=0; $k<count($xxx[$i]); $k++){
				$distance = array();
				
				$sp += $xxx[$i][$k]['Tsumego']['userWin'];
				$sc ++;
				
				for($l=0; $l<9; $l++){
					$xp = ($l+1)*10;
					$distance[$l] = $xxx[$i][$k]['Tsumego']['userWin'] - $avg[$xp];
					if($distance[$l]<0) $distance[$l]*=-1;
				}
				$lowest = 100;
				$pos = 0;
				for($j=0; $j<count($distance); $j++){
					if($distance[$j]<$lowest){
						$pos = $j;
						$lowest = $distance[$j];
					}
				}
				$newTs3['id'][$i][$jc] = $xxx[$i][$k]['Tsumego']['id'];
				$newTs3['count'][$i][$jc] = $xxx[$i][$k]['Tsumego']['userLoss'];
				$newTs3['percent'][$i][$jc] = $xxx[$i][$k]['Tsumego']['userWin'];
				$newTs3['set'][$i][$jc] = $xxx[$i][$k]['Tsumego']['set'];
				$newTs3['setid'][$i][$jc] = $xxx[$i][$k]['Tsumego']['setid'];
				$newTs3['num'][$i][$jc] = $xxx[$i][$k]['Tsumego']['num'];
				$newTs3['xp'][$i][$jc] = $xxx[$i][$k]['Tsumego']['part_increment'];
				$newTs3['newxp'][$i][$jc] = ($pos+1)*10;
				$newTs3['multiplier'][$i][$jc] = $xxx[$i][$k]['Tsumego']['multiplier'];
				$newTs3['multiplied'][$i][$jc] = ceil($xxx[$i][$k]['Tsumego']['multiplier']*$newTs3['newxp'][$i][$jc]);
				/*
				if($newTs3['setid'][$i][$jc]==104){
					$tsu = $this->Tsumego->findById($newTs3['id'][$i][$jc]);
					$tsu['Tsumego']['difficulty'] = $newTs3['multiplied'][$i][$jc];
					$this->Tsumego->save($tsu);
				}
				*/
				$jc++;
			}
			$setCount[$i] = $sc;
			$setPercent[$i] = round($sp/$sc,2);
			$distance = array();
			for($l=0; $l<9; $l++){
				$xp = ($l+1)*10;
				$distance[$l] = $setPercent[$i] - $avg[$xp];
				if($distance[$l]<0) $distance[$l]*=-1;
			}
			$lowest = 100;
			$pos = 0;
			for($j=0; $j<count($distance); $j++){
				if($distance[$j]<$lowest){
					$pos = $j;
					$lowest = $distance[$j];
				}
			}
			$setDifficulty[$i] = $pos+1;
			/*
			$s = $this->Set->findById($newTs3['setid'][$i][0]);
			$s['Set']['difficulty'] = $setDifficulty[$i];
			$this->Set->save($s);
			*/
		}
		
		for($i=0; $i<count($newTs3['id']); $i++){
			array_multisort($newTs3['num'][$i], $newTs3['set'][$i], $newTs3['percent'][$i], $newTs3['id'][$i], $newTs3['xp'][$i], $newTs3['newxp'][$i], $newTs3['count'][$i], $newTs3['multiplier'][$i], $newTs3['multiplied'][$i], $newTs3['setid'][$i]);
		}
		
		
		$this->set('t', $t);
		//$this->set('ts', $newTs2);
		$this->set('newTs3', $newTs3);
		$this->set('setPercent', $setPercent);
		$this->set('setCount', $setCount);
		$this->set('setDifficulty', $setDifficulty);
		$this->set('xxx', $xxx);
		$this->set('ur', $ur);
		$this->set('ratio', $ratio);
		$this->set('from', $from);
		$this->set('to', $to);
		$this->set('sets', $sets);
		$this->set('params', $this->params['url']['t']);
	}
}




?>
   