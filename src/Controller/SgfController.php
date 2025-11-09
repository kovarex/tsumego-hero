<?php

class SgfController extends AppController {
	public function fetch(int $sgfID) {
		if (!Auth::isLoggedIn()) {
			$this->response->statusCode(403);
			$this->response->body('User not logged in.');
			return $this->response;
		}

		$sgf = ClassRegistry::init('Sgf')->find('first', ['conditions' => ['id' => $sgfID], 'order' => 'id DESC']);
		if (!$sgf) {
			$this->response->statusCode(404);
			$this->response->body('Sgf not found.');
			return $this->response;
		}

		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $sgf['Sgf']['tsumego_id'], 'user_id' => Auth::getUserID()]]);
		if (!TsumegoUtil::isStatusAllowingInspection($status['TsumegoStatus']['status'])) {
			$this->response->statusCode(403);
			$this->response->body('Related tsumego is not in a solved state for the user ' . Auth::getUser()['name']);
			return $this->response;
		}

		$this->response->statusCode(200);
		$this->response->body($sgf['Sgf']['sgf']);
		return $this->response;
	}

	public function upload($setConnectionID) {
		$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
		if (!$setConnection) {
			throw new AppException("Specified set connection does not exist.");
		}

		if ($this->data['sgfForBesogo']) {
			$sgfData = $this->data['sgfForBesogo'];
		} else {
			$fileSize = $_FILES['adminUpload']['size'];
			$array1 = explode('.', $_FILES['adminUpload']['name']);
			$fileExtension = strtolower(end($array1));
			if ($fileExtension != 'sgf') {
				throw new AppException('Only SGF files are allowed, the file name is:' . $_FILES['adminUpload']['name']);
			}
			if ($fileSize > 2097152) {
				throw new AppException('The file is too large.');
			}
			$sgfData = file_get_contents($_FILES['adminUpload']['tmp_name']);
		}
		ClassRegistry::init('AdminActivity')->create();
		$adminActivity = [];
		$adminActivity['AdminActivity']['user_id'] = Auth::getUserID();
		$adminActivity['AdminActivity']['tsumego_id'] = $setConnection['SetConnection']['tsumego_id'];
		$adminActivity['AdminActivity']['file'] = $setConnection['SetConnection']['num'];
		$adminActivity['AdminActivity']['answer'] = '??';
		ClassRegistry::init('AdminActivity')->save($adminActivity);
		$sgf = [];
		$sgf['Sgf']['sgf'] = $sgfData;
		$sgf['Sgf']['user_id'] = Auth::getUserID();
		$sgf['Sgf']['tsumego_id'] = $setConnection['SetConnection']['tsumego_id'];
		AppController::handleContribution(Auth::getUserID(), 'made_proposal');
		ClassRegistry::init('Sgf')->save($sgf);
		return $this->redirect('/' . $setConnectionID);
	}
}
