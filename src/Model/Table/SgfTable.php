<?php

namespace App\Model\Table;

use App\Model\Entity\Sgf;
use App\Utility\TsumegoButton;
use App\Utility\Util;
use ArrayObject;
use Cake\Http\Exception\BadRequestException;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use Cake\Http\Exception\InternalErrorException;
use InvalidArgumentException;

//The versions of the SGF of a problem: the accepted ones (the latest is the current version), the
//proposals waiting for an admin and the rejected ones.
class SgfTable extends AppTable
{
	//Longest description of a saved version (what was changed and why), in characters.
	public const DESCRIPTION_MAX_LENGTH = 1000;

	// sgf_status rows
	public const STATUS_PROPOSED = 1; // waits for an admin to decide
	public const STATUS_ACCEPTED = 2; // a version the problem had, the latest one is the current version
	public const STATUS_REJECTED = 3; // kept so the proposer can still see what they proposed

	//The rules of the Cake 2 model: a version is of a problem and has an SGF that isn't blank.
	public function validationDefault(Validator $validator): Validator
	{
		return $validator
			->requirePresence('tsumego_id', 'create', 'Tsumego id is required')
			->numeric('tsumego_id', 'Tsumego id is required')
			->requirePresence('sgf', 'create', 'SGF content is required')
			->notBlank('sgf', 'SGF content is required');
	}

	//A save that sets the SGF sets its preview too, so every version has the one of its SGF.
	public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
	{
		if ($entity->isDirty('sgf'))
			$entity->set('preview', TsumegoButton::sgfToPreviewJson((string) $entity->get('sgf')));
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
	//$accepted: Whether the SGF is accepted (true for admin uploads), the uploader is the admin who accepted it then
	//Returns the saved version, read back with what the database filled in
	/** @throws BadRequestException|InternalErrorException If validation fails */
	public static function upload(array|string $sgfDataOrFile, int $tsumegoID, int $userID, bool $accepted): Sgf
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

		$sgfTable = TableRegistry::getTableLocator()->get('Sgf');
		$sgf = $sgfTable->newEntity(['sgf' => $sgfData, 'user_id' => $userID, 'tsumego_id' => $tsumegoID]
			+ ($accepted ? self::acceptedBy($userID) : ['sgf_status_id' => self::STATUS_PROPOSED]));
		if ($sgf->hasErrors() || !$sgfTable->save($sgf))
		{
			$errorMessages = array_map(fn($errors) => implode(' ', $errors), $sgf->getErrors());
			$errorMessage = empty($errorMessages) ? 'validation failed.' : implode('; ', $errorMessages);
			throw new InternalErrorException('Failed to save SGF: ' . $errorMessage);
		}

		// read back, with the columns the database filled in
		return $sgfTable->get($sgf->id);
	}

	//The fields of a version the admin makes part of the problem now, their own change or a proposal
	//they accept.
	/** @return array{sgf_status_id: int, accepted_by_user_id: int, accepted: string} */
	public static function acceptedBy(int $adminID): array
	{
		return ['sgf_status_id' => self::STATUS_ACCEPTED, 'accepted_by_user_id' => $adminID, 'accepted' => date('Y-m-d H:i:s')];
	}

	//The admin accepts the proposal, it becomes a version of the problem.
	public static function accept(int $sgfID, int $adminID): void
	{
		$accepted = self::acceptedBy($adminID);
		$changed = Util::execute('UPDATE sgf SET sgf_status_id = ?, accepted_by_user_id = ?, accepted = ? WHERE id = ?',
			[$accepted['sgf_status_id'], $accepted['accepted_by_user_id'], $accepted['accepted'], $sgfID]);
		if ($changed !== 1)
			throw new InternalErrorException('Failed to accept SGF ' . $sgfID);
	}

	//Moves the version to the given sgf_status (see the STATUS_ constants), a proposal is accepted
	//with accept().
	public static function setStatus(int $sgfID, int $statusID): void
	{
		if ($statusID === self::STATUS_ACCEPTED)
			throw new InvalidArgumentException('An accepted version needs the admin who accepted it, see accept()');
		$sgfTable = TableRegistry::getTableLocator()->get('Sgf');
		if (!$sgfTable->exists(['id' => $sgfID]))
			throw new InternalErrorException('Failed to change the status of SGF ' . $sgfID);
		$sgfTable->updateAll(['sgf_status_id' => $statusID], ['id' => $sgfID]);
	}

	//The id of the current version of the tsumego, the latest accepted one.
	public static function findCurrentVersionID(int $tsumegoID): ?int
	{
		return TableRegistry::getTableLocator()->get('Sgf')->find()
			->select(['id'])
			->where(['tsumego_id' => $tsumegoID, 'sgf_status_id' => self::STATUS_ACCEPTED])
			->orderByDesc('id')
			->first()?->id;
	}

	//The id of the accepted version of the same tsumego saved right before the given one, the one to diff it against.
	public static function findPreviousVersionID(int $tsumegoID, int $sgfID): ?int
	{
		return TableRegistry::getTableLocator()->get('Sgf')->find()
			->select(['id'])
			->where(['tsumego_id' => $tsumegoID, 'sgf_status_id' => self::STATUS_ACCEPTED, 'id <' => $sgfID])
			->orderByDesc('id')
			->first()?->id;
	}

	//SQL giving the id of the current version (the latest accepted one) of the tsumego in the given SQL
	//expression, e.g. "LEFT JOIN sgf ON sgf.id = " . SgfTable::currentVersionIDSql('tsumego.id').
	public static function currentVersionIDSql(string $tsumegoID): string
	{
		return '(SELECT MAX(current_sgf.id) FROM sgf current_sgf WHERE current_sgf.tsumego_id = ' . $tsumegoID
			. ' AND current_sgf.sgf_status_id = ' . self::STATUS_ACCEPTED . ')';
	}
}
