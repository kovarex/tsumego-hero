<?php

App::uses('AdminActivityLogger', 'Utility');
App::uses('AdminActivityType', 'Model');
App::uses('NotFoundException', 'Routing/Error');
App::uses('BadRequestException', 'Routing/Error');
App::uses('ForbiddenException', 'Routing/Error');
App::uses('UnauthorizedException', 'Routing/Error');

class SgfController extends AppController
{
	public function fetch(int $sgfID)
	{
		if (!Auth::isLoggedIn())
			throw new UnauthorizedException('User not logged in.');

		$sgf = ClassRegistry::init('Sgf')->find('first', ['conditions' => ['id' => $sgfID], 'order' => 'id DESC']);
		if (!$sgf)
			throw new NotFoundException('Sgf not found.');

		$status = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['tsumego_id' => $sgf['Sgf']['tsumego_id'], 'user_id' => Auth::getUserID()]]);
		if (!Auth::isAdmin() && (!$status || !TsumegoUtil::isRecentlySolved($status['TsumegoStatus']['status'])))
			throw new ForbiddenException('Related tsumego is not in a solved state for the user ' . Auth::getUser()['name']);

		$this->response->statusCode(200);
		$this->response->body($sgf['Sgf']['sgf']);
		return $this->response;
	}

	public function upload($setConnectionID)
	{
		$this->Authorization->authorize('Sgf', 'propose');

		$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
		if (!$setConnection)
			throw new NotFoundException("Specified set connection does not exist.");

		// Use besogo textarea if provided, otherwise use file upload
		$fileUpload = isset($_FILES['adminUpload']) && $_FILES['adminUpload']['error'] === UPLOAD_ERR_OK ? $_FILES['adminUpload'] : null;
		$sgfDataOrFile = $this->data['sgfForBesogo'] ?? file_get_contents($fileUpload['tmp_name']);

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
		$this->render('/Tsumegos/setup_new_sgf');
	}

	public static function validateSgfFormat(string $sgf): void
	{
		$maxSize = 1024 * 1024; // 1 MB
		if (strlen($sgf) > $maxSize)
			throw new BadRequestException('SGF data exceeds maximum size of 1 MB.');

		$trimmed = trim($sgf);
		if (!str_starts_with($trimmed, '(;'))
			throw new BadRequestException('Invalid SGF format: must start with "(;".');

		if (!str_contains($trimmed, 'GM[1]'))
			throw new BadRequestException('Invalid SGF format: must contain GM[1] (Go game marker).');
	}
}
