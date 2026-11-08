<?php

namespace App\Utility;

use App\Model\Table\ClassroomAssignmentTable;

//What the print page (PrintController::sheet()) puts on paper: the picked problems in their order,
//as many on a page as asked, each numbered with its goal and where it comes from, and on request a
//key of the answers at the end and room for notes under each board. Everything is read from the
//address, so a sheet can be printed again or passed on as a link.
class PrintSheet
{
	public const array PROBLEMS_PER_PAGE = [4, 6, 9, 12];
	public const int DEFAULT_PROBLEMS_PER_PAGE = 6;
	//The answers are small diagrams, as many on a page as the most problems
	public const int ANSWERS_PER_PAGE = 12;
	//More than a lesson or a book chapter, and the address with their ids stays short enough
	public const int MAXIMUM_PROBLEMS = 200;

	//$problems: Each with number (on the sheet), collectionTitle, collectionNumber, goal, setConnectionId and diagram (null for no board)
	/** @param list<array<string, mixed>> $problems */
	public function __construct(
		public readonly array $problems,
		public readonly int $problemsPerPage = self::DEFAULT_PROBLEMS_PER_PAGE,
		public readonly bool $withAnswers = false,
		public readonly bool $withNotes = false) {}

	//The sheet of the problems with the set connection ids the player can see, the options as the
	//address of the sheet has them
	/**
	 * @param list<int> $setConnectionIds
	 * @param array<string, mixed> $query
	 */
	public static function of(array $setConnectionIds, int $userId, array $query): self
	{
		$problems = [];
		foreach (ClassroomAssignmentTable::pickable(array_slice($setConnectionIds, 0, self::MAXIMUM_PROBLEMS), $userId) as $index => $row)
		{
			$diagram = ProblemDiagram::fromSgf((string) ($row['sgf'] ?? ''), (string) ($row['correct_moves'] ?? ''));
			$problems[] = [
				'number' => $index + 1,
				'setConnectionId' => (int) $row['set_connection_id'],
				'collectionTitle' => (string) $row['set_title'],
				'collectionNumber' => (int) $row['num'],
				'goal' => self::goal((string) ($row['description'] ?? ''), $row['first_move_color'] ?? $diagram?->colorToPlay()),
				'diagram' => $diagram,
			];
		}
		$perPage = (int) ($query['perPage'] ?? 0);
		return new self(
			$problems,
			in_array($perPage, self::PROBLEMS_PER_PAGE, true) ? $perPage : self::DEFAULT_PROBLEMS_PER_PAGE,
			!empty($query['answers']),
			!empty($query['notes']));
	}

	//The ids of an address like ?problems=12,15,40, the ones that aren't numbers left out
	/** @return list<int> */
	public static function parseIds(mixed $value): array
	{
		if (!is_string($value))
			return [];
		$ids = [];
		foreach (explode(',', $value) as $part)
			if (ctype_digit(trim($part)))
				$ids[] = (int) trim($part);
		return array_values(array_unique($ids));
	}

	//What the player is to do: the description of the problem ("Black to live"), else who plays first
	public static function goal(string $description, ?string $firstMoveColor): string
	{
		$description = trim(preg_replace('/\s+/u', ' ', strip_tags($description)));
		if ($description !== '')
			return $description;
		return match ($firstMoveColor)
		{
			'B' => 'Black to play',
			'W' => 'White to play',
			default => '',
		};
	}

	//The columns and rows of a page of the given number of problems: 2 × 2, 2 × 3, 3 × 3 or 3 × 4
	/** @return array{int, int} */
	public static function grid(int $perPage): array
	{
		return match ($perPage)
		{
			4 => [2, 2],
			9 => [3, 3],
			12 => [3, 4],
			default => [2, 3],
		};
	}

	/** @return list<list<array<string, mixed>>> */
	public function problemPages(): array
	{
		return array_chunk($this->problems, $this->problemsPerPage);
	}

	/** @return list<list<array<string, mixed>>> */
	public function answerPages(): array
	{
		return $this->withAnswers ? array_chunk($this->problems, self::ANSWERS_PER_PAGE) : [];
	}

	//The label of a problem: its collection and its number there
	/** @param array<string, mixed> $problem */
	public static function origin(array $problem): string
	{
		return $problem['collectionTitle'] . ' #' . $problem['collectionNumber'];
	}
}
