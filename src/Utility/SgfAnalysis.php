<?php

namespace App\Utility;

use Cake\Core\Configure;
use RuntimeException;

//What the board of the site says about SGFs: the color to move first, the first moves it marks correct (in the
//coordinates of the SGF) and the problem that keeps the board editor from saving one. The rules of correct and
//wrong live in besogo alone, so scripts/sgfCorrectMoves.mjs runs its files in node and this talks to it.
//The node binary is SgfAnalysis.node of core.local.php, node from the PATH when it is not set.
class SgfAnalysis
{
	//The seconds a whole run may take
	public const int TIMEOUT = 120;

	//With normalise: $sgf is the SGF as the site stores it (top-left, correct variations first, see
	//scripts/sgfCorrectMoves.mjs), the values are of it, $transformation goes from the given SGF to it and $changed
	//tells whether it differs from the given one (an SGF already like that comes back as it was); $samePlay tells
	//whether besogo plays the normalised text the same (every move, transposition and correct or wrong end);
	//$propertyChanges has the count of each property that differs in the normalised text, [before, after], and
	//$unexpectedChanges those changes that aren't what a save of the board editor is expected to leave out, and
	//$mergedAway what went with the nodes besogo merged into a transposition, property => count, and $setupRewritten
	//the change of the count of setup properties written as besogo shows them (a point listed twice written once),
	//property => change
	/**
	 * @param array<string, array{int, int}> $propertyChanges
	 * @param list<string> $unexpectedChanges
	 * @param array<string, int> $mergedAway
	 * @param array<string, int> $setupRewritten
	 */
	public function __construct(
		public readonly string $firstMoveColor,
		public readonly string $correctMoves,
		public readonly ?string $problem,
		public readonly ?string $sgf = null,
		public readonly ?BoardTransformation $transformation = null,
		public readonly bool $changed = false,
		public readonly bool $samePlay = true,
		public readonly array $propertyChanges = [],
		public readonly array $unexpectedChanges = [],
		public readonly array $mergedAway = [],
		public readonly array $setupRewritten = []) {}

	//Whether the normalised text may be stored in place of the given one: it plays the same and loses nothing unexpected
	public function rewriteIsFaithful(): bool
	{
		return $this->samePlay && empty($this->unexpectedChanges);
	}

	public static function of(string $sgf, bool $normalise = false): self
	{
		$result = self::ofMany([$sgf], self::TIMEOUT, $normalise)[0];
		// a text that wouldn't play the same, or would lose something unexpected, is kept as it came
		if ($result instanceof self && !$result->rewriteIsFaithful())
			$result = self::ofMany([$sgf])[0];
		if (is_string($result))
			throw new RuntimeException('The SGF could not be read: ' . $result);
		return $result;
	}

	//The analysis of each SGF by its key, or the message of why it could not be read. One node process does them all.
	/**
	 * @param array<int|string, string> $sgfs
	 * @return array<int|string, self|string>
	 */
	public static function ofMany(array $sgfs, int $timeout = self::TIMEOUT, bool $normalise = false): array
	{
		if (empty($sgfs))
			return [];
		$input = '';
		$keys = array_keys($sgfs);
		foreach ($keys as $index => $key)
			$input .= json_encode(['id' => $index, 'sgf' => $sgfs[$key], 'normalise' => $normalise], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
		$lines = explode("\n", trim(self::run($input, $timeout)));

		$results = [];
		foreach ($lines as $line)
		{
			$output = json_decode($line, true);
			if (!is_array($output) || !is_int($output['id'] ?? null) || !isset($keys[$output['id']]))
				throw new RuntimeException('Unexpected output of sgfCorrectMoves.mjs: ' . substr($line, 0, 200));
			$key = $keys[$output['id']];
			$results[$key] = isset($output['error'])
				? (string) $output['error']
				: new self(
					(string) $output['first_move_color'],
					(string) $output['correct_moves'],
					$output['problem'] ?? null,
					isset($output['sgf']) ? (string) $output['sgf'] : null,
					isset($output['transformation']) ? BoardTransformation::fromArray($output['transformation']) : null,
					!empty($output['changed']),
					($output['same_play'] ?? true) !== false,
					array_map(fn($counts) => [(int) $counts[0], (int) $counts[1]], (array) ($output['property_changes'] ?? [])),
					array_values(array_map('strval', (array) ($output['unexpected_changes'] ?? []))),
					array_map('intval', (array) ($output['merged_away'] ?? [])),
					array_map('intval', (array) ($output['setup_rewritten'] ?? [])));
		}
		if (count($results) !== count($sgfs))
			throw new RuntimeException('sgfCorrectMoves.mjs answered ' . count($results) . ' of ' . count($sgfs) . ' SGFs');
		return $results;
	}

	public static function nodeBinary(): string
	{
		return (string) (Configure::read('SgfAnalysis.node') ?: 'node');
	}

	//The output of the script for $input, the error of node when it fails or takes longer than $timeout seconds
	//The S values of each SGF that old saves damaged (a + written as a space) or besogo can't read, by its key, each with
	//the repair proposed (damagedStatuses of scripts/sgfCorrectMoves.mjs): the spaces back to +, null when besogo can't
	//read that either, and whether the value without its end spaces reads too, which makes the repair a guess
	/**
	 * @param array<int|string, string> $sgfs
	 * @return array<int|string, list<array{value: string, repaired: ?string, trimmedReads: bool}>>
	 */
	public static function damagedStatuses(array $sgfs, int $timeout = self::TIMEOUT): array
	{
		if (empty($sgfs))
			return [];
		$input = '';
		$keys = array_keys($sgfs);
		foreach ($keys as $index => $key)
			$input .= json_encode(['id' => $index, 'sgf' => $sgfs[$key], 'statuses' => true], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
		$results = [];
		foreach (explode("\n", trim(self::run($input, $timeout))) as $line)
		{
			$output = json_decode($line, true);
			if (!is_array($output) || !is_int($output['id'] ?? null) || !isset($keys[$output['id']]))
				throw new RuntimeException('Unexpected output of sgfCorrectMoves.mjs: ' . substr($line, 0, 200));
			$results[$keys[$output['id']]] = array_map(fn($status) => [
				'value' => (string) $status['value'],
				'repaired' => isset($status['repaired']) ? (string) $status['repaired'] : null,
				'trimmedReads' => !empty($status['trimmed_reads'])], (array) ($output['damaged_statuses'] ?? []));
		}
		return $results;
	}

	private static function run(string $input, int $timeout): string
	{
		//a node that can't be started fails below with its name, not with a warning
		$process = @proc_open(
			[self::nodeBinary(), ROOT . DS . 'scripts' . DS . 'sgfCorrectMoves.mjs'],
			[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
			$pipes);
		if (!is_resource($process))
			throw new RuntimeException('node could not be started (' . self::nodeBinary() . ')');

		stream_set_blocking($pipes[0], false);
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);
		$output = '';
		$errors = '';
		$written = 0;
		$deadline = microtime(true) + $timeout;
		while (true)
		{
			if (microtime(true) > $deadline)
			{
				proc_terminate($process, 9);
				proc_close($process);
				throw new RuntimeException("sgfCorrectMoves.mjs took longer than $timeout seconds");
			}
			$read = array_values(array_filter([$pipes[1], $pipes[2]], 'is_resource'));
			$write = $written < strlen($input) && is_resource($pipes[0]) ? [$pipes[0]] : [];
			$except = null;
			if (empty($read) && empty($write))
				break;
			if (stream_select($read, $write, $except, 0, 200000) === false)
				break;
			foreach ($write as $stream)
			{
				$written += (int) fwrite($stream, substr($input, $written, 65536));
				if ($written >= strlen($input))
					fclose($pipes[0]);
			}
			foreach ($read as $stream)
			{
				$chunk = fread($stream, 65536);
				if ($stream === $pipes[1])
					$output .= (string) $chunk;
				else
					$errors .= (string) $chunk;
				if (feof($stream))
					fclose($stream);
			}
		}
		if (is_resource($pipes[0]))
			fclose($pipes[0]);
		$status = proc_close($process);
		if ($status !== 0)
			throw new RuntimeException('sgfCorrectMoves.mjs failed (exit ' . $status . ', node ' . self::nodeBinary() . '): ' . trim($errors));
		return $output;
	}
}
