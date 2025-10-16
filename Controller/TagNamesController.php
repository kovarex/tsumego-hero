<?php
class TagNamesController extends AppController{
	public function add(){
		$alreadyExists = false;
		if(isset($this->data['TagName'])){
			$exists = $this->TagName->find('first', array('conditions' => array('name' => $this->data['TagName']['name'])));
			if($exists==null){
				$tn = array();
				$tn['TagName']['name'] = $this->data['TagName']['name'];
				$tn['TagName']['description'] = $this->data['TagName']['description'];
				$tn['TagName']['hint'] = $this->data['TagName']['hint'];
				$tn['TagName']['link'] = $this->data['TagName']['link'];
				$tn['TagName']['user_id'] = $_SESSION['loggedInUser']['User']['id'];
				$tn['TagName']['approved'] = 0;
				$this->TagName->save($tn);
				$saved = $this->TagName->find('first', array('conditions' => array('name' => $this->data['TagName']['name'])));
			}else{
				$alreadyExists = true;
			}
			$this->set('saved', $saved['TagName']['id']);
		}
		$allTags = $this->getAllTags([]);

		$this->set('allTags', $allTags);
		$this->set('alreadyExists', $alreadyExists);
	}
	
	public function view($id=null){
		$tn = $this->TagName->findById($id);
		$allTags = $this->getAllTags([]);
		$user = $this->User->findById($tn['TagName']['user_id']);
		$tn['TagName']['user'] = $user['User']['name'];
		$this->set('allTags', $allTags);
		$this->set('tn', $tn);
	}

	public function user($id){
		$this->loadModel('Sgf');
		$this->loadModel('Reject');

		$list = array();
		$listCreated = array();
		$listType = array();
		$listTid = array();
		$listTsumego = array();
		$listUser = array();
		$listStatus = array();
		$listTag = array();
		
		$u = $this->User->findById($id);
		$tagNames = $this->TagName->find('all', array('limit' => 50, 'order' => 'created DESC', 'conditions' => array('user_id' => $id, 'approved' => 1)));
		$tags = $this->Tag->find('all', array('limit' => 50, 'order' => 'created DESC', 'conditions' => array('user_id' => $id, 'approved' => 1)));
		$proposals = $this->Sgf->find('all', array('limit' => 50, 'order' => 'created DESC', 'conditions' => array(
			'user_id' => $id,
			'NOT' => array('version' => 0)
		)));
		$rejectedProposals = $this->Reject->find('all', array('limit' => 50, 'order' => 'created DESC', 'conditions' => array('user_id' => $id, 'type' => 'proposal')));
		$rejectedTags = $this->Reject->find('all', array('limit' => 50, 'order' => 'created DESC', 'conditions' => array('user_id' => $id, 'type' => 'tag')));
		$rejectedTagNames = $this->Reject->find('all', array('limit' => 50, 'order' => 'created DESC', 'conditions' => array('user_id' => $id, 'type' => 'tag name')));

		for($i=0; $i<count($tagNames); $i++){
			$ux = $this->User->findById($tagNames[$i]['TagName']['user_id']);
			$tagNames[$i]['TagName']['status'] = '<b style="color:#047804">accepted</b>';
			$tagNames[$i]['TagName']['type'] = 'tag name';
			$tagNames[$i]['TagName']['user'] = $ux['User']['name'];

			array_push($listCreated, $tagNames[$i]['TagName']['created']);
			array_push($listType, 'tag name');
			array_push($listTid, '');
			array_push($listTsumego, '');
			array_push($listUser, $tagNames[$i]['TagName']['user']);
			array_push($listStatus, $tagNames[$i]['TagName']['status']);
			array_push($listTag, $tagNames[$i]['TagName']['name']);
		}
		for($i=0; $i<count($rejectedTagNames); $i++){
			$ux = $this->User->findById($rejectedTagNames[$i]['Reject']['user_id']);
			$r = array();
			$r['TagName']['name'] = $rejectedTagNames[$i]['Reject']['text'];
			$r['TagName']['type'] = $rejectedTagNames[$i]['Reject']['type'];
			$r['TagName']['status'] = '<b style="color:#ce3a47">rejected</b>';
			$r['TagName']['created'] = $rejectedTagNames[$i]['Reject']['created'];
			$r['TagName']['user'] = $ux['User']['name'];
			array_push($tagNames, $r);

			array_push($listCreated, $r['TagName']['created']);
			array_push($listType, 'tag name');
			array_push($listTid, '');
			array_push($listTsumego, '');
			array_push($listUser, $r['TagName']['user']);
			array_push($listStatus, $r['TagName']['status']);
			array_push($listTag, $r['TagName']['name']);
		}
		
		for($i=0; $i<count($tags); $i++){
			$tnx = $this->TagName->findById($tags[$i]['Tag']['tag_name_id']);
			$tx = $this->Tsumego->findById($tags[$i]['Tag']['tsumego_id']);
			$scx = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $tx['Tsumego']['id'])));
			$sx = $this->Set->findById($scx['SetConnection']['set_id']);
			$ux = $this->User->findById($tags[$i]['Tag']['user_id']);
			if($tnx['TagName']['name']=='') $tags[$i]['Tag']['tag_name'] = '<i>[not found]</i>';
			else $tags[$i]['Tag']['tag_name'] = $tnx['TagName']['name'];
			$tags[$i]['Tag']['tsumego'] = $sx['Set']['title'].' - '.$tx['Tsumego']['num'];
			$tags[$i]['Tag']['user'] = $ux['User']['name'];
			$tags[$i]['Tag']['type'] = 'tag';
			$tags[$i]['Tag']['status'] = '<b style="color:#047804">accepted</b>';

			array_push($listCreated, $tags[$i]['Tag']['created']);
			array_push($listType, 'tag');
			array_push($listTid, $tags[$i]['Tag']['tsumego_id']);
			array_push($listTsumego, $tags[$i]['Tag']['tsumego']);
			array_push($listUser, $tags[$i]['Tag']['user']);
			array_push($listStatus, $tags[$i]['Tag']['status']);
			array_push($listTag, $tags[$i]['Tag']['tag_name']);
		}
		for($i=0; $i<count($rejectedTags); $i++){
			$tx = $this->Tsumego->findById($rejectedTags[$i]['Reject']['tsumego_id']);
			$scx = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $tx['Tsumego']['id'])));
			$sx = $this->Set->findById($scx['SetConnection']['set_id']);
			$ux = $this->User->findById($rejectedTags[$i]['Reject']['user_id']);
			$r = array();
			$r['Tag']['tsumego_id'] = $rejectedTags[$i]['Reject']['tsumego_id']; 
			$r['Tag']['tag_name'] = $rejectedTags[$i]['Reject']['text']; 
			$r['Tag']['tsumego'] = $sx['Set']['title'].' - '.$tx['Tsumego']['num'];
			$r['Tag']['user'] = $ux['User']['name'];
			$r['Tag']['type'] = $rejectedTags[$i]['Reject']['type'];
			$r['Tag']['status'] = '<b style="color:#ce3a47">rejected</b>';
			$r['Tag']['created'] = $rejectedTags[$i]['Reject']['created'];
			array_push($tags, $r);

			array_push($listCreated, $r['Tag']['created']);
			array_push($listType, 'tag');
			array_push($listTid, $r['Tag']['tsumego_id']);
			array_push($listTsumego, $r['Tag']['tsumego']);
			array_push($listUser, $r['Tag']['user']);
			array_push($listStatus, $r['Tag']['status']);
			array_push($listTag, $r['Tag']['tag_name']);
		}

		for($i=0; $i<count($proposals); $i++){
			$tx = $this->Tsumego->findById($proposals[$i]['Sgf']['tsumego_id']);
			$scx = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $tx['Tsumego']['id'])));
			$sx = $this->Set->findById($scx['SetConnection']['set_id']);
			$ux = $this->User->findById($proposals[$i]['Sgf']['user_id']);
			$proposals[$i]['Sgf']['tsumego'] = $sx['Set']['title'].' - '.$tx['Tsumego']['num'];
			$proposals[$i]['Sgf']['status'] = '<b style="color:#047804">accepted</b>';
			$proposals[$i]['Sgf']['user'] = $ux['User']['name'];
			$proposals[$i]['Sgf']['type'] = 'proposal';
			
			array_push($listCreated, $proposals[$i]['Sgf']['created']);
			array_push($listType, 'proposal');
			array_push($listTid, $proposals[$i]['Sgf']['tsumego_id']);
			array_push($listTsumego, $proposals[$i]['Sgf']['tsumego']);
			array_push($listUser, $proposals[$i]['Sgf']['user']);
			array_push($listStatus, $proposals[$i]['Sgf']['status']);
			array_push($listTag, '');
		}
		for($i=0; $i<count($rejectedProposals); $i++){
			$tx = $this->Tsumego->findById($rejectedProposals[$i]['Reject']['tsumego_id']);
			$scx = $this->SetConnection->find('first', array('conditions' => array('tsumego_id' => $tx['Tsumego']['id'])));
			$sx = $this->Set->findById($scx['SetConnection']['set_id']);
			$ux = $this->User->findById($rejectedProposals[$i]['Reject']['user_id']);
			$r = array();
			$r['Sgf']['tsumego_id'] = $rejectedProposals[$i]['Reject']['tsumego_id']; 
			$r['Sgf']['tsumego'] = $sx['Set']['title'].' - '.$tx['Tsumego']['num'];
			$r['Sgf']['status'] = '<b style="color:#ce3a47">rejected</b>';
			$r['Sgf']['type'] = $rejectedProposals[$i]['Reject']['type'];
			$r['Sgf']['user'] = $ux['User']['name'];
			$r['Sgf']['created'] = $rejectedProposals[$i]['Reject']['created']; 
			array_push($proposals, $r);

			array_push($listCreated, $r['Sgf']['created']);
			array_push($listType, 'proposal');
			array_push($listTid, $r['Sgf']['tsumego_id']);
			array_push($listTsumego, $r['Sgf']['tsumego']);
			array_push($listUser, $r['Sgf']['user']);
			array_push($listStatus, $r['Sgf']['status']);
			array_push($listTag, '');
		}

		array_multisort($listCreated, $listType, $listTid, $listTsumego, $listUser, $listStatus, $listTag);
		$index = 0;
		for($i=count($listCreated)-1; $i>=0; $i--){
			$list[$index]['created'] = $listCreated[$i];
			$list[$index]['type'] = $listType[$i];
			$list[$index]['tsumego_id'] = $listTid[$i];
			$list[$index]['tsumego'] = $listTsumego[$i];
			$list[$index]['user'] = $listUser[$i];
			$list[$index]['status'] = $listStatus[$i];
			$list[$index]['tag'] = $listTag[$i];
			$index++;
		}
		
		$this->set('list', $list);
	}

	public function edit($id=null){
		$tn = $this->TagName->findById($id);
		if(isset($this->data['TagName'])){
			$tn['TagName']['description'] = $this->data['TagName']['description'];
			$tn['TagName']['hint'] = $this->data['TagName']['hint'];
			$tn['TagName']['link'] = $this->data['TagName']['link'];
			$this->TagName->save($tn);
			$this->set('saved', $tn['TagName']['id']);
		}
		$setHint = array();
		if($tn['TagName']['hint'] == 1){
			$setHint[0] = 'checked="checked"';
			$setHint[1] = '';
		}else{
			$setHint[0] = '';
			$setHint[1] = 'checked="checked"';
		}

		$allTags = $this->getAllTags([]);

		$this->set('allTags', $allTags);
		$this->set('setHint', $setHint);
		$this->set('tn', $tn);
	}

	public function delete($id){
		$this->LoadModel('Tag');
		$tn = $this->TagName->findById($id);

		if(isset($this->data['TagName'])){
			if($this->data['TagName']['delete']==$id){
				$tags = $this->Tag->find('all', array('conditions' => array('tag_name_id' => $id)));
				for($i=0; $i<count($tags); $i++)
					$this->Tag->delete($tags[$i]['Tag']['id']);
				$this->TagName->delete($id);
				$this->set('del', 'del');
			}
		}

		$this->set('tn', $tn);
	}

	public function index(){

	}
}




