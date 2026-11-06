<?php

namespace App\Utility;

//The line a player played on a problem until they failed it or ran out of time, as the play page sends
//it: the moves from the root in the coordinates of the SGF version played, two letters a–s each, tt for
//a pass. It is only shown, so a line that isn't one is left out rather than the result refused.
final class FailedLine
{
	public const MAXIMUM_MOVES = 200;

	public function __construct(public readonly string $moves, public readonly int $sgfID) {}

	//The line the request sent, null when it sent none, or one that isn't a line on a version of the problem
	public static function fromRequest(mixed $moves, mixed $sgfID, int $tsumegoID): ?self
	{
		if (!is_string($moves) || !preg_match('/^(?:[a-s]{2}|tt){1,' . self::MAXIMUM_MOVES . '}$/', $moves) || !is_numeric($sgfID))
			return null;
		if (empty(Util::query('SELECT id FROM sgf WHERE id = ? AND tsumego_id = ?', [(int) $sgfID, $tsumegoID])))
			return null;
		return new self($moves, (int) $sgfID);
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
