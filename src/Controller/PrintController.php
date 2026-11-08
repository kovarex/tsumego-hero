<?php

namespace App\Controller;

use App\Attribute\HttpGet;
use App\Model\Table\ClassroomAssignmentTable;
use App\Utility\Auth;
use App\Utility\PrintSheet;

//Problems on paper: the player picks problems from the collections the way a teacher picks them for
//a class (app/classrooms/ProblemSelection.tsx), chooses how the sheet looks, and the sheet opens
//in a new tab as a plain page for the printer. Any signed-in player prints the problems they can
//pick: their own collections and the ones of the site (ClassroomAssignmentTable::canAssignSet()).
class PrintController extends AppController
{
	//The picker and the options of the sheet, starting with the problems of ?problems=, the set
	//connection ids, such as the ones of an assignment.
	#[HttpGet]
	public function index(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$this->set('_title', 'Tsumego Hero - Print problems');
		$this->set('problems', ClassroomAssignmentTable::pickablePayload(PrintSheet::parseIds($this->query('problems')), Auth::getUserID()));
		return null;
	}

	//The sheet itself, a page of its own without the site's layout:
	//?problems=<set connection ids>&perPage=<4, 6, 9 or 12>&answers=1&notes=1
	#[HttpGet]
	public function sheet(): mixed
	{
		if (!Auth::isLoggedIn())
			return $this->redirect('/users/login');

		$this->viewBuilder()->disableAutoLayout();
		$this->set('sheet', PrintSheet::of(PrintSheet::parseIds($this->query('problems')), Auth::getUserID(), [
			'perPage' => $this->query('perPage'),
			'answers' => $this->query('answers'),
			'notes' => $this->query('notes'),
		]));
		return null;
	}
}
