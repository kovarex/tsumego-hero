<?php

class Sgf extends AppModel
{
	public $validate = [
		'tsumego_id' => [
			'required' => [
				'rule' => 'numeric',
				'message' => 'Tsumego id is required',
				'allowEmpty' => false,
				'required' => true,
			],
		],
		'sgf' => [
			'notBlank' => [
				'rule' => 'notBlank',
				'message' => 'SGF content is required',
				'allowEmpty' => false,
				'required' => true,
			],
		],
	];

	public function __construct($id = false, $table = null, $ds = null)
	{
		$id['table'] =  'sgf';
		parent::__construct($id, $table, $ds);
	}

	/**
	 * Uploads and saves SGF data for a tsumego
	 *
	 * @param string|array $sgfDataOrFile The SGF content string, or $_FILES['fieldName'] array
	 * @param int $tsumegoID The tsumego ID to attach the SGF to
	 * @param int $userID The user ID uploading the SGF
	 * @param bool $accepted Whether the SGF is accepted (true for admin uploads)
	 * @return array The saved Sgf record
	 * @throws AppException If validation fails
	 */
	public function uploadSgf($sgfDataOrFile, int $tsumegoID, int $userID, bool $accepted): array
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

		$this->create();
		$sgf = [];
		$sgf['sgf'] = $sgfData;
		$sgf['user_id'] = $userID;
		$sgf['tsumego_id'] = $tsumegoID;
		$sgf['accepted'] = $accepted;
		if (!$this->save($sgf))
		{
			$errorMessages = array_map(function ($error) { return is_array($error) ? implode(' ', $error) : $error; }, $this->validationErrors);
			$errorMessage = empty($errorMessages) ? 'validation failed.' : implode('; ', $errorMessages);
			throw new AppException('Failed to save SGF: ' . $errorMessage);
		}

		// Return the saved record from the model (includes auto-generated ID and timestamps)
		return ['Sgf' => $this->data['Sgf']];
	}
}
