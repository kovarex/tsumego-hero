<?php

App::uses('BoardComparator', 'Utility');
App::uses('SetConnection', 'Model');
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
		$this->sourceBoard = SgfParser::process($sgf['Sgf']['sgf'], SgfBoard::decodePositionString($sgf['Sgf']['correct_moves'] ?? ''));
		$this->sourceFirstMoveColor = $sgf['Sgf']['first_move_color'] ?? 'N';
		$this->sourceStoneCount = $this->sourceBoard->getStoneCount();
		$this->sourceMoveCount = substr_count($sgf['Sgf']['sgf'], ';');
		$this->sourceSgf = $sgf['Sgf']['sgf'];
		$set = ClassRegistry::init('Set')->findById($this->setConnection['set_id'])['Set'];
		$this->result->title = $set['title'];
	}

	public function execute()
	{
		$start = microtime(true);
		$candidates = Util::query("
SELECT
    tsumego.id AS tsumego_id,
    sc_best.id AS set_connection_id,
    COALESCE(sgf.sgf, '') AS sgf,
    sgf.first_move_color AS first_move_color,
    sgf.correct_moves AS correct_moves
FROM tsumego
JOIN set_connection sc_best ON sc_best.id = (
    SELECT sc2.id
    FROM set_connection sc2
    JOIN `set` s2 ON s2.id = sc2.set_id
    WHERE sc2.tsumego_id = tsumego.id AND s2.public = 1
    ORDER BY " . SetConnection::displayOrderSql('s2', 'sc2') . "
    LIMIT 1
)
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
		$item->moveCount = substr_count($candidate['sgf'], ';');

		$tsumegoStatus = ClassRegistry::init('TsumegoStatus')->find('first', ['conditions' => ['user_id' => Auth::getUserID(), 'tsumego_id' => $candidate['tsumego_id']]]);

		$item->tsumegoButton = new TsumegoButton(
			$candidate['tsumego_id'],
			$setConnection['id'],
			$setConnection['num'],
			$tsumegoStatus['TsumegoStatus']['status'] ?? 'N',
			0,
			$candidate['sgf']);
		$item->tsumegoButton->diff = $comparisonResult->diff;
		$this->result->items[] = $item;
	}

	public $sourceTsumegoID;
	public $sourceTsumego = null;
	public $setConnection;
	public $maxDifference = 5;
	public $sourceBoard;
	public $sourceFirstMoveColor;
	public int $sourceMoveCount;
	public $sourceStoneCount;
	public SimilarSearchResult $result;
	public ?string $sourceSgf = null;
}
