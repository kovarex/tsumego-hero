<?php

App::uses('SgfParser', 'Utility');
App::uses('CommentsRenderer', 'Utility');

class CommentsController extends AppController
{
	public function index(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		$this->Session->write('title', 'Tsumego Hero - Discuss');
		$this->Session->write('page', 'discuss');
		$this->set('yourComments', new CommentsRenderer("your_comments", Auth::getUserID(), $this->params['url']));
		$this->set('allComments', new CommentsRenderer("all_comments", null, $this->params['url']));
		return null;
	}
}
