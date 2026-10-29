<?php

namespace App\Controller;

use Cake\Event\EventInterface;

//The controller of the error pages: the site's layout needs what AppController::beforeFilter() sets,
//as CakeErrorController of Cake 2 extended AppController.
class ErrorController extends AppController
{
	public function beforeRender(EventInterface $event): void
	{
		parent::beforeRender($event);
		$this->viewBuilder()->setTemplatePath('Error');
	}
}
