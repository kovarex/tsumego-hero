<?php
class SgfsController extends AppController {

	public function index(){
		$_SESSION['title'] = 'Tsumego Hero';
		$_SESSION['page'] = 'play';
		$sgfs = $this->Sgf->find('all');
		//echo '<pre>'; print_r($sgfs); echo '</pre>';
		$this->set('sgfs', $sgfs);
  }
	
	public function view($id=null){
		$_SESSION['page'] = 'play';
		$this->loadModel('Tsumego');
		$this->loadModel('Set');
		$this->loadModel('User');
		$this->loadModel('SetConnection');
		$name = '';
		$ux = '';
		$type = 'tsumego';
		$id2 = $id;
		$id /= 1337;
		$dId = array();
		$dTitle = array();
		
		if(isset($this->params['url']['delete'])){
			$sDel = $this->Sgf->findById($this->params['url']['delete']);
			if($_SESSION['loggedInUser']['User']['id'] == $sDel['Sgf']['user_id'])
				$this->Sgf->delete($sDel['Sgf']['id']);
		}
		
		if(isset($this->params['url']['duplicates'])){
			$newDuplicates = explode('-', $this->params['url']['duplicates']);
			for($i=0; $i<count($newDuplicates); $i++){
				$dupl = $this->Tsumego->findById($newDuplicates[$i]);
				$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $dupl['Tsumego']['id'])));
				$dupl['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
				$dSet = $this->Set->findById($dupl['Tsumego']['set_id']);
				array_push($dId, $dupl['Tsumego']['id']);
				array_push($dTitle, $dSet['Set']['title'].' - '.$dupl['Tsumego']['num']);
			}
		}
		
		$t = $this->Tsumego->findById($id);
		$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
		$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
		$set = $this->Set->findById($t['Tsumego']['set_id']);
		$name = $set['Set']['title'].' '.$set['Set']['title2'].' '.$t['Tsumego']['num'];
		$_SESSION['title'] = 'Upload History of '.$name;
		
		if(isset($this->params['url']['user'])){
			$s = $this->Sgf->find('all', array('order' => 'version DESC','limit' => 100,'conditions' => array(
				'user_id' => $this->params['url']['user'],
				'NOT' => array('version' => 0)
			)));
			$type = 'user';
		}else{
			$s = $this->Sgf->find('all', array('order' => 'version DESC','limit' => 100,'conditions' => array(
				'tsumego_id' => $id,
				'NOT' => array('version' => 0)
			)));
		}
		
		for($i=0; $i<count($s); $i++){
			$s[$i]['Sgf']['sgf'] = str_replace("\r", '', $s[$i]['Sgf']['sgf']);
			$s[$i]['Sgf']['sgf'] = str_replace("\n", '"+"\n"+"', $s[$i]['Sgf']['sgf']);
			
			$u = $this->User->findById($s[$i]['Sgf']['user_id']);
			$s[$i]['Sgf']['user'] = $u['User']['name'];
			$ux = $u['User']['name'];
			$t = $this->Tsumego->findById($s[$i]['Sgf']['tsumego_id']);
			$scT = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $t['Tsumego']['id'])));
			$t['Tsumego']['set_id'] = $scT['SetConnection']['set_id'];
			$set = $this->Set->findById($t['Tsumego']['set_id']);
			$s[$i]['Sgf']['title'] = $set['Set']['title'].' '.$set['Set']['title2'].' #'.$t['Tsumego']['num'];;
			$s[$i]['Sgf']['num'] = $t['Tsumego']['num'];
			
			if($s[$i]['Sgf']['user']=='noUser') 
				$s[$i]['Sgf']['user'] = 'automatically generated';
			if($_SESSION['loggedInUser']['User']['id'] == $s[$i]['Sgf']['user_id'])
				$s[$i]['Sgf']['delete'] = true;
			else
				$s[$i]['Sgf']['delete'] = false;
			if($type=='user'){
				$sDiff = $this->Sgf->find('all', array('order' => 'version DESC','limit' => 2,'conditions' => array('tsumego_id' => $s[$i]['Sgf']['tsumego_id'])));
				$s[$i]['Sgf']['diff'] = $sDiff[1]['Sgf']['id'];
			}else{
				if($i!=count($s)-1) 
					$s[$i]['Sgf']['diff'] = $s[$i+1]['Sgf']['id'];
			}
		}
		
		$this->set('ux', $ux);
		$this->set('type', $type);
		$this->set('name', $name);
		$this->set('s', $s);
		$this->set('id', $id);
		$this->set('id2', $id2);
		$this->set('tNum', $t['Tsumego']['num']);
		$this->set('dId', $dId);
		$this->set('dTitle', $dTitle);
	}
	
}




