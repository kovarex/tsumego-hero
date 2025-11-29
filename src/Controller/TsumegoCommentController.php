<?php

class TsumegoCommentController extends AppController
{
	public function add()
	{
		if (!Auth::isLoggedIn())
			return null;
		$comment = [];
		$comment['tsumego_id'] = $this->data['Comment']['tsumego_id'];
		$comment['message'] = $this->data['Comment']['message'];
		$comment['tsumego_issue_id'] = $this->data['Comment']['tsumego_issue_id'];
		$comment['user_id'] = Auth::getUserID();
		ClassRegistry::init('TsumegoComment')->create($comment);
		ClassRegistry::init('TsumegoComment')->save($comment);
		return $this->redirect($this->data['Comment']['redirect']);
	}

	public function delete($id)
	{
		if (Auth::isAdmin())
			return null;
		$comment = ClassRegistry::init('TsumegoComment')->find($id);
		$comment['deleted'] = true;
		ClassRegistry::init('TsumegoComment')->save($comment);
		return $this->redirect($this->data['Comment']['redirect']);
	}
}
