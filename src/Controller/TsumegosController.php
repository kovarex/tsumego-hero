<?php

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
	public $helpers = ['Html', 'Form'];

	#[HttpPost]
	public function result()
	{
		$this->response->type('application/json');

		$this->Authorization->authorize('User', 'reportResult');

		$data = json_decode($this->request->input(), true) ?: $this->request->data;
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

		$this->response->body(json_encode($result));

		return $this->response;
	}

	private function deduceRelevantSetConnection(array $setConnections): array
	{
		if (!isset($this->request->query['sid']))
			return $setConnections[0];
		foreach ($setConnections as $setConnection)
			if ($setConnection['SetConnection']['set_id'] == $this->request->query['sid'])
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
				$sc = ClassRegistry::init('SetConnection')->findById($setConnectionID);
				if ($sc)
					$this->PlayResultProcessor->markAsVisited((int) $sc['SetConnection']['tsumego_id']);
			}
			$this->set('canonicalPath', '/' . (int) $setConnectionID);
			new Play(function ($name, $value) { $this->set($name, $value); })->play((int) $setConnectionID, $this->request);
			return null;
		}

		if (!$id)
			throw new NotFoundException("Tsumego id not provided");

		$tsumego = ClassRegistry::init('Tsumego')->findById($id);
		if (!$tsumego)
			throw new NotFoundException("Tsumego not found");
		// The Play menu leads to the last visited problem, which may have been deleted since,
		// so the player lands on the collections instead of an error.
		if ($tsumego['Tsumego']['deleted'] && !Auth::isAdmin())
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

		$this->set('canonicalPath', '/' . $setConnections[0]['SetConnection']['id']);
		new Play(function ($name, $value) {
			$this->set($name, $value);
		})->play((int) $setConnection['SetConnection']['id'], $this->request);
		return null;
	}

	//The problem settings an admin changes on the play page: the alternative response mode,
	//passing, and on a sandbox problem whether it is a multiple choice or a score estimating one
	#[HttpPost]
	public function settings(string $tsumegoID): CakeResponse
	{
		$this->Authorization->authorize('Tsumego', 'editSettings');
		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
			throw new NotFoundException('Problem not found');
		$data = $this->request->data;
		foreach (['alternative_response' => AdminActivityTypeTable::ALTERNATIVE_RESPONSE, 'pass' => AdminActivityTypeTable::PASS_MODE] as $field => $activityType)
		{
			$value = empty($data[$field]) ? 0 : 1;
			if ((int) $tsumego['Tsumego'][$field] === $value)
				continue;
			AdminActivityLogger::log($activityType, (int) $tsumegoID, null, null, (string) $value);
			$tsumego['Tsumego'][$field] = $value;
		}
		ClassRegistry::init('Tsumego')->save($tsumego, ['fieldList' => ['alternative_response', 'pass']]);
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
		$variantTable->patchEntity($variant, array_intersect_key((array) $this->request->data, array_flip($fields)));
		$variantTable->saveOrFail($variant);
		return $this->redirect($this->referer('/tsumegos/play/' . (int) $tsumegoID, true));
	}

	#[HttpGet]
	public function duplicatesearch(string $setConnectionID): mixed
	{
		$this->loadModel('Sgf');
		$this->loadModel('Set');

		$setConnection = ClassRegistry::init('SetConnection')->findById($setConnectionID);
		if (!$setConnection)
		{
			CookieFlash::set('Problem not found', 'error');
			return $this->redirect('/sets');
		}

		$similarSearchLogic = new SimilarSearchLogic($setConnection['SetConnection']);
		$similarSearchLogic->execute();

		$this->set('result', $similarSearchLogic->result);
		$tsumegoStatusResult = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'tsumego_id' => $similarSearchLogic->sourceTsumego['id']]]);
		$tsumegoStatus = $tsumegoStatusResult ? $tsumegoStatusResult['TsumegoStatus']['status'] : null;
		$this->set(
			'sourceTsumegoButton',
			new TsumegoButton(
				$similarSearchLogic->sourceTsumego['id'],
				(int) $setConnectionID,
				$similarSearchLogic->setConnection['num'],
				$tsumegoStatus ?: 'N', 0, $similarSearchLogic->sourceSgf));
		$this->set('sourceSetName', ClassRegistry::init('Set')->findById($setConnection['SetConnection']['set_id'])['Set']['title']);
		$this->set('sourceMoveCount', $similarSearchLogic->sourceMoveCount);
		return null;
	}

	#[HttpPost]
	public function edit(string $tsumegoID)
	{
		$this->Authorization->authorize('Tsumego');
		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
		{
			CookieFlash::set("Tsumego with id=" . $tsumegoID . ' doesn\'t exist.', 'error');
			return $this->redirect('/sets');
		}
		$tsumego = $tsumego['Tsumego'];

		if (($this->request->data['delete'] ?? '') == 'delete')
			return $this->moveToTrash((int) $tsumegoID);

		try
		{
			$rating = Rating::parseRatingOrReadableRank($this->request->data['rating']);
		}
		catch (RatingParseException $e)
		{
			CookieFlash::set("Rating parse error:" . $e->getMessage(), 'error');
			return $this->redirect($this->request->data['redirect']);
		}

		$minimumRating = null;
		if (!empty($this->request->data['minimum-rating']))
		{
			try
			{
				$minimumRating = Rating::parseRatingOrReadableRank($this->request->data['minimum-rating']);
			}
			catch (RatingParseException $e)
			{
				CookieFlash::set("Minimum rating parse error:" . $e->getMessage(), 'error');
				return $this->redirect($this->request->data['redirect']);
			}
		}

		$maximumRating = null;
		if (!empty($this->request->data['maximum-rating']))
		{
			try
			{
				$maximumRating = Rating::parseRatingOrReadableRank($this->request->data['maximum-rating']);
			}
			catch (RatingParseException $e)
			{
				CookieFlash::set("Maximum rating parse error:" . $e->getMessage(), 'error');
				return $this->redirect($this->request->data['redirect']);
			}
		}

		if (!is_null($minimumRating)
			&& !is_null($maximumRating)
			&& $minimumRating > $maximumRating)
		{
			CookieFlash::set("Minimum rating can't be bigger than maximum", 'error');
			return $this->redirect($this->request->data['redirect']);
		}

		// Normalize description: the edit form shows the display version (with colors swapped for visual context)
		$newDescription = $this->request->data['description'];
		if (!empty($this->request->data['color_swapped']))
			$newDescription = preg_replace_callback(
				'/\b(Black|black|White|white)\b/',
				fn($m) => ['Black' => 'White', 'black' => 'white', 'White' => 'Black', 'white' => 'black'][$m[1]],
				$newDescription
			);

		if ($tsumego['description'] != $newDescription)
		{
			AdminActivityLogger::log(AdminActivityTypeTable::DESCRIPTION_EDIT, $tsumegoID, null, $tsumego['description'], $newDescription);
			$tsumego['description'] = $newDescription;
		}

		if ($tsumego['hint'] != $this->request->data['hint'])
		{
			AdminActivityLogger::log(AdminActivityTypeTable::HINT_EDIT, $tsumegoID, null, $tsumego['hint'], $this->request->data['hint']);
			$tsumego['hint'] = $this->request->data['hint'];
		}
		if ($tsumego['author'] != $this->request->data['author'])
		{
			AdminActivityLogger::log(AdminActivityTypeTable::AUTHOR_EDIT, $tsumegoID, null, $tsumego['author'], $this->request->data['author']);
			$tsumego['author'] = $this->request->data['author'];
		}
		if ($tsumego['minimum_rating'] != $minimumRating)
		{
			AdminActivityLogger::log(AdminActivityTypeTable::MINIMUM_RATING_EDIT, $tsumegoID, null, Util::strOrNull($tsumego['minimum_rating']), Util::strOrNull($minimumRating));
			$tsumego['minimum_rating'] = $minimumRating;
		}

		if ($tsumego['maximum_rating'] != $maximumRating)
		{
			AdminActivityLogger::log(AdminActivityTypeTable::MAXIMUM_RATING_EDIT, $tsumegoID, null, Util::strOrNull($tsumego['maximum_rating']), Util::strOrNull($maximumRating));
			$tsumego['maximum_rating'] = $maximumRating;
		}

		if ($tsumego['rating'] != $rating)
			AdminActivityLogger::log(AdminActivityTypeTable::RATING_EDIT, $tsumegoID, null, Util::strOrNull($tsumego['rating']), Util::strOrNull($rating));

		$tsumego['rating'] = Util::clampOptional($rating, $minimumRating, $maximumRating);

		ClassRegistry::init('Tsumego')->save($tsumego);
		return $this->redirect($this->request->data['redirect']);
	}

	//Moves the problem to the trash and goes back to its collection, or to the list of collections
	//when it was the last problem of it, as the collection went to the trash with it.
	private function moveToTrash(int $tsumegoID): mixed
	{
		$setConnection = ClassRegistry::init('SetConnection')->findDisplaySetConnection($tsumegoID);
		Trash::deleteProblem($tsumegoID);
		AdminActivityLogger::log(AdminActivityTypeTable::PROBLEM_DELETE, $tsumegoID);
		CookieFlash::set('The problem was moved to the trash, it can be restored for ' . Trash::$RETENTION_DAYS . ' days.', 'success');
		if (!$setConnection)
			return $this->redirect('/sets');
		$set = ClassRegistry::init('Set')->findById($setConnection['SetConnection']['set_id']);
		if (!empty($set['Set']['deleted']))
			return $this->redirect('/sets');
		return $this->redirect(Set::url((int) $set['Set']['id']));
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
		$masterSetConnectionID = $this->request->data['master-id'];
		$slaveSetConnectionID = $this->request->data['slave-id'];
		$masterSetConnection = ClassRegistry::init('SetConnection')->findById($masterSetConnectionID);
		if (!$masterSetConnection)
		{
			CookieFlash::set('Master set connection does not exist.', 'error');
			$this->redirect('/tsumegos/mergeForm');
		}
		$masterSetConnection = $masterSetConnection['SetConnection'];

		$slaveSetConnection = ClassRegistry::init('SetConnection')->findById($slaveSetConnectionID);
		if (!$slaveSetConnection)
		{
			CookieFlash::set('Slave set connection does not exist.', 'error');
			$this->redirect('/tsumegos/mergeForm');
		}
		$slaveSetConnection = $slaveSetConnection['SetConnection'];

		if ($slaveSetConnection['tsumego_id'] == $masterSetConnection['tsumego_id'])
		{
			CookieFlash::set('These are already merged.', 'error');
			$this->redirect('/tsumegos/mergeForm');
		}
		$masterSetConnectionBrothers = ClassRegistry::init('SetConnection')->find('all', [
			'joins' => [[
				'table' => 'set',
				'alias' => 'S',
				'type' => 'INNER',
				'conditions' => ['S.id = SetConnection.set_id'],
			]],
			'conditions' => ['SetConnection.tsumego_id' => $masterSetConnection['tsumego_id'], Set::officialCondition('S')],
		]);
		$slaveSetConnectionBrothers = ClassRegistry::init('SetConnection')->find('all', [
			'joins' => [[
				'table' => 'set',
				'alias' => 'S',
				'type' => 'INNER',
				'conditions' => ['S.id = SetConnection.set_id'],
			]],
			'conditions' => ['SetConnection.tsumego_id' => $slaveSetConnection['tsumego_id'], Set::officialCondition('S')],
		]);
		$masterTsumego = ClassRegistry::init('Tsumego')->findById($masterSetConnection['tsumego_id']);
		$slaveTsumego = ClassRegistry::init('Tsumego')->findById($slaveSetConnection['tsumego_id']);

		$sgfs = [];
		$ids = array_unique(array_merge(
			Hash::extract($masterSetConnectionBrothers, '{n}.SetConnection.tsumego_id'),
			Hash::extract($slaveSetConnectionBrothers, '{n}.SetConnection.tsumego_id'),
		));
		if ($ids)
			foreach (ClassRegistry::init('Sgf')->find('all', [
				'fields' => ['tsumego_id', 'sgf'],
				'conditions' => ['tsumego_id' => $ids, 'id = ' . Sgf::currentVersionIDSql('Sgf.tsumego_id')],
			]) as $s)
				$sgfs[$s['Sgf']['tsumego_id']] = $s['Sgf']['sgf'];

		$masterSetConnectionBrothersButtons = [];
		foreach ($masterSetConnectionBrothers as $masterSetConnectionBrother)
			$masterSetConnectionBrothersButtons [] = new TsumegoButton($masterSetConnectionBrother['SetConnection']['tsumego_id'], $masterSetConnectionBrother['SetConnection']['id'], $masterSetConnectionBrother['SetConnection']['num'], 'N', 0, $sgfs[$masterSetConnectionBrother['SetConnection']['tsumego_id']] ?? '');
		$this->set('masterTsumegoButtons', $masterSetConnectionBrothersButtons);
		$this->set('masterTsumegoID', $masterSetConnection['tsumego_id']);

		$slaveSetConnectionBrothersButtons = [];
		foreach ($slaveSetConnectionBrothers as $slaveSetConnectionBrother)
			$slaveSetConnectionBrothersButtons [] = new TsumegoButton($slaveSetConnectionBrother['SetConnection']['tsumego_id'], $slaveSetConnectionBrother['SetConnection']['id'], $slaveSetConnectionBrother['SetConnection']['num'], 'N', 0, $sgfs[$slaveSetConnectionBrother['SetConnection']['tsumego_id']] ?? '');
		$this->set('slaveTsumegoButtons', $slaveSetConnectionBrothersButtons);
		$this->set('slaveTsumegoID', $slaveSetConnection['tsumego_id']);
		return null;
	}

	#[HttpPost]
	public function performMerge(): void
	{
		$this->Authorization->authorize('Tsumego');
		$merger = new TsumegoMerger($this->request->data['master-tsumego-id'], $this->request->data['slave-tsumego-id']);
		$flash = $merger->execute();
		if ($flash)
			CookieFlash::set($flash['message'], $flash['type']);
		$this->redirect('/tsumegos/mergeForm');
	}

	#[HttpGet]
	public function setupSgf(): mixed
	{
		$this->Authorization->authorize('Tsumego');

		$sgf = ClassRegistry::init('Sgf')->find('first', ['conditions' => ['OR' => ['first_move_color' => null, 'correct_moves' => null]]]);
		if (!$sgf)
		{
			CookieFlash::set('All sgfs converted', 'success');
			return $this->redirect('/sets');
		}
		$sgf = $sgf['Sgf'];
		$this->set('sgf', $sgf['sgf']);
		$this->set('sgfID', $sgf['id']);
		return null;
	}

	#[HttpGet]
	public function setupSgfStep2(string $sgfID, string $firstMoveColor, ?string $correctMoves = null)
	{
		$this->Authorization->authorize('Tsumego');
		$sgf = ClassRegistry::init("Sgf")->findById($sgfID);
		if (!$sgf)
			return;
		$sgf = $sgf['Sgf'];
		if (empty($sgf['sgf']))
		{
			ClassRegistry::init("Sgf")->delete($sgfID);
			return $this->redirect('/tsumegos/setupSgf');
		}
		$sgf['first_move_color'] = $firstMoveColor;
		$sgf['correct_moves'] = $correctMoves ?? '';
		ClassRegistry::init('Sgf')->save($sgf);
		return $this->redirect('/tsumegos/setupSgf');
	}

	#[HttpPost]
	public function setupNewSgfStep2()
	{
		$this->Authorization->authorize('Sgf', 'propose');

		$setConnectionID = $this->request->data["setConnectionID"];

		$setConnection = ClassRegistry::init("SetConnection")->findById($setConnectionID);
		if (!$setConnection)
			return;
		$tsumegoID = $setConnection['SetConnection']['tsumego_id'];

		$sgfData = $this->request->data['sgf'];
		$firstMoveColor = $this->request->data['firstMoveColor'];
		$correctMoves = $this->request->data['correctMoves'];

		if ($problem = SgfParser::validationProblem($sgfData))
			throw new BadRequestException('Invalid SGF format: ' . $problem);

		$tsumego = ClassRegistry::init("Tsumego")->findById($tsumegoID);
		if (!$tsumego)
			return;

		$tsumego = $tsumego['Tsumego'];
		$sgf = [];
		$sgf['sgf'] = $sgfData;
		$sgf['user_id'] = Auth::getUserId();
		$sgf['tsumego_id'] = $tsumego['id'];
		$sgf += Auth::isAdmin() ? Sgf::acceptedBy(Auth::getUserID()) : ['sgf_status_id' => Sgf::STATUS_PROPOSED];
		$sgf['first_move_color'] = $firstMoveColor;
		$sgf['correct_moves'] = $correctMoves;
		// the admin reviewing a proposal needs to know what it is about, an admin's own edit may go without
		$sgf['description'] = Sgf::descriptionFromRequest($this->request->data['description'] ?? null, !Auth::isAdmin());
		// what the board editor did to the SGF it was opened with, so the comments can follow
		$source = ClassRegistry::init('Sgf')->findById((int) ($this->request->data['sourceSgfID'] ?? 0));
		if ($source && $source['Sgf']['tsumego_id'] == $tsumego['id'])
		{
			$sgf['source_sgf_id'] = $source['Sgf']['id'];
			$sgf['transformation_from_source'] = BoardTransformation::fromJson($this->request->data['transformationFromSource'] ?? null)->toJson();
		}
		ClassRegistry::init("Sgf")->create();
		ClassRegistry::init("Sgf")->save($sgf);
		SgfCommentReorientation::afterAccepted((int) ClassRegistry::init("Sgf")->id);
		return $this->redirect('/' . $setConnectionID);
	}

	#[HttpGet]
	public function history(string $setConnectionID)
	{
		$setConnection = ClassRegistry::init("SetConnection")->findById($setConnectionID);
		if (!$setConnection)
		{
			CookieFlash::set('Specified set connection not found', 'error');
			return $this->redirect('/sets');
		}

		$setConnection = $setConnection['SetConnection'];
		$tsumegoID = $setConnection['tsumego_id'];

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
		$this->set('urlParams', $this->params['url']);
		$this->set('set', ClassRegistry::init("Set")->findById($setConnection['set_id'])['Set']);
	}
}
