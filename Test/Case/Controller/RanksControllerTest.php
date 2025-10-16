<?php
class RanksControllerTest extends ControllerTestCase  {

	public function overview(){
		$this->loadModel('Tsumego');
		$this->loadModel('User');
		$this->loadModel('RankOverview');
		$this->loadModel('RankSetting');
		$this->loadModel('Set');
		$_SESSION['title'] = 'Time Mode - Select';
		$_SESSION['page'] = 'time mode';
		
		
	}
	
	public function result($hash=null){
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('RankOverview');
		$_SESSION['title'] = 'Time Mode - Result';
		$_SESSION['page'] = 'time mode';
		$sess = $_SESSION['loggedInUser']['User']['activeRank'];
		$_SESSION['loggedInUser']['User']['activeRank'] = 0;
		$_SESSION['loggedInUser']['User']['mode'] = 1;
		
		
	}

	private function calculatePoints($time=null, $max=null){
		$rx = 0;
		if($max==240){
			$rx = 20+round($time/3);
		}elseif($max==60){
			$rx = 40+round($time);
		}elseif($max==30){
			$rx = 40+round($time*2);
		}
		return $rx;
	}
	
}




