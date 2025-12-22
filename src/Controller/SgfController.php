<?php

App::uses('AdminActivityLogger', 'Utility');
App::uses('AdminActivityType', 'Model');

class SgfController extends AppController
{
	public function fetch(int $sgfID)
	{
		if (!Auth::isLoggedIn())
		{
			$this->response->statusCode(403);
			$this->response->body('User not logged in.');
			return $this->response;
		}

		$sgf = ClassRegistry::init('Sgf')->find('first', ['conditions' => ['id' => $sgfID], 'order' => 'id DESC']);
		if (!$sgf)
		{
			$this->response->statusCode(404);
			$this->response->body('Sgf not found.');
			return $this->response;
		}

		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $sgf['Sgf']['tsumego_id'], 'user_id' => Auth::getUserID()]]);
		if (!Auth::isAdmin() && !TsumegoUtil::isRecentlySolved($status['TsumegoStatus']['status']))
		{
			$this->response->statusCode(403);
			$this->response->body('Related tsumego is not in a solved state for the user ' . Auth::getUser()['name']);
			return $this->response;
		}

		$this->response->statusCode(200);
		$this->response->body($sgf['Sgf']['sgf']);
		return $this->response;
	}

	public function upload($setConnectionID)
	{
		$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
		if (!$setConnection)
			throw new AppException("Specified set connection does not exist.");

		// Use besogo textarea if provided, otherwise use file upload
		$fileUpload = isset($_FILES['adminUpload']) && $_FILES['adminUpload']['error'] === UPLOAD_ERR_OK ? $_FILES['adminUpload'] : null;
		$sgfDataOrFile = $this->data['sgfForBesogo'] ?? $fileUpload;

		if (!$sgfDataOrFile)
			throw new AppException('No SGF data provided.');

		$this->set('sgf', $sgfDataOrFile);
		$this->set('tsumegoID', $setConnection['SetConnection']['tsumego_id']);
		$this->render('/Tsumegos/setupNewSgf');
	}
}
