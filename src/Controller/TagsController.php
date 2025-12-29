<?php

class TagsController extends AppController
{
	public function add()
	{
		$allTags = $this->getAllTags();
		$this->set('allTags', $allTags);
	}

	public function addAction(): CakeResponse
	{
		$tagName = $this->data['tag_name'];
		if (empty($tagName))
		{
			CookieFlash::set('Tag name not provided', 'error');
			return $this->redirect('/tags/add');
		}

		$existingTag = ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => $tagName]]);
		if ($existingTag)
		{
			CookieFlash::set('Tag "' . $tagName . '" already exists.', 'error');
			return $this->redirect('/tags/add');
		}

		$tag = [];
		$tag['name'] = $tagName;
		$tag['description'] = $this->data['tag_description'];
		$tag['hint'] = $this->data['tag_hint'];
		$tag['link'] = $this->data['tag_reference'];
		$tag['user_id'] = Auth::getUserID();
		$tag['approved'] = Auth::isAdmin() ? 1 : 0;
		ClassRegistry::init('Tag')->save($tag);
		$saved = ClassRegistry::init('Tag')->find('first', ['conditions' => ['name' => $tagName]])['Tag'];

		CookieFlash::set('Tag "' . $tagName . '" has been added.', 'success');
		return $this->redirect('/tags/view/' . $saved['id']);
	}

	/**
	 * @param string|int|null $id
	 * @return void
	 */
	public function view($id = null)
	{
		$tn = $this->Tag->findById($id);
		$allTags = $this->getAllTags();
		$user = $this->User->findById($tn['Tag']['user_id']);
		$tn['Tag']['user'] = $user['User']['name'];
		$this->set('allTags', $allTags);
		$this->set('tn', $tn);
	}

	/**
	 * @param string|int|null $id User ID
	 * @return void
	 */
	public function user($id)
	{
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
		$tagNames = $this->Tag->find('all', ['limit' => 50, 'order' => 'created DESC', 'conditions' => ['user_id' => $id, 'approved' => 1]]);
		if (!$tagNames)
			$tagNames = [];
		$tags = ClassRegistry::init('TagConnection')->find('all', ['limit' => 50, 'order' => 'created DESC', 'conditions' => ['user_id' => $id, 'approved' => 1]]) ?: [];
		$proposals = $this->Sgf->find('all', [
			'limit' => 50,
			'order' => 'created DESC',
			'conditions' => ['user_id' => $id],
		]) ?: [];
		$rejectedProposals = $this->Reject->find('all', ['limit' => 50, 'order' => 'created DESC', 'conditions' => ['user_id' => $id, 'type' => 'proposal']]) ?: [];
		$rejectedTags = $this->Reject->find('all', ['limit' => 50, 'order' => 'created DESC', 'conditions' => ['user_id' => $id, 'type' => 'tag']]) ?: [];
		$rejectedTagNames = $this->Reject->find('all', ['limit' => 50, 'order' => 'created DESC', 'conditions' => ['user_id' => $id, 'type' => 'tag name']]) ?: [];
		$tagNamesCount = count($tagNames);
		for ($i = 0; $i < $tagNamesCount; $i++)
		{
			$ux = $this->User->findById($tagNames[$i]['Tag']['user_id']);
			$tagNames[$i]['Tag']['status'] = '<b style="color:#047804">accepted</b>';
			$tagNames[$i]['Tag']['type'] = 'tag name';
			$tagNames[$i]['Tag']['user'] = $ux['User']['name'];

			$listCreated[] = $tagNames[$i]['Tag']['created'];
			$listType[] = 'tag name';
			$listTid[] = '';
			$listTsumego[] = '';
			$listUser[] = $tagNames[$i]['Tag']['user'];
			$listStatus[] = $tagNames[$i]['Tag']['status'];
			$listTag[] = $tagNames[$i]['Tag']['name'];
		}
		$rejectedTagNamesCount = count($rejectedTagNames);
		for ($i = 0; $i < $rejectedTagNamesCount; $i++)
		{
			$ux = $this->User->findById($rejectedTagNames[$i]['Reject']['user_id']);
			$r = [];
			$r['Tag']['name'] = $rejectedTagNames[$i]['Reject']['text'];
			$r['Tag']['type'] = $rejectedTagNames[$i]['Reject']['type'];
			$r['Tag']['status'] = '<b style="color:#ce3a47">rejected</b>';
			$r['Tag']['created'] = $rejectedTagNames[$i]['Reject']['created'];
			$r['Tag']['user'] = $ux['User']['name'];
			$tagNames[] = $r;

			$listCreated[] = $r['Tag']['created'];
			$listType[] = 'tag name';
			$listTid[] = '';
			$listTsumego[] = '';
			$listUser[] = $r['Tag']['user'];
			$listStatus[] = $r['Tag']['status'];
			$listTag[] = $r['Tag']['name'];
		}

		$tagsCount = count($tags);
		for ($i = 0; $i < $tagsCount; $i++)
		{
			$tnx = $this->Tag->findById($tags[$i]['TagConnection']['tag_id']);
			$tx = $this->Tsumego->findById($tags[$i]['TagConnection']['tsumego_id']);
			$scx = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tx['Tsumego']['id']]]);
			if (!$scx)
				continue;
			$sx = $this->Set->findById($scx['SetConnection']['set_id']);
			$ux = $this->User->findById($tags[$i]['TagConnection']['user_id']);
			if ($tnx['Tag']['name'] == '')
				$tags[$i]['TagConnection']['tag_name'] = '<i>[not found]</i>';
			else
				$tags[$i]['TagConnection']['tag_name'] = $tnx['Tag']['name'];
			$tags[$i]['TagConnection']['tsumego'] = $sx['Set']['title'] . ' - ' . $scx['SetConnection']['num'];
			$tags[$i]['TagConnection']['user'] = $ux['User']['name'];
			$tags[$i]['TagConnection']['type'] = 'tag';
			$tags[$i]['TagConnection']['status'] = '<b style="color:#047804">accepted</b>';

			$listCreated[] = $tags[$i]['TagConnection']['created'];
			$listType[] = 'tag';
			$listTid[] = $tags[$i]['TagConnection']['tsumego_id'];
			$listTsumego[] = $tags[$i]['TagConnection']['tsumego'];
			$listUser[] = $tags[$i]['TagConnection']['user'];
			$listStatus[] = $tags[$i]['TagConnection']['status'];
			$listTag[] = $tags[$i]['TagConnection']['tag_name'];
		}
		$rejectedTagsCount = count($rejectedTags);
		for ($i = 0; $i < $rejectedTagsCount; $i++)
		{
			$tx = $this->Tsumego->findById($rejectedTags[$i]['Reject']['tsumego_id']);
			$scx = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tx['Tsumego']['id']]]);
			if (!$scx)
				continue;
			$sx = $this->Set->findById($scx['SetConnection']['set_id']);
			$ux = $this->User->findById($rejectedTags[$i]['Reject']['user_id']);
			$r = [];
			$r['TagConnection']['tsumego_id'] = $rejectedTags[$i]['Reject']['tsumego_id'];
			$r['TagConnection']['tag_name'] = $rejectedTags[$i]['Reject']['text'];
			$r['TagConnection']['tsumego'] = $sx['Set']['title'] . ' - ' . $scx['SetConnection']['num'];
			$r['TagConnection']['user'] = $ux['User']['name'];
			$r['TagConnection']['type'] = $rejectedTags[$i]['Reject']['type'];
			$r['TagConnection']['status'] = '<b style="color:#ce3a47">rejected</b>';
			$r['TagConnection']['created'] = $rejectedTags[$i]['Reject']['created'];
			$tags[] = $r;

			$listCreated[] = $r['TagConnection']['created'];
			$listType[] = 'tag';
			$listTid[] = $r['TagConnection']['tsumego_id'];
			$listTsumego[] = $r['TagConnection']['tsumego'];
			$listUser[] = $r['TagConnection']['user'];
			$listStatus[] = $r['TagConnection']['status'];
			$listTag[] = $r['TagConnection']['tag_name'];
		}

		$proposalsCount = count($proposals);
		for ($i = 0; $i < $proposalsCount; $i++)
		{
			$tx = $this->Tsumego->findById($proposals[$i]['Sgf']['tsumego_id']);
			$scx = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tx['Tsumego']['id']]]);
			if (!$scx)
				continue;
			$sx = $this->Set->findById($scx['SetConnection']['set_id']);
			$ux = $this->User->findById($proposals[$i]['Sgf']['user_id']);
			$proposals[$i]['Sgf']['tsumego'] = $sx['Set']['title'] . ' - ' . $scx['SetConnection']['num'];
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
		for ($i = 0; $i < $rejectedProposalsCount; $i++)
		{
			$tx = $this->Tsumego->findById($rejectedProposals[$i]['Reject']['tsumego_id']);
			$scx = $this->SetConnection->find('first', ['conditions' => ['tsumego_id' => $tx['Tsumego']['id']]]);
			if (!$scx)
				continue;
			$sx = $this->Set->findById($scx['SetConnection']['set_id']);
			$ux = $this->User->findById($rejectedProposals[$i]['Reject']['user_id']);
			$r = [];
			$r['Sgf']['tsumego_id'] = $rejectedProposals[$i]['Reject']['tsumego_id'];
			$r['Sgf']['tsumego'] = $sx['Set']['title'] . ' - ' . $scx['SetConnection']['num'];
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
		for ($i = $listCreatedCount - 1; $i >= 0; $i--)
		{
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
	 * @param string|int|null $id Tag name ID
	 * @return void
	 */
	public function edit($id = null)
	{
		$tn = $this->Tag->findById($id);
		if (isset($this->data['Tag']))
		{
			$tn['Tag']['description'] = $this->data['Tag']['description'];
			$tn['Tag']['hint'] = $this->data['Tag']['hint'];
			$tn['Tag']['link'] = $this->data['Tag']['link'];
			$this->Tag->save($tn);
			$this->set('saved', $tn['Tag']['id']);
		}
		$setHint = [];
		if ($tn['Tag']['hint'] == 1)
		{
			$setHint[0] = 'checked="checked"';
			$setHint[1] = '';
		}
		else
		{
			$setHint[0] = '';
			$setHint[1] = 'checked="checked"';
		}

		$allTags = $this->getAllTags();

		$this->set('allTags', $allTags);
		$this->set('setHint', $setHint);
		$this->set('tn', $tn);
	}

	/**
	 * @param string|int $id Tag name ID
	 * @return void
	 */
	public function delete($id)
	{
		$this->loadModel('Tag');
		$tn = $this->Tag->findById($id);

		if (isset($this->data['Tag']))
			if ($this->data['Tag']['delete'] == $id)
			{
				$tags = $this->TagConnection->find('all', ['conditions' => ['tag_id' => $id]]);
				if (!$tags)
					$tags = [];
				foreach ($tags as $tag)
					$this->TagConnection->delete($tag['TagConnection']['id']);
				$this->Tag->delete($id);
				$this->set('del', 'del');
			}

		$this->set('tn', $tn);
	}

	/**
	 * @return void
	 */
	public function index() {}

}
