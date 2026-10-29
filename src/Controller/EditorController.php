<?php

namespace App\Controller;

use App\Attribute\HttpGet;

class EditorController extends AppController
{
	//The board editor (/editor/?sgfID=…&diffID=…&setConnectionID=…), a page of its own without the
	//site's layout. It loads besogo from the hashed Vite bundle, like the play page, so a browser
	//never runs a new script with old styles after a deploy.
	#[HttpGet]
	public function index(): void
	{
		$this->viewBuilder()->disableAutoLayout();
	}
}
