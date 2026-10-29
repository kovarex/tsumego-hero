<?php

namespace App\Utility;

use Cake\ORM\TableRegistry;
use App\Model\Table\SgfTable;
use App\Model\Table\TsumegoStatusTable;
use Cake\Http\Exception\NotFoundException;
use App\Model\Table\SetTable;
use App\Model\Table\SetConnectionTable;
use App\Model\Entity\SetConnection;

class SimilarSearchLogic
{
	public function __construct(SetConnection $setConnection)
	{
		$this->setConnection = $setConnection;
		$this->result = new SimilarSearchResult();
		$this->sourceTsumegoID = $this->setConnection->tsumego_id;
		$sgf = TableRegistry::getTableLocator()->get('Sgf')->find()
			->select(['sgf', 'correct_moves', 'first_move_color'])
			->where(['tsumego_id' => $this->sourceTsumegoID, 'sgf_status_id' => SgfTable::STATUS_ACCEPTED])
			->orderByDesc('id')
			->first();
		if (!$sgf)
			throw new NotFoundException('SGF not found');
		$this->sourceBoard = SgfParser::process($sgf->sgf, SgfBoard::decodePositionString($sgf->correct_moves ?? ''));
		$this->sourceFirstMoveColor = $sgf->first_move_color ?? 'N';
		$this->sourceStoneCount = $this->sourceBoard->getStoneCount();
		$this->sourceMoveCount = substr_count($sgf->sgf, ';');
		$this->sourceSgf = $sgf->sgf;
		$this->result->title = TableRegistry::getTableLocator()->get('Set')->get($this->setConnection->set_id)->title;
	}

	public function execute(): void
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
    WHERE sc2.tsumego_id = tsumego.id AND " . SetTable::officialCondition('s2') . "
    ORDER BY " . SetConnectionTable::displayOrderSql('s2', 'sc2') . "
    LIMIT 1
)
LEFT JOIN sgf ON sgf.id = " . SgfTable::currentVersionIDSql('tsumego.id') . "
WHERE tsumego.deleted IS NULL");

		foreach ($candidates as $candidate)
			if ($candidate['tsumego_id'] != $this->sourceTsumegoID)
				$this->checkCandidate($candidate);

		usort($this->result->items, [SimilarSearchResultItem::class, 'compare']);

		$this->result->elapsed = microtime(true) - $start;
	}

	private function checkCandidate(array $candidate): void
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

	private function addCandidateToResult(array $candidate, BoardComparisonResult $comparisonResult): void
	{
		$setConnection = TableRegistry::getTableLocator()->get('SetConnection')->get($candidate['set_connection_id']);
		// not so many should match, so I get the sql additional data manually instead in the original select, which is big
		$set = TableRegistry::getTableLocator()->get('Set')->find()->where(['id' => $setConnection->set_id])->first();

		$item = new SimilarSearchResultItem();
		$item->difference = $comparisonResult->difference;
		$item->diff = $comparisonResult->diff;
		$item->title = $set->title;
		$item->moveCount = substr_count($candidate['sgf'], ';');


		$item->tsumegoButton = new TsumegoButton(
			$candidate['tsumego_id'],
			$setConnection->id,
			$setConnection->num,
			TsumegoStatusTable::statusOf(Auth::getUserID(), (int) $candidate['tsumego_id']) ?? 'N',
			0,
			$candidate['sgf']);
		$item->tsumegoButton->diff = $comparisonResult->diff;
		$this->result->items[] = $item;
	}

	public int|string $sourceTsumegoID;
	public SetConnection $setConnection;
	public int $maxDifference = 5;
	public SgfBoard $sourceBoard;
	public string $sourceFirstMoveColor;
	public int $sourceMoveCount;
	public int $sourceStoneCount;
	public SimilarSearchResult $result;
	public ?string $sourceSgf = null;
}
