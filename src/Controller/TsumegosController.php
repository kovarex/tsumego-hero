<?php

namespace App\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use App\Model\Entity\Tsumego;
use App\Model\Table\SetTable;
use App\Model\Table\SetConnectionTable;
use App\Model\Table\SgfTable;
use App\Model\Table\TsumegoStatusTable;
use App\Attribute\HttpGet;
use App\Attribute\HttpPost;
use App\Model\Table\AdminActivityTypeTable;
use App\Model\Table\TsumegoVariantTable;
use App\Utility\AchievementChecker;
use App\Utility\AdminActivityLogger;
use App\Utility\Auth;
use App\Utility\Constants;
use App\Utility\DailyStreak;
use App\Utility\CookieFlash;
use App\Utility\PlayOutcome;
use App\Utility\Rating;
use App\Utility\RatingParseException;
use App\Utility\SgfParser;
use App\Utility\SimilarSearchLogic;
use App\Utility\Trash;
use App\Utility\TsumegoButton;
use App\Utility\TsumegoMerger;
use App\Utility\TsumegoUtil;
use App\Utility\Util;

class TsumegosController extends AppController
{
	#[HttpPost]
	public function result()
	{
		$this->Authorization->authorize('User', 'reportResult');

		$data = json_decode($this->requestBody(), true) ?: $this->inputs();
		if (!$data || empty($data['tsumego_id']))
			throw new BadRequestException('Missing tsumego_id');

		$outcome = PlayOutcome::tryFrom((string) ($data['outcome'] ?? ''));
		if (!$outcome)
			throw new BadRequestException('Unknown play outcome');

		$result = $this->PlayResultProcessor->processResult(
			tsumegoID: (int) $data['tsumego_id'],
			outcome: $outcome,
			seconds: (float) ($data['seconds'] ?? 0),
			setConnectionID: isset($data['set_connection_id']) ? (int) $data['set_connection_id'] : null,
			pageMode: isset($data['mode']) ? (int) $data['mode'] : null
		);

		// Keep the response as pure data; the client renders the popup from the
		// fields it needs (see webroot/js/AchievementAlerts.js).
		if (!empty($result['achievement_updates']))
			$result['achievement_updates'] = array_map([AchievementChecker::class, 'toPopupData'], $result['achievement_updates']);
		//The flame of the menu lights up at the solve that counted the day (webroot/js/DailyStreak.js)
		if (empty($result['error']))
			$result['daily_streak'] = DailyStreak::view(Auth::getUser());

		return $this->respondJson($result);
	}

	private function deduceRelevantSetConnection(array $setConnections): array
	{
		if ($this->query('sid') === null)
			return $setConnections[0];
		foreach ($setConnections as $setConnection)
			if ($setConnection['set_id'] == $this->query('sid'))
				return $setConnection;
		throw new NotFoundException("Problem doesn't exist in the specified set");
	}


	#[HttpGet]
	public function play(?string $id = null, ?string $setConnectionID = null)
	{
		if (Auth::isLoggedIn() && !Auth::isInLevelMode())
			Auth::saveUserField('mode_id', Constants::$LEVEL_MODE);

		if ($setConnectionID)
		{
			$this->renderPlay((int) $setConnectionID, ['canonicalPath' => '/' . (int) $setConnectionID], markAsVisited: true);
			return null;
		}

		if (!$id)
			throw new NotFoundException("Tsumego id not provided");

		$tsumego = $this->fetchTable('Tsumego')->find()->select(['id', 'deleted'])->where(['id' => (int) $id])->first();
		if (!$tsumego)
			throw new NotFoundException("Tsumego not found");
		// The Play menu leads to the last visited problem, which may have been deleted since,
		// so the player lands on the collections instead of an error.
		if ($tsumego->deleted !== null && !Auth::isAdmin())
		{
			CookieFlash::set('That problem was deleted', 'info');
			return $this->redirect('/sets');
		}

		$setConnections = TsumegoUtil::getSetConnectionsWithTitles((int) $id);
		if (!$setConnections)
			throw new NotFoundException("Problem not found in any set");
		$setConnection = $this->deduceRelevantSetConnection($setConnections);

		$this->renderPlay((int) $setConnection['id'], ['canonicalPath' => '/' . $setConnections[0]['id']], markAsVisited: true);
		return null;
	}

	#[HttpGet]
	public function duplicatesearch(string $setConnectionID): mixed
	{

		$setConnection = $this->fetchTable('SetConnection')->find()->where(['id' => (int) $setConnectionID])->first();
		if (!$setConnection)
		{
			CookieFlash::set('Problem not found', 'error');
			return $this->redirect('/sets');
		}

		$similarSearchLogic = new SimilarSearchLogic($setConnection);
		$similarSearchLogic->execute();

		$this->set('result', $similarSearchLogic->result);
		$tsumegoStatus = TsumegoStatusTable::statusOf(Auth::getUserID(), (int) $similarSearchLogic->sourceTsumegoID);
		$this->set(
			'sourceTsumegoButton',
			new TsumegoButton(
				(int) $similarSearchLogic->sourceTsumegoID,
				(int) $setConnectionID,
				$similarSearchLogic->setConnection->num,
				$tsumegoStatus ?: 'N', 0, $similarSearchLogic->sourceSgf));
		$this->set('sourceSetName', $this->fetchTable('Set')->get($setConnection->set_id)->title);
		$this->set('sourceMoveCount', $similarSearchLogic->sourceMoveCount);
		return null;
	}

	#[HttpPost]
	public function edit(string $tsumegoID)
	{
		$this->Authorization->authorize('Tsumego');
		$tsumegoTable = $this->fetchTable('Tsumego');
		$tsumego = $tsumegoTable->find()->where(['id' => (int) $tsumegoID])->first();
		if (!$tsumego)
		{
			CookieFlash::set("Tsumego with id=" . $tsumegoID . ' doesn\'t exist.', 'error');
			return $this->redirect('/sets');
		}

		if (($this->input('delete') ?? '') == 'delete')
			return $this->moveToTrash((int) $tsumegoID);

		//All that is sent is checked first, so a wrong part saves nothing
		$data = $this->inputs();
		$variantType = ($data['variant_type'] ?? '') === '' ? null : (int) $data['variant_type'];
		if ($variantType !== null && !isset(TsumegoVariantTable::BOARD_TYPE_NAMES[$variantType]))
			throw new BadRequestException('Unknown question type');
		$newSgf = $this->uploadedSgf();
		$setConnection = null;
		if ($newSgf !== null)
		{
			$setConnection = $this->fetchTable('SetConnection')->find()->where(['id' => (int) $this->input('set_connection_id'), 'tsumego_id' => (int) $tsumegoID])->first();
			if (!$setConnection)
				throw new BadRequestException('The SGF has to be uploaded to the problem in one of its collections');
		}

		try
		{
			$rating = $this->ratingInput('rating', 'Rating');
			$minimumRating = empty($this->input('minimum-rating')) ? null : $this->ratingInput('minimum-rating', 'Minimum rating');
			$maximumRating = empty($this->input('maximum-rating')) ? null : $this->ratingInput('maximum-rating', 'Maximum rating');
		}
		catch (RatingParseException $e)
		{
			CookieFlash::set($e->getMessage(), 'error');
			return $this->redirect($this->input('redirect'));
		}

		if (!is_null($minimumRating)
			&& !is_null($maximumRating)
			&& $minimumRating > $maximumRating)
		{
			CookieFlash::set("Minimum rating can't be bigger than maximum", 'error');
			return $this->redirect($this->input('redirect'));
		}

		// Normalize description: the edit form shows the display version (with colors swapped for visual context)
		$newDescription = $this->input('description');
		if (!empty($this->input('color_swapped')))
			$newDescription = preg_replace_callback(
				'/\b(Black|black|White|white)\b/',
				fn($match) => ['Black' => 'White', 'black' => 'white', 'White' => 'Black', 'white' => 'black'][$match[1]],
				$newDescription
			);

		$this->changeField($tsumego, 'description', $newDescription, AdminActivityTypeTable::DESCRIPTION_EDIT);
		$this->changeField($tsumego, 'hint', $this->input('hint'), AdminActivityTypeTable::HINT_EDIT);
		$this->changeField($tsumego, 'author', $this->input('author'), AdminActivityTypeTable::AUTHOR_EDIT);
		$this->changeField($tsumego, 'minimum_rating', $minimumRating, AdminActivityTypeTable::MINIMUM_RATING_EDIT);
		$this->changeField($tsumego, 'maximum_rating', $maximumRating, AdminActivityTypeTable::MAXIMUM_RATING_EDIT);

		if ($tsumego->rating != $rating)
			AdminActivityLogger::log(AdminActivityTypeTable::RATING_EDIT, $tsumegoID, null, Util::strOrNull($tsumego->rating), Util::strOrNull($rating));

		$tsumego->rating = Util::clampOptional($rating, $minimumRating, $maximumRating);

		if (array_key_exists('pass', $data) && (int) $tsumego->pass !== (empty($data['pass']) ? 0 : 1))
		{
			$pass = empty($data['pass']) ? 0 : 1;
			AdminActivityLogger::log(AdminActivityTypeTable::PASS_MODE, (int) $tsumegoID, null, null, (string) $pass);
			$tsumego->pass = (bool) $pass;
		}

		$tsumegoTable->saveOrFail($tsumego);
		$this->saveQuestion((int) $tsumegoID, $data, $variantType);

		if ($newSgf !== null)
		{
			AdminActivityLogger::log(AdminActivityTypeTable::SGF_EDIT, (int) $tsumegoID, $setConnection->set_id);
			return $this->saveNewSgf($setConnection->id, $newSgf, $data['sgf_description'] ?? null);
		}
		return $this->redirect($this->input('redirect'));
	}

	//The rating the field gives as a rating or a readable rank; what is wrong with it is named after the field.
	private function ratingInput(string $field, string $label): float
	{
		try
		{
			return Rating::parseRatingOrReadableRank($this->input($field));
		}
		catch (RatingParseException $e)
		{
			throw new RatingParseException($label . ' parse error:' . $e->getMessage(), 0, $e);
		}
	}

	//Sets a field of the problem that the edit form changed, and notes the change in the admin activity.
	private function changeField(Tsumego $tsumego, string $field, mixed $value, int $activityType): void
	{
		if ($tsumego->get($field) == $value)
			return;
		AdminActivityLogger::log($activityType, $tsumego->id, null, Util::strOrNull($tsumego->get($field)), Util::strOrNull($value));
		$tsumego->set($field, $value);
	}

	//The question of a sandbox problem: its type (none or multiple choice) and the answers of a multiple
	//choice the form showed, which go only to a question that stays a multiple choice
	/** @param array<string, mixed> $data */
	private function saveQuestion(int $tsumegoID, array $data, ?int $variantType): void
	{
		if (!array_key_exists('variant_type', $data))
			return;
		$variantTable = $this->fetchTable('TsumegoVariant');
		$variant = $variantTable->find()->where(['tsumego_id' => $tsumegoID])->first();
		$keepsItsQuestion = $variant !== null && $variant->tsumego_variant_type_id === $variantType;
		TsumegoVariantTable::changeType($tsumegoID, $variantType);
		if (!$keepsItsQuestion)
			return;
		$fields = ['answer1', 'answer2', 'answer3', 'answer4', 'explanation', 'correct_answer'];
		$variantTable->patchEntity($variant, array_intersect_key($data, array_flip($fields)));
		$variantTable->saveOrFail($variant);
	}

	//The SGF file attached to the problem's edit form, checked, or null when none was attached
	private function uploadedSgf(): ?string
	{
		$file = $this->uploadedFile('adminUpload');
		if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE)
			return null;
		if ($file['error'] !== UPLOAD_ERR_OK)
			throw new BadRequestException('The SGF file could not be uploaded.');
		$sgf = (string) file_get_contents($file['tmp_name']);
		if ($problem = SgfParser::validationProblem($sgf))
			throw new BadRequestException('Invalid SGF format: ' . $problem);
		return $sgf;
	}

	//Moves the problem to the trash and goes back to its collection, or to the list of collections
	//when it was the last problem of it, as the collection went to the trash with it.
	private function moveToTrash(int $tsumegoID): mixed
	{
		$setConnection = SetConnectionTable::findDisplaySetConnection($tsumegoID);
		Trash::deleteProblem($tsumegoID);
		AdminActivityLogger::log(AdminActivityTypeTable::PROBLEM_DELETE, $tsumegoID);
		CookieFlash::set('The problem was moved to the trash, it can be restored for ' . Trash::$RETENTION_DAYS . ' days.', 'success');
		if (!$setConnection)
			return $this->redirect('/sets');
		$set = $this->fetchTable('Set')->find()->where(['id' => $setConnection->set_id])->first();
		if (!empty($set->deleted))
			return $this->redirect('/sets');
		return $this->redirect(SetTable::url((int) $set->id));
	}

	#[HttpGet]
	public function mergeForm(): mixed
	{
		$this->Authorization->authorize('Tsumego');
		$this->set('_page', 'sandbox');
		$this->set('_title', 'Merge Duplicates');
		return null;
	}

	#[HttpPost]
	public function mergeFinalForm(): mixed
	{
		$this->Authorization->authorize('Tsumego');
		$masterSetConnectionID = $this->input('master-id');
		$slaveSetConnectionID = $this->input('slave-id');
		$setConnectionTable = $this->fetchTable('SetConnection');
		$masterSetConnection = $setConnectionTable->find()->where(['id' => (int) $masterSetConnectionID])->first();
		if (!$masterSetConnection)
		{
			CookieFlash::set('Master set connection does not exist.', 'error');
			return $this->redirect('/tsumegos/mergeForm');
		}

		$slaveSetConnection = $setConnectionTable->find()->where(['id' => (int) $slaveSetConnectionID])->first();
		if (!$slaveSetConnection)
		{
			CookieFlash::set('Slave set connection does not exist.', 'error');
			return $this->redirect('/tsumegos/mergeForm');
		}

		if ($slaveSetConnection->tsumego_id == $masterSetConnection->tsumego_id)
		{
			CookieFlash::set('These are already merged.', 'error');
			return $this->redirect('/tsumegos/mergeForm');
		}
		// the two problems and the current SGF of each, read once
		$sgfs = [];
		foreach (Util::query('SELECT sgf.tsumego_id, sgf.sgf FROM sgf WHERE sgf.tsumego_id IN (?, ?)'
			. ' AND sgf.id = ' . SgfTable::currentVersionIDSql('sgf.tsumego_id'), [$masterSetConnection->tsumego_id, $slaveSetConnection->tsumego_id]) as $row)
				$sgfs[$row['tsumego_id']] = $row['sgf'];

		$this->set('masterTsumegoButtons', $this->mergeButtons($masterSetConnection->tsumego_id, $sgfs));
		$this->set('masterTsumegoID', $masterSetConnection->tsumego_id);
		$this->set('slaveTsumegoButtons', $this->mergeButtons($slaveSetConnection->tsumego_id, $sgfs));
		$this->set('slaveTsumegoID', $slaveSetConnection->tsumego_id);
		return null;
	}

	//A button for each place the problem has in the site's collections, for the merge form.
	/**
	 * @param array<int, string> $sgfs the current SGF of each problem by its id
	 * @return TsumegoButton[]
	 */
	private function mergeButtons(int $tsumegoID, array $sgfs): array
	{
		$buttons = [];
		foreach ($this->fetchTable('SetConnection')->find()
			->innerJoin(['S' => 'set'], ['S.id = SetConnection.set_id'])
			->where(['SetConnection.tsumego_id' => $tsumegoID, SetTable::officialCondition('S')])
			->all() as $setConnection)
				$buttons[] = new TsumegoButton($tsumegoID, $setConnection->id, $setConnection->num, 'N', 0, $sgfs[$tsumegoID] ?? '');
		return $buttons;
	}

	#[HttpPost]
	public function performMerge(): Response
	{
		$this->Authorization->authorize('Tsumego');
		$merger = new TsumegoMerger($this->input('master-tsumego-id'), $this->input('slave-tsumego-id'));
		$flash = $merger->execute();
		if ($flash)
			CookieFlash::set($flash['message'], $flash['type']);
		return $this->redirect('/tsumegos/mergeForm');
	}

	#[HttpGet]
	public function history(string $setConnectionID)
	{
		$setConnection = $this->fetchTable('SetConnection')->find()->where(['id' => (int) $setConnectionID])->first();
		if (!$setConnection)
		{
			CookieFlash::set('Specified set connection not found', 'error');
			return $this->redirect('/sets');
		}

		$tsumegoID = $setConnection->tsumego_id;

		$dailyResults = Util::query("
			SELECT
				DATE(created) AS day,
				MAX(tsumego_rating) AS Rating
			FROM tsumego_attempt
			WHERE tsumego_id = :tsumego_id AND tsumego_rating != 0
			GROUP BY DATE(created)
			ORDER BY day ASC
		", ['tsumego_id' => $tsumegoID]);
		$this->set('dailyResults', $dailyResults);
		$this->set('setConnection', $setConnection);
		$this->set('urlParams', $this->queries());
		$this->set('set', $this->fetchTable('Set')->get($setConnection->set_id));
	}
}
