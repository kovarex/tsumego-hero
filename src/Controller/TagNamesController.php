<?php
class TagNamesController extends AppController {

	/**
	 * @return void
	 */
	public function add() {
		$alreadyExists = false;
		if (isset($this->data['TagName'])) {
			$exists = $this->TagName->find('first', ['conditions' => ['name' => $this->data['TagName']['name']]]);
			if ($exists == null) {
				$tn = [];
				$tn['TagName']['name'] = $this->data['TagName']['name'];
				$tn['TagName']['description'] = $this->data['TagName']['description'];
				$tn['TagName']['hint'] = $this->data['TagName']['hint'];
				$tn['TagName']['link'] = $this->data['TagName']['link'];
				$tn['TagName']['user_id'] = $this->loggedInUserID();
				$tn['TagName']['approved'] = 0;
				$this->TagName->save($tn);
				$saved = $this->TagName->find('first', ['conditions' => ['name' => $this->data['TagName']['name']]]);
				if ($saved) {
					$this->set('saved', $saved['TagName']['id']);
				}
			} else {
				$alreadyExists = true;
			}
		}
		$allTags = $this->getAllTags([]);

		$this->set('allTags', $allTags);
		$this->set('alreadyExists', $alreadyExists);
	}

	/**
	 * @return void
	 */
	public function view($id = null) {
		$tn = $this->TagName->findById($id);
		$allTags = $this->getAllTags([]);
		$user = $this->User->findById($tn['TagName']['user_id']);
		$tn['TagName']['user'] = $user['User']['name'];
		$this->set('allTags', $allTags);
		$this->set('tn', $tn);
	}

	/**
	 * @return void
	 */
	public function user($id) {
		$this->loadModel('Sgf');
		$this->loadModel('Reject');

		$list = [];
		$listCreated = [];
		$listType = [];
		$listTid = [];
		$listTsumego = [];
		$listUser = [];
		$listStatus = [];
		$listTag = [];

		$u = $this->User->findById($id);
		$tagNames = $this->TagName->find('all', ['limit' => 50, 'order' => 'created DESC', 'conditions' => ['user_id' => $id, 'approved' => 1]]);
		if (!$tagNames) {
			$tagNames = [];
		}
		$tags = $this->Tag->find('all', ['limit' => 50, 'order' => 'created DESC', 'conditions' => ['user_id' => $id, 'approved' => 1]]);
		if (!$tags) {
			$tags = [];
		}
		$proposals = $this->Sgf->find('all', [
			'limit' => 50,
			'order' => 'created DESC',
			'conditions' => [
				'user_id' => $id,
				'NOT' => ['version' => 0],
			],
		]);
		if (!$proposals) {
			$proposals = [];
		}
		$rejectedProposals = $this->Reject->find('all', ['limit' => 50, 'order' => 'created DESC', 'conditions' => ['user_id' => $id, 'type' => 'proposal']]);
		if (!$rejectedProposals) {
			$rejectedProposals = [];
		}
		$rejectedTags = $this->Reject->find('all', ['limit' => 50, 'order' => 'created DESC', 'conditions' => ['user_id' => $id, 'type' => 'tag']]);
		if (!$rejectedTags) {
			$rejectedTags = [];
		}
		$rejectedTagNames = $this->Reject->find('all', ['limit' => 50, 'order' => 'created DESC', 'conditions' => ['user_id' => $id, 'type' => 'tag name']]);
		if (!$rejectedTagNames) {
			$rejectedTagNames = [];
		}

		$tagNamesCount = count($tagNames);
		for ($i = 0; $i < $tagNamesCount; $i++) {
			$ux = $this->User->findById($tagNames[$i]['TagName']['user_id']);
			$tagNames[$i]['TagName']['status'] = '<b style="color:#047804">accepted</b>';
			$tagNames[$i]['TagName']['type'] = 'tag name';
			$tagNames[$i]['TagName']['user'] = $ux['User']['name'];

			$listCreated[] = $tagNames[$i]['TagName']['created'];
			$listType[] = 'tag name';
			$listTid[] = '';
			$listTsumego[] = '';
			$listUser[] = $tagNames[$i]['TagName']['user'];
			$listStatus[] = $tagNames[$i]['TagName']['status'];
			$listTag[] = $tagNames[$i]['TagName']['name'];
		}
		$rejectedTagNamesCount = count($rejectedTagNames);
		for ($i = 0; $i < $rejectedTagNamesCount; $i++) {
			$ux = $this->User->findById($rejectedTagNames[$i]['Reject']['user_id']);
			$r = [];
			$r['TagName']['name'] = $rejectedTagNames[$i]['Reject']['text'];
			$r['TagName']['type'] = $rejectedTagNames[$i]['Reject']['type'];
			$r['TagName']['status'] = '<b style="color:#ce3a47">rejected</b>';
			$r['TagName']['created'] = $rejectedTagNames[$i]['Reject']['created'];
			$r['TagName']['user'] = $ux['User']['name'];
			$tagNames[] = $r;

			$listCreated[] = $r['TagName']['created'];
			$listType[] = 'tag name';
			$listTid[] = '';
			$listTsumego[] = '';
			$listUser[] = $r['TagName']['user'];
			$listStatus[] = $r['TagName']['status'];
			$listTag[] = $r['TagName']['name'];
		}

		$tagsCount = count($tags);
		for ($i = 0; $i < $tagsCount; $i++) {
			$tnx = $this->TagName->findById($tags[$i]['Tag']['tag_name_id']);
			$tx = $this->Tsumego->findById($tags[$i]['Tag']['tsumego_id']);
			$scx = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tx['Tsumego']['id']]]);
			if (!$scx) {
				continue;
			}
			$sx = $this->Set->findById($scx['SetConnection']['set_id']);
			$ux = $this->User->findById($tags[$i]['Tag']['user_id']);
			if ($tnx['TagName']['name'] == '') { $tags[$i]['Tag']['tag_name'] = '<i>[not found]</i>';
			} else { $tags[$i]['Tag']['tag_name'] = $tnx['TagName']['name'];
			}
			$tags[$i]['Tag']['tsumego'] = $sx['Set']['title'] . ' - ' . $tx['Tsumego']['num'];
			$tags[$i]['Tag']['user'] = $ux['User']['name'];
			$tags[$i]['Tag']['type'] = 'tag';
			$tags[$i]['Tag']['status'] = '<b style="color:#047804">accepted</b>';

			$listCreated[] = $tags[$i]['Tag']['created'];
			$listType[] = 'tag';
			$listTid[] = $tags[$i]['Tag']['tsumego_id'];
			$listTsumego[] = $tags[$i]['Tag']['tsumego'];
			$listUser[] = $tags[$i]['Tag']['user'];
			$listStatus[] = $tags[$i]['Tag']['status'];
			$listTag[] = $tags[$i]['Tag']['tag_name'];
		}
		$rejectedTagsCount = count($rejectedTags);
		for ($i = 0; $i < $rejectedTagsCount; $i++) {
			$tx = $this->Tsumego->findById($rejectedTags[$i]['Reject']['tsumego_id']);
			$scx = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tx['Tsumego']['id']]]);
			if (!$scx) {
				continue;
			}
			$sx = $this->Set->findById($scx['SetConnection']['set_id']);
			$ux = $this->User->findById($rejectedTags[$i]['Reject']['user_id']);
			$r = [];
			$r['Tag']['tsumego_id'] = $rejectedTags[$i]['Reject']['tsumego_id'];
			$r['Tag']['tag_name'] = $rejectedTags[$i]['Reject']['text'];
			$r['Tag']['tsumego'] = $sx['Set']['title'] . ' - ' . $tx['Tsumego']['num'];
			$r['Tag']['user'] = $ux['User']['name'];
			$r['Tag']['type'] = $rejectedTags[$i]['Reject']['type'];
			$r['Tag']['status'] = '<b style="color:#ce3a47">rejected</b>';
			$r['Tag']['created'] = $rejectedTags[$i]['Reject']['created'];
			$tags[] = $r;

			$listCreated[] = $r['Tag']['created'];
			$listType[] = 'tag';
			$listTid[] = $r['Tag']['tsumego_id'];
			$listTsumego[] = $r['Tag']['tsumego'];
			$listUser[] = $r['Tag']['user'];
			$listStatus[] = $r['Tag']['status'];
			$listTag[] = $r['Tag']['tag_name'];
		}

		$proposalsCount = count($proposals);
		for ($i = 0; $i < $proposalsCount; $i++) {
			$tx = $this->Tsumego->findById($proposals[$i]['Sgf']['tsumego_id']);
			$scx = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tx['Tsumego']['id']]]);
			if (!$scx) {
				continue;
			}
			$sx = $this->Set->findById($scx['SetConnection']['set_id']);
			$ux = $this->User->findById($proposals[$i]['Sgf']['user_id']);
			$proposals[$i]['Sgf']['tsumego'] = $sx['Set']['title'] . ' - ' . $tx['Tsumego']['num'];
			$proposals[$i]['Sgf']['status'] = '<b style="color:#047804">accepted</b>';
			$proposals[$i]['Sgf']['user'] = $ux['User']['name'];
			$proposals[$i]['Sgf']['type'] = 'proposal';

			$listCreated[] = $proposals[$i]['Sgf']['created'];
			$listType[] = 'proposal';
			$listTid[] = $proposals[$i]['Sgf']['tsumego_id'];
			$listTsumego[] = $proposals[$i]['Sgf']['tsumego'];
			$listUser[] = $proposals[$i]['Sgf']['user'];
			$listStatus[] = $proposals[$i]['Sgf']['status'];
			$listTag[] = '';
		}
		$rejectedProposalsCount = count($rejectedProposals);
		for ($i = 0; $i < $rejectedProposalsCount; $i++) {
			$tx = $this->Tsumego->findById($rejectedProposals[$i]['Reject']['tsumego_id']);
			$scx = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tx['Tsumego']['id']]]);
			if (!$scx) {
				continue;
			}
			$sx = $this->Set->findById($scx['SetConnection']['set_id']);
			$ux = $this->User->findById($rejectedProposals[$i]['Reject']['user_id']);
			$r = [];
			$r['Sgf']['tsumego_id'] = $rejectedProposals[$i]['Reject']['tsumego_id'];
			$r['Sgf']['tsumego'] = $sx['Set']['title'] . ' - ' . $tx['Tsumego']['num'];
			$r['Sgf']['status'] = '<b style="color:#ce3a47">rejected</b>';
			$r['Sgf']['type'] = $rejectedProposals[$i]['Reject']['type'];
			$r['Sgf']['user'] = $ux['User']['name'];
			$r['Sgf']['created'] = $rejectedProposals[$i]['Reject']['created'];
			$proposals[] = $r;

			$listCreated[] = $r['Sgf']['created'];
			$listType[] = 'proposal';
			$listTid[] = $r['Sgf']['tsumego_id'];
			$listTsumego[] = $r['Sgf']['tsumego'];
			$listUser[] = $r['Sgf']['user'];
			$listStatus[] = $r['Sgf']['status'];
			$listTag[] = '';
		}

		array_multisort($listCreated, $listType, $listTid, $listTsumego, $listUser, $listStatus, $listTag);
		$index = 0;
		$listCreatedCount = count($listCreated);
		for ($i = $listCreatedCount - 1; $i >= 0; $i--) {
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

	/**
	 * @return void
	 */
	public function edit($id = null) {
		$tn = $this->TagName->findById($id);
		if (isset($this->data['TagName'])) {
			$tn['TagName']['description'] = $this->data['TagName']['description'];
			$tn['TagName']['hint'] = $this->data['TagName']['hint'];
			$tn['TagName']['link'] = $this->data['TagName']['link'];
			$this->TagName->save($tn);
			$this->set('saved', $tn['TagName']['id']);
		}
		$setHint = [];
		if ($tn['TagName']['hint'] == 1) {
			$setHint[0] = 'checked="checked"';
			$setHint[1] = '';
		} else {
			$setHint[0] = '';
			$setHint[1] = 'checked="checked"';
		}

		$allTags = $this->getAllTags([]);

		$this->set('allTags', $allTags);
		$this->set('setHint', $setHint);
		$this->set('tn', $tn);
	}

	/**
	 * @return void
	 */
	public function delete($id) {
		$this->LoadModel('Tag');
		$tn = $this->TagName->findById($id);

		if (isset($this->data['TagName'])) {
			if ($this->data['TagName']['delete'] == $id) {
				$tags = $this->Tag->find('all', ['conditions' => ['tag_name_id' => $id]]);
				if (!$tags) {
					$tags = [];
				}
				foreach ($tags as $tag) {
					$this->Tag->delete($tag['Tag']['id']);
				}
				$this->TagName->delete($id);
				$this->set('del', 'del');
			}
		}

		$this->set('tn', $tn);
	}

	/**
	 * @return void
	 */
	public function index() {
	}

}
