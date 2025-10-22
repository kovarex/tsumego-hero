<?php
App::uses('CakeEmail', 'Network/Email');
class UsersController extends AppController {

	public $name = 'Users';

	public $pageTitle = 'Users';

	public $helpers = ['Html', 'Form'];

	/**
	 * @return void
	 */
	public function playerdb5() {
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
		while ($startDay <= $endDay) {
			$sc = $this->SetConnection->find('all', array('order' => 'num ASC', 'conditions' => array(
				'set_id' => $setFrom,
				'num >=' => $numFrom,
				'num <=' => $numTo
			)));
			$scCount = count($sc);
			for ($i=0; $i<$scCount; $i++) {
				if ($startDay>9) $digit = '';
				else $digit = '0';
				$s = array();
				$s['Schedule']['published'] = '0';
				$s['Schedule']['date'] = '2025-'.$month.'-'.$digit.$startDay;
				$s['Schedule']['set_id'] = $setTo;
				$s['Schedule']['tsumego_id'] = $sc[$i]['SetConnection']['tsumego_id'];
				$this->Schedule->create();
				$this->Schedule->save($s);
			}

			$numFrom += $step;
			$numTo += $step;
			$startDay += 1;
		}

		////////////////

		$t = $this->Tsumego->find('all', array('conditions' => array(
			'set_id' => 210,
			'num <=' => 20
		)));

		foreach ($t as $item) {
			$tag = array();
			$tag['Tag']['tsumego_id'] = $item['Tsumego']['id'];
			$tag['Tag']['user_id'] = 72;
			$tag['Tag']['tag_name_id'] = 10;
			$tag['Tag']['approved'] = 1;
			$this->Tag->create();
			$this->Tag->save($tag);
		}


		//
		$st = $this->Tsumego->find('all', array('conditions' => array(
			'id >=' => 1,
			'id <=' => 10000
		)));
		$counter=0;

		$stCount = count($st);
		for ($i=0; $i<$stCount; $i++) {
			if (strpos(' '.$st[$i]['Tsumego']['description'], 'b ')) {
				$st[$i]['Tsumego']['description'] = str_replace('b ', '[b] ', $st[$i]['Tsumego']['description']);
				$this->Tsumego->save($st[$i]);
				$counter++;
			}
		}



		//
		$tn = $this->TagName->find('all');
		$tnCount = count($tn);
		for ($i=0; $i<$tnCount; $i++) {
			$tn[$i]['TagName']['color'] = rand(0,24);
			$this->TagName->save($tn[$i]);
		}

		$test = $this->findTsumegoSet(50);


		$ts = $this->Tsumego->find('all', array('order' => 'id ASC', 'conditions' => array(
			'set_id' => 50
		)));


		$u = $this->Tsumego->find('all', array('conditions' => array(
			'NOT' => array('set_id' => null)
		)));

		$u = $this->Tsumego->find('all', array('conditions' => array(
			'public' => 1
		)));

		$ux = $this->Tsumego->find('all', array('conditions' => array(
			'public' => 1,
			'set_id' => null
		)));


		$sc = $this->SetConnection->find('all', array('order' => 'num ASC', 'conditions' => array(
			'set_id' => 194,
			'num >=' => 1,
			'num <=' => 10
		)));

		$scCount = count($sc);
		for ($i=0; $i<$scCount; $i++) {
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

		$scCount = count($sc);
		for ($i=0; $i<$scCount; $i++) {
			$s = array();
			$s['Schedule']['published'] = '0';
			$s['Schedule']['date'] = '2024-03-21';
			$s['Schedule']['set_id'] = '227';
			$s['Schedule']['tsumego_id'] = $sc[$i]['SetConnection']['tsumego_id'];
			$this->Schedule->create();
			$this->Schedule->save($s);
		}

		foreach ($ts as $item) {
			//$this->setTsumegoElo($item['Tsumego']['id']);
			$this->setTsumegoElo($item);
		}

		$u = $this->User->find('all', array('conditions' => array(
			'id >=' => 10000,
			'id <=' => 20000
		)));

		$uCount = count($u);
		for ($i=0; $i<$uCount; $i++) {
			if ($u[$i]['User']['elo_rating_mode']!=100) {
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

		$tsCount = count($ts);
		for ($i=0; $i<$tsCount; $i++) {
			$sc['SetConnection']['set_id'] = 216;
			$sc['SetConnection']['tsumego_id'] = $ts[$i]['Tsumego']['id'];
			$sc['SetConnection']['num'] = $ts[$i]['Tsumego']['num'];
			$this->SetConnection->create();
			$this->SetConnection->save($sc);
		}

		$comments = $this->Comment->find('all');
		$c = array();
		foreach ($comments as $item) {
			$c[] = $item['Comment']['user_id'];
		}
		$c = array_count_values($c);
		$this->set('c', $c);



		*/

		//$this->SetConnection->save($sc);
		//$s = $this->Tsumego->find('all', array('conditions' => array('id >' => 14000)));
		/*
		$sCount = count($s);
		for ($j=0; $j<$sCount; $j++) {
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
		$ts1Count = count($ts1);
		for ($j=0; $j<$ts1Count; $j++) {
			if ($ts1[$j]['TsumegoStatus']['status']=='S' || $ts1[$j]['TsumegoStatus']['status']=='W' || $ts1[$j]['TsumegoStatus']['status']=='C') {
				$correctCounter++;

			}
			array_push($ts2, $ts1[$j]['TsumegoStatus']['tsumego_id']);
		}


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
		foreach ($c as $item) {
			$u = $this->User->findById($item['Comment']['user_id']);
			$cc[] = $u['User']['name'];
		}


		$ts = $this->Tsumego->find('all', array('conditions' => array('set_id' => 42)));
		$sgfs = array();
		foreach ($ts as $item) {
			//$this->Tsumego->delete($item['Tsumego']['id']);
			$sgfs[] = $this->Sgf->find('first', array('conditions' => array('tsumego_id' => $item['Tsumego']['id'])));
		}

		foreach ($sgfs as $sgf) {
			$this->Sgf->delete($sgf['Sgf']['id']);
		}



		$ts = $this->Tsumego->find('all', array('order' => 'num ASC', 'conditions' => array(
			'set_id' => 185,
			'num >=' => 531,
			'num <=' => 540
		)));

		$tsCount = count($ts);
		for ($i=0; $i<$tsCount; $i++) {
			$s = array();
			$s['Schedule']['published'] = '0';
			$s['Schedule']['date'] = '2023-11-05';
			$s['Schedule']['set_id'] = '198';
			$s['Schedule']['tsumego_id'] = $ts[$i]['Tsumego']['id'];
			$this->Schedule->create();
			$this->Schedule->save($s);
		}*/

		//$this->set('t', $t);
		//$this->set('u', $u);
		//$this->set('ou', $ou);
		//$this->set('ouc', $ouc);
		//$this->set('tr', $tr);
		//$this->set('ut', $ut);
		//$this->set('out', $out);
		//$this->set('ux', $ux);
	}

	/**
	 * @return void
	 */
	public function deleteoldattempts() {
		$this->loadModel('TsumegoAttempt');
		$ta = $this->TsumegoAttempt->find('all', ['limit' => 5000, 'order' => 'created ASC']);
		echo '<pre>';
		print_r($ta[0]['TsumegoAttempt']['created']);
		echo '</pre>';

		foreach ($ta as $item) {
			$this->TsumegoAttempt->delete($item['TsumegoAttempt']['id']);
		}

		$this->set('x', '2023-08-01 00:00:00');
		$this->set('date', $ta[0]['TsumegoAttempt']['created']);
	}

	/**
	 * @return void
	 */
	public function rank_list() {
		$this->loadModel('Tsumego');

		$ts = $this->Tsumego->find('all', [
			'conditions' => [
				'id >=' => 20000,
				'id <=' => 30000,
			],
		]);
		foreach ($ts as $item) {
			$this->set_elo($item['Tsumego']['id']);
		}
	}

	/**
	 * @param string|int $tid Tsumego ID
	 * @return void
	 */
	private function set_elo($tid) {
		$this->loadModel('Tsumego');
		$t = $this->Tsumego->findById($tid);
		$rank = $this->getTsumegoRankx($t['Tsumego']['userWin']);
		$tMax = $this->getTsumegoRankMax($t['Tsumego']['userWin']);
		$tVal = $this->getTsumegoRankVal($t['Tsumego']['userWin']);
		if ($tMax != 0) {
			$p = $tVal / $tMax;
		} else {
			$p = 0;
		}
		$newElo = $this->getTsumegoElo($rank, $p);
		$adjustElo = $this->adjustElo($newElo);
		$t['Tsumego']['elo_rating_mode'] = $adjustElo;
		$t['Tsumego']['difficulty'] = $this->convertEloToXp($t['Tsumego']['elo_rating_mode']);
		$this->Tsumego->save($t);
	}

	/**
	 * @return void
	 */
	public function rank_single() {
		$this->loadModel('Tsumego');
		$a = [];
		$a['c'] = [];
		$a['rank'] = [];
		$a['rank2'] = [];
		$a['rank3'] = [];
		$a['elo'] = [];
		$a['elo2'] = [];
		$counter = 0;
		while ($counter <= 100) {
			array_push($a['c'], $counter);
			$rank = $this->getTsumegoRankx($counter);
			array_push($a['rank'], $rank);

			$tMax = $this->getTsumegoRankMax($counter);
			array_push($a['rank2'], $tMax);

			$tVal = $this->getTsumegoRankVal($counter);
			if ($tMax != 0) {
				$p = $tVal / $tMax;
			} else {
				$p = 0;
			}
			array_push($a['rank3'], $p);

			$newElo = $this->getTsumegoElo($rank, $p);
			array_push($a['elo'], $newElo);

			$adjustElo = $this->adjustElo($newElo);
			array_push($a['elo2'], $adjustElo);

			$counter += .5;
		}
		echo '<table>';
		$aCount = count($a['c']);
		for ($i = 0; $i < $aCount; $i++) {
			echo '<tr>';
			echo '<td>' . $a['c'][$i] . '</td><td>' . $a['rank'][$i] . '</td><td>' . $a['rank3'][$i] . '</td><td>' . $a['elo'][$i] . '</td><td>' . $a['elo2'][$i] . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}

	/**
	 * @return void
	 */
	public function adjusttsumego() {
		$this->loadModel('Tsumego');
		$ts = $this->Tsumego->find('all', ['order' => 'elo_rating_mode ASC']);
		echo '<pre>';
		print_r(count($ts));
		echo '</pre>';
		echo '<table>';
		foreach ($ts as $item) {
			echo '<tr><td>' . $item['Tsumego']['id'] . '</td><td>' . $item['Tsumego']['difficulty']
			. '</td><td>' . $item['Tsumego']['userWin'] . '</td><td>' . $item['Tsumego']['elo_rating_mode'] . '</td></tr>';
		}
		echo '</table>';
	}

	/**
	 * @param string|int|null $id Tsumego ID
	 * @return void
	 */
	public function tsumego_rating($id = null) {
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');

		$t = $this->Tsumego->findById($id);
		$sc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $id]]);
		if (!$sc) {
			throw new NotFoundException('SetConnection not found');
		}
		$s = $this->Set->findById($sc['SetConnection']['set_id']);
		if (!$s) {
			throw new NotFoundException('Set not found');
		}
		$name = $s['Set']['title'] . ' - ' . $t['Tsumego']['num'];
		$ta = $this->TsumegoAttempt->find('all', [
			'order' => 'created ASC',
			'conditions' => [
				'tsumego_id' => $id,
				'NOT' => [
					'tsumego_elo' => 0,
				],
			],
		]);
		$this->set('rating', $t['Tsumego']['elo_rating_mode']);
		$this->set('name', $name);
		$this->set('ta', $ta);
		$this->set('id', $id);
	}

	//scan for glitches
	/**
	 * @param string|int|null $x Index value
	 * @return void
	 */
	public function test1a($x = null) {
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		$ts = $this->Tsumego->find('all', ['order' => 'id ASC']);
		$id = $ts[$x]['Tsumego']['id'];
		$ta = $this->TsumegoAttempt->find('all', [
			'order' => 'created ASC',
			'conditions' => [
				'tsumego_id' => $id,
				'NOT' => [
					'tsumego_elo' => 0,
				],
			],
		]);
		$change = $ta[count($ta) - 1]['TsumegoAttempt']['tsumego_elo'] - $ta[0]['TsumegoAttempt']['tsumego_elo'];
		$t = $this->Tsumego->findById($id);
		$t['Tsumego']['rd'] = $change;
		$this->Tsumego->save($t);
		$p = $x . '/' . count($ts);
		echo '<pre>';
		print_r($p);
		echo '</pre>';
		echo '<pre>';
		print_r($id);
		echo '</pre>';
		echo '<pre>';
		print_r($change);
		echo '</pre>';
		$this->set('next', $x + 1);
		$this->set('finish', count($ts) - 1);
	}

	//fix glitched problems
	/**
	 * @param string|int|null $id Tsumego ID
	 * @return void
	 */
	public function test1b($id = null) {
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');

		$t = $this->Tsumego->find('all', [
			'conditions' => [
				'rd <' => -500,
			],
		]);
		$ta = [];
		$tCount = count($t);
		for ($i = 0; $i < $tCount; $i++) {
			$ta = $this->TsumegoAttempt->find('all', [
				'limit' => 2,
				'order' => 'created ASC',
				'conditions' => [
					'tsumego_id' => $t[$i]['Tsumego']['id'],
					'NOT' => [
						'tsumego_elo' => 0,
					],
				],
			]);
			$t[$i]['Tsumego']['rd'] = 0;
			$t[$i]['Tsumego']['elo_rating_mode'] = $ta[0]['TsumegoAttempt']['tsumego_elo'];
			$this->Tsumego->save($t[$i]);
			echo '<pre>';
			print_r('saved ' . $t[$i]['Tsumego']['id']);
			echo '</pre>';
		}
		echo '<pre>';
		print_r(count($t));
		echo '</pre>';
		echo '<pre>';
		print_r($ta[0]['TsumegoAttempt']['tsumego_elo']);
		echo '</pre>';
	}

	//no author
	/**
	 * @param string|int|null $id Tsumego ID
	 * @return void
	 */
	public function test1c($id = null) {
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');

		$t = $this->Tsumego->find('all', [
			'conditions' => [
				'author' => '',
			],
		]);
		/*
		$tCount = count($t);
		for ($i=0; $i<$tCount; $i++) {
			$ta = $this->TsumegoAttempt->find('all', array('limit' => 2, 'order' => 'created ASC', 'conditions' => array(
				'tsumego_id' => $t[$i]['Tsumego']['id'],
				'NOT' => array(
					'tsumego_elo' => 0
				)
			)));
			$t[$i]['Tsumego']['rd'] = 0;
			$t[$i]['Tsumego']['elo_rating_mode'] = $ta[0]['TsumegoAttempt']['tsumego_elo'];
			$this->Tsumego->save($t[$i]);
		}
		*/
		echo '<pre>';
		print_r(count($t));
		echo '</pre>';
		echo '<pre>';
		print_r($t);
		echo '</pre>';
	}

	//list ratings
	/**
	 * @return void
	 */
	public function test1d() {
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('SetConnection');
		$this->loadModel('Set');

		$ts = $this->Tsumego->find('all', ['order' => 'elo_rating_mode ASC']);

		$x1min = 2200;
		$x1max = 2673;
		$x2min = 2200;
		$x2max = 2900;

		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++) {
			$sc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
			if (!$sc) {
				continue;
			}
			$s = $this->Set->findById($sc['SetConnection']['set_id']);
			if (!$s) {
				continue;
			}
			$ts[$i]['Tsumego']['public'] = $s['Set']['public'];
			$ts[$i]['Tsumego']['rank'] = Rating::getReadableRankFromRating($ts[$i]['Tsumego']['elo_rating_mode']);
			$ts[$i]['Tsumego']['shift'] = $x2min;
			$ts[$i]['Tsumego']['rank2'] = Rating::getReadableRankFromRating($x2min);
		}
		$this->set('ts', $ts);
	}

	/**
	 * @return void
	 */
	public function map19k() {
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('SetConnection');
		$this->loadModel('Set');

		$ts = $this->Tsumego->find('all', [
			'order' => 'elo_rating_mode ASC',
			'conditions' => [
				'elo_rating_mode >' => 500,
				'elo_rating_mode <' => 1200,
			],
		]);

		$x1min = 500;
		$x1max = 1200;
		$x2min = 100;
		$x2max = 1200;

		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++) {
			$sc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
			if (!$sc) {
				continue;
			}
			$s = $this->Set->findById($sc['SetConnection']['set_id']);
			if (!$s) {
				continue;
			}
			$ts[$i]['Tsumego']['public'] = $s['Set']['public'];
			$ts[$i]['Tsumego']['rank'] = Rating::getReadableRankFromRating($ts[$i]['Tsumego']['elo_rating_mode']);
			$ts[$i]['Tsumego']['shift'] = $x2min;
			$ts[$i]['Tsumego']['rank2'] = Rating::getReadableRankFromRating($x2min);
			if ($ts[$i]['Tsumego']['public'] == 1) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = $ts[$i]['Tsumego']['shift'];
				//$this->Tsumego->save($ts[$i]);
			}
		}
		$this->set('ts', $ts);
	}

	/**
	 * @return void
	 */
	public function map8d() {
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('SetConnection');
		$this->loadModel('Set');

		$ts = $this->Tsumego->find('all', [
			'order' => 'elo_rating_mode ASC',
			'conditions' => [
				'elo_rating_mode >=' => 2200,
			],
		]);

		$x1min = 2200;
		$x1max = 2673;
		$x2min = 2200;
		$x2max = 2900;

		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++) {
			$sc = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
			if (!$sc) {
				continue;
			}
			$s = $this->Set->findById($sc['SetConnection']['set_id']);
			if (!$s) {
				continue;
			}
			$ts[$i]['Tsumego']['public'] = $s['Set']['public'];
			$ts[$i]['Tsumego']['rank'] = Rating::getReadableRankFromRating($ts[$i]['Tsumego']['elo_rating_mode']);
			$ts[$i]['Tsumego']['shift'] = $x2min;
			$ts[$i]['Tsumego']['rank2'] = Rating::getReadableRankFromRating($x2min);
			if ($ts[$i]['Tsumego']['public'] == 1) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = $ts[$i]['Tsumego']['shift'];
				//$this->Tsumego->save($ts[$i]);
			}
		}
		$this->set('ts', $ts);
	}

	/**
	 * @return void
	 */
	public function test1e() {
		$this->loadModel('Tsumego');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('SetConnection');
		$this->loadModel('Set');

		$x1min = 500;
		$x1max = 1200;
		$x2min = 100;
		$x2max = 1200;

		$x = 700;

		/** @phpstan-ignore-next-line */
		if ($x1max != $x1min) {
			$result = $x2min + (($x2max - $x2min) / ($x1max - $x1min)) * ($x - $x1min);
		} else {
			$result = $x2min;
		}
		echo $result;
	}

	//list tsumego variations
	/**
	 * @return void
	 */
	public function test2() {
		$this->loadModel('Tsumego');

		$ts = $this->Tsumego->find('all', ['order' => 'rd ASC']);
		$more = [];
		$less = [];

		foreach ($ts as $item) {
			if ($item['Tsumego']['rd'] > 0) {
				$more[] = $item['Tsumego']['rd'];
			}
			if ($item['Tsumego']['rd'] < 0) {
				$less[] = $item['Tsumego']['rd'];
			}
		}
		echo '<pre>';
		print_r(count($less));
		echo '</pre>';
		echo '<pre>';
		print_r(count($more));
		echo '</pre>';

		$this->set('ts', $ts);
	}

	/**
	 * @return void
	 */
	public function adjusttsumego2() {
		$ts = [];
		$min = 600;
		$max = 2600;

		$scale = $max - $min;
		$step = $scale / 100;
		$x = [];
		for ($i = 0; $i <= 100; $i++) {
			$x[$i] = $min + $step * $i;
		}

		for ($i = 0; $i <= 100; $i++) {
			$a = [];
			$a['elo'] = $x[$i];
			$a['xp'] = round(pow($a['elo'] / 100, 1.55) - 6);
			array_push($ts, $a);
		}

		echo '<table>';
		foreach ($ts as $item) {
			echo '<tr><td>' . $item['elo'] . '</td><td>' . $item['xp'] . '</td></tr>';
		}
		echo '</table>';
	}

	/**
	 * @return void
	 */
	public function publish() {
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('Schedule');
		$this->loadModel('SetConnection');

		$p = $this->Schedule->find('all', ['order' => 'date ASC', 'conditions' => ['published' => 0]]);

		$pCount = count($p);
		for ($i = 0; $i < $pCount; $i++) {
			$t = $this->Tsumego->findById($p[$i]['Schedule']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$p[$i]['Schedule']['num'] = $t['Tsumego']['num'];
			$p[$i]['Schedule']['set'] = $s['Set']['title'] . ' ' . $s['Set']['title2'] . ' ';
		}
		$this->set('p', $p);
	}

	/**
	 * @return void
	 */
	public function empty_uts() {
		$this->loadModel('TsumegoStatus');
		$this->loadModel('PurgeList');
		$pl = $this->PurgeList->find('first', ['order' => 'id DESC']);
		$pl['PurgeList']['empty_uts'] = date('Y-m-d H:i:s');
		$this->PurgeList->save($pl);

		$ut = $this->TsumegoStatus->find('all', ['limit' => 10000, 'conditions' => ['user_id' => 33]]);
		foreach ($ut as $item) {
			$this->TsumegoStatus->delete($item['TsumegoStatus']['id']);
		}
		$this->set('ut', count($ut));
	}

	/**
	 * @return void
	 */
	public function best_tsumego() {
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('TsumegoRatingAttempt');
		$this->loadModel('Schedule');
		$this->loadModel('Tsumego');

		$tsumegoOfTheDay1 = $this->TsumegoRatingAttempt->find('all', ['limit' => 10000, 'order' => 'created DESC', 'conditions' => ['status' => 'S']]);
		$tsumegoOfTheDay2 = $this->TsumegoAttempt->find('all', ['limit' => 30000, 'order' => 'created DESC', 'conditions' => ['gain >=' => 40]]);

		$date = date('Y-m-d', strtotime('yesterday'));
		$s = $this->Schedule->find('all', ['conditions' => ['date' => $date]]);

		$t = $this->Tsumego->find('all');

		$this->set('ut', $tsumegoOfTheDay1);
		$this->set('out', $tsumegoOfTheDay2);
		$this->set('s', $s);
		$this->set('t', $t);
	}

	/**
	 * @return void
	 */
	public function resetpassword() {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'Tsumego Hero - Sign In');
		if (!empty($this->data)) {
			$u = $this->User->findByEmail($this->data['User']['email']);
			if ($u) {
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
				$Email->from(['me@joschkazimdars.com' => 'https://tsumego-hero.com']);
				$Email->to($this->data['User']['email']);
				$Email->subject('Password reset for your Tsumego Hero account');
				$ans = 'Click the following button to reset your password. If you have not requested the password reset,
then ignore this email. https://tsumego-hero.com/users/newpassword/' . $randomString;
				$Email->send($ans);
			}
			$this->set('sent', true);
		} else {
			$this->set('sent', false);
		}
	}

	/**
	 * @param string|null $checksum Password reset checksum
	 * @return void
	 */
	public function newpassword($checksum = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'Tsumego Hero - Sign In');
		$valid = false;
		$done = false;
		if ($checksum == null) {
			$checksum = 1;
		}
		$u = $this->User->find('first', ['conditions' => ['passwordreset' => $checksum]]);
		if (!empty($this->data)) {
			$newPw = $this->tinkerEncode($this->data['User']['pw'], 1);
			$u['User']['pw'] = $newPw;
			$this->User->save($u);
			$done = true;
		} else {
			if ($u != null) {
				$valid = true;
			}
		}
		$this->set('u', $u['User']['name']);
		$this->set('valid', $valid);
		$this->set('done', $done);
		$this->set('checksum', $checksum);
	}

	/**
	 * @return void
	 */
	public function routine0() {//23:55 signed in users today
		$this->loadModel('Answer');

		$activity = $this->User->find('all', ['order' => ['User.reuse3 DESC']]);
		$todaysUsers = [];
		$today = date('Y-m-d', strtotime('today'));
		$activityCount = count($activity);
		for ($i = 0; $i < $activityCount; $i++) {
			$a = new DateTime($activity[$i]['User']['created']);
			if ($a->format('Y-m-d') == $today) {
				array_push($todaysUsers, $activity[$i]['User']);
			}
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
	public function routine1() {//0:00 uotd
		$this->loadModel('DayRecord');
		$today = date('Y-m-d');
		$dateUser = $this->DayRecord->find('first', ['conditions' => ['date' => $today]]);
		if (count($dateUser) == 0) {
			$this->uotd();
			$this->deleteUserBoards();
		}
	}

	/**
	 * @return void
	 */
	public function routine2() {//0:02 halfXP
		$this->halfXP();
	}

	/**
	 * @return void
	 */
	public function routine3() {//0:04 t_glicko
		$this->loadModel('User');
		$this->loadModel('TsumegoRatingAttempt');
		$ux = $this->User->find('all', ['limit' => 1000, 'order' => 'created DESC']);

		$trs = [];
		$activeToday = false;
		$uxCount = count($ux);
		for ($i = 0; $i < $uxCount; $i++) {
			$trs = $this->TsumegoRatingAttempt->find('all', ['order' => 'created DESC', 'conditions' => ['user_id' => $ux[$i]['User']['id']]]);
			$activeToday = false;
			$d1 = date('Y-m-d', strtotime('yesterday'));

			$trsCount = count($trs);
			for ($j = 0; $j < $trsCount; $j++) {
				$date = new DateTime($trs[$j]['TsumegoRatingAttempt']['created']);
				$date = $date->format('Y-m-d');
				$trs[$j]['TsumegoRatingAttempt']['created'] = $date;
				if ($date == $d1) {
					$activeToday = true;

					break;
				}
			}
			if ($ux[$i]['User']['rd'] < 90) {//125
				$ux[$i]['User']['rd'] += 35;
				$this->User->save($ux[$i]);
			}
			/*
			if (!$activeToday && $ux[$i]['User']['rd']<250) {
				$ux[$i]['User']['rd'] += 60;
				$this->User->save($ux[$i]);
			}
			*/
		}

		$this->set('activeToday', $activeToday);
		$this->set('trs', $trs);
	}

	/**
	 * @return void
	 */
	public function routine11() {//0:05 userRefresh
		$this->userRefresh(1);
	}
	/**
	 * @return void
	 */
	public function routine12() {//0:06 userRefresh
		$this->userRefresh(2);
	}
	/**
	 * @return void
	 */
	public function routine13() {//0:07 userRefresh
		$this->userRefresh(3);
	}
	/**
	 * @return void
	 */
	public function routine14() {//0:08 userRefresh
		$this->userRefresh(4);
	}
	/**
	 * @return void
	 */
	public function routine15() {//0:09 userRefresh
		$this->userRefresh(5);
	}
	/**
	 * @return void
	 */
	public function routine16() {//0:10 userRefresh
		$this->userRefresh(6);
	}
	/**
	 * @return void
	 */
	public function routine17() {//0:11 userRefresh
		$this->userRefresh(7);
	}
	/**
	 * @return void
	 */
	public function routine18() {//0:12 userRefresh
		$this->userRefresh(8);
	}
	/**
	 * @return void
	 */
	public function routine19() {//0:13 userRefresh
		$this->userRefresh(9);
	}
	/**
	 * @return void
	 */
	public function routine110() {//0:14 userRefresh
		$this->userRefresh(10);
	}
	/**
	 * @return void
	 */
	public function routine111() {//0:15 userRefresh
		$this->userRefresh(11);
	}
	/**
	 * @return void
	 */
	public function routine112() {//0:16 userRefresh
		$this->userRefresh(12);
	}
	/**
	 * @return void
	 */
	public function routine20() {//popular tags
		$tags = $this->Tag->find('all', ['conditions' => ['approved' => 1]]);
		$tagCount = [];
		$tagsCount = count($tags);
		for ($i = 0; $i < $tagsCount; $i++) {
			array_push($tagCount, $tags[$i]['Tag']['tag_name_id']);
		}

		$tagCount = array_count_values($tagCount);
		$tagId = [];
		$tagNum = [];
		foreach ($tagCount as $key => $value) {
			array_push($tagId, $key);
			array_push($tagNum, $value);
		}
		array_multisort($tagNum, $tagId);
		$array = [];
		$tagIdCount = count($tagId);
		for ($i = $tagIdCount - 1; $i >= 0; $i--) {
			$a = [];
			$a['id'] = $tagId[$i];
			$a['num'] = $tagNum[$i];
			array_push($array, $a);
		}
		file_put_contents('json/popular_tags.json', json_encode($array));
	}
	/**
	 * @return void
	 */
	public function routine21() {//level highscore
		$users = $this->User->find('all', ['limit' => 1000, 'order' => 'level DESC']);
		$userP = [];
		$stop = 1;
		$usersCount = count($users);
		for ($i = 0; $i < $usersCount; $i++) {
			if ($stop <= 1000) {
				$lvl = 1;
				$toplvl = $users[$i]['User']['level'];
				$startxp = 50;
				$sum = 0;
				$xpJump = 10;
				for ($j = 1; $j < $toplvl; $j++) {
					if ($j >= 11) {
						$xpJump = 25;
					}
					if ($j >= 19) {
						$xpJump = 50;
					}
					if ($j >= 39) {
						$xpJump = 100;
					}
					if ($j >= 69) {
						$xpJump = 150;
					}
					if ($j == 99) {
						$xpJump = 50000;
					}
					if ($j == 100) {
						$xpJump = 1150;
					}
					if ($j >= 101) {
						$xpJump = 0;
					}
					$sum += $startxp;
					$startxp += $xpJump;
				}
				$sum += $users[$i]['User']['xp'];
				$users[$i]['User']['xpSum'] = $sum;
				$stop++;
			}
		}
		$UxpSum = [];
		$Uname = [];
		$Ulevel = [];
		$Uid = [];
		$Utype = [];
		$Usolved = [];
		$stop = 1;
		$anz = 0;
		$rand = rand(0, 9);
		echo $rand;
		$usersCount = count($users);
		for ($i = 0; $i < $usersCount; $i++) {
			if ($anz < 1000) {
				array_push($UxpSum, $users[$i]['User']['xpSum']);
				if (strlen($users[$i]['User']['name']) > 20) {
					$users[$i]['User']['name'] = substr($users[$i]['User']['name'], 0, 20);
				}
				array_push($Uname, $this->checkPicture($users[$i]));
				array_push($Ulevel, $users[$i]['User']['level']);
				array_push($Uid, $users[$i]['User']['id']);
				array_push($Utype, $users[$i]['User']['premium']);
				if ($users[$i]['User']['solved'] == null) {
					$users[$i]['User']['solved'] = 0;
				}
				if (($i + $rand) % 9 == 0) {
					array_push($Usolved, $this->saveSolvedNumber($users[$i]['User']['id']));
				} else {
					array_push($Usolved, $users[$i]['User']['solved']);
				}
				$anz++;
			}
		}
		array_multisort($UxpSum, $Uname, $Ulevel, $Uid, $Utype, $Usolved);
		$users2 = [];
		$UxpSumCount = count($UxpSum);
		for ($i = 0; $i < $UxpSumCount; $i++) {
			$a = [];
			$a['xpSum'] = $UxpSum[$i];
			$a['name'] = mb_convert_encoding($Uname[$i], 'UTF-8', 'ISO-8859-1');
			$a['level'] = $Ulevel[$i];
			$a['id'] = $Uid[$i];
			$a['type'] = $Utype[$i];
			$a['solved'] = $Usolved[$i];
			array_push($users2, $a);
		}
		file_put_contents('json/level_highscore.json', json_encode($users2));
	}

	/**
	 * @return void
	 */
	public function routine22() {//achievement highscore
		$aNum = count($this->Achievement->find('all') ?: []);
		$as = $this->AchievementStatus->find('all');
		$as2 = [];

		$asCount = count($as);
		for ($i = 0; $i < $asCount; $i++) {
			if ($as[$i]['AchievementStatus']['achievement_id'] != 46) {
				array_push($as2, $as[$i]['AchievementStatus']['user_id']);
			} else {
				$as46counter = $as[$i]['AchievementStatus']['value'];
				while ($as46counter > 0) {
					array_push($as2, $as[$i]['AchievementStatus']['user_id']);
					$as46counter--;
				}
			}
		}
		$as3 = array_count_values($as2);
		$uaNum = [];
		$uaId = [];
		foreach ($as3 as $key => $value) {
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
	public function routine23() {//daily highscore
		$activity = $this->User->find('all', ['order' => ['User.reuse3 DESC']]);
		$todaysUsers = [];
		$today = date('Y-m-d', strtotime('today'));
		$activityCount = count($activity);
		for ($i = 0; $i < $activityCount; $i++) {
			$activity[$i]['User']['name'] = mb_convert_encoding($activity[$i]['User']['name'], 'UTF-8', 'ISO-8859-1');
			$a = new DateTime($activity[$i]['User']['created']);
			if ($a->format('Y-m-d') == $today) {
				array_push($todaysUsers, $activity[$i]['User']);
			}
		}
		file_put_contents('json/daily_highscore.json', json_encode($todaysUsers));
	}

	/**
	 * @return void
	 */
	public function routine24() {//time mode overview
		$sets = $this->Set->find('all', ['conditions' => ['public' => 1]]);
		$tsumegos = [];
		$setsCount = count($sets);
		for ($i = 0; $i < $setsCount; $i++) {
			$tx = $this->findTsumegoSet($sets[$i]['Set']['id']);
			$txCount = count($tx);
			for ($j = 0; $j < $txCount; $j++) {
				array_push($tsumegos, $tx[$j]);
			}
		}
		$rx = [];
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
		$rxx = [];
		$rxCount = count($rx);
		for ($i = 0; $i < $rxCount; $i++) {
			$rxx[$rx[$i]] = [];
		}
		$tsumegosCount = count($tsumegos);
		for ($i = 0; $i < $tsumegosCount; $i++) {
			if ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 2500) {
				array_push($rxx['5d'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 2400) {
				array_push($rxx['4d'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 2300) {
				array_push($rxx['3d'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 2200) {
				array_push($rxx['2d'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 2100) {
				array_push($rxx['1d'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 2000) {
				array_push($rxx['1k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 1900) {
				array_push($rxx['2k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 1800) {
				array_push($rxx['3k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 1700) {
				array_push($rxx['4k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 1600) {
				array_push($rxx['5k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 1500) {
				array_push($rxx['6k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 1400) {
				array_push($rxx['7k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 1300) {
				array_push($rxx['8k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 1200) {
				array_push($rxx['9k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 1100) {
				array_push($rxx['10k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 1000) {
				array_push($rxx['11k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 900) {
				array_push($rxx['12k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 800) {
				array_push($rxx['13k'], $tsumegos[$i]);
			} elseif ($tsumegos[$i]['Tsumego']['elo_rating_mode'] >= 700) {
				array_push($rxx['14k'], $tsumegos[$i]);
			} else {
				array_push($rxx['15k'], $tsumegos[$i]);
			}
		}
		$rxxCount = [];
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

	/**
	 * @return void
	 */
	public function routine25() {//tsumego public and set_id
		$sets = $this->Set->find('all', ['conditions' => ['public' => 1]]);
		$setsCount = count($sets);
		for ($i = 0; $i < $setsCount; $i++) {
			$ts = $this->findTsumegoSet($sets[$i]['Set']['id']);
			$tsCount = count($ts);
			for ($j = 0; $j < $tsCount; $j++) {
				$save = false;
				if ($ts[$j]['Tsumego']['public'] != 1) {
					$ts[$j]['Tsumego']['public'] = 1;
					$save = true;
				}
				if ($ts[$j]['Tsumego']['set_id'] == null) {
					$ts[$j]['Tsumego']['set_id'] = $sets[$i]['Set']['id'];
					$save = true;
				}
				if ($save) {
					$this->Tsumego->save($ts[$j]);
				}
			}
		}
	}

	/**
	 * @param string|int|null $filter Filter type
	 * @return void
	 */
	public function refresh_dates($filter = null) {//0:17 refresh rest (routine999)
		if ($filter == 1) {
			$u = $this->User->find('all', [
				'conditions' => [
					'NOT' => ['lastRefresh' => date('Y-m-d')],
				],
			]);
		} elseif ($filter == 2) {
			$u = $this->User->find('all', [
				'conditions' => [
					'NOT' => ['lastRefresh' => date('Y-m-d')],
				],
			]);
			$uCount = count($u);
			for ($i = 0; $i < $uCount; $i++) {
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
		} else {
			$u = $this->User->find('all', ['limit' => 50, 'order' => 'lastRefresh ASC']);
		}
		$this->set('u', $u);
	}

	/**
	 * @return void
	 */
	public function routine4x() {//0:20 remove inactive players 2
		$this->loadModel('TsumegoStatus');
		$ux = $this->User->find('all', ['limit' => 1000, 'order' => 'created DESC']);
		$u = [];
		$d1 = date('Y-m-d', strtotime('-7 days'));
		$uxCount = count($ux);
		for ($i = 0; $i < $uxCount; $i++) {
			$date = new DateTime($ux[$i]['User']['created']);
			$date = $date->format('Y-m-d');
			if ($date == $d1) {
			}
		}
		$this->set('u', $d1);
	}

	/**
	 * @return void
	 */
	public function routine5() {//0:25 update user solved field
		$this->loadModel('TsumegoStatus');

		$users = $this->User->find('all', ['limit' => 100, 'order' => 'created DESC']);
		$usersCount = count($users);
		for ($i = 0; $i < $usersCount; $i++) {
			$uid = $users[$i]['User']['id'];
			$ux = $this->User->findById($uid);
			$uts = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => $uid]]);
			$solvedUts = [];
			$utsCount = count($uts);
			for ($j = 0; $j < $utsCount; $j++) {
				if ($uts[$j]['TsumegoStatus']['status'] == 'S' || $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C') {
					array_push($solvedUts, $uts[$j]);
				}
			}
			/*$uts = $this->OldTsumegoStatus->find('all', array('conditions' =>  array('user_id' => $uid)));
			$solvedUts2 = array();
			$utsCount = count($uts);
			for ($j=0; $j<$utsCount; $j++) {
				if ($uts[$j]['OldTsumegoStatus']['status']=='S' || $uts[$j]['OldTsumegoStatus']['status']=='W' || $uts[$j]['OldTsumegoStatus']['status']=='C') {
					array_push($solvedUts2, $uts[$j]);
				}
			}*/
			$ux['User']['solved'] = count($solvedUts);
			$this->User->save($ux);
		}

		$this->set('u', $users);
	}

	/**
	 * @return void
	 */
	public function routine6() {//0:30 update user solved field
		$this->loadModel('Answer');
		$this->loadModel('TsumegoStatus');
		$a = $this->Answer->findById(1);
		$u = $this->User->find('all', [
			'order' => 'id ASC',
			'conditions' => [
				'id >' => $a['Answer']['message'],
				'id <=' => $a['Answer']['dismissed'],
			],
		]);
		$uCount = count($u);
		for ($i = 0; $i < $uCount; $i++) {
			$solvedUts = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => $u[$i]['User']['id'],
					'OR' => [
						['status' => 'S'],
						['status' => 'W'],
						['status' => 'C'],
					],
				],
			]);
			$u[$i]['User']['solved'] = count($solvedUts);
			$this->User->save($u);
		}
		$uLast = $this->User->find('first', ['order' => 'id DESC']);
		if ($uLast['User']['id'] < $a['Answer']['message']) {
			$a['Answer']['message'] = 0;
			$a['Answer']['dismissed'] = 300;
		} else {
			$a['Answer']['message'] += 300;
			$a['Answer']['dismissed'] += 300;
		}
		$this->Answer->save($a);
		$this->set('u', $u);
	}

	/**
	 * @param string|int|null $uid User ID
	 * @return void
	 */
	public function userstats($uid = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'USER STATS');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');
		if ($uid == null) {
			$ur = $this->TsumegoAttempt->find('all', ['limit' => 500, 'order' => 'created DESC']);
		} elseif ($uid == 99) {
			$ur = $this->TsumegoAttempt->find('all', [
				'order' => 'created DESC',
				'conditions' => [
					'tsumego_id >=' => 19752,
					'tsumego_id <=' => 19761,
				],
			]);
		} else {
			$ur = $this->TsumegoAttempt->find('all', ['limit' => 500, 'order' => 'created DESC', 'conditions' => ['user_id' => $uid]]);
		}

		$urCount = count($ur);
		for ($i = 0; $i < $urCount; $i++) {
			$u = $this->User->findById($ur[$i]['TsumegoAttempt']['user_id']);
			$ur[$i]['TsumegoAttempt']['user_name'] = $u['User']['name'];
			$ur[$i]['TsumegoAttempt']['level'] = $u['User']['level'];
			$t = $this->Tsumego->findById($ur[$i]['TsumegoAttempt']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$ur[$i]['TsumegoAttempt']['tsumego_num'] = $t['Tsumego']['num'];
			$ur[$i]['TsumegoAttempt']['tsumego_xp'] = $t['Tsumego']['difficulty'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$ur[$i]['TsumegoAttempt']['set_name'] = $s['Set']['title'];
		}

		$noIndex = false;
		if ($uid != null) {
			$noIndex = true;
		}
		if (isset($this->params['url']['c'])) {
			$this->set('count', 1);
		} else {
			$this->set('count', 0);
		}
		$this->set('noIndex', $noIndex);
		$this->set('ur', $ur);
		$this->set('uid', $uid);
	}

	/**
	 * @param string|int|null $uid User ID
	 * @return void
	 */
	public function userstats2($uid = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'USER STATS');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');
		if ($uid == null) {
			$ur = $this->TsumegoAttempt->find('all', ['limit' => 500, 'order' => 'created DESC']);
		} else {
			$ur = $this->TsumegoAttempt->find('all', ['order' => 'created DESC', 'conditions' => ['user_id' => $uid]]);
		}

		$performance = [];
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

		$urCount = count($ur);
		for ($i = 0; $i < $urCount; $i++) {
			$u = $this->User->findById($ur[$i]['TsumegoAttempt']['user_id']);
			$ur[$i]['TsumegoAttempt']['user_name'] = $u['User']['name'];
			$t = $this->Tsumego->findById($ur[$i]['TsumegoAttempt']['tsumego_id']);
			$ur[$i]['TsumegoAttempt']['tsumego_num'] = $t['Tsumego']['num'];
			$ur[$i]['TsumegoAttempt']['tsumego_xp'] = $t['Tsumego']['difficulty'] * 10;
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$ur[$i]['TsumegoAttempt']['set_name'] = $s['Set']['title'];

			if ($ur[$i]['TsumegoAttempt']['solved'] != 'S' && $ur[$i]['TsumegoAttempt']['solved'] != 'F') {
				$performance['pX']++;
			}

			if ($ur[$i]['TsumegoAttempt']['tsumego_xp'] == 10) {
				if ($ur[$i]['TsumegoAttempt']['solved'] == 'S') {
					$performance['p10S']++;
				} elseif ($ur[$i]['TsumegoAttempt']['solved'] == 'F') {
					$performance['p10F']++;
				}
				$performance['p10']++;
			} elseif ($ur[$i]['TsumegoAttempt']['tsumego_xp'] == 20) {
				if ($ur[$i]['TsumegoAttempt']['solved'] == 'S') {
					$performance['p20S']++;
				} elseif ($ur[$i]['TsumegoAttempt']['solved'] == 'F') {
					$performance['p20F']++;
				}
				$performance['p20']++;
			} elseif ($ur[$i]['TsumegoAttempt']['tsumego_xp'] == 30) {
				if ($ur[$i]['TsumegoAttempt']['solved'] == 'S') {
					$performance['p30S']++;
				} elseif ($ur[$i]['TsumegoAttempt']['solved'] == 'F') {
					$performance['p30F']++;
				}
				$performance['p30']++;
			} elseif ($ur[$i]['TsumegoAttempt']['tsumego_xp'] == 40) {
				if ($ur[$i]['TsumegoAttempt']['solved'] == 'S') {
					$performance['p40S']++;
				} elseif ($ur[$i]['TsumegoAttempt']['solved'] == 'F') {
					$performance['p40F']++;
				}
				$performance['p40']++;
			} elseif ($ur[$i]['TsumegoAttempt']['tsumego_xp'] == 50) {
				if ($ur[$i]['TsumegoAttempt']['solved'] == 'S') {
					$performance['p50S']++;
				} elseif ($ur[$i]['TsumegoAttempt']['solved'] == 'F') {
					$performance['p50F']++;
				}
				$performance['p50']++;
			} elseif ($ur[$i]['TsumegoAttempt']['tsumego_xp'] == 60) {
				if ($ur[$i]['TsumegoAttempt']['solved'] == 'S') {
					$performance['p60S']++;
				} elseif ($ur[$i]['TsumegoAttempt']['solved'] == 'F') {
					$performance['p60F']++;
				}
				$performance['p60']++;
			} elseif ($ur[$i]['TsumegoAttempt']['tsumego_xp'] == 70) {
				if ($ur[$i]['TsumegoAttempt']['solved'] == 'S') {
					$performance['p70S']++;
				} elseif ($ur[$i]['TsumegoAttempt']['solved'] == 'F') {
					$performance['p70F']++;
				}
				$performance['p70']++;
			} elseif ($ur[$i]['TsumegoAttempt']['tsumego_xp'] == 80) {
				if ($ur[$i]['TsumegoAttempt']['solved'] == 'S') {
					$performance['p80S']++;
				} elseif ($ur[$i]['TsumegoAttempt']['solved'] == 'F') {
					$performance['p80F']++;
				}
				$performance['p80']++;
			} elseif ($ur[$i]['TsumegoAttempt']['tsumego_xp'] == 90) {
				if ($ur[$i]['TsumegoAttempt']['solved'] == 'S') {
					$performance['p90S']++;
				} elseif ($ur[$i]['TsumegoAttempt']['solved'] == 'F') {
					$performance['p90F']++;
				}
				$performance['p90']++;
			}
		}

		$noIndex = false;
		if ($uid != null) {
			$noIndex = true;
		}

		$this->set('noIndex', $noIndex);
		$this->set('ur', $ur);
		$this->set('uid', $uid);
		$this->set('performance', $performance);
	}

	/**
	 * @param string|int|null $sid Set ID
	 * @return void
	 */
	public function userstats3($sid = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'USER STATS');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('SetConnection');

		$ts = $this->findTsumegoSet($sid);
		$ids = [];
		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++) {
			array_push($ids, $ts[$i]['Tsumego']['id']);
		}

		if ($sid == null) {
			$ur = $this->TsumegoAttempt->find('all', ['limit' => 500, 'order' => 'created DESC']);
		} else {
			$ur = $this->TsumegoAttempt->find('all', ['order' => 'created DESC', 'conditions' => ['tsumego_id' => $ids]]);
		}

		$urCount = count($ur);
		for ($i = 0; $i < $urCount; $i++) {
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
		if ($sid != null) {
			$noIndex = true;
		}
		if (isset($this->params['url']['c'])) {
			$this->set('count', 1);
		} else {
			$this->set('count', 0);
		}
		$this->set('noIndex', $noIndex);
		$this->set('ur', $ur);
		//$this->set('uid', $uid);
	}

	/**
	 * @param string|int|null $p Page parameter
	 * @return void
	 */
	public function stats($p = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'PAGE STATS');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Comment');
		$this->loadModel('User');
		$this->loadModel('DayRecord');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('AdminActivity');
		$this->loadModel('SetConnection');

		$today = date('Y-m-d', strtotime('today'));

		if (isset($this->params['url']['c'])) {
			$cx = $this->Comment->findById($this->params['url']['c']);
			$cx['Comment']['status'] = $this->params['url']['s'];
			$this->Comment->save($cx);
		}

		$comments = $this->Comment->find('all', ['order' => 'created DESC']);
		$c1 = [];
		$c2 = [];
		$c3 = [];
		$commentsCount = count($comments);
		for ($i = 0; $i < $commentsCount; $i++) {
			if (is_numeric($comments[$i]['Comment']['status'])) {
				if ($comments[$i]['Comment']['status'] == 0) {
					array_push($c1, $comments[$i]);
				}
			}
		}
		$comments = $c1;
		$commentsCount = count($comments);
		for ($i = 0; $i < $commentsCount; $i++) {
			$t = $this->Tsumego->findById($comments[$i]['Comment']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($t['Tsumego']['set_id']);
			if ($s['Set']['public'] == 1) {
				array_push($c2, $comments[$i]);
			} else {
				array_push($c3, $comments[$i]);
			}
		}
		if ($p == 'public') {
			$comments = $c2;
		} else if ($p == 'sandbox') {
			$comments = $c3;
		} else if ($p != 0 && is_numeric($p)) {
			$comments = $this->Comment->find('all', ['order' => 'created DESC', 'conditions' => ['user_id' => $p]]);
		}

		$todaysUsers = [];
		$activity = $this->User->find('all', ['order' => ['User.reuse3 DESC']]);

		$commentsCount = count($comments);
		for ($i = 0; $i < $commentsCount; $i++) {
			$userID = $comments[$i]['Comment']['user_id'];
			$activityCount = count($activity);
			for ($j = 0; $j < $activityCount; $j++) {
				if ($activity[$j]['User']['id'] == $userID) {
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
		}
		$comments2 = [];
		$commentsCount = count($comments);
		for ($i = 0; $i < $commentsCount; $i++) {
			if (is_numeric($comments[$i]['Comment']['status'])) {
				if ($comments[$i]['Comment']['status'] == 0) {
					array_push($comments2, $comments[$i]);
				}
			}
		}
		$comments = $comments2;

		$activityCount = count($activity);
		for ($i = 0; $i < $activityCount; $i++) {
			$a = new DateTime($activity[$i]['User']['created']);
			if ($a->format('Y-m-d') == $today) {
				array_push($todaysUsers, $activity[$i]['User']);
			}
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
	public function uservisits() {
		$this->Session->write('page', 'set');
		$this->Session->write('title', 'User Visits');
		$this->loadModel('Answer');

		$ans = $this->Answer->find('all', ['order' => 'created DESC']);
		$a = [];
		$ansCount = count($ans);
		for ($i = 0; $i < $ansCount; $i++) {
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
	public function duplicates() {
		$this->Session->write('page', 'sandbox');
		$this->Session->write('title', 'Merge Duplicates');
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

		if (isset($this->params['url']['remove'])) {
			$remove = $this->Tsumego->findById($this->params['url']['remove']);
			if ($remove) {
				$remove['Tsumego']['duplicate'] = 0;
				$this->Tsumego->save($remove);
			}
		}
		if (isset($this->params['url']['removeDuplicate'])) {
			$remove = $this->Tsumego->findById($this->params['url']['removeDuplicate']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $remove['Tsumego']['id']]]);
			$remove['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			if (!empty($remove) && $remove['Tsumego']['duplicate'] > 9) {
				$r1 = $this->Tsumego->findById($remove['Tsumego']['duplicate']);
				$r2 = $this->Tsumego->find('all', ['conditions' => ['duplicate' => $remove['Tsumego']['duplicate']]]);
				array_push($r2, $r1);
				if (count($r2) == 2) {
					$r2Count = count($r2);
					for ($i = 0; $i < $r2Count; $i++) {
						$r2[$i]['Tsumego']['duplicate'] = 0;
						$this->Tsumego->save($r2[$i]);
					}
				} elseif (count($r2) > 2) {
					$remove['Tsumego']['duplicate'] = 0;
					$this->Tsumego->save($remove);
				}
				$sx = $this->Set->findById($remove['Tsumego']['set_id']);
				$title = $sx['Set']['title'] . ' - ' . $remove['Tsumego']['num'];
				$adminActivity = [];
				$adminActivity['AdminActivity']['user_id'] = $this->loggedInUserID();
				$adminActivity['AdminActivity']['tsumego_id'] = $this->params['url']['removeDuplicate'];
				$adminActivity['AdminActivity']['file'] = 'settings';
				$adminActivity['AdminActivity']['answer'] = 'Removed duplicate: ' . $title;
				$this->AdminActivity->save($adminActivity);
			} else {
				$aMessage = 'You can\'t remove the main duplicate.';
			}
		}
		if (isset($this->params['url']['main']) && isset($this->params['url']['duplicates'])) {
			$newDuplicates = explode('-', $this->params['url']['duplicates']);
			$newD = [];
			$newDmain = [];
			$checkSc = $this->SetConnection->find('all', ['conditions' => ['tsumego_id' => $this->params['url']['main']]]);
			$errSet = '';
			$errNotNull = '';
			if (count($checkSc) <= 1) {
				$validSc = true;
			} else {
				$validSc = false;
				$errNotNull = 'Already set as duplicate.';
			}
			$newD0check = [];
			$newDuplicatesCount = count($newDuplicates);
			for ($i = 0; $i < $newDuplicatesCount; $i++) {
				$newD0 = $this->Tsumego->findById($newDuplicates[$i]);
				$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $newD0['Tsumego']['id']]]);
				$newD0['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
				array_push($newD0check, $newD0['Tsumego']['set_id']);
			}
			$newD0check = array_count_values($newD0check);
			foreach ($newD0check as $key => $value) {
				if ($value > 1) {
					$validSc = false;
					$errSet = 'You can\'t link duplicates in the same collection.';
				}
			}

			if ($validSc) {
				$newDuplicatesCount = count($newDuplicates);
				for ($i = 0; $i < $newDuplicatesCount; $i++) {
					$newD = $this->Tsumego->findById($newDuplicates[$i]);
					$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $newD['Tsumego']['id']]]);
					$newD['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
					if ($newD['Tsumego']['id'] == $this->params['url']['main']) {
						$newDmain = $newD;
						$newD['Tsumego']['duplicate'] = $this->params['url']['main'];
						$this->Tsumego->save($newD);
					} else {
						$comments = $this->Comment->find('all', ['conditions' => ['tsumego_id' => $newD['Tsumego']['id']]]);
						$commentsCount = count($comments);
						for ($j = 0; $j < $commentsCount; $j++) {
							$this->Comment->delete($comments[$j]['Comment']['id']);
						}
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
					for ($j = 0; $j < $dupDelCount; $j++) {
						$this->Duplicate->delete($dupDel[$j]['Duplicate']['id']);
					}
				}
				$sx = $this->Set->findById($newDmain['Tsumego']['set_id']);
				$title = $sx['Set']['title'] . ' - ' . $newDmain['Tsumego']['num'];
				$adminActivity = [];
				$adminActivity['AdminActivity']['user_id'] = $this->loggedInUserID();
				$adminActivity['AdminActivity']['tsumego_id'] = $this->params['url']['main'];
				$adminActivity['AdminActivity']['file'] = 'settings';
				$adminActivity['AdminActivity']['answer'] = 'Created duplicate group: ' . $title;
				$this->AdminActivity->save($adminActivity);
			}
		}
		if (!empty($this->data['Mark'])) {
			$mark = $this->Tsumego->findById($this->data['Mark']['tsumego_id']);
			if (!empty($mark) && $mark['Tsumego']['duplicate'] == 0) {
				$mark['Tsumego']['duplicate'] = -1;
				$this->Tsumego->save($mark);
			}
		}
		if (!empty($this->data['Mark2'])) {
			$mark = $this->Tsumego->findById($this->data['Mark2']['tsumego_id']);
			$group = $this->Tsumego->findById($this->data['Mark2']['group_id']);

			if ($mark != null && $mark['Tsumego']['duplicate'] == 0 && $group != null) {
				$scTx = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $mark['Tsumego']['id']]]);
				$scTx['SetConnection']['tsumego_id'] = $this->data['Mark2']['group_id'];
				$this->SetConnection->save($scTx);
				$comments = $this->Comment->find('all', ['conditions' => ['tsumego_id' => $mark['Tsumego']['id']]]);
				$commentsCount = count($comments);
				for ($j = 0; $j < $commentsCount; $j++) {
					$this->Comment->delete($comments[$j]['Comment']['id']);
				}
				$this->Tsumego->delete($mark['Tsumego']['id']);
			}
		}

		$marks = $this->Tsumego->find('all', ['conditions' => ['duplicate' => -1]]);
		$marksCount = count($marks);
		for ($i = 0; $i < $marksCount; $i++) {
			array_push($idMap2, $marks[$i]['Tsumego']['id']);
		}
		$uts2 = $this->TsumegoStatus->find('all', ['conditions' => ['tsumego_id' => $idMap2, 'user_id' => $this->loggedInUserID()]]);
		$counter2 = 0;
		$markTooltipSgfs = [];
		$markTooltipInfo = [];
		$markTooltipBoardSize = [];
		$marksCount = count($marks);
		for ($i = 0; $i < $marksCount; $i++) {
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $marks[$i]['Tsumego']['id']]]);
			$marks[$i]['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$s = $this->Set->findById($marks[$i]['Tsumego']['set_id']);
			$marks[$i]['Tsumego']['title'] = $s['Set']['title'] . ' - ' . $marks[$i]['Tsumego']['num'];
			$marks[$i]['Tsumego']['status'] = $uts2[$counter2]['TsumegoStatus']['status'];
			$tts = $this->Sgf->find('all', ['limit' => 1, 'order' => 'version DESC', 'conditions' => ['tsumego_id' => $marks[$i]['Tsumego']['id']]]);
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			$markTooltipSgfs[$i] = $tArr[0];
			$markTooltipInfo[$i] = $tArr[2];
			$markTooltipBoardSize[$i] = $tArr[3];
			$counter2++;
		}

		$sc = $this->SetConnection->find('all');
		$scCount = [];
		$scCount2 = [];
		$scCount = count($sc);
		for ($i = 0; $i < $scCount; $i++) {
			array_push($scCount, $sc[$i]['SetConnection']['tsumego_id']);
		}
		$scCount = array_count_values($scCount);
		foreach ($scCount as $key => $value) {
			if ($value > 1) {
				array_push($scCount2, $key);
			}
		}

		$duplicates1 = [];

		$showAll = false;

		if (isset($this->params['url']['load'])) {
			$showAll = true;
			$counter = 0;
			$scCount2Count = count($scCount2);
			for ($i = 0; $i < $scCount2Count; $i++) {
				$duplicates1[$i] = [];
				$scCount = count($sc);
				for ($j = 0; $j < $scCount; $j++) {
					if ($sc[$j]['SetConnection']['tsumego_id'] == $scCount2[$i]) {
						$scT1 = $this->Tsumego->findById($sc[$j]['SetConnection']['tsumego_id']);
						$scT1['Tsumego']['num'] = $sc[$j]['SetConnection']['num'];
						$scT1['Tsumego']['set_id'] = $sc[$j]['SetConnection']['set_id'];
						$scT1['Tsumego']['status'] = 'N';
						array_push($duplicates1[$i], $scT1);
						array_push($idMap, $scT1['Tsumego']['id']);
					}
				}
			}

			$uts = $this->TsumegoStatus->find('all', ['conditions' => ['tsumego_id' => $idMap, 'user_id' => $this->loggedInUserID()]]);
			$tooltipSgfs = [];
			$tooltipInfo = [];
			$tooltipBoardSize = [];
			$duplicates1Count = count($duplicates1);
			for ($i = 0; $i < $duplicates1Count; $i++) {
				$tooltipSgfs[$i] = [];
				$tooltipInfo[$i] = [];
				$tooltipBoardSize[$i] = [];
				$duplicates1Count = count($duplicates1[$i]);
				for ($j = 0; $j < $duplicates1Count; $j++) {
					$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $duplicates1[$i][$j]['Tsumego']['id'], 'set_id' => $duplicates1[$i][$j]['Tsumego']['set_id']]]);
					$duplicates1[$i][$j]['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
					$s = $this->Set->findById($duplicates1[$i][$j]['Tsumego']['set_id']);
					if ($s != null) {
						$duplicates1[$i][$j]['Tsumego']['title'] = $s['Set']['title'] . ' - ' . $duplicates1[$i][$j]['Tsumego']['num'];
						$duplicates1[$i][$j]['Tsumego']['duplicateLink'] = '?sid=' . $duplicates1[$i][$j]['Tsumego']['set_id'];
						$utsCount = count($uts);
						for ($k = 0; $k < $utsCount; $k++) {
							if ($uts[$k]['TsumegoStatus']['tsumego_id'] == $duplicates1[$i][$j]['Tsumego']['id']) {
								$duplicates1[$i][$j]['Tsumego']['status'] = $uts[$k]['TsumegoStatus']['status'];
							}
						}
						$tts = $this->Sgf->find('all', ['limit' => 1, 'order' => 'version DESC', 'conditions' => ['tsumego_id' => $duplicates1[$i][$j]['Tsumego']['id']]]);
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

	/**
	 * @return void
	 */
	public function uploads() {
		$this->Session->write('page', 'set');
		$this->Session->write('title', 'Uploads');
		$this->loadModel('Sgf');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');

		$s = $this->Sgf->find('all', [
			'limit' => 250,
			'order' => 'created DESC',
			'conditions' => [
				'NOT' => ['user_id' => 33, 'version' => 0],
			],
		]);

		$sCount = count($s);
		for ($i = 0; $i < $sCount; $i++) {
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
			$sDiff = $this->Sgf->find('all', ['order' => 'version DESC', 'limit' => 2, 'conditions' => ['tsumego_id' => $s[$i]['Sgf']['tsumego_id']]]);
			$s[$i]['Sgf']['diff'] = $sDiff[1]['Sgf']['id'];
		}
		$this->set('s', $s);
	}

	/**
	 * @param string|int|null $p Page parameter
	 * @return void
	 */
	public function adminstats($p = null) {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'Admin Panel');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('TsumegoRatingAttempt');
		$this->loadModel('Comment');
		$this->loadModel('User');
		$this->loadModel('DayRecord');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('AdminActivity');
		$this->loadModel('SetConnection');
		$this->loadModel('Tag');
		$this->loadModel('TagName');
		$this->loadModel('Sgf');
		$this->loadModel('UserContribution');
		$this->loadModel('Reject');

		if ($this->isAdmin()) {
			if (isset($this->params['url']['accept']) && isset($this->params['url']['tag_id'])) {
				if (md5((string)$this->loggedInUserID()) == $this->params['url']['hash']) {

					$tagsToApprove = explode('-', $_COOKIE['tagList']);
					$tagsToApproveCount = count($tagsToApprove);
					for ($i = 1; $i < $tagsToApproveCount; $i++) {
						$tagToApprove = $this->Tag->findById(substr($tagsToApprove[$i], 1));
						if ($tagToApprove != null && $tagToApprove['Tag']['approved'] != 1) {
							$this->handleContribution($this->loggedInUserID(), 'reviewed');
							if (substr($tagsToApprove[$i], 0, 1) == 'a') {
								$tagToApprove['Tag']['approved'] = '1';
								$this->Tag->save($tagToApprove);
								$this->handleContribution($tagToApprove['Tag']['user_id'], 'added_tag');
							} else {
								$reject = [];
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

					$tagNamesToApprove = explode('-', $_COOKIE['tagNameList']);
					$tagNamesToApproveCount = count($tagNamesToApprove);
					for ($i = 1; $i < $tagNamesToApproveCount; $i++) {
						$tagNameToApprove = $this->TagName->findById(substr($tagNamesToApprove[$i], 1));
						if ($tagNameToApprove != null && $tagNameToApprove['TagName']['approved'] != 1) {
							$this->handleContribution($this->loggedInUserID(), 'reviewed');
							if (substr($tagNamesToApprove[$i], 0, 1) == 'a') {
								$tagNameToApprove['TagName']['approved'] = '1';
								$this->TagName->save($tagNameToApprove);
								$this->handleContribution($tagNameToApprove['TagName']['user_id'], 'created_tag');
							} else {
								$reject = [];
								$reject['Reject']['user_id'] = $tagNameToApprove['TagName']['user_id'];
								$reject['Reject']['type'] = 'tag name';
								$reject['Reject']['text'] = $tagNameToApprove['TagName']['name'];
								$this->Reject->create();
								$this->Reject->save($reject);
								$this->TagName->delete($tagNameToApprove['TagName']['id']);
							}
						}
					}

					$proposalsToApprove = explode('-', $_COOKIE['proposalList']);
					$proposalsToApproveCount = count($proposalsToApprove);
					for ($i = 1; $i < $proposalsToApproveCount; $i++) {
						$proposalToApprove = $this->Sgf->findById(substr($proposalsToApprove[$i], 1));
						if ($proposalToApprove != null && $proposalToApprove['Sgf']['version'] == 0) {
							$this->handleContribution($this->loggedInUserID(), 'reviewed');
							if (substr($proposalsToApprove[$i], 0, 1) == 'a') {
								$recentSgf = $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $proposalToApprove['Sgf']['tsumego_id']]]);
								$proposalToApprove['Sgf']['version'] = $this->createNewVersionNumber($recentSgf, 0);
								$this->Sgf->save($proposalToApprove);
								$this->handleContribution($proposalToApprove['Sgf']['user_id'], 'made_proposal');
							} else {
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
			}

			if (isset($this->params['url']['delete']) && isset($this->params['url']['hash'])) {
				$toDelete = $this->User->findById($this->params['url']['delete'] / 1111);
				$del1 = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => $toDelete['User']['id']]]);
				$del2 = $this->TsumegoAttempt->find('all', ['conditions' => ['user_id' => $toDelete['User']['id']]]);
				$del3 = $this->TsumegoRatingAttempt->find('all', ['conditions' => ['user_id' => $toDelete['User']['id']]]);
				if (md5($toDelete['User']['name']) == $this->params['url']['hash']) {
					foreach ($del1 as $item) {
						$this->TsumegoStatus->delete($item['TsumegoStatus']['id']);
					}
					foreach ($del2 as $item) {
						$this->TsumegoAttempt->delete($item['TsumegoAttempt']['id']);
					}
					foreach ($del3 as $item) {
						$this->TsumegoRatingAttempt->delete($item['TsumegoRatingAttempt']['id']);
					}
					$this->User->delete($toDelete['User']['id']);
					echo '<pre>';
					print_r('Deleted user ' . $toDelete['User']['name']);
					echo '</pre>';
				}
			}
		}

		$tags = $this->Tag->find('all', ['conditions' => ['approved' => 0]]);
		$tagNames = $this->TagName->find('all', ['conditions' => ['approved' => 0]]);
		$tagsByKey = $this->TagName->find('all');
		$tKeys = [];
		$tagsByKeyCount = count($tagsByKey);
		for ($i = 0; $i < $tagsByKeyCount; $i++) {
			$tKeys[$tagsByKey[$i]['TagName']['id']] = $tagsByKey[$i]['TagName']['name'];
		}

		$tsIds = [];
		$tagTsumegos = [];
		$tagsCount = count($tags);
		for ($i = 0; $i < $tagsCount; $i++) {
			$at = $this->Tsumego->find('first', ['conditions' => ['id' => $tags[$i]['Tag']['tsumego_id']]]);
			array_push($tsIds, $at['Tsumego']['id']);
			array_push($tagTsumegos, $at);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $at['Tsumego']['id']]]);
			$as = $this->Set->find('first', ['conditions' => ['id' => $scT['SetConnection']['set_id']]]);
			$au = $this->User->findById($tags[$i]['Tag']['user_id']);
			$tags[$i]['Tag']['name'] = $tKeys[$tags[$i]['Tag']['tag_name_id']];
			$tags[$i]['Tag']['tsumego'] = $as['Set']['title'] . ' - ' . $at['Tsumego']['num'];
			$tags[$i]['Tag']['user'] = $this->checkPicture($au);
		}
		$tagNamesCount = count($tagNames);
		for ($i = 0; $i < $tagNamesCount; $i++) {
			$au = $this->User->findById($tagNames[$i]['TagName']['user_id']);
			$tagNames[$i]['TagName']['user'] = $this->checkPicture($au);
		}

		$approveSgfs = $this->Sgf->find('all', ['conditions' => ['version' => 0]]);
		$sgfTsumegos = [];
		$latestVersionTsumegos = [];
		$approveSgfsCount = count($approveSgfs);
		for ($i = 0; $i < $approveSgfsCount; $i++) {
			$at = $this->Tsumego->find('first', ['conditions' => ['id' => $approveSgfs[$i]['Sgf']['tsumego_id']]]);
			array_push($latestVersionTsumegos, $this->Sgf->find('first', ['order' => 'version DESC', 'conditions' => ['tsumego_id' => $at['Tsumego']['id']]]));
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
				'user_id' => $this->loggedInUserID(),
				'tsumego_id' => $tsIds,
			],
		]);

		$tsMap = [];
		$utsCount = count($uts);
		for ($i = 0; $i < $utsCount; $i++) {
			$tsMap[$uts[$i]['TsumegoStatus']['tsumego_id']] = $uts[$i]['TsumegoStatus']['status'];
		}

		$tooltipSgfs = [];
		$tooltipInfo = [];
		$tooltipBoardSize = [];
		$tagTsumegosCount = count($tagTsumegos);
		for ($i = 0; $i < $tagTsumegosCount; $i++) {
			$tagTsumegos[$i]['Tsumego']['status'] = $tsMap[$tagTsumegos[$i]['Tsumego']['id']];
			$tts = $this->Sgf->find('all', ['limit' => 1, 'order' => 'version DESC', 'conditions' => ['tsumego_id' => $tagTsumegos[$i]['Tsumego']['id']]]);
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			array_push($tooltipSgfs, $tArr[0]);
			array_push($tooltipInfo, $tArr[2]);
			array_push($tooltipBoardSize, $tArr[3]);
		}
		$tooltipSgfs2 = [];
		$tooltipInfo2 = [];
		$tooltipBoardSize2 = [];
		$sgfTsumegosCount = count($sgfTsumegos);
		for ($i = 0; $i < $sgfTsumegosCount; $i++) {
			$sgfTsumegos[$i]['Tsumego']['status'] = $tsMap[$sgfTsumegos[$i]['Tsumego']['id']];
			$tts = $this->Sgf->find('all', ['limit' => 1, 'order' => 'version DESC', 'conditions' => ['tsumego_id' => $sgfTsumegos[$i]['Tsumego']['id']]]);
			$tArr = $this->processSGF($tts[0]['Sgf']['sgf']);
			array_push($tooltipSgfs2, $tArr[0]);
			array_push($tooltipInfo2, $tArr[2]);
			array_push($tooltipBoardSize2, $tArr[3]);
		}

		$u = $this->User->find('all', ['conditions' => ['isAdmin >' => 0]]);
		$uArray = [];
		$uCount = count($u);
		for ($i = 0; $i < $uCount; $i++) {
			array_push($uArray, $u[$i]['User']['id']);
		}

		$aa = $this->AdminActivity->find('all', ['limit' => 100, 'order' => 'created DESC']);
		$aa2 = [];
		$b1 = [];
		$ca = [];
		$ca['tsumego_id'] = [];
		$ca['tsumego'] = [];
		$ca['created'] = [];
		$ca['name'] = [];
		$ca['answer'] = [];
		$ca['type'] = [];
		$aaCount = count($aa);
		for ($i = 0; $i < $aaCount; $i++) {
			$at = $this->Tsumego->find('first', ['conditions' => ['id' => $aa[$i]['AdminActivity']['tsumego_id']]]);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $at['Tsumego']['id']]]);
			$at['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$as = $this->Set->find('first', ['conditions' => ['id' => $at['Tsumego']['set_id']]]);
			$au = $this->User->find('first', ['conditions' => ['id' => $aa[$i]['AdminActivity']['user_id']]]);
			$aa[$i]['AdminActivity']['name'] = $au['User']['name'];
			$aa[$i]['AdminActivity']['isAdmin'] = $au['User']['isAdmin'];
			$aa[$i]['AdminActivity']['tsumego'] = $as['Set']['title'] . ' - ' . $at['Tsumego']['num'];
			if ($aa[$i]['AdminActivity']['answer'] == 96) {
				$aa[$i]['AdminActivity']['answer'] = 'Approved.';
			}
			if ($aa[$i]['AdminActivity']['answer'] == 97) {
				$aa[$i]['AdminActivity']['answer'] = 'No answer necessary.';
			}
			if ($aa[$i]['AdminActivity']['answer'] == 98) {
				$aa[$i]['AdminActivity']['answer'] = 'Can\'t resolve this.';
			}
			if ($aa[$i]['AdminActivity']['answer'] == 99) {
				$aa[$i]['AdminActivity']['answer'] = 'Deleted.';
			}
			if (!strpos($aa[$i]['AdminActivity']['answer'], '.sgf')) {
				array_push($aa2, $aa[$i]);
				array_push($ca['tsumego_id'], $aa[$i]['AdminActivity']['tsumego_id']);
				array_push($ca['tsumego'], $aa[$i]['AdminActivity']['tsumego']);
				array_push($ca['created'], $aa[$i]['AdminActivity']['created']);
				array_push($ca['name'], $aa[$i]['AdminActivity']['name']);
				array_push($ca['answer'], $aa[$i]['AdminActivity']['answer']);
				array_push($ca['type'], 'Answer');
			} else {
				if ($aa[$i]['AdminActivity']['isAdmin'] > 0) {
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
		$adminComments = $this->Comment->find('all', [
			'order' => 'created DESC',
			'conditions' => [
				'created >' => $aa[count($aa) - 1]['AdminActivity']['created'],
				'user_id' => $uArray,
				'NOT' => [
					'status' => [99],
				],
			],
		]);
		$adminCommentsCount = count($adminComments);
		for ($i = 0; $i < $adminCommentsCount; $i++) {
			$at = $this->Tsumego->find('first', ['conditions' => ['id' => $adminComments[$i]['Comment']['tsumego_id']]]);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $at['Tsumego']['id']]]);
			$at['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$as = $this->Set->find('first', ['conditions' => ['id' => $at['Tsumego']['set_id']]]);
			$au = $this->User->find('first', ['conditions' => ['id' => $adminComments[$i]['Comment']['user_id']]]);
			array_push($ca['tsumego_id'], $adminComments[$i]['Comment']['tsumego_id']);
			array_push($ca['tsumego'], $as['Set']['title'] . ' - ' . $at['Tsumego']['num']);
			array_push($ca['created'], $adminComments[$i]['Comment']['created']);
			array_push($ca['name'], $au['User']['name']);
			array_push($ca['answer'], $adminComments[$i]['Comment']['message']);
			array_push($ca['type'], 'Comment');
		}
		$caCount = count($ca['tsumego_id']);
		for ($i = 0; $i < $caCount; $i++) {
			array_multisort($ca['created'], $ca['tsumego_id'], $ca['tsumego'], $ca['name'], $ca['answer'], $ca['type']);
		}

		$requestDeletion = $this->User->find('all', ['conditions' => ['dbstorage' => 1111]]);

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

	public function login() {
		$this->loadModel('TsumegoStatus');
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'Tsumego Hero - Sign In');

		$clearSession = true;
		if (!empty($this->data)) {
			$clearSession = false;
			$u = $this->User->findByName($this->data['User']['name']);
			if ($u) {
				if ($this->validateLogin($this->data)) {
					$this->signIn($u);
					$this->Session->setFlash(__('Login successful.', true));
					$isLoaded = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => $u['User']['id']]]);

					return $this->redirect(['controller' => 'sets', 'action' => 'index']);
				}

				$this->Session->setFlash(__('Login incorrect.', true));
			} else {
				$this->Session->setFlash(__('Login incorrect.', true));
			}
		} else {
			$clearSession = true;
		}
		$this->set('clearSession', $clearSession);
	}

	public function login2() {
		$this->loadModel('TsumegoStatus');
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'Tsumego Hero - Sign In');
		if (!empty($this->data)) {
			$u = $this->User->findByEmail($this->data['User']['email']);
			if ($u) {
				if ($this->validateLogin2($this->data)) {
					$this->signIn($u);
					$this->Session->setFlash(__('Login successful.', true));
					$isLoaded = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => $u['User']['id']]]);

					return $this->redirect(['controller' => 'sets', 'action' => 'index']);
				}

				$this->Session->setFlash(__('Login incorrect.', true));
			} else {
				$this->Session->setFlash(__('Login incorrect.', true));
			}
		}
	}

	/**
	 * @return void
	 */
	public function loading() {
	}

	public function add() {
		$this->Session->write('page', 'user');
		$this->Session->write('title', 'Tsumego Hero - Sign Up');
		if (!empty($this->data)) {
			$userData = $this->data;
			$userData['User']['pw'] = $this->tinkerEncode($this->data['User']['pw'], 1);
			$userData['User']['pw2'] = $this->tinkerEncode($this->data['User']['pw2'], 1);

			if ($this->data['User']['pw'] == $this->data['User']['pw2']) {
				if (strlen($this->data['User']['pw']) < 4) {
					$userData['User']['pw'] = 'x';
					$userData['User']['pw2'] = 'x';
				}
			}

			$this->User->create();
			if ($this->User->save($userData, true)) {
				if ($this->validateLogin($this->data)) {
					$this->Session->setFlash(__('Registration successful.', true));

					return $this->redirect(['controller' => 'sets', 'action' => 'index']);
				}

				$this->Session->setFlash(__('Login incorrect.', true));
			}
		}
	}

	/**
	 * @return void
	 */
	public function highscore() {
		$this->Session->write('page', 'levelHighscore');
		$this->Session->write('title', 'Tsumego Hero - Highscore');

		$this->loadModel('Tsumego');
		$this->loadModel('Activate');

		$this->saveSolvedNumber($this->loggedInUserID());

		$activate = false;
		if ($this->isLoggedIn()) {
			$activate = $this->Activate->find('first', ['conditions' => ['user_id' => $this->loggedInUserID()]]);
		}

		$json = json_decode(file_get_contents('json/level_highscore.json'), true);

		$uAll = $this->User->find('all', ['limit' => 1250, 'order' => 'level DESC']);
		$uMap = [];
		$uAllCount = count($uAll);
		for ($i = 0; $i < $uAllCount; $i++) {
			$uMap[$uAll[$i]['User']['id']] = $uAll[$i]['User']['name'];
		}
		$jsonCount = count($json);
		for ($i = 0; $i < $jsonCount; $i++) {
			if (isset($uMap[$json[$i]['id']]) && $uMap[$json[$i]['id']]) {
				$json[$i]['name'] = $uMap[$json[$i]['id']];
			}
		}

		$this->set('users', $json);
		$this->set('activate', $activate);
	}

	/**
	 * @return void
	 */
	public function rating() {
		$this->Session->write('page', 'ratingHighscore');
		$this->Session->write('title', 'Tsumego Hero - Rating');

		$this->loadModel('TsumegoStatus');
		$this->loadModel('Tsumego');
		if ($this->isLoggedIn()) {
			$ux = $this->User->findById($this->loggedInUserID());
			$ux['User']['lastHighscore'] = 2;
			$this->User->save($ux);
		}

		$users = $this->User->find('all', [
			'limit' => 1000,
			'order' => 'elo_rating_mode DESC',
			'conditions' => [
				'NOT' => ['id' => [33, 34, 35]],
			],
		]);

		$this->set('users', $users);
	}

	/**
	 * @return void
	 */
	public function added_tags() {
		$this->Session->write('page', 'timeHighscore');
		$this->Session->write('title', 'Tsumego Hero - Added Tags');
		$this->loadModel('UserContribution');

		$list = [];
		$uc = $this->UserContribution->find('all', ['limit' => 100, 'order' => 'score DESC']);
		$ucCount = count($uc);
		for ($i = 0; $i < $ucCount; $i++) {
			$x = [];
			$x['id'] = $uc[$i]['UserContribution']['user_id'];
			$user = $this->User->findById($uc[$i]['UserContribution']['user_id']);
			if ($user && isset($user['User'])) {
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
	public function rewards() {
		$this->loadModel('UserContribution');
		$uc = $this->UserContribution->find('first', ['conditions' => ['user_id' => $this->loggedInUserID()]]);

		if (isset($this->params['url']['action']) && isset($this->params['url']['token'])) {
			if (md5('level') == $this->params['url']['action']) {
				if (md5($uc['UserContribution']['score']) == $this->params['url']['token']) {
					$uc['UserContribution']['reward1'] = 1;
					$this->UserContribution->save($uc);
					$u = $this->User->findById($this->loggedInUserID());
					$u['User']['level'] += 1;
					$u['User']['nextlvl'] += $this->getXPJump($u['User']['level']);
					$u['User']['health'] = $this->getHealth($u['User']['level']);
					$this->Session->read('loggedInUser')['User']['level'] = $u['User']['level'];
					$this->Session->read('loggedInUser')['User']['nextlvl'] = $u['User']['nextlvl'];
					$this->Session->read('loggedInUser')['User']['health'] = $u['User']['health'];
					$this->User->save($u);
					$u = $this->User->findById($this->loggedInUserID());
					$this->Session->write('loggedInUser', $u);
					$this->set('refresh', 'refresh');
				}
			} elseif (md5('rank') == $this->params['url']['action']) {
				if (md5($uc['UserContribution']['score']) == $this->params['url']['token']) {
					$uc['UserContribution']['reward2'] = 1;
					$this->UserContribution->save($uc);
					$u = $this->User->findById($this->loggedInUserID());
					$u['User']['elo_rating_mode'] += 100;
					$this->Session->read('loggedInUser')['User']['elo_rating_mode'] = $u['User']['elo_rating_mode'];
					$this->User->save($u);
					$u = $this->User->findById($this->loggedInUserID());
					$this->Session->write('loggedInUser', $u);
					$this->set('refresh', 'refresh');
				}
			} elseif (md5('heropower') == $this->params['url']['action']) {
				if (md5($uc['UserContribution']['score']) == $this->params['url']['token']) {
					$uc['UserContribution']['reward3'] = 1;
					$this->UserContribution->save($uc);
				}
			} elseif (md5('premium') == $this->params['url']['action']) {
				if (md5($uc['UserContribution']['score']) == $this->params['url']['token']) {
					if (!$this->hasPremium()) {
						$u = $this->User->findById($this->loggedInUserID());
						$u['User']['premium'] = 1;
						$this->Session->read('loggedInUser')['User']['premium'] = $u['User']['premium'];
						$this->User->save($u);
						$u = $this->User->findById($this->loggedInUserID());
						$this->Session->write('loggedInUser', $u);
					}
				}
			}
		}
		$reachedGoals = floor($uc['UserContribution']['score'] / 30);
		$goals = [];
		$goals[0] = $reachedGoals >= 1;
		$goals[1] = $reachedGoals >= 2;
		$goals[2] = $reachedGoals >= 3;
		$goals[3] = $reachedGoals >= 4;
		$goalsColor = [];
		$goalsCount = count($goals);
		for ($i = 0; $i < $goalsCount; $i++) {
			if ($goals[$i]) {
				$goalsColor[$i] = '#e9cc2c';
			} else {
				$goalsColor[$i] = 'black';
			}
		}

		$this->set('goals', $goals);
		$this->set('goalsColor', $goalsColor);
		$this->set('uc', $uc);
	}

	/**
	 * @return void
	 */
	public function achievements() {
		$this->Session->write('page', 'achievementHighscore');
		$this->Session->write('title', 'Tsumego Hero - Achievements Highscore');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Tsumego');
		$this->loadModel('AchievementStatus');
		$this->loadModel('Achievement');
		$this->loadModel('User');

		if ($this->isLoggedIn()) {
			$ux = $this->User->findById($this->loggedInUserID());
			$ux['User']['lastHighscore'] = 2;
			$this->User->save($ux);
		}
		$json = json_decode(file_get_contents('json/achievement_highscore.json'), true);
		$jsonUaIdCount = count($json['uaId']);
		for ($i = $jsonUaIdCount - 1; $i >= $jsonUaIdCount - 100; $i--) {
			$u = $this->User->findById($json['uaId'][$i]);
			if ($u && isset($u['User']['name'])) {
				$json['uaId'][$i] = $u['User']['name'];
			}
		}

		$this->set('uaNum', $json['uaNum']);
		$this->set('uName', $json['uaId']);
		$this->set('aNum', $json['aNum']);
	}

	/**
	 * @return void
	 */
	public function highscore3() {
		$this->Session->write('page', 'timeHighscore');
		$this->Session->write('title', 'Tsumego Hero - Time Highscore');

		$this->loadModel('TsumegoStatus');
		$this->loadModel('Tsumego');
		$this->loadModel('RankOverview');
		$currentRank = '';
		$params1 = '';
		$params2 = '';

		if ($this->isLoggedIn()) {
			$ux = $this->User->findById($this->loggedInUserID());
			$ux['User']['lastHighscore'] = 2;
			$this->User->save($ux);
		}

		if (isset($this->params['url']['category'])) {
			$ro = $this->RankOverview->find('all', [
				'order' => 'points DESC',
				'conditions' => [
					'mode' => $this->params['url']['category'],
					'rank' => $this->params['url']['rank'],
				],
			]);
			$currentRank = $this->params['url']['rank'];
			$params1 = $this->params['url']['category'];
			$params2 = $this->params['url']['rank'];
		} else {
			if ($this->isLoggedIn()) {
				$lastModex = $this->Session->read('loggedInUser')['User']['lastMode'] - 1;
			} else {
				$lastModex = 2;
			}

			$params1 = $lastModex;
			$params2 = '15k';
			$currentRank = $params2;
			$ro = $this->RankOverview->find('all', [
				'order' => 'points DESC',
				'conditions' => [
					'mode' => $params1,
					'rank' => $params2,
				],
			]);
		}
		$roAll = [];
		$roAll['user'] = [];
		$roAll['picture'] = [];
		$roAll['points'] = [];
		$roAll['result'] = [];

		$roCount = count($ro);
		for ($i = 0; $i < $roCount; $i++) {
			$us = $this->User->findById($ro[$i]['RankOverview']['user_id']);
			$alreadyIn = false;
			$roAllCount = count($roAll['user']);
			for ($j = 0; $j < $roAllCount; $j++) {
				if ($roAll['user'][$j] == $us['User']['name']) {
					$alreadyIn = true;
				}
			}
			if (!$alreadyIn) {
				array_push($roAll['user'], $us['User']['name']);
				array_push($roAll['picture'], $us['User']['picture']);
				array_push($roAll['points'], $ro[$i]['RankOverview']['points']);
				array_push($roAll['result'], $ro[$i]['RankOverview']['status']);
			}
		}

		$modes = [];
		$modes[0] = [];
		$modes[1] = [];
		$modes[2] = [];
		for ($i = 0;$i < 3;$i++) {
			$rank = 15;
			$j = 0;
			while ($rank > -5) {
				$kd = 'k';
				$rank2 = $rank;
				if ($rank >= 1) {
					$kd = 'k';
				} else {
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
		for ($i = 0;$i < 3;$i++) {
			$rank = 15;
			$j = 0;
			while ($rank > -5) {
				$kd = 'k';
				$rank2 = $rank;
				if ($rank >= 1) {
					$kd = 'k';
				} else {
					$rank2 = ($rank - 1) * (-1);
					$kd = 'd';
				}
				$modes2[$i][$j] = $rank2 . $kd;
				$rank--;
				$j++;
			}
		}

		$modesCount = count($modes);
		for ($i = 0; $i < $modesCount; $i++) {
			$modesCount = count($modes[$i]);
			for ($j = 0; $j < $modesCount; $j++) {
				$mx = $this->RankOverview->find('first', [
					'conditions' => [
						'rank' => $modes[$i][$j],
						'mode' => $i,
					],
				]);
				if ($mx) {
					$modes[$i][$j] = 1;
				}
			}
		}

		if ($this->isLoggedIn()) {
			$ux = $this->User->findById($this->loggedInUserID());
			$ux['User']['lastHighscore'] = 4;
			$this->User->save($ux);
		}

		$this->set('roAll', $roAll);
		$this->set('rank', $currentRank);
		$this->set('params1', $params1);
		$this->set('params2', $params2);
		$this->set('modes', $modes);
		$this->set('modes2', $modes2);
	}

	/**
	 * @return void
	 */
	public function leaderboard() {
		$this->Session->write('page', 'dailyHighscore');
		$this->Session->write('title', 'Tsumego Hero - Daily Highscore');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Tsumego');
		$this->loadModel('DayRecord');

		$adminsList = $this->User->find('all', ['order' => 'id ASC', 'conditions' => ['isAdmin >' => 0]]);
		if (!$adminsList) {
			$adminsList = [];
		}
		$admins = [];
		$adminsListCount = count($adminsList);
		for ($i = 0; $i < $adminsListCount; $i++) {
			array_push($admins, $adminsList[$i]['User']['name']);
		}
		$dayRecord = $this->DayRecord->find('all', ['limit' => 2, 'order' => 'id DESC']);
		$userYesterdayName = 'Unknown';
		if (count($dayRecord) > 0 && isset($dayRecord[0]['DayRecord']['user_id'])) {
			$userYesterday = $this->User->findById($dayRecord[0]['DayRecord']['user_id']);
			if ($userYesterday && isset($userYesterday['User']['name'])) {
				$userYesterdayName = $userYesterday['User']['name'];
			}
		}
		if ($this->isLoggedIn()) {
			$ux = $this->User->findById($this->loggedInUserID());
			$ux['User']['lastHighscore'] = 3;
			$this->User->save($ux);
		}
		$json = json_decode(file_get_contents('json/daily_highscore.json'), true);
		if (!$json) {
			$json = [];
		}
		$jsonCount = count($json);
		for ($i = 0; $i < $jsonCount; $i++) {
			$u = $this->User->findById($json[$i]['id']);
			if ($u && isset($u['User']['name'])) {
				$json[$i]['name'] = $u['User']['name'];
			}
		}

		$this->set('a', $json);
		$this->set('uNum', count($json));
		$this->set('admins', $admins);
		$this->set('dayRecord', $userYesterdayName);
	}

	/**
	 * @param string|int|null $id
	 * @return void
	 */
	public function view($id = null) {
		$this->Session->write('page', 'user');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('Achievement');
		$this->loadModel('AchievementStatus');
		$this->loadModel('SetConnection');
		$this->loadModel('RankOverview');
		$hideEmail = false;

		$solvedUts2 = $this->saveSolvedNumber($this->loggedInUserID());

		$as = $this->AchievementStatus->find('all', ['limit' => 12, 'order' => 'created DESC', 'conditions' => ['user_id' => $this->loggedInUserID()]]);
		$ach = $this->Achievement->find('all');

		$user = $this->User->findById($id);
		$this->Session->write('title', 'Profile of ' . $user['User']['name']);

		if ($this->loggedInUserID() != $id && $this->loggedInUserID() != 72) {
			$this->Session->write('redirect', 'sets');
			$user['User']['email'] = '';
			$hideEmail = true;
		}
		if (!empty($this->data)) {
			if (isset($this->data['User']['email'])) {
				$changeUser = $user;
				$changeUser['User']['email'] = $this->data['User']['email'];
				$this->set('data', $changeUser['User']['email']);
				$this->User->save($changeUser, true);
				$user = $this->User->findById($id);
			}
		}
		if (isset($this->params['url']['undo'])) {
			if ($this->params['url']['undo'] / 1111 == $id) {
				$user['User']['dbstorage'] = 1;
				$this->Session->read('loggedInUser')['User']['dbstorage'] = $user['User']['dbstorage'];
				$this->User->save($user);
				$user = $this->User->findById($id);
			}
		}

		$tsumegos = $this->SetConnection->find('all');
		if (!$tsumegos) {
			$tsumegos = [];
		}
		$uts = $this->TsumegoStatus->find('all', ['order' => 'created DESC', 'conditions' => ['user_id' => $id]]);
		if (!$uts) {
			$uts = [];
		}
		$tsumegoDates = [];

		$setKeys = [];
		$setArray = $this->Set->find('all', ['conditions' => ['public' => 1]]);
		if (!$setArray) {
			$setArray = [];
		}
		$setArrayCount = count($setArray);
		for ($i = 0; $i < $setArrayCount; $i++) {
			$setKeys[$setArray[$i]['Set']['id']] = $setArray[$i]['Set']['id'];
		}

		$scs = [];
		$tsumegosCount = count($tsumegos);
		for ($j = 0; $j < $tsumegosCount; $j++) {
			if (isset($setKeys[$tsumegos[$j]['SetConnection']['set_id']])) {
				array_push($tsumegoDates, $tsumegos[$j]);
			}
		}
		$tsumegoNum = count($tsumegoDates);
		$solvedUts = [];
		$lastYear = date('Y-m-d', strtotime('-1 year'));
		$dNum = 0;

		$utsCount = count($uts);
		for ($j = 0; $j < $utsCount; $j++) {
			$date = new DateTime($uts[$j]['TsumegoStatus']['created']);
			$uts[$j]['TsumegoStatus']['created'] = $date->format('Y-m-d');
			if ($uts[$j]['TsumegoStatus']['status'] == 'S' || $uts[$j]['TsumegoStatus']['status'] == 'W' || $uts[$j]['TsumegoStatus']['status'] == 'C') {
				$oldest = new DateTime(date('Y-m-d', strtotime('-30 days')));
				if ($uts[$j]['TsumegoStatus']['created'] > $oldest->format('Y-m-d')) {
					array_push($solvedUts, $uts[$j]);
				}
			}
			if ($uts[$j]['TsumegoStatus']['created'] < $lastYear) {
				$dNum++;
			}
		}
		$lvl = 1;
		$toplvl = $user['User']['level'];
		$startxp = 50;
		$sumx = 0;
		$xpJump = 10;

		for ($i = 1; $i < $toplvl; $i++) {
			if ($i >= 11) {
				$xpJump = 25;
			}
			if ($i >= 19) {
				$xpJump = 50;
			}
			if ($i >= 39) {
				$xpJump = 100;
			}
			if ($i >= 69) {
				$xpJump = 150;
			}
			if ($i >= 99) {
				$xpJump = 50000;
			}
			if ($i == 100) {
				$xpJump = 1150;
			}
			if ($i >= 101) {
				$xpJump = 0;
			}
			$sumx += $startxp;
			$startxp += $xpJump;
		}
		$sumx += $user['User']['xp'];

		$oldest = new DateTime(date('Y-m-d', strtotime('-30 days')));
		$oldest = $oldest->format('Y-m-d');
		$ta = $this->TsumegoAttempt->find('all', [
			'limit' => 400,
			'order' => 'created DESC',
			'conditions' => [
				'user_id' => $this->loggedInUserID(),
			],
		]);

		$taBefore = '';
		$graph = [];
		$highestElo = 0;
		$ta2 = [];
		$ta2['date'] = [];
		$ta2['elo'] = [];
		$testCounter = 0;

		$taCount = count($ta);
		for ($i = 0; $i < $taCount; $i++) {
			if ($ta[$i]['TsumegoAttempt']['elo'] != null) {
				if ($ta[$i]['TsumegoAttempt']['elo'] > $highestElo) {
					$highestElo = $taBefore = $ta[$i]['TsumegoAttempt']['elo'];
				}
				array_push($ta2['date'], $ta[$i]['TsumegoAttempt']['created']);
				array_push($ta2['elo'], $ta[$i]['TsumegoAttempt']['elo']);
			}
			if ($ta[$i]['TsumegoAttempt']['mode'] == 1 || $ta[$i]['TsumegoAttempt']['mode'] == 0) {
				$ta[$i]['TsumegoAttempt']['created'] = new DateTime(date($ta[$i]['TsumegoAttempt']['created']));
				$ta[$i]['TsumegoAttempt']['created'] = $ta[$i]['TsumegoAttempt']['created']->format('Y-m-d');
				if ($ta[$i]['TsumegoAttempt']['created'] >= $oldest) {
					if ($taBefore == $ta[$i]['TsumegoAttempt']['created']) {
						if ($ta[$i]['TsumegoAttempt']['solved'] == 1) {
							$graph[$ta[$i]['TsumegoAttempt']['created']]['s']++;
						} else {
							$graph[$ta[$i]['TsumegoAttempt']['created']]['f']++;
						}
						$testCounter++;
					} else {
						$graph[$ta[$i]['TsumegoAttempt']['created']] = [];
						if ($ta[$i]['TsumegoAttempt']['solved'] == 1) {
							$graph[$ta[$i]['TsumegoAttempt']['created']]['s'] = 1;
							$graph[$ta[$i]['TsumegoAttempt']['created']]['f'] = 0;
						} else {
							$graph[$ta[$i]['TsumegoAttempt']['created']]['s'] = 0;
							$graph[$ta[$i]['TsumegoAttempt']['created']]['f'] = 1;
						}
						$taBefore = $ta[$i]['TsumegoAttempt']['created'];
					}
				}
			}
		}

		$eloRank = Rating::getReadableRankFromRating($this->Session->read('loggedInUser')['User']['elo_rating_mode']);
		$highestEloRank = Rating::getReadableRankFromRating($highestElo);

		if ($highestElo < $user['User']['elo_rating_mode']) {
			$highestElo = $user['User']['elo_rating_mode'];
		}

		$timeGraph = [];
		$ro = $this->RankOverview->find('all', [
			'order' => 'rank ASC',
			'conditions' => [
				'user_id' => $this->loggedInUserID(),
			],
		]);
		$highestRo = '15k';
		$roCount = count($ro);
		for ($i = 0; $i < $roCount; $i++) {
			$highestRo = $this->getHighestRo($ro[$i]['RankOverview']['rank'], $highestRo);
			if (isset($timeGraph[$ro[$i]['RankOverview']['rank']][$ro[$i]['RankOverview']['status']])) {
				$timeGraph[$ro[$i]['RankOverview']['rank']][$ro[$i]['RankOverview']['status']]++;
			} else {
				$timeGraph[$ro[$i]['RankOverview']['rank']][$ro[$i]['RankOverview']['status']] = 1;
			}
		}
		$timeGraph = $this->formatTimegraph($timeGraph);

		$p = $user['User']['solved'] / $tsumegoNum * 100;
		$p = round($p);
		if ($p == 100 && $user['User']['solved'] < $tsumegoNum) {
			$p = 99;
		}
		if ($p > 100) {
			$p = 100;
		}

		$deletedProblems = 1;
		if (isset($this->params['url']['delete-uts'])) {
			if ($this->params['url']['delete-uts'] == 'true' && $p >= 75) {
				$utsCount = count($uts);
				for ($j = 0; $j < $utsCount; $j++) {
					if ($uts[$j]['TsumegoStatus']['created'] < $lastYear) {
						$this->TsumegoStatus->delete($uts[$j]['TsumegoStatus']['id']);
					}
				}
				$deletedProblems = 2;
				$utx = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => $id]]);
				$correctCounter = 0;
				$utxCount = count($utx);
				for ($j = 0; $j < $utxCount; $j++) {
					if ($utx[$j]['TsumegoStatus']['status'] == 'S' || $utx[$j]['TsumegoStatus']['status'] == 'W' || $utx[$j]['TsumegoStatus']['status'] == 'C') {
						$correctCounter++;
					}
				}

				$user['User']['solved'] = $correctCounter;
				$user['User']['dbstorage'] = 99;
				$this->User->save($user);

				$p = $user['User']['solved'] / $tsumegoNum * 100;
				$p = round($p);
				if ($p == 100 && $user['User']['solved'] < $tsumegoNum) {
					$p = 99;
				}
				if ($p > 100) {
					$p = 100;
				}
			}
		}

		if ($this->loggedInUserID() != $id) {
			$deletedProblems = 3;
		}

		$asCount = count($as);
		for ($i = 0; $i < $asCount; $i++) {
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
			$achievementUpdate2 ?: [],
		);

		if (count($achievementUpdate) > 0) {
			$this->updateXP($this->loggedInUserID(), $achievementUpdate);
		}
		$aNum = $this->AchievementStatus->find('all', ['conditions' => ['user_id' => $this->loggedInUserID()]]);
		$asx = $this->AchievementStatus->find('first', ['conditions' => ['user_id' => $id, 'achievement_id' => 46]]);
		$aNumx = count($aNum);
		if ($asx != null) {
			$aNumx = $aNumx + $asx['AchievementStatus']['value'] - 1;
		}

		$countGraph = 160 + count($graph) * 25;
		$countTimeGraph = 160 + count($timeGraph) * 25;

		$user['User']['name'] = $this->checkPicture($user);

		if (substr($this->Session->read('loggedInUser')['User']['email'], 0, 3) == 'g__' && $this->Session->read('loggedInUser')['User']['external_id'] != null) {
			$user['User']['email'] = substr($this->Session->read('loggedInUser')['User']['email'], 3);
		}

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

	private function formatTimegraph($graph) {
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
		foreach ($graph as $key => $value) {
			$g[$key] = $value;
		}
		$g2 = [];
		foreach ($g as $key => $value) {
			if ($g[$key] != 0) {
				$g2[$key] = $value;
			}
		}

		return $g2;
	}

	private function getHighestRo($new, $old) {
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
		for ($i = 0; $i < $aCount; $i++) {
			if ($a[$i] == $new) {
				$newNum = $i;
			}
			if ($a[$i] == $old) {
				$oldNum = $i;
			}
		}
		if ($newNum < $oldNum) {
			return $new;
		}

		return $old;
	}

	/**
	 * @param string|int|null $id Donation ID
	 * @return void
	 */
	public function donate($id = null) {
		$this->Session->write('page', 'home');
		$this->Session->write('title', 'Tsumego Hero - Upgrade');

		$overallCounter = 0;
		$sandboxSets = $this->Set->find('all', ['conditions' => ['public' => 0]]);
		$sandboxSetsCount = count($sandboxSets);
		for ($i = 0; $i < $sandboxSetsCount; $i++) {
			$ts = $this->findTsumegoSet($sandboxSets[$i]['Set']['id']);
			$overallCounter += count($ts);
		}

		$setsWithPremium = [];
		$tsumegosWithPremium = [];
		$swp = $this->Set->find('all', ['conditions' => ['premium' => 1]]);
		$swpCount = count($swp);
		for ($i = 0; $i < $swpCount; $i++) {
			array_push($setsWithPremium, $swp[$i]['Set']['id']);
			$twp = $this->findTsumegoSet($swp[$i]['Set']['id']);
			$twpCount = count($twp);
			for ($j = 0; $j < $twpCount; $j++) {
				array_push($tsumegosWithPremium, $twp[$j]);
			}
		}

		$this->set('id', $id);
		$this->set('overallCounter', $overallCounter);
		$this->set('premiumSets', $swp);
		$this->set('premiumTsumegos', count($tsumegosWithPremium));
	}

	/**
	 * @return void
	 */
	public function authors() {
		$this->loadModel('Comment');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');

		$this->Session->write('page', 'about');
		$this->Session->write('title', 'Tsumego Hero - About');

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
		for ($i = 0; $i < $setCount; $i++) {
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
	public function success($id = null) {
		$this->Session->write('page', 'home');
		$this->Session->write('title', 'Tsumego Hero - Success');

		$s = $this->User->findById($this->loggedInUserID());
		$s['User']['reward'] = date('Y-m-d H:i:s');
		$s['User']['premium'] = 1;
		$this->User->create();
		$this->User->save($s);

		$Email = new CakeEmail();
		$Email->from(['me@joschkazimdars.com' => 'https://tsumego-hero.com']);
		$Email->to('joschka.zimdars@googlemail.com');
		$Email->subject('Upgrade');
		if ($this->isLoggedIn()) {
			$ans = $this->Session->read('loggedInUser.User.name') . ' ' . $this->Session->read('loggedInUser')['User']['email'];
		} else {
			$ans = 'no login';
		}
		$Email->send($ans);
		if ($this->isLoggedIn()) {
			$Email = new CakeEmail();
			$Email->from(['me@joschkazimdars.com' => 'https://tsumego-hero.com']);
			$Email->to($this->Session->read('loggedInUser')['User']['email']);
			$Email->subject('Tsumego Hero');
			$ans = '
Hello ' . $this->Session->read('loggedInUser.User.name') . ',

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
	public function penalty($id = null) {
		$this->Session->write('page', 'home');
		$this->Session->write('title', 'Tsumego Hero - Penalty');

		$p = $this->User->findById($this->loggedInUserID());
		$p['User']['penalty'] = $p['User']['penalty'] + 1;
		$this->User->create();
		$this->User->save($p);

		$this->set('id', $id);
	}

	/**
	 * @param string|int|null $id Set ID
	 * @return void
	 */
	public function sets($id = null) {
		$this->set('id', $id);
	}

	/**
	 * @return void
	 */
	public function logout() {
		$this->Session->delete('loggedInUser');
	}

	public function delete($id) {
		$this->loadModel('Comment');
		if ($this->request->is('get')) {
			throw new MethodNotAllowedException();
		}

		if ($this->Comment->delete($id)) {
			$this->Flash->success(
				__('The post with id: %s has been deleted.', h($id)),
			);
		} else {
			$this->Flash->error(
				__('The post with id: %s could not be deleted.', h($id)),
			);
		}

		return $this->redirect(['action' => '/stats']);
	}

	private function validateLogin($data) {
		$u = $this->User->findByName($data['User']['name']);
		if (!$u || !isset($u['User']['pw'])) {
			return false;
		}
		if ($this->tinkerDecode($u['User']['pw'], 1) == $data['User']['pw']) {
			return true;
		}

			return false;
	}

	private function validateLogin2($data) {
		$u = $this->User->findByEmail($data['User']['email']);
		if (!$u || !isset($u['User']['pw'])) {
			return false;
		}
		if ($this->tinkerDecode($u['User']['pw'], 1) == $data['User']['pw']) {
			return true;
		}

			return false;
	}

	private function tinkerEncode($string, $key) {
		if (!is_string($string)) {
			return '';
		}
		 $j = 1.0;
		 $hash = '';
		 $key = sha1((string)$key);
		 $strLen = strlen($string);
		 $keyLen = strlen($key);
		for ($i = 0; $i < $strLen; $i++) {
			$ordStr = ord(substr($string, $i, 1));
			if ($j == $keyLen) {
				$j = 0; }
			$ordKey = ord(substr($key, $j, 1));
			$j++;
			$hash .= strrev(base_convert(dechex($ordStr + $ordKey), 16, 36));
		}

		 return $hash;
	}

	private function tinkerDecode($string, $key) {
		if (!is_string($string)) {
			return '';
		}
		 $j = 1.0;
		 $hash = '';
		 $key = sha1((string)$key);
		 $strLen = strlen($string);
		 $keyLen = strlen($key);
		for ($i = 0; $i < $strLen; $i += 2) {
			$ordStr = hexdec(base_convert(strrev(substr($string, $i, 2)), 36, 16));
			if ($j == $keyLen) {
				$j = 0; }
			$ordKey = ord(substr($key, $j, 1));
			$j++;
			$hash .= chr($ordStr - $ordKey);
		}

		 return $hash;
	}

	/**
	 * @return void
	 */
	public function listplayers() { //list players in ut database
		$this->loadModel('TsumegoStatus');
		$ux = $this->User->find('all', ['limit' => 300, 'order' => 'created DESC']);
		$u = [];
		$uxCount = count($ux);
		for ($i = 0; $i < $uxCount; $i++) {
			$uts1 = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => $ux[$i]['User']['id']]]);
			$date = new DateTime($ux[$i]['User']['created']);
			$date = $date->format('Y-m-d');
			$u[$i][0] = $ux[$i]['User']['id'];
			$u[$i][1] = $date;
		}
		$this->set('u', $u);
	}

	//visualization and set difficulty
	/**
	 * @return void
	 */
	public function tsumego_score() {
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('PurgeList');
		$this->loadModel('SetConnection');

		$pl = $this->PurgeList->find('first', ['order' => 'id DESC']);
		$pl['PurgeList']['set_scores'] = date('Y-m-d H:i:s');
		$this->PurgeList->save($pl);

		$t = null;
		$ur = [];
		$ratio = 0;
		$from = 0;
		$to = 0;
		$sets = $this->Set->find('all', ['conditions' => ['public' => 1]]);
		$xxx = [];
		$setsCount = count($sets);
		for ($i = 0; $i < $setsCount; $i++) {
			$xxx[$i] = [];
			$tsx = $this->findTsumegoSet($sets[$i]['Set']['id']);

			$tsxCount = count($tsx);
			for ($j = 0; $j < $tsxCount; $j++) {
				$tsx[$j]['Tsumego']['set'] = $sets[$i]['Set']['title'];
				$tsx[$j]['Tsumego']['setid'] = $sets[$i]['Set']['id'];
				$tsx[$j]['Tsumego']['multiplier'] = $sets[$i]['Set']['multiplier'];
				array_push($xxx[$i], $tsx[$j]);
			}
		}
		$newTs2 = [];
		$newTs3 = [];
		$jc = 0;

		$avg = [];
		$avg[10] = 83.139212328767;
		$avg[20] = 73.936962406015;
		$avg[30] = 63.582480620155;
		$avg[40] = 57.21416496945;
		$avg[50] = 52.61221;
		$avg[60] = 44.959153094463;
		$avg[70] = 36.729862258953;
		$avg[80] = 31.031111111111;
		$avg[90] = 24.950833333333;

		$setPercent = [];
		$setCount = [];
		$setDifficulty = [];
		$xxxCount = count($xxx);
		for ($i = 0; $i < $xxxCount; $i++) {
			$jc = 0;
			$sp = 0;
			$sc = 0;
			$xxxCount = count($xxx[$i]);
			for ($k = 0; $k < $xxxCount; $k++) {
				$distance = [];

				$sp += $xxx[$i][$k]['Tsumego']['elo_rating_mode'];
				$sc++;

				for ($l = 0; $l < 9; $l++) {
					$xp = ($l + 1) * 10;
					$distance[$l] = $xxx[$i][$k]['Tsumego']['userWin'] - $avg[$xp];
					if ($distance[$l] < 0) {
						$distance[$l] *= -1;
					}
				}
				$lowest = 100;
				$pos = 0;
				$distanceCount = count($distance);
				for ($j = 0; $j < $distanceCount; $j++) {
					if ($distance[$j] < $lowest) {
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
				$newTs3['newxp'][$i][$jc] = ($pos + 1) * 10;
				$newTs3['multiplier'][$i][$jc] = $xxx[$i][$k]['Tsumego']['multiplier'];
				$newTs3['multiplied'][$i][$jc] = ceil($xxx[$i][$k]['Tsumego']['multiplier'] * $newTs3['newxp'][$i][$jc]);

				/*
				if ($newTs3['setid'][$i][$jc]==104) {
					$tsu = $this->Tsumego->findById($newTs3['id'][$i][$jc]);
					$tsu['Tsumego']['difficulty'] = $newTs3['multiplied'][$i][$jc];
					$this->Tsumego->save($tsu);
				}
				*/
				$jc++;
			}
			$setCount[$i] = $sc;
			$setPercent[$i] = round($sp / $sc, 2);

			$distance = [];
			for ($l = 0; $l < 9; $l++) {
				$xp = ($l + 1) * 10;
				$distance[$l] = $setPercent[$i] - $avg[$xp];
				if ($distance[$l] < 0) {
					$distance[$l] *= -1;
				}
			}
			$lowest = 100;
			$pos = 0;
			$distanceCount = count($distance);
			for ($j = 0; $j < $distanceCount; $j++) {
				if ($distance[$j] < $lowest) {
					$pos = $j;
					$lowest = $distance[$j];
				}
			}

			$setDifficulty[$i] = round($setPercent[$i]);
			//$setDifficulty[$i] = $pos+1;
		}

		$newTs3Count = count($newTs3['id']);
		for ($i = 0; $i < $newTs3Count; $i++) {
			array_multisort($newTs3['num'][$i], $newTs3['set'][$i], $newTs3['percent'][$i], $newTs3['id'][$i], $newTs3['xp'][$i], $newTs3['newxp'][$i], $newTs3['count'][$i], $newTs3['multiplier'][$i], $newTs3['multiplied'][$i], $newTs3['setid'][$i]);
		}

		$setDifficultyCount = count($setDifficulty);
		for ($i = 0; $i < $setDifficultyCount; $i++) {
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
	/**
	 * @return void
	 */
	public function tsumego_score2() {
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$avg = [];
		$count = [];
		$ts2 = [];
		$tsx = [];
		$ts = $this->Tsumego->find('all', ['order' => 'userWin ASC']);
		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++) {
			$s = $this->Set->findById($ts[$i]['Tsumego']['set_id']);
			if ($s['Set']['public'] == 1 && $ts[$i]['Tsumego']['userWin'] != 0) {
				$ts[$i]['Tsumego']['userWin'] = round($ts[$i]['Tsumego']['userWin']);
				array_push($ts2, $ts[$i]);
				array_push($tsx, $ts[$i]['Tsumego']['userWin']);
			}
		}

		$this->set('ts', $ts2);
		$this->set('tsx', $tsx);
	}

	//single score without set
	/**
	 * @return void
	 */
	public function single_tsumego_score() {
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Tsumego');

		$ur = $this->TsumegoAttempt->find('all', ['order' => 'created DESC', 'limit' => 1000, 'conditions' => ['tsumego_id' => $this->params['url']['t']]]);
		$t = $this->Tsumego->findById($this->params['url']['t']);

		$ratio = [];
		$ratio['s'] = 0;
		$ratio['f'] = 0;
		$urCount = count($ur);
		for ($i = 0; $i < $urCount; $i++) {
			if ($ur[$i]['TsumegoAttempt']['solved'] == 'S' || $ur[$i]['TsumegoAttempt']['solved'] == 1) {
				$ratio['s']++;
			} elseif ($ur[$i]['TsumegoAttempt']['solved'] == 'F' || $ur[$i]['TsumegoAttempt']['solved'] == 0) {
				$ratio['f']++;
			}
		}
		$ratio['count'] = $ratio['s'] + $ratio['f'];

		$ratio['percent'] = $ratio['s'] / $ratio['count'];
		$ratio['percent'] *= 100;
		$ratio['percent'] = round($ratio['percent'], 2);

		$this->set('t', $t);
		$this->set('ur', $ur);
		$this->set('ratio', $ratio);
	}

	//find average percentages of 10 to 90 xp
	//look if no outliers
	/**
	 * @return void
	 */
	public function avg_tsumego_score() {
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');
		$avg = [];
		$count = [];
		for ($h = 10; $h <= 90; $h += 10) {
			$ts = $this->Tsumego->find('all', ['conditions' => ['difficulty' => $h]]);
			$ts2 = [];
			$counter = 0;
			$sum = 0;

			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++) {
				$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
				$ts[$i]['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

				$s = $this->Set->findById($ts[$i]['Tsumego']['set_id']);

				if ($s['Set']['public'] == 1 && $ts[$i]['Tsumego']['userWin'] != 0) {
					$counter++;
					$sum += $ts[$i]['Tsumego']['userWin'];
					array_push($ts2, $ts[$i]);
				}
			}
			$avg[$h] = $sum / $counter;
			$count[$h] = $counter;
		}

		$tsx = [];
		$tsCount = $this->Tsumego->find('all', ['order' => 'difficulty ASC']);
		$tsCountCount = count($tsCount);
		for ($i = 0; $i < $tsCountCount; $i++) {
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tsCount[$i]['Tsumego']['id']]]);
			$tsCount[$i]['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$s = $this->Set->findById($tsCount[$i]['Tsumego']['set_id']);
			if ($s['Set']['public'] == 1) {
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

		$x1 = $this->Tsumego->find('all', [
			'order' => 'difficulty ASC',
			'conditions' => [
				'difficulty >' => 103,
			],
		]);
		/*
		$x1Count = count($x1);
		for ($i=0; $i<$x1Count; $i++) {
			$s = $this->Set->findById($x1[$i]['Tsumego']['set_id']);
			if ($s['Set']['public']==1) {
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
	/**
	 * @return void
	 */
	public function set_single_tsumego_score() {
		$this->loadModel('Tsumego');

		$ts2 = [];
		$ts3 = [];
		$avg = [];
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
		$distance = [];
		$lowest = 100;
		$pos = 0;
		for ($i = 10; $i <= 90; $i += 10) {
			$distance[$i] = $t['Tsumego']['userWin'] - $avg[$i];
			if ($distance[$i] < 0) {
				$distance[$i] *= -1;
			}
			if ($distance[$i] < $lowest) {
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
	/**
	 * @return void
	 */
	public function set_full_tsumego_scores() {
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Tsumego');
		$this->loadModel('PurgeList');
		$this->loadModel('Set');
		$this->loadModel('Sgf');

		$pl = $this->PurgeList->find('first', ['order' => 'id DESC']);
		$pl['PurgeList']['tsumego_scores'] = date('Y-m-d H:i:s');
		$this->PurgeList->save($pl);

		$ur = [];
		$from = $this->params['url']['t'];
		$to = $this->params['url']['t'] + 10;

		$hightestT = $this->Tsumego->find('first', ['order' => 'id DESC']);
		$hightestT++;
		$ts = $this->Tsumego->find('all', [
			'order' => 'id ASC',
			'conditions' => [
				'id >=' => $from,
				'id <' => $to,
			],
		]);
		$ts2 = $ts;

		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++) {
			$ur = $this->TsumegoAttempt->find('all', ['order' => 'created DESC', 'limit' => 1000, 'conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
			$ratio = [];
			$ratio['s'] = 0;
			$ratio['f'] = 0;
			$urCount = count($ur);
			for ($j = 0; $j < $urCount; $j++) {
				if ($ur[$j]['TsumegoAttempt']['solved'] == 'S' || $ur[$j]['TsumegoAttempt']['solved'] == 1) {
					$ratio['s']++;
				} elseif ($ur[$j]['TsumegoAttempt']['solved'] == 'F' || $ur[$j]['TsumegoAttempt']['solved'] == 0) {
					$ratio['f']++;
				}
			}
			$ts[$i]['Tsumego']['solved'] = $ratio['s'];
			$ts[$i]['Tsumego']['failed'] = $ratio['f'];
			$count = $ts[$i]['Tsumego']['solved'] + $ts[$i]['Tsumego']['failed'];
			$percent = $ts[$i]['Tsumego']['solved'] / $count;
			$percent *= 100;
			$percent = round($percent, 2);
			$ts[$i]['Tsumego']['userWin'] = $percent;
			$ts[$i]['Tsumego']['userLoss'] = $count;

			$newXp = 110 - round($ts[$i]['Tsumego']['userWin']);
			$ts[$i]['Tsumego']['difficultyOld'] = $ts[$i]['Tsumego']['difficulty'];
			$ts[$i]['Tsumego']['difficulty'] = $newXp;

			if ($percent >= 1 && $percent <= 23) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 2500;//$tRank='5d';
			} elseif ($percent <= 26) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 2400;//$tRank='4d';
			} elseif ($percent <= 29) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 2300;//$tRank='3d';
			} elseif ($percent <= 32) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 2200;//$tRank='2d';
			} elseif ($percent <= 35) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 2100;//$tRank='1d';
			} elseif ($percent <= 38) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 2000;//$tRank='1k';
			} elseif ($percent <= 42) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 1900;//$tRank='2k';
			} elseif ($percent <= 46) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 1800;//$tRank='3k';
			} elseif ($percent <= 50) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 1700;//$tRank='4k';
			} elseif ($percent <= 55) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 1600;//$tRank='5k';
			} elseif ($percent <= 60) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 1500;//$tRank='6k';
			} elseif ($percent <= 65) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 1400;//$tRank='7k';
			} elseif ($percent <= 70) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 1300;//$tRank='8k';
			} elseif ($percent <= 75) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 1200;//$tRank='9k';
			} elseif ($percent <= 80) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 1100;//$tRank='10k';
			} elseif ($percent <= 85) {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 1000;//$tRank='11k';
			} else {
				$ts[$i]['Tsumego']['elo_rating_mode'] = 900;
			}

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
	/**
	 * @return void
	 */
	public function set_tsumego_scores() {
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Tsumego');

		$from = $this->params['url']['t'];
		$to = $this->params['url']['t'] + 10;

		$ts = $this->Tsumego->find('all', [
			'order' => 'id ASC',
			'conditions' => [
				'id >' => $from,
				'id <=' => $to,
			],
		]);

		$ur = [];
		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++) {
			$ur = $this->TsumegoAttempt->find('all', ['order' => 'created DESC', 'limit' => 1000, 'conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
			$ratio = [];
			$ratio['s'] = 0;
			$ratio['f'] = 0;
			$urCount = count($ur);
			for ($j = 0; $j < $urCount; $j++) {
				if ($ur[$j]['TsumegoAttempt']['status'] == 'S' || $ur[$j]['TsumegoAttempt']['solved'] == 1) {
					$ratio['s']++;
				} elseif ($ur[$j]['TsumegoAttempt']['status'] == 'F' || $ur[$j]['TsumegoAttempt']['solved'] == 0) {
					$ratio['f']++;
				}
			}
			$ts[$i]['Tsumego']['solved'] = $ratio['s'];
			$ts[$i]['Tsumego']['failed'] = $ratio['f'];
			$this->Tsumego->save($ts[$i]);
		}
		$t = $ts[count($ts) - 1];
		$this->set('t', $t);
		$this->set('ts', $ts);
		$this->set('ur', $ur);
		$this->set('from', $from);
		$this->set('to', $to);
		$this->set('params', $this->params['url']['t']);
	}

	//set userWin(%), userLoss(count)
	/**
	 * @return void
	 */
	public function set_tsumego_scores2() {
		$this->loadModel('Tsumego');

		$ts = $this->Tsumego->find('all');

		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++) {
			$count = $ts[$i]['Tsumego']['solved'] + $ts[$i]['Tsumego']['failed'];
			$percent = $ts[$i]['Tsumego']['solved'] / $count;
			$percent *= 100;
			$percent = round($percent, 2);
			$ts[$i]['Tsumego']['userWin'] = $percent;
			$ts[$i]['Tsumego']['userLoss'] = $count;
			$this->Tsumego->save($ts[$i]);
		}
	}

	//distance to avg, save closest
	/**
	 * @return void
	 */
	public function set_tsumego_scores3() {
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('SetConnection');

		$avg = [];
		$avg[10] = 87.382505764796;
		$avg[20] = 73.129444444444;
		$avg[30] = 64.567987288136;
		$avg[40] = 56.862570224719;
		$avg[50] = 52.194473324213;
		$avg[60] = 45.272835820896;
		$avg[70] = 37.944619771863;
		$avg[80] = 31.759309210526;
		$avg[90] = 21.345405982906;

		$ts2 = [];
		$ts = $this->Tsumego->find('all');
		$tsCount = count($ts);
		for ($i = 0; $i < $tsCount; $i++) {
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
			$ts[$i]['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$s = $this->Set->findById($ts[$i]['Tsumego']['set_id']);
			if ($s['Set']['public'] == 1 && $ts[$i]['Tsumego']['userWin'] != 0) {
				array_push($ts2, $ts[$i]);
			}
		}

		$distance = [];
		$lowest = 100;
		$pos = 0;
		$ts2Count = count($ts2);
		for ($i = 0; $i < $ts2Count; $i++) {
			$distance = [];
			$lowest = 100;
			$pos = 0;
			for ($j = 10; $j <= 90; $j += 10) {
				$distance[$j] = $ts2[$i]['Tsumego']['userWin'] - $avg[$j];
				if ($distance[$j] < 0) {
					$distance[$j] *= -1;
				}
				if ($distance[$j] < $lowest) {
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

	/**
	 * @return void
	 */
	public function activeuts() { //count active uts
		$this->loadModel('TsumegoStatus');
		$ux = $this->User->find('all', ['order' => 'created DESC']);
		$u = [];
		$uxCount = count($ux);
		for ($i = 0; $i < $uxCount; $i++) {
			if ($ux[$i]['User']['dbstorage'] == 1) {
				$uts = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => $ux[$i]['User']['id']]]);
				array_push($u, count($uts));
			}
		}
		$this->set('u', $u);
	}

	/**
	 * @return void
	 */
	public function cleanuts() {
		$this->loadModel('User');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Answer');

		$dbToken = $this->Answer->findById(1);
		$start = $dbToken['Answer']['message'];
		$increment = 200;
		$dbToken['Answer']['message'] += $increment;
		$this->Answer->save($dbToken);

		$end = $start + $increment;
		$u = [];

		$all = $this->User->find('all', ['order' => 'id ASC']);
		for ($i = $start; $i < $end; $i++) {
			$uts = $this->TsumegoStatus->find('first', ['conditions' => ['user_id' => $all[$i]['User']['id']]]);
			array_push($u, $all[$i]);
		}

		$this->set('u', $u);
	}

	/**
	 * @return void
	 */
	public function cleanuts2() {
		$this->loadModel('User');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Answer');

		$dbToken = $this->Answer->findById(1);
		$start = $dbToken['Answer']['message'];
		$increment = 10;
		$dbToken['Answer']['message'] += $increment;
		$this->Answer->save($dbToken);

		$end = $start + $increment;
		$u = [];

		$all = $this->User->find('all', ['order' => 'id ASC']);
		for ($i = $start; $i < $end; $i++) {
			/*
			$uts = $this->TsumegoStatus->find('all', array('conditions' => array('user_id' => $all[$i]['User']['id'])));
			$outs = $this->OldTsumegoStatus->find('all', array('conditions' => array('user_id' => $all[$i]['User']['id'])));

			$idMap = array();
			$status = array();

			$utsCount = count($uts);
			for ($i=0; $i<$utsCount; $i++) {
				array_push($idMap, $uts[$i]['TsumegoStatus']['tsumego_id']);
			}
			$result = array_unique(array_diff_assoc($idMap, array_unique($idMap)));
			if (count($result)==0) $all[$i]['User']['x'] = 'clean';
			else $all[$i]['User']['x'] = 'duplicates';
			*/

			array_push($u, $all[$i]);
		}

		$this->set('u', $u);
	}

	/**
	 * @return void
	 */
	public function playerdb6() { //update solved
		$this->loadModel('TsumegoStatus');
		$this->loadModel('Answer');

		$dbToken = $this->Answer->findById(1);
		$start = $dbToken['Answer']['message'];
		$increment = 100;
		$dbToken['Answer']['message'] += $increment;
		$this->Answer->save($dbToken);

		$ux = $this->User->find('all', [
			'order' => 'id ASC',
			'conditions' => [
				'id >' => $start,
				'id <=' => $start + $increment,
			],
		]);
		$u = [];
		$uxCount = count($ux);
		for ($i = 0; $i < $uxCount; $i++) {
			$ut = $this->TsumegoStatus->find('all', [
				'conditions' => [
					'user_id' => $ux[$i]['User']['id'],
					'OR' => [
						['status' => 'S'],
						['status' => 'W'],
						['status' => 'C'],
					],
				],
			]);
			$c = [];
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

	/**
	 * @param string|int $id User ID
	 * @return void
	 */
	public function purgesingle($id) {
		$this->loadModel('Purge');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('SetConnection');
		$ux = $this->User->findById($id);
		$ut = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => $id]]);
		$st2 = $this->Set->find('all', ['conditions' => ['public' => 1]]);
		$uty = [];
		$keep = 0;
		$deleted = 0;
		$all = count($ut);
		$utCount = count($ut);
		for ($i = 0; $i < $utCount; $i++) {
			array_push($uty, $ut[$i]['TsumegoStatus']['tsumego_id']);
		}
		$utCount = count($ut);
		for ($i = 0; $i < $utCount; $i++) {
			$t = $this->Tsumego->findById($ut[$i]['TsumegoStatus']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$isMain = false;
			$st2Count = count($st2);
			for ($j = 0; $j < $st2Count; $j++) {
				if ($t['Tsumego']['set_id'] == $st2[$j]['Set']['id']) {
					$isMain = true;
				}
			}
			if ($isMain) {
				$keep++;
			} else {
				//$this->TsumegoStatus->delete($ut[$i]['TsumegoStatus']['id']);
				$deleted++;
			}
		}
		$a = array_unique(array_diff_assoc($uty, array_unique($uty)));
		$a = array_values($a);
		$b = [];

		$this->Purge->create();
		$answer = [];
		$answer['Purge']['user_id'] = $id;
		$answer['Purge']['duplicates'] = '|';
		$aCount = count($a);
		for ($i = 0; $i < $aCount; $i++) {
			$utd = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => $id, 'tsumego_id' => $a[$i]]]);
			$c = count($utd);
			$j = 0;
			$b[$i]['uid'] = $id;
			$b[$i]['tid'] = $a[$i];
			$b[$i]['count'] = 1;
			$answer['Purge']['duplicates'] .= $b[$i]['tid'] . '-';
			while ($c > 1) {
				$this->TsumegoStatus->delete($utd[$j]['TsumegoStatus']['id']);
				$b[$i]['count']++;
				if ($c == 2) {
					$answer['Purge']['duplicates'] .= $b[$i]['count'] . '|';
				}
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

	/**
	 * @param string|int $id User ID
	 * @return void
	 */
	private function countsingle($id) {
		$this->loadModel('Purge');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$u = $this->User->findById($id);
		$ut = $this->TsumegoStatus->find('all', ['conditions' => ['user_id' => $id]]);
		$correctCounter1 = 0;
		$utCount = count($ut);
		for ($j = 0; $j < $utCount; $j++) {
			if ($ut[$j]['TsumegoStatus']['status'] == 'S' || $ut[$j]['TsumegoStatus']['status'] == 'W' || $ut[$j]['TsumegoStatus']['status'] == 'C') {
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
		$p = [];
		$p['Purge']['user_id'] = $id;
		$p['Purge']['duplicates'] = '$' . $sum;
		$this->Purge->save($p);
		$this->User->save($u);
	}

	/**
	 * @param string|int $id User ID
	 * @return void
	 */
	public function archivesingle($id) {
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Purge');
		$ux = $this->User->findById($id);
		$p = [];
		$p['Purge']['user_id'] = $id;

		if ($ux == null) {
			$ux['User']['d2'] = 'null';
		} else {
			$d1 = date('Y-m-d', strtotime('-7 days'));
			$date = new DateTime($ux['User']['created']);
			$date = $date->format('Y-m-d');
			$ux['User']['d1'] = $date;
			if ($date < $d1) {
				$ux['User']['d2'] = 'archive';
				$c = count($this->TsumegoStatus->find('all', ['conditions' => ['user_id' => $ux['User']['id']]]) ?: []);
				if ($c == 0) {
					$c = '';
				}
				$p['Purge']['duplicates'] = '-' . $c;
			} else {
				$ux['User']['d2'] = 'ok';
				$p['Purge']['duplicates'] = '+';
			}
		}
		$this->Purge->create();
		$this->Purge->save($p);
		$this->set('ux', $ux);
	}

	/**
	 * @return void
	 */
	public function purgelist() {
		$this->loadModel('Purge');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('PurgeList');

		$pl = $this->PurgeList->find('first', ['order' => 'id DESC']);
		$pl['PurgeList']['purge'] = date('Y-m-d H:i:s');
		$this->PurgeList->save($pl);

		$dbToken = $this->Purge->findById(3);
		$start = $dbToken['Purge']['user_id'];
		$u = $this->User->find('all', ['order' => 'id ASC']);
		$uCount = count($u) + 50;
		if (isset($u[$start]['User']['id'])) {
			$this->purgesingle($u[$start]['User']['id']);
		}
		$dbToken['Purge']['user_id']++;
		$this->Purge->save($dbToken);
		if ($start < $uCount) {
			$this->set('stop', 'f');
		} else {
			$this->set('stop', 't');
		}
		$this->set('x', $u[$start]['User']['id']);
		$this->set('s', $start);
		$this->set('u', $u[$start]);
		$this->set('uCount', $uCount);
	}

	/**
	 * @return void
	 */
	public function countlist() {
		$this->loadModel('Purge');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('PurgeList');

		$pl = $this->PurgeList->find('first', ['order' => 'id DESC']);
		$pl['PurgeList']['count'] = date('Y-m-d H:i:s');
		$this->PurgeList->save($pl);

		$ux = [];
		$dbToken = $this->Purge->findById(2);
		$start = $dbToken['Purge']['user_id'];
		$u = $this->User->find('all', ['order' => 'id ASC']);
		$uCount = count($u) + 50;
		if (isset($u[$start]['User']['id'])) {
			$this->countsingle($u[$start]['User']['id']);
		}
		$dbToken['Purge']['user_id']++;
		$this->Purge->save($dbToken);
		if ($start < $uCount) {
			$this->set('stop', 'f');
		} else {
			$this->set('stop', 't');
		}
		$this->set('s', $start);
		$this->set('u', $u[$start]);
		$this->set('ux', $ux);
		$this->set('uCount', $uCount);
	}

	/**
	 * @return void
	 */
	public function archivelist() {
		$this->loadModel('Purge');
		$this->loadModel('TsumegoStatus');
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('PurgeList');

		$pl = $this->PurgeList->find('first', ['order' => 'id DESC']);
		$pl['PurgeList']['archive'] = date('Y-m-d H:i:s');
		$this->PurgeList->save($pl);

		$ux = [];
		$dbToken = $this->Purge->findById(1);
		$start = $dbToken['Purge']['user_id'];
		$u = $this->User->find('all', ['order' => 'id ASC']);
		$uCount = count($u) + 50;
		if (isset($u[$start]['User']['id'])) {
			$this->archivesingle($u[$start]['User']['id']);
		}
		$dbToken['Purge']['user_id']++;
		$this->Purge->save($dbToken);
		if ($start < $uCount) {
			$this->set('stop', 'f');
		} else {
			$this->set('stop', 't');
		}
		$this->set('s', $start);
		$this->set('u', $u[$start]);
		$this->set('ux', $ux);
		$this->set('uCount', $uCount);
	}

	/**
	 * @return void
	 */
	public function likesview() {
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

		$repPos = $this->Reputation->find('all', ['conditions' => ['value' => 1]]);
		$repPos2 = [];
		$repPos3 = [];
		$repPosCount = count($repPos);
		for ($i = 0; $i < $repPosCount; $i++) {
			$tx = $this->Tsumego->findById($repPos[$i]['Reputation']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tx['Tsumego']['id']]]);
			$tx['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$repPos[$i]['Reputation']['set_id'] = $tx['Tsumego']['set_id'];
			array_push($repPos2, $repPos[$i]['Reputation']['set_id']);
		}
		$repPos3 = array_count_values($repPos2);
		ksort($repPos3);

		$repNeg = $this->Reputation->find('all', ['conditions' => ['value' => -1]]);
		$repNeg2 = [];
		$repNeg3 = [];
		$repNegCount = count($repNeg);
		for ($i = 0; $i < $repNegCount; $i++) {
			$tx = $this->Tsumego->findById($repNeg[$i]['Reputation']['tsumego_id']);
			$repNeg[$i]['Reputation']['set_id'] = $tx['Tsumego']['set_id'];
			array_push($repNeg2, $repNeg[$i]['Reputation']['set_id']);
		}
		$repNeg3 = array_count_values($repNeg2);
		ksort($repNeg3);

		$repSets = [];
		$ii = 0;
		foreach ($repPos3 as $key => $value) {
			$repSets[$ii] = [];
			$repSets[$ii]['set_id'] = $key;
			$repSets[$ii]['pos'] = $value;
			$repSets[$ii]['neg'] = 0;
			$ii++;
		}

		foreach ($repNeg3 as $key => $value) {
			$found = false;
			$repSetsCount = count($repSets);
			for ($i = 0; $i < $repSetsCount; $i++) {
				if ($repSets[$i]['set_id'] == $key) {
					$repSets[$i]['neg'] = $value;
					$found = true;
				}
			}
			if ($found == false) {
				$ax = [];
				$ax['set_id'] = $key;
				$ax['pos'] = 0;
				$ax['neg'] = $value;
				array_push($repSets, $ax);
			}
		}

		$as = [];
		$repSetsCount = count($repSets);
		for ($i = 0; $i < $repSetsCount; $i++) {
			$sx = $this->Set->findById($repSets[$i]['set_id']);
			$repSets[$i]['set_name'] = $sx['Set']['title'] . ' ' . $sx['Set']['title2'];
			array_push($as, $repSets[$i]['set_id']);
		}
		sort($as);

		$all = $this->Reputation->find('all', ['order' => 'created DESC']);
		$allCount = count($all);
		for ($i = 0; $i < $allCount; $i++) {
			$allT = $this->Tsumego->findById($all[$i]['Reputation']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $allT['Tsumego']['id']]]);
			$allT['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$all[$i]['Reputation']['num'] = $allT['Tsumego']['num'];

			$allS = $this->Set->findById($allT['Tsumego']['set_id']);
			$all[$i]['Reputation']['name'] = $allS['Set']['title'];

			$allU = $this->User->findById($all[$i]['Reputation']['user_id']);
			$all[$i]['Reputation']['user'] = $allU['User']['name'];

			if ($all[$i]['Reputation']['value'] == 1) {
				$all[$i]['Reputation']['value'] = 'like';
			} else {
				$all[$i]['Reputation']['value'] = 'dislike';
			}
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

	/**
	 * @param string|int|null $id Set ID
	 * @return void
	 */
	public function i($id = null) {
		$this->loadModel('Set');
		$this->loadModel('Tsumego');
		$this->loadModel('Reputation');
		$this->loadModel('SetConnection');

		$s = $this->Set->findById($id);
		$a = [];

		$r = $this->Reputation->find('all', ['order' => 'created DESC']);
		$rCount = count($r);
		for ($i = 0; $i < $rCount; $i++) {
			$t = $this->Tsumego->findById($r[$i]['Reputation']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			if ($t['Tsumego']['set_id'] == $id) {
				array_push($a, $r[$i]);
			}
		}
		$likes = 0;
		$dislikes = 0;

		$aCount = count($a);
		for ($i = 0; $i < $aCount; $i++) {
			if ($a[$i]['Reputation']['value'] == 1) {
				$likes++;
			} else {
				$dislikes++;
			}

			$u = $this->User->findById($a[$i]['Reputation']['user_id']);
			$a[$i]['Reputation']['user'] = $u['User']['name'];

			$t = $this->Tsumego->findById($a[$i]['Reputation']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$a[$i]['Reputation']['tsumego'] = $s['Set']['title'] . ' ' . $t['Tsumego']['num'];
			$a[$i]['Reputation']['set_id'] = $t['Tsumego']['set_id'];
		}
		$this->set('a', $a);
		$this->set('id', $id);
		$this->set('s', $s);
		$this->set('likes', $likes);
		$this->set('dislikes', $dislikes);
	}

	/**
	 * @return void
	 */
	public function googlesignin() {
		$name = '';
		$email = '';
		$picture = '';
		$id_token = $_POST['credential'];
		$client_id = '842499094931-nt12l2fehajo4k7f39bb44fsjl0l4h6u.apps.googleusercontent.com';
		$token_info = file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . $id_token);
		$token_data = json_decode($token_info, true);
		if (isset($token_data['aud']) && $token_data['aud'] == $client_id) {
			$name = $token_data['name'];
			$email = $token_data['email'];
			$picture = $token_data['picture'];
		} else {
			echo 'Invalid token';
		}
		$externalId = 'g__' . $token_data['sub'];
		$u = $this->User->find('first', ['conditions' => ['external_id' => $externalId]]);
		if ($u == null) {
			$imageUrl = $picture;
			$imageContent = file_get_contents($imageUrl);

			$userData = [];
			$userData['User']['name'] = 'g__' . $name;
			$userData['User']['email'] = 'g__' . $email;
			$userData['User']['pw'] = 'k4y284t2w4v264z2a4t2h464h4x2m5x2t4v2';
			$userData['User']['pw2'] = 'k4y284t2w4v264z2a4t2h464h4x2m5x2t4v2';
			$userData['User']['external_id'] = $externalId;

			if ($imageContent === false) {
				$userData['User']['picture'] = 'default.png';
			} else {
				$userData['User']['picture'] = $externalId . '.png';
				file_put_contents('img/google/' . $externalId . '.png', $imageContent);
			}
			$this->User->create();
			$this->User->save($userData, true);
			$u = $this->User->find('first', ['conditions' => ['external_id' => $externalId]]);
		}
		$this->signIn($u);
		$this->Session->write('redirect', 'sets');

		$this->set('name', $name);
		$this->set('email', $email);
		$this->set('picture', $picture);
	}

	/**
	 * @param string|int|null $id User ID
	 * @return void
	 */
	public function fbsignin($id = null) {
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
				// Get the access token from the request
				$input = json_decode(file_get_contents('php://input'), true);
				$accessToken = $input['accessToken'];

				// Your app credentials
				$app_id = '866506025665869';
				$app_secret = '6f7fd195f177db9fe30205fc52dba785';

				// Exchange the short-lived token for a long-lived one (optional)
				$url = 'https://graph.facebook.com/oauth/access_token?'
						. 'grant_type=fb_exchange_token&'
						. "client_id={$app_id}&"
						. "client_secret={$app_secret}&"
						. "fb_exchange_token={$accessToken}";

				$response = file_get_contents($url);
				$responseData = json_decode($response, true);

				// Get user info from Facebook
				$url = 'https://graph.facebook.com/me?fields=id,name,email&access_token=' . $responseData['access_token'];
				$userInfo = file_get_contents($url);
				$userInfoData = json_decode($userInfo, true);

				// Handle login logic here, such as checking if the user exists in your database
				// and creating a session.

				echo json_encode($userInfoData); // Return user info as JSON
		} else {
				echo json_encode(['error' => 'Invalid request']);
		}
	}

	/**
	 * @return void
	 */
	public function overview1() {
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
		for ($i = 0; $i < $commentsCount; $i++) {
			$u = $this->User->findById($comments[$i]['Comment']['user_id']);
			$comments[$i]['Comment']['user'] = $u['User']['name'];
			$t = $this->Tsumego->findById($comments[$i]['Comment']['tsumego_id']);
			$scT = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $t['Tsumego']['id']]]);
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];

			$s = $this->Set->findById($t['Tsumego']['set_id']);
			$comments[$i]['Comment']['tsumego'] = $s['Set']['title'] . ' ' . $t['Tsumego']['num'];

			$date = new DateTime($comments[$i]['Comment']['created']);

			$comments[$i]['Comment']['created2'] = $date->format('Y-m-d');

			if ($comments[$i]['Comment']['created2'] > $monthBack && $comments[$i]['Comment']['user_id'] != 0) {
				array_push($comments2, $comments[$i]);
			}
		}
		$comments = $comments2;

		$users = [];
		$adminIds = [];

		$commentsCount = count($comments);
		for ($i = 0; $i < $commentsCount; $i++) {
			array_push($users, $comments[$i]['Comment']['user']);
			array_push($adminIds, $comments[$i]['Comment']['admin_id']);
		}
		$adminIds = array_count_values($adminIds);

		$users = array_count_values($users);
		$uValue = [];
		$uName = [];
		foreach ($users as $key => $value) {
			array_push($uValue, $value);
			array_push($uName, $key);
		}
		array_multisort($uValue, $uName);

		$u2 = [];
		$u2['name'] = [];
		$u2['value'] = [];
		$uNameCount = count($uName);
		for ($i = $uNameCount - 1;$i >= 0;$i--) {
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
	public function purge() {
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
		$pl = $this->PurgeList->find('all', ['order' => 'id DESC', 'limit' => 3]);

		if (isset($this->data['Schedule'])) {
			$schedule = [];
			$st = $this->Tsumego->find('first', ['conditions' => ['set_id' => $this->data['Schedule']['set_id_from'], 'num' => $this->data['Schedule']['num']]]);
			$schedule['Schedule']['tsumego_id'] = $st['Tsumego']['id'];
			$schedule['Schedule']['set_id'] = $this->data['Schedule']['set_id_to'];
			$schedule['Schedule']['date'] = $this->data['Schedule']['date'];
			if (is_numeric($this->data['Schedule']['num'])) {
				if ($this->data['Schedule']['num'] > 0) {
					$this->Schedule->save($schedule);
				}
			}
		}

		if (isset($this->params['url']['p'])) {
			if ($this->params['url']['p'] == 1) {
				$p = $this->Purge->find('all');
				$pCount = count($p);
				for ($i = 0; $i < $pCount; $i++) {
					if ($p[$i]['Purge']['id'] != 1 && $p[$i]['Purge']['id'] != 2 && $p[$i]['Purge']['id'] != 3) {
						$this->Purge->delete($p[$i]['Purge']['id']);
					} else {
						$p[$i]['Purge']['user_id'] = 1;
						$this->Purge->save($p[$i]);
					}
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
		}
		$s = $this->Set->find('all', ['conditions' => ['public' => 1]]);
		$de = $this->Set->find('all', ['conditions' => ['public' => -1]]);
		$in = $this->Set->find('all', ['conditions' => ['public' => 0]]);

		$a = [];
		$inCount = count($in);
		for ($i = 0; $i < $inCount; $i++) {
			array_push($a, $in[$i]['Set']['id']);
		}
		$in = $a;

		$a = [];
		$deCount = count($de);
		for ($i = 0; $i < $deCount; $i++) {
			array_push($a, $de[$i]['Set']['id']);
		}
		$de = $a;

		$t = $this->getTsumegoOfTheDay();

		$ans = $this->Answer->find('all', ['limit' => 100, 'order' => 'created DESC']);
		$s = $this->Schedule->find('all', ['limit' => 100, 'order' => 'date DESC']);

		$this->set('t', $t);
		$this->set('ans', $ans);
		$this->set('s', $s);
		$this->set('p', $p);
		$this->set('pl', $pl);
	}

	/**
	 * @return void
	 */
	public function delete_account() {
		$u = null;
		$redirect = false;
		$status = '';
		if ($this->isLoggedIn()) {
			$u = $this->User->findById($this->loggedInUserID());
		}

		if (!empty($this->data)) {
			if (isset($this->data['User']['delete'])) {
				if ($u['User']['pw'] == $this->tinkerEncode($this->data['User']['delete'], 1)) {
					$u['User']['dbstorage'] = 1111;
					$this->Session->read('loggedInUser')['User']['dbstorage'] = $u['User']['dbstorage'];
					$this->User->save($u);
					$redirect = true;
				} else {
					$status = '<p style="color:#d63a49">Password incorrect.</p>';
				}
			}
		}
		$u['User']['name'] = $this->checkPicture($u);

		$this->set('redirect', $redirect);
		$this->set('status', $status);
		$this->set('u', $u);
	}

	/**
	 * @return void
	 */
	public function demote_admin() {
		$u = null;
		$redirect = false;
		$status = '';
		if ($this->isLoggedIn()) {
			$u = $this->User->findById($this->loggedInUserID());
		}

		if (!empty($this->data)) {
			if (isset($this->data['User']['demote'])) {
				if ($u['User']['pw'] == $this->tinkerEncode($this->data['User']['demote'], 1)) {
					$u['User']['isAdmin'] = 0;
					$this->Session->read('loggedInUser')['User']['isAdmin'] = 0;
					$this->User->save($u);
					$redirect = true;
				} else {
					$status = '<p style="color:#d63a49">Password incorrect.</p>';
				}
			}
		}
		$u['User']['name'] = $this->checkPicture($u);

		$this->set('redirect', $redirect);
		$this->set('status', $status);
		$this->set('u', $u);
	}

	/**
	 * @return void
	 */
	public function set_score() {
		$this->loadModel('TsumegoAttempt');
		$this->loadModel('Tsumego');
		$this->loadModel('Set');

		$sets = $this->Set->find('all', ['conditions' => ['public' => 1]]);
		//$sets = $this->Set->find('all', array('conditions' => array('id' => 74761)));
		$ts = [];
		$xxx = [];
		$setsCount = count($sets);
		for ($i = 0; $i < $setsCount; $i++) {
			$xxx[$i] = [];
			//$tsx = $this->Tsumego->find('all', array('conditions' =>  array('set_id' => $sets[$i]['Set']['id'])));
			$tsx = $this->findTsumegoSet($sets[$i]['Set']['id']);
			$tsxCount = count($tsx);
			for ($j = 0; $j < $tsxCount; $j++) {
				$tsx[$j]['Tsumego']['set'] = $sets[$i]['Set']['title'];
				$tsx[$j]['Tsumego']['setid'] = $sets[$i]['Set']['id'];
				$tsx[$j]['Tsumego']['multiplier'] = $sets[$i]['Set']['multiplier'];
				array_push($ts, $tsx[$j]);
				array_push($xxx[$i], $tsx[$j]);
			}
		}
		$newTs2 = [];
		$newTs3 = [];
		$jc = 0;

		$avg = [];
		$avg[10] = 83.139212328767;
		$avg[20] = 73.936962406015;
		$avg[30] = 63.582480620155;
		$avg[40] = 57.21416496945;
		$avg[50] = 52.61221;
		$avg[60] = 44.959153094463;
		$avg[70] = 36.729862258953;
		$avg[80] = 31.031111111111;
		$avg[90] = 24.950833333333;

		$setPercent = [];
		$setCount = [];
		$setDifficulty = [];
		$xxxCount = count($xxx);
		for ($i = 0; $i < $xxxCount; $i++) {
			$jc = 0;
			$sp = 0;
			$sc = 0;
			$xxxCount = count($xxx[$i]);
			for ($k = 0; $k < $xxxCount; $k++) {
				$distance = [];

				$sp += $xxx[$i][$k]['Tsumego']['userWin'];
				$sc++;

				for ($l = 0; $l < 9; $l++) {
					$xp = ($l + 1) * 10;
					$distance[$l] = $xxx[$i][$k]['Tsumego']['userWin'] - $avg[$xp];
					if ($distance[$l] < 0) {
						$distance[$l] *= -1;
					}
				}
				$lowest = 100;
				$pos = 0;
				$distanceCount = count($distance);
				for ($j = 0; $j < $distanceCount; $j++) {
					if ($distance[$j] < $lowest) {
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
				$newTs3['newxp'][$i][$jc] = ($pos + 1) * 10;
				$newTs3['multiplier'][$i][$jc] = $xxx[$i][$k]['Tsumego']['multiplier'];
				$newTs3['multiplied'][$i][$jc] = ceil($xxx[$i][$k]['Tsumego']['multiplier'] * $newTs3['newxp'][$i][$jc]);
				/*
				if ($newTs3['setid'][$i][$jc]==104) {
					$tsu = $this->Tsumego->findById($newTs3['id'][$i][$jc]);
					$tsu['Tsumego']['difficulty'] = $newTs3['multiplied'][$i][$jc];
					$this->Tsumego->save($tsu);
				}
				*/
				$jc++;
			}
			$setCount[$i] = $sc;
			$setPercent[$i] = round($sp / $sc, 2);
			$distance = [];
			for ($l = 0; $l < 9; $l++) {
				$xp = ($l + 1) * 10;
				$distance[$l] = $setPercent[$i] - $avg[$xp];
				if ($distance[$l] < 0) {
					$distance[$l] *= -1;
				}
			}
			$lowest = 100;
			$pos = 0;
			$distanceCount = count($distance);
			for ($j = 0; $j < $distanceCount; $j++) {
				if ($distance[$j] < $lowest) {
					$pos = $j;
					$lowest = $distance[$j];
				}
			}
			$setDifficulty[$i] = $pos + 1;
			/*
			$s = $this->Set->findById($newTs3['setid'][$i][0]);
			$s['Set']['difficulty'] = $setDifficulty[$i];
			$this->Set->save($s);
			*/
		}

		$newTs3Count = count($newTs3['id']);
		for ($i = 0; $i < $newTs3Count; $i++) {
			array_multisort($newTs3['num'][$i], $newTs3['set'][$i], $newTs3['percent'][$i], $newTs3['id'][$i], $newTs3['xp'][$i], $newTs3['newxp'][$i], $newTs3['count'][$i], $newTs3['multiplier'][$i], $newTs3['multiplied'][$i], $newTs3['setid'][$i]);
		}

		//$this->set('t', $t);
		//$this->set('ts', $newTs2);
		$this->set('newTs3', $newTs3);
		$this->set('setPercent', $setPercent);
		$this->set('setCount', $setCount);
		$this->set('setDifficulty', $setDifficulty);
		$this->set('xxx', $xxx);
		//$this->set('ur', $ur);
		//$this->set('ratio', $ratio);
		//$this->set('from', $from);
		//$this->set('to', $to);
		$this->set('sets', $sets);
		$this->set('params', $this->params['url']['t']);
	}

}
