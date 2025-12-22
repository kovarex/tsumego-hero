<?php

App::uses('SgfParser', 'Utility');
App::uses('TsumegoUtil', 'Utility');
App::uses('AdminActivityUtil', 'Utility');
App::uses('TsumegoButton', 'Utility');
App::uses('AppException', 'Utility');
App::uses('CookieFlash', 'Utility');
App::uses('TsumegoMerger', 'Utility');
App::uses('SimilarSearchResultItem', 'Utility');
App::uses('SimilarSearchLogic', 'Utility');
require_once(__DIR__ . "/Component/Play.php");

class TsumegosController extends AppController
{
	public $helpers = ['Html', 'Form'];

	private function deduceRelevantSetConnection(array $setConnections): array
	{
		if (!isset($this->params->query['sid']))
			return $setConnections[0];
		foreach ($setConnections as $setConnection)
			if ($setConnection['SetConnection']['set_id'] == $this->params->query['sid'])
				return $setConnection;
		throw new AppException("Problem doesn't exist in the specified set");
	}

	public static function getMatchingSetConnectionOfOtherTsumego(int $tsumegoID, int $currentSetID): ?int
	{
		if ($setConnections = ClassRegistry::init('SetConnection')->find('all', ['conditions' => ['tsumego_id' => $tsumegoID]]))
			if ($result = array_find($setConnections, function (array $setConnection) use (&$currentSetID): bool {
				return $setConnection['SetConnection']['set_id'] == $currentSetID;
			}))
				return $result['SetConnection']['id'];
		return null;
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
		{
			Auth::getUser()['mode'] = Constants::$LEVEL_MODE;
			Auth::saveUser();
		}

		if ($setConnectionID)
			return new Play(
				function ($name, $value) { $this->set($name, $value); },
				function ($url) { return $this->redirect($url); })->play($setConnectionID, $this->params, $this->data);

		if (!$id)
			throw new AppException("Tsumego id not provided");

		$setConnections = TsumegoUtil::getSetConnectionsWithTitles($id);
		if (!$setConnections)
			throw new AppException("Problem without any set connection"); // some redirect/nicer message ?
		$setConnection = $this->deduceRelevantSetConnection($setConnections);
		return new Play(
			function ($name, $value) { $this->set($name, $value); },
			function ($url) { return $this->redirect($url); })->play($setConnection['SetConnection']['id'], $this->params, $this->data);
	}

	public static function inArrayX($x, $newArray)
	{
		$newArrayCount = count($newArray);

		for ($i = 0; $i < $newArrayCount; $i++)
			if ($x['TagConnection']['tag_id'] == $newArray[$i]['TagConnection']['tag_id'] && $x['TagConnection']['approved'] == 1)
				return true;

		return false;
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
		$tsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', [
			'conditions' => [
				'user_id' => Auth::getUserID(),
				'tsumego_id' => $similarSearchLogic->sourceTsumego['id']]])['TsumegoStatus'];
		$this->set(
			'sourceTsumegoButton',
			new TsumegoButton(
				$similarSearchLogic->sourceTsumego['id'],
				$setConnectionID,
				$similarSearchLogic->setConnection['num'],
				$tsumegoStatus['status'],
				$similarSearchLogic->sourceTsumego['rating']));
		$this->set('sourceSetName', ClassRegistry::init('Set')->findById($setConnection['SetConnection']['set_id'])['Set']['title']);
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

	public static function getStartingPlayer($sgf)
	{
		$bStart = strpos($sgf, ';B');
		$wStart = strpos($sgf, ';W');
		if ($wStart == 0)
			return 0;
		if ($bStart == 0)
			return 1;
		if ($bStart <= $wStart)
			return 0;

		return 1;
	}

	public static function findUt($id = null, $utsMap = null)
	{
		if (!isset($utsMap[$id]))
			return null;
		$ut = [];
		$ut['TsumegoStatus']['tsumego_id'] = $id;
		$ut['TsumegoStatus']['user_id'] = Auth::getUserID();
		$ut['TsumegoStatus']['status'] = $utsMap[$id];
		$ut['TsumegoStatus']['created'] = date('Y-m-d H:i:s');

		return $ut;
	}

	public static function commentCoordinates($c = null, $counter = null, $noSyntax = null)
	{
		if (!is_string($c))
			return [$c, ''];
		$m = str_split($c);
		$n = '';
		$n2 = '';
		$hasLink = false;
		$mCount = count($m);

		for ($i = 0; $i < $mCount; $i++)
		{
			if (isset($m[$i + 1]))
				if (preg_match('/[a-tA-T]/', $m[$i]) && is_numeric($m[$i + 1]))
					if (!preg_match('/[a-tA-T]/', $m[$i - 1]) && !is_numeric($m[$i - 1]))
						if (is_numeric($m[$i + 2]))
						{
							if (!preg_match('/[a-tA-T]/', $m[$i + 3]) && !is_numeric($m[$i + 3]))
							{
								$n .= $m[$i] . $m[$i + 1] . $m[$i + 2] . ' ';
								$n2 .= $i . '/' . ($i + 2) . ' ';
							}
						}
						elseif (!preg_match('/[a-tA-T]/', $m[$i + 2]))
						{
							$n .= $m[$i] . $m[$i + 1] . ' ';
							$n2 .= $i . '/' . ($i + 1) . ' ';
						}

			if ($m[$i] == '<' && $m[$i + 1] == '/' && $m[$i + 2] == 'a' && $m[$i + 3] == '>')
				$hasLink = true;
		}
		if (substr($n, -1) == ' ')
			$n = substr($n, 0, -1);
		if (substr($n2, -1) == ' ')
			$n2 = substr($n2, 0, -1);

		if ($hasLink)
			$n2 = [];
		$coordForBesogo = [];

		if (is_string($n2) && strlen($n2) > 1)
		{
			$n2x = explode(' ', $n2);
			$fn = 1;
			$n2xCount = count($n2x);
			for ($i = $n2xCount - 1; $i >= 0; $i--)
			{
				$n2xx = explode('/', $n2x[$i]);
				$a = substr($c, 0, (int) $n2xx[0]);
				$cx = substr($c, (int) $n2xx[0], (int) $n2xx[1] - (int) $n2xx[0] + 1);
				if ($noSyntax)
					$b = '<span class="go-coord" title="Hover to highlight on board" id="ccIn' . $counter . $fn . '" onmouseover="ccIn' . $counter . $fn . '(event)" onmouseout="ccOut' . $counter . $fn . '()">';
				else
					$b = '<span class=\"go-coord\" title="Hover to highlight on board" id="ccIn' . $counter . $fn . '" onmouseover=\"ccIn' . $counter . $fn . '(event)\" onmouseout=\"ccOut' . $counter . $fn . '()\">';

				$d = '</span>';
				$e = substr($c, $n2xx[1] + 1, strlen($c) - 1);
				$coordForBesogo[$i] = $cx;
				$c = $a . $b . $cx . $d . $e;
				$fn++;
			}
		}

		$xx = explode(' ', $n);
		$coord1 = 0;
		$finalCoord = '';
		$xxCount = count($xx);

		for ($i = 0; $i < $xxCount; $i++)
			if (strlen($xx[$i]) > 1)
			{
				$xxxx = [];
				$xxx = str_split($xx[$i]);
				$xxxCount = count($xxx);

				for ($j = 0; $j < $xxxCount; $j++)
				{
					if (preg_match('/[0-9]/', $xxx[$j]))
						array_push($xxxx, $xxx[$j]);
					if (preg_match('/[a-tA-T]/', $xxx[$j]))
						$coord1 = TsumegosController::convertCoord($xxx[$j]);
				}
				$coord2 = TsumegosController::convertCoord2(implode('', $xxxx));
				if ($coord1 != -1 && $coord2 != -1)
					$finalCoord .= $coord1 . '-' . $coord2 . '-' . $coordForBesogo[$i] . ' ';
			}
		if (substr($finalCoord, -1) == ' ')
			$finalCoord = substr($finalCoord, 0, -1);

		$array = [];
		array_push($array, $c);
		array_push($array, $finalCoord);

		return $array;
	}

	public static function convertCoord($l = null)
	{
		switch (strtolower($l))
		{
			case 'a':
				return 0;
			case 'b':
				return 1;
			case 'c':
				return 2;
			case 'd':
				return 3;
			case 'e':
				return 4;
			case 'f':
				return 5;
			case 'g':
				return 6;
			case 'h':
				return 7;
			case 'j':
				return 8;
			case 'k':
				return 9;
			case 'l':
				return 10;
			case 'm':
				return 11;
			case 'n':
				return 12;
			case 'o':
				return 13;
			case 'p':
				return 14;
			case 'q':
				return 15;
			case 'r':
				return 16;
			case 's':
				return 17;
			case 't':
				return 18;
		}

		return 0;
	}
	public static function convertCoord2($n = null)
	{
		switch ($n)
		{
			case '0':
				return 19;
			case '1':
				return 18;
			case '2':
				return 17;
			case '3':
				return 16;
			case '4':
				return 15;
			case '5':
				return 14;
			case '6':
				return 13;
			case '7':
				return 12;
			case '8':
				return 11;
			case '9':
				return 10;
			case '10':
				return 9;
			case '11':
				return 8;
			case '12':
				return 7;
			case '13':
				return 6;
			case '14':
				return 5;
			case '15':
				return 4;
			case '16':
				return 3;
			case '17':
				return 2;
			case '18':
				return 1;
		}

		return 0;
	}

	public function edit($tsumegoID)
	{
		if (!Auth::isAdmin())
			return $this->redirect($this->data['redirect']);
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
		if (!Auth::isAdmin())
		{
			CookieFlash::set('error', 'You are not authorized to access this page.');
			return $this->redirect('/');
		}
		$this->set('_page', 'sandbox');
		$this->set('_title', 'Merge Duplicates');
		return null;
	}

	public function mergeFinalForm(): mixed
	{
		if (!Auth::isAdmin())
			return $this->redirect('/');
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

		$masterSetConnectionBrothersButtons = [];
		foreach ($masterSetConnectionBrothers as $masterSetConnectionBrother)
			$masterSetConnectionBrothersButtons [] = TsumegoButton::createFromSetConnection($masterSetConnectionBrother['SetConnection']);
		$this->set('masterTsumegoButtons', $masterSetConnectionBrothersButtons);
		$this->set('masterTsumegoID', $masterSetConnection['tsumego_id']);

		$slaveSetConnectionBrothersButtons = [];
		foreach ($slaveSetConnectionBrothers as $slaveSetConnectionBrother)
			$slaveSetConnectionBrothersButtons [] = TsumegoButton::createFromSetConnection($slaveSetConnectionBrother['SetConnection']);
		$this->set('slaveTsumegoButtons', $slaveSetConnectionBrothersButtons);
		$this->set('slaveTsumegoID', $slaveSetConnection['tsumego_id']);
		return null;
	}

	public function performMerge()
	{
		$merger = new TsumegoMerger($this->request->data['master-tsumego-id'], $this->request->data['slave-tsumego-id']);
		$flash = $merger->execute();
		if ($flash)
			CookieFlash::set($flash['message'], $flash['type']);
		$this->redirect('/tsumegos/mergeForm');
	}

	public function setupSgf(): mixed
	{
		if (!Auth::isAdmin())
		{
			CookieFlash::set('No rights to call this', 'error');
			return $this->redirect('/sets');
		}

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

	public function setupSgfStep2($sgfID, $firstMoveColor, $correctMoves)
	{
		if (!Auth::isAdmin())
			return;
		$sgf = ClassRegistry::init("Sgf")->findById($sgfID);
		if (!$sgf)
			return;
		$sgf= $sgf['Sgf'];
		$sgf['first_move_color'] = $firstMoveColor;
		$sgf['correct_moves'] = $correctMoves;
		ClassRegistry::init("Sgf")->save($sgf);
		return $this->redirect('/tsumegos/setupSgf');
	}

	public function setupNewSgfStep2()
	{
		if (!Auth::isAdmin())
			return;

		$tsumegoID = $this->data["tsumegoID"];
		$sgfData = $this->data['sgf'];
		$firstMoveColor = $this->data['firstMoveColor'];
		$correctMoves = $this->data['correctMoves'];

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
		return $this->redirect('/tsumegos/setupSgf');
	}
}
