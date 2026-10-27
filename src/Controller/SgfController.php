<?php

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Utility\AdminActivityLogger;
use App\Utility\SgfParser;

class SgfController extends AppController
{
	#[HttpGet]
	public function fetch(int $sgfID)
	{
		$sgf = ClassRegistry::init('Sgf')->find('first', ['conditions' => ['id' => $sgfID], 'order' => 'id DESC']);
		if (!$sgf)
			throw new NotFoundException('Sgf not found.');

		$this->Authorization->authorize($sgf, 'fetch');

		$this->response->statusCode(200);
		$this->response->body($sgf['Sgf']['sgf']);
		return $this->response;
	}

	//What the author wrote about the version and the author as the site shows a player (avatar,
	//link to the profile, rank), for the diff of the version in the editor.
	//Allowed to whoever may fetch the version.
	#[HttpGet]
	public function info(int $sgfID)
	{
		$sgf = ClassRegistry::init('Sgf')->find('first', ['conditions' => ['id' => $sgfID]]);
		if (!$sgf)
			throw new NotFoundException('Sgf not found.');

		$this->Authorization->authorize($sgf, 'fetch');

		$author = $sgf['Sgf']['user_id'] ? ClassRegistry::init('User')->findById($sgf['Sgf']['user_id']) : null;
		$this->autoRender = false;
		$this->response->type('json');
		$this->response->body(json_encode([
			'description' => $sgf['Sgf']['description'],
			'authorLink' => User::renderLink($author ? $author['User'] : null)]));
		return $this->response;
	}

	#[HttpPost]
	public function upload(string $setConnectionID): void
	{
		$this->Authorization->authorize('Sgf', 'propose');

		$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
		if (!$setConnection)
			throw new NotFoundException("Specified set connection does not exist.");

		// Use besogo textarea if provided, otherwise use file upload
		$fileUpload = isset($_FILES['adminUpload']) && $_FILES['adminUpload']['error'] === UPLOAD_ERR_OK ? $_FILES['adminUpload'] : null;
		$sgfDataOrFile = $this->request->data['sgfForBesogo'] ?? file_get_contents($fileUpload['tmp_name']);

		if (!$sgfDataOrFile)
			throw new BadRequestException('No SGF data provided.');

		if ($problem = SgfParser::validationProblem($sgfDataOrFile))
			throw new BadRequestException('Invalid SGF format: ' . $problem);

		AdminActivityLogger::log(
			AdminActivityType::SGF_EDIT,
			$setConnection['SetConnection']['tsumego_id'],
			$setConnection['SetConnection']['set_id'],
		);

		$this->set('sgf', $sgfDataOrFile);
		$this->set('setConnectionID', $setConnectionID);
		// sent by the board editor, see SgfCommentReorientation
		$this->set('sourceSgfID', $fileUpload ? null : ($this->request->data['sourceSgfID'] ?? null));
		$this->set('transformationFromSource', $fileUpload ? null : ($this->request->data['transformationFromSource'] ?? null));
		$this->set('description', (string) ($this->request->data['description'] ?? ''));
		$this->render('/Tsumegos/setup_new_sgf');
	}
}
