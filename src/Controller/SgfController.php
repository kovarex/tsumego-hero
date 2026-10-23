<?php

use App\Attribute\HttpPost;
use App\Utility\AdminActivityLogger;
use App\Utility\SgfParser;

class SgfController extends AppController
{
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

		self::validateSgfFormat($sgfDataOrFile);

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

	public static function validateSgfFormat(string $sgf): void
	{
		$error = SgfParser::validate($sgf);
		if ($error !== null)
			throw new BadRequestException('Invalid SGF format: ' . $error);

		$gameError = SgfParser::validateGame($sgf);
		if ($gameError !== null)
			throw new BadRequestException('Invalid SGF format: ' . $gameError);
	}
}
