<?php

use App\Utility\Auth;
use App\Attribute\HttpGet;
use App\Utility\CommentsRenderer;

class CommentsController extends AppController
{
	#[HttpGet]
	public function index(): mixed
	{
		$this->set('_title', 'Tsumego Hero - Discuss');
		$this->set('_page', 'discuss');
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');
		$this->set('yourComments', new CommentsRenderer("your_comments", Auth::getUserID(), $this->queries()));
		$this->set('allComments', new CommentsRenderer("all_comments", null, $this->queries()));
		return null;
	}
}
