<?php

namespace App\Utility;

use Cake\ORM\TableRegistry;
use App\Model\Table\SgfTable;
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
		// the source is transformed once, for all the candidates
		$this->comparator = new BoardComparator($this->sourceBoard->stones, $this->sourceFirstMoveColor, $this->sourceBoard->correctMoves);
		$this->sourceStoneCount = $this->sourceBoard->getStoneCount();
		$this->sourceMoveCount = substr_count($sgf->sgf, ';');
		$this->sourceSgf = $sgf->sgf;
		$this->result->title = TableRegistry::getTableLocator()->get('Set')->get($this->setConnection->set_id)->title;
	}

	public function execute(): void
	{
		$start = microtime(true);
		// The problems of the site's collections with the current version of each. The preview of a version
		// keeps its parsed stones, two letters each, so the database counts them and passes on only the
		// problems near the stone count of this one, and only those are parsed. A problem without a
		// readable version (no preview) is passed on as it is.
		$candidates = Util::query("
SELECT
    tsumego.id AS tsumego_id,
    COALESCE(sgf.sgf, '') AS sgf,
    sgf.first_move_color AS first_move_color,
    sgf.correct_moves AS correct_moves
FROM tsumego
LEFT JOIN sgf ON sgf.id = " . SgfTable::currentVersionIDSql('tsumego.id') . "
WHERE tsumego.deleted IS NULL
    AND tsumego.id != ?
    AND EXISTS (SELECT 1 FROM set_connection sc JOIN `set` s ON s.id = sc.set_id
        WHERE sc.tsumego_id = tsumego.id AND " . SetTable::officialCondition('s') . ")
    AND (sgf.preview IS NULL OR sgf.preview = ''
        OR ABS((CHAR_LENGTH(JSON_VALUE(sgf.preview, '$.black')) + CHAR_LENGTH(JSON_VALUE(sgf.preview, '$.white'))) DIV 2 - ?) <= ?)
ORDER BY tsumego.id",
			[$this->sourceTsumegoID, $this->sourceStoneCount, $this->maxDifference]);

		$matches = [];
		foreach ($candidates as $candidate)
		{
			$comparisonResult = $this->compareCandidate($candidate);
			if ($comparisonResult)
				$matches[(int) $candidate['tsumego_id']] = [$candidate, $comparisonResult];
		}
		$this->addMatchesToResult($matches);

		usort($this->result->items, [SimilarSearchResultItem::class, 'compare']);

		$this->result->elapsed = microtime(true) - $start;
	}

	private function compareCandidate(array $candidate): ?BoardComparisonResult
	{
		$correctMoves = SgfBoard::decodePositionString($candidate['correct_moves'] ?? '');
		if (count($this->sourceBoard->correctMoves) != count($correctMoves))
			return null;
		$board = SgfParser::process($candidate['sgf'], $correctMoves);
		if (abs($board->getStoneCount() - $this->sourceStoneCount) > $this->maxDifference)
			return null;

		return $this->comparator->compareWith($board->stones, $candidate['first_move_color'] ?? 'N', $board->correctMoves);
	}

	//The matches as result items: the collection each is shown in and the status of the player on it, read
	//for all of them at once.
	/** @param array<int, array{0: array, 1: BoardComparisonResult}> $matches by tsumego id */
	private function addMatchesToResult(array $matches): void
	{
		if (empty($matches))
			return;
		$tsumegoIDs = array_keys($matches);
		$placeholders = implode(',', array_fill(0, count($tsumegoIDs), '?'));
		$shownIn = [];
		foreach (Util::query("
SELECT tsumego.id AS tsumego_id, sc.id AS set_connection_id, sc.num, s.title
FROM tsumego
JOIN set_connection sc ON sc.id = (
    SELECT sc2.id
    FROM set_connection sc2
    JOIN `set` s2 ON s2.id = sc2.set_id
    WHERE sc2.tsumego_id = tsumego.id AND " . SetTable::officialCondition('s2') . "
    ORDER BY " . SetConnectionTable::displayOrderSql('s2', 'sc2') . "
    LIMIT 1
)
JOIN `set` s ON s.id = sc.set_id
WHERE tsumego.id IN ($placeholders)", $tsumegoIDs) as $row)
			$shownIn[(int) $row['tsumego_id']] = $row;
		$statuses = [];
		if (Auth::isLoggedIn())
			foreach (Util::query("SELECT tsumego_id, status FROM tsumego_status WHERE user_id = ? AND tsumego_id IN ($placeholders)", [Auth::getUserID(), ...$tsumegoIDs]) as $row)
				$statuses[(int) $row['tsumego_id']] = $row['status'];

		foreach ($matches as $tsumegoID => [$candidate, $comparisonResult])
		{
			$item = new SimilarSearchResultItem();
			$item->difference = $comparisonResult->difference;
			$item->diff = $comparisonResult->diff;
			$item->title = $shownIn[$tsumegoID]['title'];
			$item->moveCount = substr_count($candidate['sgf'], ';');
			$item->tsumegoButton = new TsumegoButton(
				$tsumegoID,
				(int) $shownIn[$tsumegoID]['set_connection_id'],
				(int) $shownIn[$tsumegoID]['num'],
				$statuses[$tsumegoID] ?? 'N',
				0,
				$candidate['sgf']);
			$item->tsumegoButton->diff = $comparisonResult->diff;
			$this->result->items[] = $item;
		}
	}

	public int|string $sourceTsumegoID;
	public SetConnection $setConnection;
	public int $maxDifference = 5;
	public SgfBoard $sourceBoard;
	public string $sourceFirstMoveColor;
	private BoardComparator $comparator;
	public int $sourceMoveCount;
	public int $sourceStoneCount;
	public SimilarSearchResult $result;
	public ?string $sourceSgf = null;
}
