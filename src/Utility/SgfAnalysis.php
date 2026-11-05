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
	//tells whether it differs from the given one (an SGF already like that comes back as it was)
	public function __construct(
		public readonly string $firstMoveColor,
		public readonly string $correctMoves,
		public readonly ?string $problem,
		public readonly ?string $sgf = null,
		public readonly ?BoardTransformation $transformation = null,
		public readonly bool $changed = false) {}

	public static function of(string $sgf, bool $normalise = false): self
	{
		$result = self::ofMany([$sgf], self::TIMEOUT, $normalise)[0];
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
					!empty($output['changed']));
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
