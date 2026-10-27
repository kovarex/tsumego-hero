<?php

class Sgf extends AppModel
{
	//Longest description of a saved version (what was changed and why), in characters.
	public const DESCRIPTION_MAX_LENGTH = 1000;

	// sgf_status rows
	public const STATUS_PROPOSED = 1; // waits for an admin to decide
	public const STATUS_ACCEPTED = 2; // a version the problem had, the latest one is the current version
	public const STATUS_REJECTED = 3; // kept so the proposer can still see what they proposed

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

	public function __construct(mixed $id = false, ?string $table = null, ?string $ds = null)
	{
		$id['table'] =  'sgf';
		parent::__construct($id, $table, $ds);
	}

	//The description sent with a saved version: blank space dropped, null when there is none.
	/** @throws BadRequestException When it is missing for a proposal or too long */
	public static function descriptionFromRequest(mixed $description, bool $required): ?string
	{
		$description = trim(str_replace(["\r\n", "\r"], "\n", is_string($description) ? $description : ''));
		if ($description === '')
		{
			if ($required)
				throw new BadRequestException('Describe what you changed and why.');
			return null;
		}
		if (mb_strlen($description) > self::DESCRIPTION_MAX_LENGTH)
			throw new BadRequestException('The description is too long, at most ' . self::DESCRIPTION_MAX_LENGTH . ' characters fit.');
		return $description;
	}

	//Uploads and saves SGF data for a tsumego
	//$sgfDataOrFile: The SGF content string, or $_FILES['fieldName'] array
	//$tsumegoID: The tsumego ID to attach the SGF to
	//$userID: The user ID uploading the SGF
	//$accepted: Whether the SGF is accepted (true for admin uploads)
	//Returns the saved Sgf record
	/** @throws BadRequestException|InternalErrorException If validation fails */
	public function uploadSgf(array|string $sgfDataOrFile, int $tsumegoID, int $userID, bool $accepted): array
	{
		// If it's a file upload array, extract the SGF data
		if (is_array($sgfDataOrFile))
		{
			if (!isset($sgfDataOrFile['tmp_name']) || $sgfDataOrFile['error'] !== UPLOAD_ERR_OK)
				throw new BadRequestException('Invalid file upload.');

			$fileSize = $sgfDataOrFile['size'];
			$array1 = explode('.', $sgfDataOrFile['name']);
			$fileExtension = strtolower(end($array1));
			if ($fileExtension != 'sgf')
				throw new BadRequestException('Only SGF files are allowed, the file name is: ' . $sgfDataOrFile['name']);
			if ($fileSize > 2097152)
				throw new BadRequestException('The file is too large.');

			$sgfData = file_get_contents($sgfDataOrFile['tmp_name']);
		}
		else
			$sgfData = $sgfDataOrFile;

		$this->create();
		$sgf = [];
		$sgf['sgf'] = $sgfData;
		$sgf['user_id'] = $userID;
		$sgf['tsumego_id'] = $tsumegoID;
		$sgf['sgf_status_id'] = $accepted ? self::STATUS_ACCEPTED : self::STATUS_PROPOSED;
		if (!$this->save($sgf))
		{
			$errorMessages = array_map(function ($error) {
				return is_array($error) ? implode(' ', $error) : $error;
			}, $this->validationErrors);
			$errorMessage = empty($errorMessages) ? 'validation failed.' : implode('; ', $errorMessages);
			throw new InternalErrorException('Failed to save SGF: ' . $errorMessage);
		}

		// Return the saved record (includes auto-generated ID and timestamps), the model's data is cleared by the save
		return $this->find('first', ['conditions' => ['id' => $this->id]]);
	}

	//Moves the version to the given sgf_status (see the STATUS_ constants).
	public function setStatus(int $sgfID, int $statusID): void
	{
		$this->id = $sgfID;
		if (!$this->saveField('sgf_status_id', $statusID))
			throw new InternalErrorException('Failed to change the status of SGF ' . $sgfID);
	}

	//The id of the current version of the tsumego, the latest accepted one.
	public function findCurrentVersionID(int $tsumegoID): ?int
	{
		$current = $this->find('first', [
			'fields' => ['id'],
			'order' => 'id DESC',
			'conditions' => ['tsumego_id' => $tsumegoID, 'sgf_status_id' => self::STATUS_ACCEPTED]]);
		return $current ? (int) $current['Sgf']['id'] : null;
	}

	//The id of the accepted version of the same tsumego saved right before the given one, the one to diff it against.
	public function findPreviousVersionID(int $tsumegoID, int $sgfID): ?int
	{
		$previous = $this->find('first', [
			'fields' => ['id'],
			'order' => 'id DESC',
			'conditions' => ['tsumego_id' => $tsumegoID, 'sgf_status_id' => self::STATUS_ACCEPTED, 'id <' => $sgfID]]);
		return $previous ? (int) $previous['Sgf']['id'] : null;
	}

	//SQL giving the id of the current version (the latest accepted one) of the tsumego in the given SQL
	//expression, e.g. "LEFT JOIN sgf ON sgf.id = " . Sgf::currentVersionIDSql('tsumego.id').
	public static function currentVersionIDSql(string $tsumegoID): string
	{
		return '(SELECT MAX(current_sgf.id) FROM sgf current_sgf WHERE current_sgf.tsumego_id = ' . $tsumegoID
			. ' AND current_sgf.sgf_status_id = ' . self::STATUS_ACCEPTED . ')';
	}
}
