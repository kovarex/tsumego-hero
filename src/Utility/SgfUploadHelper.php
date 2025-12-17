<?php

App::uses('AppException', 'Utility');

class SgfUploadHelper
{
	/**
	 * Saves SGF data for a tsumego
	 *
	 * @param string|array $sgfDataOrFile The SGF content string, or $_FILES['fieldName'] array
	 * @param int $tsumegoID The tsumego ID to attach the SGF to
	 * @param int $userID The user ID uploading the SGF
	 * @param bool $accepted Whether the SGF is accepted (true for admin uploads)
	 * @return array The saved Sgf record
	 * @throws AppException If validation fails
	 */
	public static function saveSgf($sgfDataOrFile, int $tsumegoID, int $userID, bool $accepted): array
	{
		// If it's a file upload array, extract the SGF data
		if (is_array($sgfDataOrFile))
		{
			if (!isset($sgfDataOrFile['tmp_name']) || $sgfDataOrFile['error'] !== UPLOAD_ERR_OK)
				throw new AppException('Invalid file upload.');

			$fileSize = $sgfDataOrFile['size'];
			$array1 = explode('.', $sgfDataOrFile['name']);
			$fileExtension = strtolower(end($array1));
			if ($fileExtension != 'sgf')
				throw new AppException('Only SGF files are allowed, the file name is: ' . $sgfDataOrFile['name']);
			if ($fileSize > 2097152)
				throw new AppException('The file is too large.');

			$sgfData = file_get_contents($sgfDataOrFile['tmp_name']);
		}
		else
			$sgfData = $sgfDataOrFile;

		$sgfModel = ClassRegistry::init('Sgf');
		$sgfModel->create();
		$sgf = [];
		$sgf['sgf'] = $sgfData;
		$sgf['user_id'] = $userID;
		$sgf['tsumego_id'] = $tsumegoID;
		$sgf['accepted'] = $accepted;
		if (!$sgfModel->save($sgf))
		{
			$errorMessages = array_map(function ($error) { return is_array($error) ? implode(' ', $error) : $error; }, $sgfModel->validationErrors);
			$errorMessage = empty($errorMessages) ? 'validation failed.' : implode('; ', $errorMessages);
			throw new AppException('Failed to save SGF: ' . $errorMessage);
		}

		return $sgfModel->find('first', ['order' => 'id DESC']);
	}
}
