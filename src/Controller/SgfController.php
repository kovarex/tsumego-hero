<?php

use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\UserTable;
use App\Utility\AdminActivityLogger;
use App\Utility\SgfParser;

class SgfController extends AppController
{
	#[HttpGet]
	public function fetch(int $sgfID)
	{
		$sgf = $this->fetchTable('Sgf')->find()->where(['id' => $sgfID])->first();
		if (!$sgf)
			throw new NotFoundException('Sgf not found.');

		$this->Authorization->authorize($sgf, 'fetch');

		return $this->respond($sgf->sgf);
	}

	//What the author wrote about the version and the author as the site shows a player (avatar,
	//link to the profile, rank), for the diff of the version in the editor.
	//Allowed to whoever may fetch the version.
	#[HttpGet]
	public function info(int $sgfID)
	{
		$sgf = $this->fetchTable('Sgf')->find()->select(['id', 'tsumego_id', 'user_id', 'description'])->where(['id' => $sgfID])->first();
		if (!$sgf)
			throw new NotFoundException('Sgf not found.');

		$this->Authorization->authorize($sgf, 'fetch');

		$author = $sgf->user_id ? UserTable::row($sgf->user_id) : null;
		return $this->respondJson([
			'description' => $sgf->description,
			'authorLink' => UserTable::renderLink($author)]);
	}

	#[HttpPost]
	public function upload(string $setConnectionID): void
	{
		$this->Authorization->authorize('Sgf', 'propose');

		$setConnection = $this->fetchTable('SetConnection')->find()->where(['id' => (int) $setConnectionID])->first();
		if (!$setConnection)
			throw new NotFoundException("Specified set connection does not exist.");

		// Use besogo textarea if provided, otherwise use file upload
		$fileUpload = $this->uploadedFile('adminUpload');
		if ($fileUpload !== null && $fileUpload['error'] !== UPLOAD_ERR_OK)
			$fileUpload = null;
		$sgfDataOrFile = $this->input('sgfForBesogo') ?? file_get_contents($fileUpload['tmp_name']);

		if (!$sgfDataOrFile)
			throw new BadRequestException('No SGF data provided.');

		if ($problem = SgfParser::validationProblem($sgfDataOrFile))
			throw new BadRequestException('Invalid SGF format: ' . $problem);

		AdminActivityLogger::log(
			AdminActivityTypeTable::SGF_EDIT,
			$setConnection->tsumego_id,
			$setConnection->set_id,
		);

		$this->set('sgf', $sgfDataOrFile);
		$this->set('setConnectionID', $setConnectionID);
		// sent by the board editor, see SgfCommentReorientation
		$this->set('sourceSgfID', $fileUpload ? null : $this->input('sourceSgfID'));
		$this->set('transformationFromSource', $fileUpload ? null : $this->input('transformationFromSource'));
		$this->set('description', (string) ($this->input('description') ?? ''));
		$this->render('/Tsumegos/setup_new_sgf');
	}
}
