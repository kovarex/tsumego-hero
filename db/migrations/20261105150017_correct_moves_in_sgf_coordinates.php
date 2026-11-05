<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

//sgf.correct_moves was written in the orientation the board was shown in when it was set up (turned to the
//top-left corner, see editor.exportCorrectMoves), not in the coordinates of the SGF, so the similar search
//turned it a second time. The true correct moves are first moves of the SGF, so for each row this finds the
//one symmetry of the board that maps all its moves onto first moves of its SGF and stores them that way.
//A row that already fits is left as it is; one that fits no symmetry, or several with different results, is
//left as it is too and named in the output, nothing is guessed. Each changed row is copied to
//sgf_correct_moves_before_sgf_coordinates first, so down() can put it back.
final class CorrectMovesInSgfCoordinates extends AbstractMigration
{
	public const string UNCHANGED = 'unchanged';
	public const string FIXED = 'fixed';
	public const string AMBIGUOUS = 'ambiguous';
	public const string UNMAPPABLE = 'unmappable';

	public function up(): void
	{
		$this->execute("CREATE TABLE sgf_correct_moves_before_sgf_coordinates (
				sgf_id INT(10) UNSIGNED NOT NULL PRIMARY KEY,
				correct_moves VARCHAR(128) NOT NULL,
				CONSTRAINT sgf_correct_moves_before_sgf_coordinates_sgf_id FOREIGN KEY (sgf_id) REFERENCES sgf (id) ON DELETE CASCADE ON UPDATE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			COMMENT='sgf.correct_moves before it was moved to the coordinates of the SGF'");

		$connection = $this->getAdapter()->getConnection();
		$select = $connection->prepare("SELECT id, sgf, correct_moves FROM sgf WHERE id > ? AND correct_moves IS NOT NULL AND correct_moves <> '' ORDER BY id LIMIT 1000");
		$backup = $connection->prepare('INSERT INTO sgf_correct_moves_before_sgf_coordinates (sgf_id, correct_moves) VALUES (?, ?)');
		$update = $connection->prepare('UPDATE sgf SET correct_moves = ? WHERE id = ?');
		$counts = [self::UNCHANGED => 0, self::FIXED => 0, self::AMBIGUOUS => 0, self::UNMAPPABLE => 0];
		$lastID = 0;
		do
		{
			$select->execute([$lastID]);
			$rows = $select->fetchAll(PDO::FETCH_ASSOC);
			foreach ($rows as $row)
			{
				$lastID = (int) $row['id'];
				[$status, $moves] = self::correctedMoves((string) $row['sgf'], (string) $row['correct_moves']);
				$counts[$status]++;
				if ($status === self::FIXED)
				{
					$backup->execute([$lastID, $row['correct_moves']]);
					$update->execute([$moves, $lastID]);
				}
				elseif ($status !== self::UNCHANGED)
					$this->getOutput()?->writeln("sgf {$row['id']}: correct_moves '{$row['correct_moves']}' is $status, left as it is");
			}
		}
		while (count($rows) > 0);
		$this->getOutput()?->writeln('correct_moves: ' . implode(', ', array_map(fn($status, $count) => "$count $status", array_keys($counts), $counts)));
	}

	public function down(): void
	{
		$this->execute('UPDATE sgf JOIN sgf_correct_moves_before_sgf_coordinates backup ON backup.sgf_id = sgf.id SET sgf.correct_moves = backup.correct_moves');
		$this->execute('DROP TABLE sgf_correct_moves_before_sgf_coordinates');
	}

	//The status of the correct moves of an SGF and what they are in its coordinates: unchanged when they are
	//first moves of the SGF as they are, fixed when exactly one result of the symmetries of the board makes
	//them all first moves, ambiguous or unmappable (and kept) otherwise.
	/** @return array{string, string} */
	public static function correctedMoves(string $sgf, string $correctMoves): array
	{
		$stored = self::points($correctMoves);
		$tree = self::rootChildren($sgf);
		if ($stored === null || $tree === null)
			return [self::UNMAPPABLE, $correctMoves];
		[$size, $firstMoves] = $tree;
		$results = [];
		foreach (self::symmetries($size) as $index => $symmetry)
		{
			$mapped = array_map($symmetry, $stored);
			foreach ($mapped as $point)
				if (!in_array($point, $firstMoves, true))
					continue 2;
			if ($index === 0)
				return [self::UNCHANGED, $correctMoves];
			$moves = implode('', array_map(fn($point) => chr(96 + $point[0]) . chr(96 + $point[1]), $mapped));
			$sorted = array_map(fn($point) => implode(',', $point), $mapped);
			sort($sorted);
			$results[implode(';', $sorted)] ??= $moves;
		}
		if (count($results) === 0)
			return [self::UNMAPPABLE, $correctMoves];
		if (count($results) > 1)
			return [self::AMBIGUOUS, $correctMoves];
		return [self::FIXED, (string) reset($results)];
	}

	//The points (x, y from 1) of a string of letter pairs, null when it is not one
	/** @return list<array{int, int}>|null */
	private static function points(string $moves): ?array
	{
		if (strlen($moves) % 2 !== 0 || !preg_match('/^[a-z]*$/', $moves))
			return null;
		$points = [];
		for ($i = 0; $i < strlen($moves); $i += 2)
			$points[] = [ord($moves[$i]) - 96, ord($moves[$i + 1]) - 96];
		return $points;
	}

	//The symmetries of a board of [width, height], the identity first: the flips, and on a square board also
	//the transposes with the flips
	/** @param array{int, int} $size @return list<callable(array{int, int}): array{int, int}> */
	private static function symmetries(array $size): array
	{
		[$width, $height] = $size;
		$flips = [];
		foreach ([[false, false], [true, false], [false, true], [true, true]] as [$h, $v])
			$flips[] = fn(array $point): array => [$h ? $width + 1 - $point[0] : $point[0], $v ? $height + 1 - $point[1] : $point[1]];
		if ($width !== $height)
			return $flips;
		$transposed = array_map(fn(callable $flip) => fn(array $point): array => $flip([$point[1], $point[0]]), $flips);
		return array_merge($flips, $transposed);
	}

	//The size of the board and the moves of the first nodes after the root, as points; only as much of the
	//SGF is read as it takes. Null when it is not an SGF.
	/** @return array{array{int, int}, list<array{int, int}>}|null */
	public static function rootChildren(string $sgf): ?array
	{
		$position = strpos($sgf, '(');
		if ($position === false)
			return null;
		$position++;
		self::skipWhitespace($sgf, $position);
		if (($sgf[$position] ?? '') !== ';')
			return null;
		$position++;
		$root = self::readNode($sgf, $position);
		$size = [19, 19];
		if (isset($root['SZ']) && preg_match('/^\s*(\d+)\s*(?::\s*(\d+)\s*)?$/', $root['SZ'][0], $match))
			$size = [(int) $match[1], (int) ($match[2] ?? $match[1])];

		$firstMoves = [];
		self::skipWhitespace($sgf, $position);
		$next = $sgf[$position] ?? '';
		if ($next === ';')
		{
			$position++;
			$firstMoves[] = self::readNode($sgf, $position);
		}
		elseif ($next === '(')
			while (($sgf[$position] ?? '') === '(')
			{
				$position++;
				self::skipWhitespace($sgf, $position);
				if (($sgf[$position] ?? '') === ';')
				{
					$position++;
					$firstMoves[] = self::readNode($sgf, $position);
				}
				self::skipTree($sgf, $position);
				self::skipWhitespace($sgf, $position);
			}

		$points = [];
		foreach ($firstMoves as $node)
			foreach (['B', 'W'] as $color)
				if (isset($node[$color]) && ($point = self::points(strtolower(trim($node[$color][0])))) && count($point) === 1
					&& $point[0][0] >= 1 && $point[0][0] <= $size[0] && $point[0][1] >= 1 && $point[0][1] <= $size[1])
					$points[] = $point[0];
		return [$size, $points];
	}

	//The properties of the node at $position, which ends before the next ';', '(' or ')'
	/** @return array<string, list<string>> */
	private static function readNode(string $sgf, int &$position): array
	{
		$properties = [];
		$length = strlen($sgf);
		while ($position < $length)
		{
			self::skipWhitespace($sgf, $position);
			if (!preg_match('/\G[A-Za-z]+/', $sgf, $match, 0, $position))
				break;
			$name = strtoupper(preg_replace('/[a-z]/', '', $match[0]));
			$position += strlen($match[0]);
			self::skipWhitespace($sgf, $position);
			while (($sgf[$position] ?? '') === '[')
			{
				$properties[$name][] = self::readValue($sgf, $position);
				self::skipWhitespace($sgf, $position);
			}
		}
		return $properties;
	}

	//The value of the [...] at $position, with its escapes undone
	private static function readValue(string $sgf, int &$position): string
	{
		$value = '';
		$length = strlen($sgf);
		for ($position++; $position < $length && $sgf[$position] !== ']'; $position++)
		{
			if ($sgf[$position] === '\\')
				$position++;
			$value .= $sgf[$position] ?? '';
		}
		$position++;
		return $value;
	}

	//Moves $position past the ')' that ends the tree it is in, past its nested trees and values
	private static function skipTree(string $sgf, int &$position): void
	{
		$depth = 1;
		$length = strlen($sgf);
		while ($position < $length && $depth > 0)
		{
			$character = $sgf[$position];
			if ($character === '[')
			{
				self::readValue($sgf, $position);
				continue;
			}
			if ($character === '(')
				$depth++;
			elseif ($character === ')')
				$depth--;
			$position++;
		}
	}

	private static function skipWhitespace(string $sgf, int &$position): void
	{
		$length = strlen($sgf);
		while ($position < $length && ctype_space($sgf[$position]))
			$position++;
	}
}
