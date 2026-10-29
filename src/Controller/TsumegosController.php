<?php

namespace App\Controller;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response as CakeResponse;
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
use App\Utility\BoardTransformation;
use App\Utility\Constants;
use App\Utility\CookieFlash;
use App\Utility\Play;
use App\Utility\PlayOutcome;
use App\Utility\Rating;
use App\Utility\RatingParseException;
use App\Utility\SgfCommentReorientation;
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
			if (Auth::isLoggedIn())
			{
				$setConnection = $this->fetchTable('SetConnection')->find()->select(['tsumego_id'])->where(['id' => (int) $setConnectionID])->first();
				if ($setConnection)
					$this->PlayResultProcessor->markAsVisited($setConnection->tsumego_id);
			}
			$this->set('canonicalPath', '/' . (int) $setConnectionID);
			new Play(function ($name, $value) { $this->set($name, $value); })->play((int) $setConnectionID, $this->request);
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

		if (Auth::isLoggedIn())
			$this->PlayResultProcessor->markAsVisited((int) $id);

		$setConnections = TsumegoUtil::getSetConnectionsWithTitles((int) $id);
		if (!$setConnections)
			throw new NotFoundException("Problem not found in any set");
		$setConnection = $this->deduceRelevantSetConnection($setConnections);

		$this->set('canonicalPath', '/' . $setConnections[0]['id']);
		new Play(function ($name, $value) {
			$this->set($name, $value);
		})->play((int) $setConnection['id'], $this->request);
		return null;
	}

	//The problem settings an admin changes on the play page: the alternative response mode,
	//passing, and on a sandbox problem whether it is a multiple choice or a score estimating one
	#[HttpPost]
	public function settings(string $tsumegoID): CakeResponse
	{
		$this->Authorization->authorize('Tsumego', 'editSettings');
		$tsumegoTable = $this->fetchTable('Tsumego');
		$tsumego = $tsumegoTable->find()->select(['id', 'alternative_response', 'pass'])->where(['id' => (int) $tsumegoID])->first();
		if (!$tsumego)
			throw new NotFoundException('Problem not found');
		$data = $this->inputs();
		foreach (['alternative_response' => AdminActivityTypeTable::ALTERNATIVE_RESPONSE, 'pass' => AdminActivityTypeTable::PASS_MODE] as $field => $activityType)
		{
			$value = empty($data[$field]) ? 0 : 1;
			if ((int) $tsumego->get($field) === $value)
				continue;
			AdminActivityLogger::log($activityType, (int) $tsumegoID, null, null, (string) $value);
			$tsumego->set($field, (bool) $value);
		}
		$tsumegoTable->saveOrFail($tsumego);
		if (array_key_exists('variant_type', $data))
			TsumegoVariantTable::changeType((int) $tsumegoID, $data['variant_type'] === '' ? null : (int) $data['variant_type']);
		return $this->redirect($this->referer('/tsumegos/play/' . (int) $tsumegoID, true));
	}

	//The answers of the problem's multiple choice or score estimating question
	#[HttpPost]
	public function variant(string $tsumegoID): CakeResponse
	{
		$this->Authorization->authorize('Tsumego', 'editSettings');
		$variantTable = $this->fetchTable('TsumegoVariant');
		$variant = $variantTable->find()->where(['tsumego_id' => (int) $tsumegoID])->first();
		if (!$variant)
			throw new NotFoundException('The problem has no question to answer');
		$fields = $variant->tsumego_variant_type_id === TsumegoVariantTable::TYPE_MULTIPLE_CHOICE
			? ['answer1', 'answer2', 'answer3', 'answer4', 'explanation', 'correct_answer']
			: ['winner', 'answer1', 'answer2', 'answer3'];
		$variantTable->patchEntity($variant, array_intersect_key($this->inputs(), array_flip($fields)));
		$variantTable->saveOrFail($variant);
		return $this->redirect($this->referer('/tsumegos/play/' . (int) $tsumegoID, true));
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

		try
		{
			$rating = Rating::parseRatingOrReadableRank($this->input('rating'));
		}
		catch (RatingParseException $e)
		{
			CookieFlash::set("Rating parse error:" . $e->getMessage(), 'error');
			return $this->redirect($this->input('redirect'));
		}

		$minimumRating = null;
		if (!empty($this->input('minimum-rating')))
		{
			try
			{
				$minimumRating = Rating::parseRatingOrReadableRank($this->input('minimum-rating'));
			}
			catch (RatingParseException $e)
			{
				CookieFlash::set("Minimum rating parse error:" . $e->getMessage(), 'error');
				return $this->redirect($this->input('redirect'));
			}
		}

		$maximumRating = null;
		if (!empty($this->input('maximum-rating')))
		{
			try
			{
				$maximumRating = Rating::parseRatingOrReadableRank($this->input('maximum-rating'));
			}
			catch (RatingParseException $e)
			{
				CookieFlash::set("Maximum rating parse error:" . $e->getMessage(), 'error');
				return $this->redirect($this->input('redirect'));
			}
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

		if ($tsumego->description != $newDescription)
		{
			AdminActivityLogger::log(AdminActivityTypeTable::DESCRIPTION_EDIT, $tsumegoID, null, $tsumego->description, $newDescription);
			$tsumego->description = $newDescription;
		}

		if ($tsumego->hint != $this->input('hint'))
		{
			AdminActivityLogger::log(AdminActivityTypeTable::HINT_EDIT, $tsumegoID, null, $tsumego->hint, $this->input('hint'));
			$tsumego->hint = $this->input('hint');
		}
		if ($tsumego->author != $this->input('author'))
		{
			AdminActivityLogger::log(AdminActivityTypeTable::AUTHOR_EDIT, $tsumegoID, null, $tsumego->author, $this->input('author'));
			$tsumego->author = $this->input('author');
		}
		if ($tsumego->minimum_rating != $minimumRating)
		{
			AdminActivityLogger::log(AdminActivityTypeTable::MINIMUM_RATING_EDIT, $tsumegoID, null, Util::strOrNull($tsumego->minimum_rating), Util::strOrNull($minimumRating));
			$tsumego->minimum_rating = $minimumRating;
		}

		if ($tsumego->maximum_rating != $maximumRating)
		{
			AdminActivityLogger::log(AdminActivityTypeTable::MAXIMUM_RATING_EDIT, $tsumegoID, null, Util::strOrNull($tsumego->maximum_rating), Util::strOrNull($maximumRating));
			$tsumego->maximum_rating = $maximumRating;
		}

		if ($tsumego->rating != $rating)
			AdminActivityLogger::log(AdminActivityTypeTable::RATING_EDIT, $tsumegoID, null, Util::strOrNull($tsumego->rating), Util::strOrNull($rating));

		$tsumego->rating = Util::clampOptional($rating, $minimumRating, $maximumRating);

		$tsumegoTable->saveOrFail($tsumego);
		return $this->redirect($this->input('redirect'));
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
		$masterSetConnectionBrothers = $setConnectionTable->find()
			->innerJoin(['S' => 'set'], ['S.id = SetConnection.set_id'])
			->where(['SetConnection.tsumego_id' => $masterSetConnection->tsumego_id, SetTable::officialCondition('S')])
			->all()->toList();
		$slaveSetConnectionBrothers = $setConnectionTable->find()
			->innerJoin(['S' => 'set'], ['S.id = SetConnection.set_id'])
			->where(['SetConnection.tsumego_id' => $slaveSetConnection->tsumego_id, SetTable::officialCondition('S')])
			->all()->toList();

		$sgfs = [];
		$ids = array_unique(array_merge(
			array_map(fn($brother) => $brother->tsumego_id, $masterSetConnectionBrothers),
			array_map(fn($brother) => $brother->tsumego_id, $slaveSetConnectionBrothers),
		));
		if ($ids)
			foreach (Util::query('SELECT sgf.tsumego_id, sgf.sgf FROM sgf WHERE sgf.tsumego_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'
				. ' AND sgf.id = ' . SgfTable::currentVersionIDSql('sgf.tsumego_id'), array_values($ids)) as $row)
					$sgfs[$row['tsumego_id']] = $row['sgf'];

		$masterSetConnectionBrothersButtons = [];
		foreach ($masterSetConnectionBrothers as $masterSetConnectionBrother)
			$masterSetConnectionBrothersButtons [] = new TsumegoButton($masterSetConnectionBrother->tsumego_id, $masterSetConnectionBrother->id, $masterSetConnectionBrother->num, 'N', 0, $sgfs[$masterSetConnectionBrother->tsumego_id] ?? '');
		$this->set('masterTsumegoButtons', $masterSetConnectionBrothersButtons);
		$this->set('masterTsumegoID', $masterSetConnection->tsumego_id);

		$slaveSetConnectionBrothersButtons = [];
		foreach ($slaveSetConnectionBrothers as $slaveSetConnectionBrother)
			$slaveSetConnectionBrothersButtons [] = new TsumegoButton($slaveSetConnectionBrother->tsumego_id, $slaveSetConnectionBrother->id, $slaveSetConnectionBrother->num, 'N', 0, $sgfs[$slaveSetConnectionBrother->tsumego_id] ?? '');
		$this->set('slaveTsumegoButtons', $slaveSetConnectionBrothersButtons);
		$this->set('slaveTsumegoID', $slaveSetConnection->tsumego_id);
		return null;
	}

	#[HttpPost]
	public function performMerge(): CakeResponse
	{
		$this->Authorization->authorize('Tsumego');
		$merger = new TsumegoMerger($this->input('master-tsumego-id'), $this->input('slave-tsumego-id'));
		$flash = $merger->execute();
		if ($flash)
			CookieFlash::set($flash['message'], $flash['type']);
		return $this->redirect('/tsumegos/mergeForm');
	}

	#[HttpGet]
	public function setupSgf(): mixed
	{
		$this->Authorization->authorize('Tsumego');

		$sgf = $this->fetchTable('Sgf')->find()
			->select(['id', 'sgf'])
			->where(['OR' => ['first_move_color IS' => null, 'correct_moves IS' => null]])
			->first();
		if (!$sgf)
		{
			CookieFlash::set('All sgfs converted', 'success');
			return $this->redirect('/sets');
		}
		$this->set('sgf', $sgf->sgf);
		$this->set('sgfID', $sgf->id);
		return null;
	}

	#[HttpGet]
	public function setupSgfStep2(int $sgfID, string $firstMoveColor, ?string $correctMoves = null)
	{
		$this->Authorization->authorize('Tsumego');
		$sgfTable = $this->fetchTable('Sgf');
		$sgf = $sgfTable->find()->select(['id', 'sgf'])->where(['id' => $sgfID])->first();
		if (!$sgf)
			return;
		if (empty($sgf->sgf))
		{
			$sgfTable->deleteOrFail($sgf);
			return $this->redirect('/tsumegos/setupSgf');
		}
		$sgf->first_move_color = $firstMoveColor;
		$sgf->correct_moves = $correctMoves ?? '';
		$sgfTable->saveOrFail($sgf);
		return $this->redirect('/tsumegos/setupSgf');
	}

	#[HttpPost]
	public function setupNewSgfStep2()
	{
		$this->Authorization->authorize('Sgf', 'propose');

		$setConnectionID = $this->input('setConnectionID');

		$setConnection = $this->fetchTable('SetConnection')->find()->select(['tsumego_id'])->where(['id' => (int) $setConnectionID])->first();
		if (!$setConnection)
			return;
		$tsumegoID = $setConnection->tsumego_id;

		$sgfData = $this->input('sgf');
		$firstMoveColor = $this->input('firstMoveColor');
		$correctMoves = $this->input('correctMoves');

		if ($problem = SgfParser::validationProblem($sgfData))
			throw new BadRequestException('Invalid SGF format: ' . $problem);

		if (!$this->fetchTable('Tsumego')->exists(['id' => $tsumegoID]))
			return;

		$sgf = [];
		$sgf['sgf'] = $sgfData;
		$sgf['user_id'] = Auth::getUserId();
		$sgf['tsumego_id'] = $tsumegoID;
		$sgf += Auth::isAdmin() ? SgfTable::acceptedBy(Auth::getUserID()) : ['sgf_status_id' => SgfTable::STATUS_PROPOSED];
		$sgf['first_move_color'] = $firstMoveColor;
		$sgf['correct_moves'] = $correctMoves;
		// the admin reviewing a proposal needs to know what it is about, an admin's own edit may go without
		$sgf['description'] = SgfTable::descriptionFromRequest($this->input('description'), !Auth::isAdmin());
		// what the board editor did to the SGF it was opened with, so the comments can follow
		$sgfTable = $this->fetchTable('Sgf');
		$source = $sgfTable->find()->select(['id', 'tsumego_id'])->where(['id' => (int) ($this->input('sourceSgfID') ?? 0)])->first();
		if ($source && $source->tsumego_id == $tsumegoID)
		{
			$sgf['source_sgf_id'] = $source->id;
			$sgf['transformation_from_source'] = BoardTransformation::fromJson($this->input('transformationFromSource'))->toJson();
		}
		$saved = $sgfTable->saveOrFail($sgfTable->newEntity($sgf));
		SgfCommentReorientation::afterAccepted($saved->id);
		return $this->redirect('/' . $setConnectionID);
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
