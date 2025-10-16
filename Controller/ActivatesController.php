<?php
App::uses('CakeEmail', 'Network/Email');
class ActivatesController extends AppController{

	public function index(){
		$_SESSION['page'] = 'home';
		$_SESSION['title'] = 'Tsumego Hero - Activate';
		$this->loadModel('User');
		
		
		
		/*
		$this->Activate->create();
		$s = $this->rdm();
		$a = array();
		$a['Activate']['string'] = $s;
		$this->Activate->save($a);
		
		$this->Activate->create();
		$s = $this->rdm();
		$a = array();
		$a['Activate']['string'] = $s;
		$this->Activate->save($a);
		
		for($i=3; $i<14; $i++){
			$this->Activate->create();
			$s = $this->rdm();
			$a = array();
			$a['Activate']['id'] = $i;
			$a['Activate']['string'] = $s;
			$this->Activate->save($a);
		}
		*/
		
		$us = $this->User->find('all', array('conditions' =>  array(
			'premium' => 2,
			'NOT' => array(
				'id' => array(2781, 4580, 1543, 1206, 453, 4363, 4275, 72, 73, 81, 87, 89, 94)
			)
		)));
		
		$us2 = $this->User->find('all', array('conditions' =>  array(
			'OR' => array(
				array('id' => 88),
				array('id' => 4370)
			)
		)));
		
		
		
		
		for($i=0; $i<count($us); $i++){
			/*
			$this->Activate->create();
			$s = $this->rdm();
			$a = array();
			$a['Activate']['string'] = $s;
			$this->Activate->save($a);
			
			
			$Email = new CakeEmail();
			$Email->from(array('me@joschkazimdars.com' => 'https://tsumego-hero.com'));
			$Email->to($us[$i]['User']['email']);
			$Email->subject('Tsumego Hero - key for rating mode');
			$ans = '
Hello '.$us[$i]['User']['name'].',

this is an invitation for the new rating mode on Tsumego Hero. The rating mode gives you a rank for your ability to solve tsumego. The system is based on elo rating, which is used for tournaments in chess, go and other games. As in tournaments players get opponents around their rank, the rating mode matches you with go problems around your skill level.

In the next few weeks, we evaluate the user data and try to find the best configuration. After that, the highscore will be reset and more users can play it. To have a bit of competition, the first three places in the highscore at the end of the beta phase get a secret area that has never been published. It contains 6 extremely hard problems for 2000 XP each and a board design.

Here is your key: '.$a['Activate']['string'].'

-- 
Best Regards
Joschka Zimdars

';
			$Email->send($ans);*/
		}	
		
		
		
		$key = 0;
		if(!empty($this->data)){
			$ac = $this->Activate->find('first', array('conditions' =>  array('string' => $this->data['Activate']['Key'])));
			if($ac){ 
				$ac['Activate']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
				$this->Activate->save($ac);
				$key = 1;
			}else{
				$key = 2;
			}
		}
		
		if($this->Activate->find('first', array('conditions' =>  array('user_id' => $_SESSION['loggedInUser']['User']['id'])))) $key = 1;
		
		$u = $this->User->findById($_SESSION['loggedInUser']['User']['id']);
		$u['User']['readingTrial'] = 30;
		$this->User->save($u);
		
		$this->set('key', $key);
		$this->set('a', $a);
		$this->set('s', $s);
		$this->set('us', $us);
		$this->set('us2', $us2);
    }
	
	
	private function rdm(){
		$length = 15;
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
}




