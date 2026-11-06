<?php

namespace App\Utility;

//The line a player played on a problem of a challenge until they failed it or ran out of time, as the play page
//sends it: the moves from the root in the coordinates of the SGF version played, two letters a–s each, tt for a
//pass. It is only shown on the challenge page, so a line that isn't one is left out rather than the result
//refused, and plain time mode keeps none (challenge_failed_line).
final class ChallengeFailedLine
{
	public const MAXIMUM_MOVES = 200;

	public function __construct(public readonly string $moves, public readonly int $sgfID) {}

	//Kept for the attempt, as the line of its fail or time-out
	public function store(int $timeModeAttemptID): void
	{
		Util::execute('INSERT INTO challenge_failed_line (time_mode_attempt_id, moves, sgf_id) VALUES (?, ?, ?)
			ON DUPLICATE KEY UPDATE moves = VALUES(moves), sgf_id = VALUES(sgf_id)', [$timeModeAttemptID, $this->moves, $this->sgfID]);
	}

	//The line the request sent, null when it sent none, or one that isn't a line on a version of the problem
	public static function fromRequest(mixed $moves, mixed $sgfID, int $tsumegoID): ?self
	{
		if (!is_string($moves) || !preg_match('/^(?:[a-s]{2}|tt){1,' . self::MAXIMUM_MOVES . '}$/', $moves) || !is_numeric($sgfID))
			return null;
		if (empty(Util::query('SELECT id FROM sgf WHERE id = ? AND tsumego_id = ?', [(int) $sgfID, $tsumegoID])))
			return null;
		return new self($moves, (int) $sgfID);
	}

	//The moves turned as the SGF they are in is turned (NormaliseSgfsCommand rewrites a version in place); a pass
	//stays a pass
	public static function transformed(string $moves, BoardTransformation $transformation, int $boardSize): string
	{
		$result = '';
		foreach (str_split($moves, 2) as $move)
		{
			if ($move === 'tt')
			{
				$result .= 'tt';
				continue;
			}
			[$x, $y] = $transformation->applyToPoint(ord($move[0]) - ord('a') + 1, ord($move[1]) - ord('a') + 1, $boardSize);
			$result .= chr(ord('a') + $x - 1) . chr(ord('a') + $y - 1);
		}
		return $result;
	}

	//The board of the problem with the line, as previewBoard.js draws it: the stones as the problem buttons
	//show them, turned to the top left, the line turned with them, and the colour of its first move
	/** @return array{black: string, white: string, xMax: int, yMax: int, boardSize: int, line: string, firstColor: string} */
	public static function previewData(string $sgf, string $moves): array
	{
		$board = SgfParser::process($sgf);
		$preview = TsumegoButton::previewDataOf($board);
		$line = '';
		foreach (str_split($moves, 2) as $move)
		{
			if ($move === 'tt')
			{
				$line .= 'tt';
				continue;
			}
			$point = $board->orientedPoint(BoardPosition::fromLetters($move[0], $move[1]));
			$line .= BoardPosition::toLetters($point);
			//the board is cut to the stones, and the line can go beyond them
			$preview['xMax'] = max($preview['xMax'], BoardPosition::unpackX($point));
			$preview['yMax'] = max($preview['yMax'], BoardPosition::unpackY($point));
		}
		return $preview + ['line' => $line, 'firstColor' => SgfParser::firstMoveColor($sgf) === 'W' ? 'W' : 'B'];
	}
}
