<?php

App::uses('SgfParser', 'Utility');
App::uses('TsumegoUtil', 'Utility');
App::uses('AdminActivityUtil', 'Utility');
App::uses('TsumegoButton', 'Utility');
App::uses('CookieFlash', 'Utility');
App::uses('TsumegoMerger', 'Utility');
App::uses('SimilarSearchResultItem', 'Utility');
App::uses('SimilarSearchLogic', 'Utility');
App::uses('AchievementChecker', 'Utility');
App::uses('NotFoundException', 'Routing/Error');
App::uses('BadRequestException', 'Routing/Error');
App::uses('ForbiddenException', 'Routing/Error');

use App\Attribute\HttpPost;

require_once(__DIR__ . "/Component/Play.php");

class TsumegosController extends AppController
{
	public $helpers = ['Html', 'Form'];

	#[HttpPost]
	public function result()
	{
		$this->response->type('application/json');

		if (!Auth::isLoggedIn())
			throw new ForbiddenException('Login required');

		$data = json_decode($this->request->input(), true) ?: $this->request->data;
		if (!$data || empty($data['tsumego_id']))
			throw new BadRequestException('Missing tsumego_id');

		$result = $this->PlayResultProcessor->processResult(
			(int) $data['tsumego_id'],
			!empty($data['solved']),
			(float) ($data['seconds'] ?? 0),
			!empty($data['timeout'])
		);

		// Keep the response as pure data; the client renders the popup from the
		// fields it needs (see webroot/js/AchievementAlerts.js).
		if (!empty($result['achievement_updates']))
			$result['achievement_updates'] = array_map(['AchievementChecker', 'toPopupData'], $result['achievement_updates']);

		$this->response->body(json_encode($result));

		return $this->response;
	}

	private function deduceRelevantSetConnection(array $setConnections): array
	{
		if (!isset($this->params->query['sid']))
			return $setConnections[0];
		foreach ($setConnections as $setConnection)
			if ($setConnection['SetConnection']['set_id'] == $this->params->query['sid'])
				return $setConnection;
		throw new NotFoundException("Problem doesn't exist in the specified set");
	}

	public static function tsumegoOrSetLink($tsumegoFilters, ?int $setConnectionID, string $setID): string
	{
		if ($setConnectionID)
			return '/' . $setConnectionID;
		return '/sets/view/' . $setID; // edge of the set (last or first), so we return to the set
	}

	public function play($id = null, $setConnectionID = null)
	{
		if (Auth::isLoggedIn() && !Auth::isInLevelMode())
			Auth::saveUserField('mode', Constants::$LEVEL_MODE);

		if ($setConnectionID)
		{
			if (Auth::isLoggedIn())
			{
				$sc = ClassRegistry::init('SetConnection')->findById($setConnectionID);
				if ($sc)
					$this->PlayResultProcessor->markAsVisited((int) $sc['SetConnection']['tsumego_id']);
			}
			return new Play(function ($name, $value) { $this->set($name, $value); })->play($setConnectionID, $this->params, $this->data);
		}

		if (!$id)
			throw new NotFoundException("Tsumego id not provided");

		$tsumego = ClassRegistry::init('Tsumego')->findById($id);
		if (!$tsumego)
			throw new NotFoundException("Tsumego not found");

		if (Auth::isLoggedIn())
			$this->PlayResultProcessor->markAsVisited((int) $id);

		$setConnections = TsumegoUtil::getSetConnectionsWithTitles($id);
		if (!$setConnections)
			throw new NotFoundException("Problem not found in any set");
		$setConnection = $this->deduceRelevantSetConnection($setConnections);
		return new Play(function ($name, $value) {
			$this->set($name, $value);
		})->play($setConnection['SetConnection']['id'], $this->params, $this->data);
	}

	public function duplicatesearch($setConnectionID): mixed
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
				$setConnectionID,
				$similarSearchLogic->setConnection['num'],
				$tsumegoStatus ?: 'N', 0, $similarSearchLogic->sourceSgf));
		$this->set('sourceSetName', ClassRegistry::init('Set')->findById($setConnection['SetConnection']['set_id'])['Set']['title']);
		$this->set('sourceMoveCount', $similarSearchLogic->sourceMoveCount);
		return null;
	}

	public static function getTheIdForTheThing($num)
	{
		$t = [];
		$s = ClassRegistry::init('Set')->find('all', ['order' => 'id ASC', 'conditions' => ['public' => 1]]) ?: [];
		$sCount = count($s);

		for ($i = 0; $i < $sCount; $i++)
		{
			$sc = ClassRegistry::init('SetConnection')->find('all', ['order' => 'tsumego_id ASC', 'conditions' => ['set_id' => $s[$i]['Set']['id']]]) ?: [];
			$scCount = count($sc);

			for ($j = 0; $j < $scCount; $j++)
				array_push($t, $sc[$j]['SetConnection']['tsumego_id']);
		}
		if ($num >= count($t))
			return -1;

		return $t[$num];
	}

	public function edit($tsumegoID)
	{
		$this->Authorization->authorize('Tsumego');
		$tsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID);
		if (!$tsumego)
		{
			CookieFlash::set("Tsumego with id=" . $tsumegoID . ' doesn\'t exist.', 'error');
			return $this->redirect('/sets');
		}
		$tsumego = $tsumego['Tsumego'];

		if ($this->data['delete'] == 'delete')
		{
			$tsumego['deleted'] = date('Y-m-d H:i:s');
			ClassRegistry::init('Tsumego')->save($tsumego);
			AdminActivityLogger::log(AdminActivityType::PROBLEM_DELETE, $tsumegoID);
			return $this->redirect("/sets");
		}

		try
		{
			$rating = Rating::parseRatingOrReadableRank($this->data['rating']);
		}
		catch (RatingParseException $e)
		{
			CookieFlash::set("Rating parse error:" . $e->getMessage(), 'error');
			return $this->redirect($this->data['redirect']);
		}

		$minimumRating = null;
		if (!empty($this->data['minimum-rating']))
		{
			try
			{
				$minimumRating = Rating::parseRatingOrReadableRank($this->data['minimum-rating']);
			}
			catch (RatingParseException $e)
			{
				CookieFlash::set("Minimum rating parse error:" . $e->getMessage(), 'error');
				return $this->redirect($this->data['redirect']);
			}
		}

		$maximumRating = null;
		if (!empty($this->data['maximum-rating']))
		{
			try
			{
				$maximumRating = Rating::parseRatingOrReadableRank($this->data['maximum-rating']);
			}
			catch (RatingParseException $e)
			{
				CookieFlash::set("Maximum rating parse error:" . $e->getMessage(), 'error');
				return $this->redirect($this->data['redirect']);
			}
		}

		if (!is_null($minimumRating)
			&& !is_null($maximumRating)
			&& $minimumRating > $maximumRating)
		{
			CookieFlash::set("Minimum rating can't be bigger than maximum", 'error');
			return $this->redirect($this->data['redirect']);
		}

		if ($tsumego['description'] != $this->data['description'])
		{
			AdminActivityLogger::log(AdminActivityType::DESCRIPTION_EDIT, $tsumegoID, null, $tsumego['description'], $this->data['description']);
			$tsumego['description'] = $this->data['description'];
		}

		if ($tsumego['hint'] != $this->data['hint'])
		{
			AdminActivityLogger::log(AdminActivityType::HINT_EDIT, $tsumegoID, null, $tsumego['hint'], $this->data['hint']);
			$tsumego['hint'] = $this->data['hint'];
		}
		if ($tsumego['author'] != $this->data['author'])
		{
			AdminActivityLogger::log(AdminActivityType::AUTHOR_EDIT, $tsumegoID, null, $tsumego['author'], $this->data['author']);
			$tsumego['author'] = $this->data['author'];
		}
		if ($tsumego['minimum_rating'] != $minimumRating)
		{
			AdminActivityLogger::log(AdminActivityType::MINIMUM_RATING_EDIT, $tsumegoID, null, Util::strOrNull($tsumego['minimum_rating']), Util::strOrNull($minimumRating));
			$tsumego['minimum_rating'] = $minimumRating;
		}

		if ($tsumego['maximum_rating'] != $maximumRating)
		{
			AdminActivityLogger::log(AdminActivityType::MAXIMUM_RATING_EDIT, $tsumegoID, null, Util::strOrNull($tsumego['maximum_rating']), Util::strOrNull($maximumRating));
			$tsumego['maximum_rating'] = $maximumRating;
		}

		if ($tsumego['rating'] != $rating)
			AdminActivityLogger::log(AdminActivityType::RATING_EDIT, $tsumegoID, null, Util::strOrNull($tsumego['rating']), Util::strOrNull($rating));

		$tsumego['rating'] = Util::clampOptional($rating, $minimumRating, $maximumRating);

		ClassRegistry::init('Tsumego')->save($tsumego);
		return $this->redirect($this->data['redirect']);
	}

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
		$masterSetConnectionBrothers = ClassRegistry::init('SetConnection')->find('all', ['conditions' => ['tsumego_id' => $masterSetConnection['tsumego_id']]]);
		$slaveSetConnectionBrothers = ClassRegistry::init('SetConnection')->find('all', ['conditions' => ['tsumego_id' => $slaveSetConnection['tsumego_id']]]);
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
				'conditions' => ['tsumego_id' => $ids, 'id IN (SELECT MAX(id) FROM sgf GROUP BY tsumego_id)'],
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
	public function performMerge()
	{
		$this->Authorization->authorize('Tsumego');
		$merger = new TsumegoMerger($this->request->data['master-tsumego-id'], $this->request->data['slave-tsumego-id']);
		$flash = $merger->execute();
		if ($flash)
			CookieFlash::set($flash['message'], $flash['type']);
		$this->redirect('/tsumegos/mergeForm');
	}

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

	public function setupSgfStep2($sgfID, $firstMoveColor, $correctMoves = null)
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

	public function setupNewSgfStep2()
	{
		$this->Authorization->authorize('Sgf', 'propose');

		$setConnectionID = $this->data["setConnectionID"];

		$setConnection = ClassRegistry::init("SetConnection")->findById($setConnectionID);
		if (!$setConnection)
			return;
		$tsumegoID = $setConnection['SetConnection']['tsumego_id'];

		$sgfData = $this->data['sgf'];
		$firstMoveColor = $this->data['firstMoveColor'];
		$correctMoves = $this->data['correctMoves'];

		SgfController::validateSgfFormat($sgfData);

		$tsumego = ClassRegistry::init("Tsumego")->findById($tsumegoID);
		if (!$tsumego)
			return;

		$tsumego = $tsumego['Tsumego'];
		$sgf = [];
		$sgf['sgf'] = $sgfData;
		$sgf['user_id'] = Auth::getUserId();
		$sgf['tsumego_id'] = $tsumego['id'];
		$sgf['accepted'] = Auth::isAdmin() ? true : false;
		$sgf['first_move_color'] = $firstMoveColor;
		$sgf['correct_moves'] = $correctMoves;
		ClassRegistry::init("Sgf")->create();
		ClassRegistry::init("Sgf")->save($sgf);
		return $this->redirect('/' . $setConnectionID);
	}

	public function history($setConnectionID)
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
