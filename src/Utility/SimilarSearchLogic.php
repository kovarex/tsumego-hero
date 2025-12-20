<?php

App::uses('BoardComparator', 'Utility');
require_once __DIR__ . '/BoardComparator.php';

class SimilarSearchLogic
{
	public function __construct($setConnection)
	{
		$this->setConnection = $setConnection;
		$this->result = new SimilarSearchResult();
	}

	public function execute()
	{
		$maxDifference = 5;
		$includeSandbox = 'false';
		$includeColorSwitch = 'false';

		if (isset($this->params['url']['diff']))
		{
			$maxDifference = $this->params['url']['diff'];
			$includeSandbox = $this->params['url']['sandbox'];
			$includeColorSwitch = $this->params['url']['colorSwitch'];
		}
		$similarDiffType = [];
		$similarOrder = [];
		$tsumegoID = $this->setConnection['tsumego_id'];
		$this->sourceTsumego = ClassRegistry::init('Tsumego')->findById($tsumegoID)['Tsumego'];
		$set = ClassRegistry::init('Set')->findById($this->setConnection['set_id'])['Set'];
		$this->result->title = $set['title'];
		$sgf = ClassRegistry::init('Sgf')->find('first', ['order' => 'id DESC', 'conditions' => ['tsumego_id' => $tsumegoID]]);
		if (!$sgf)
			throw new NotFoundException('SGF not found');
		$tBoard = new SgfResultBoard(SgfParser::process($sgf['Sgf']['sgf']));
		$tBoardStoneCount = $tBoard->input->getStoneCount();

		$sets2 = [];
		$sets3 = [];
		$sets3content = [];
		$sets1 = ClassRegistry::init('Set')->find('all', [
			'conditions' => [
				'public' => '1',
				'NOT' => [
					'id' => [6473, 11969, 29156, 31813, 33007, 71790, 74761, 81578],
				],
			],
		]);

		if (Auth::isLoggedIn())
		{
			if (!Auth::hasPremium())
			{
				$includeSandbox = 'false';
				$hideSandbox = true;
			}
			else
			{
				array_push($sets3content, 6473);
				array_push($sets3content, 11969);
				array_push($sets3content, 29156);
				array_push($sets3content, 31813);
				array_push($sets3content, 33007);
				array_push($sets3content, 71790);
				array_push($sets3content, 74761);
				array_push($sets3content, 81578);
			}
			$sets3 = ClassRegistry::init('Set')->find('all', ['conditions' => ['id' => $sets3content]]);
		}
		else
			$includeSandbox = 'false';
		if ($includeSandbox == 'true')
			$sets2 = ClassRegistry::init('Set')->find('all', ['conditions' => ['public' => '0']]);
		$sets = array_merge($sets1, $sets2, $sets3);

		$setsCount = count($sets);
		for ($h = 0; $h < $setsCount; $h++)
		{
			$ts = TsumegoUtil::collectTsumegosFromSet($sets[$h]['Set']['id']);
			$tsCount = count($ts);
			for ($i = 0; $i < $tsCount; $i++)
				if ($ts[$i]['Tsumego']['id'] != $tsumegoID)
				{
					$setConnection = ClassRegistry::init('SetConnection')->find('first', ['conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
					if (!$setConnection)
						continue;
					$sgf = ClassRegistry::init('Sgf')->find('first', ['order' => 'id DESC', 'conditions' => ['tsumego_id' => $ts[$i]['Tsumego']['id']]]);
					$board = new SgfResultBoard(SgfParser::process($sgf['Sgf']['sgf']));
					$numStones = $board->input->getStoneCount();
					$stoneNumberDiff = abs($numStones - $tBoardStoneCount);
					if ($stoneNumberDiff <= $maxDifference)
					{
						if ($includeColorSwitch == 'true')
							$comparisonResult = BoardComparator::compare($tBoard->data, $board->data, true);
						else
							$comparisonResult = BoardComparator::compare($tBoard->data, $board->data, false);
						if ($comparisonResult->difference <= $maxDifference)
						{
							if ($comparisonResult->transformType == 0)
								array_push($similarDiffType, '');
							elseif ($comparisonResult->transformType == 1)
								array_push($similarDiffType, 'Shifted position.');
							elseif ($comparisonResult->transformType == 2)
								array_push($similarDiffType, 'Shifted and rotated.');
							elseif ($comparisonResult->transformType == 3)
								array_push($similarDiffType, 'Switched colors.');
							elseif ($comparisonResult->transformType == 4)
								array_push($similarDiffType, 'Switched colors and shifted position.');
							elseif ($comparisonResult->transformType == 5)
								array_push($similarDiffType, 'Switched colors, shifted and rotated.');
							$set = ClassRegistry::init('Set')->findById($ts[$i]['Tsumego']['set_id']);

							$item = new SimilarSearchResultItem();
							$item->difference = $comparisonResult->difference;
							$item->title = $set['Set']['title'];

							$tsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $ts[$i]['Tsumego']['id']]]);

							$item->tsumegoButton = new TsumegoButton(
								$ts[$i]['Tsumego']['id'],
								$setConnection['SetConnection']['id'],
								$setConnection['SetConnection']['num'],
								$tsumegoStatus['TsumegoStatus']['status'],
								$ts[$i]['Tsumego']['rating']);
							$this->result->items[]= $item;
						}
					}
				}
		}

		usort($this->result->items, [SimilarSearchResultItem::class, 'compare']);
	}

	public $sourceTsumego = null;
	public $setConnection;
	public SimilarSearchResult $result;
}
