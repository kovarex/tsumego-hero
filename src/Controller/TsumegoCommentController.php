<?php

class TsumegoCommentController extends Controller
{
  function add()
  {
	  if (!Auth::isLoggedIn())
		  return null;
	  $comment = [];
	  $comment['tsumego_id'] = $_POST['tsumegoID'];
	  $comment['message'] = $_POST['message'];
	  $comment['user_id'] = Auth::getUserID();
	  ClassRegistry::init('Comment')->create($comment);
	  ClassRegistry::init('Comment')->save($comment);
	  return $this->redirect($_POST['redirect']);
  }
}
