<?php

App::uses('BoardComparator', 'Utility');
require_once __DIR__ . '/BoardComparator.php';
require_once __DIR__ . '/SimilarSearchResult.php';

class SimilarSearchLogic
{
	public function __construct($setConnection)
	{
		$this->setConnection = $setConnection;
		$this->result = new SimilarSearchResult();
		$this->sourceTsumegoID = $this->setConnection['tsumego_id'];
		$this->sourceTsumego = ClassRegistry::init('Tsumego')->findById($this->sourceTsumegoID)['Tsumego'];
		$sgf = ClassRegistry::init('Sgf')->find('first', ['order' => 'id DESC', 'conditions' => ['tsumego_id' => $this->sourceTsumegoID]]);
		if (!$sgf)
			throw new NotFoundException('SGF not found');
		$this->sourceBoard = SgfParser::process($sgf['Sgf']['sgf'], SgfBoard::decodePositionString($candidate['correct_moves'] ?? ''));
		$this->sourceFirstMoveColor = $sgf['Sgf']['first_move_color'] ?? 'N';
		$this->sourceStoneCount = $this->sourceBoard->getStoneCount();
		$set = ClassRegistry::init('Set')->findById($this->setConnection['set_id'])['Set'];
		$this->result->title = $set['title'];
	}

	public function execute()
	{
		$start = microtime(true);
		$candidates = Util::query("
SELECT
    tsumego.id AS tsumego_id,
    set_connection_latest.id AS set_connection_id,
    sgf.sgf AS sgf,
    sgf.first_move_color AS first_move_color,
    sgf.correct_moves AS correct_moves
FROM tsumego
JOIN (
    SELECT
        set_connection.tsumego_id,
        MAX(set_connection.id) AS id
    FROM set_connection
    JOIN `set`
        ON `set`.id = set_connection.set_id
       AND `set`.public = 1
    GROUP BY set_connection.tsumego_id
) AS set_connection_latest
    ON set_connection_latest.tsumego_id = tsumego.id
LEFT JOIN sgf
    ON sgf.tsumego_id = tsumego.id
   AND sgf.accepted = 1
   AND sgf.id = (
        SELECT MAX(id)
        FROM sgf
        WHERE sgf.tsumego_id = tsumego.id
          AND sgf.accepted = 1
   )");

		foreach ($candidates as $candidate)
			if ($candidate['tsumego_id'] != $this->sourceTsumegoID)
				$this->checkCandidate($candidate);

		usort($this->result->items, [SimilarSearchResultItem::class, 'compare']);

		$this->result->elapsed = microtime(true) - $start;
	}

	private function checkCandidate($candidate): void
	{
		$correctMoves = SgfBoard::decodePositionString($candidate['correct_moves'] ?? '');
		if (count($this->sourceBoard->correctMoves) != count($correctMoves))
			return;
		$board = SgfParser::process($candidate['sgf'], $correctMoves);
		$numStones = $board->getStoneCount();
		$stoneNumberDiff = abs($numStones - $this->sourceStoneCount);
		if ($stoneNumberDiff > $this->maxDifference)
			return;

		$comparisonResult = BoardComparator::compare(
			$this->sourceBoard->stones,
			$this->sourceFirstMoveColor,
			$this->sourceBoard->correctMoves,
			$board->stones,
			$candidate['first_move_color'] ?? 'N',
			$board->correctMoves);
		if (!$comparisonResult)
			return;
		$this->addCandidateToResult($candidate, $comparisonResult);
	}

	private function addCandidateToResult($candidate, BoardComparisonResult $comparisonResult): void
	{
		$setConnection = ClassRegistry::init('SetConnection')->findById($candidate['set_connection_id'])['SetConnection'];
		// not so many should match, so I get the sql additional data manually instead in the original select, which is big
		$set = ClassRegistry::init('Set')->findById($setConnection['set_id']);

		$item = new SimilarSearchResultItem();
		$item->difference = $comparisonResult->difference;
		$item->diff = $comparisonResult->diff;
		$item->title = $set['Set']['title'];

		$tsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $candidate['tsumego_id']]]);

		$item->tsumegoButton = new TsumegoButton(
			$candidate['tsumego_id'],
			$setConnection['id'],
			$setConnection['num'],
			$tsumegoStatus['TsumegoStatus']['status']);
		$this->result->items[] = $item;
	}

	public $sourceTsumegoID;
	public $sourceTsumego = null;
	public $setConnection;
	public $maxDifference = 5;
	public $sourceBoard;
	public $sourceFirstMoveColor;
	public $sourceStoneCount;
	public SimilarSearchResult $result;
}
