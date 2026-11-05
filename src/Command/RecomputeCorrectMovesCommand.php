<?php

namespace App\Command;

use App\Utility\SgfAnalysis;
use App\Utility\Util;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

//bin/cake recompute_correct_moves [--write]: works out sgf.first_move_color and sgf.correct_moves again for every
//version of every problem, by besogo's own rules (SgfAnalysis), in the coordinates of the SGF. It reports how many
//rows differ and the first of them; only --write stores the new values, a run without it changes nothing.
//Every version is done, not only the current ones: the history, the proposals and the merge of problems compare
//versions too, and an old version becomes the current one again when a newer one is deleted.
class RecomputeCorrectMovesCommand extends Command
{
	public static function defaultName(): string
	{
		return 'recompute_correct_moves';
	}

	public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		return $parser
			->setDescription('Recomputes sgf.first_move_color and sgf.correct_moves through besogo; a dry run unless --write.')
			->addOption('write', ['boolean' => true, 'help' => 'Store the new values; without it nothing is written'])
			->addOption('show', ['default' => '20', 'help' => 'How many of the differing rows to list'])
			->addOption('batch', ['default' => '500', 'help' => 'Rows analysed per run of node']);
	}

	public function execute(Arguments $args, ConsoleIo $io): int
	{
		$write = (bool) $args->getOption('write');
		$show = max(0, (int) $args->getOption('show'));
		$batch = max(1, (int) $args->getOption('batch'));
		$io->out($write ? 'Writing the recomputed values.' : 'Dry run: nothing is written (--write stores the values).');

		$total = 0;
		$differing = 0;
		$unreadable = [];
		$listed = 0;
		$lastID = 0;
		while ($rows = Util::query('SELECT id, sgf, first_move_color, correct_moves FROM sgf WHERE id > ? ORDER BY id LIMIT ' . $batch, [$lastID]))
		{
			$lastID = (int) end($rows)['id'];
			$total += count($rows);
			$results = SgfAnalysis::ofMany(array_column($rows, 'sgf', 'id'));
			foreach ($rows as $row)
			{
				$result = $results[$row['id']];
				if (is_string($result))
				{
					$unreadable[] = (int) $row['id'];
					continue;
				}
				if ($result->firstMoveColor === $row['first_move_color'] && $result->correctMoves === $row['correct_moves'])
					continue;
				$differing++;
				if ($listed++ < $show)
					$io->out(sprintf('sgf %d: first_move_color %s -> %s, correct_moves %s -> %s', $row['id'],
						self::shown($row['first_move_color']), $result->firstMoveColor, self::shown($row['correct_moves']), "'" . $result->correctMoves . "'"));
				if ($write)
					Util::execute('UPDATE sgf SET first_move_color = ?, correct_moves = ? WHERE id = ?', [$result->firstMoveColor, $result->correctMoves, $row['id']]);
			}
		}

		$io->out("$total rows, $differing " . ($write ? 'updated' : 'would change') . ', ' . count($unreadable) . ' unreadable (kept as they are)'
			. ($unreadable ? ': ' . implode(', ', array_slice($unreadable, 0, $show)) . (count($unreadable) > $show ? ', ...' : '') : ''));
		return static::CODE_SUCCESS;
	}

	private static function shown(?string $value): string
	{
		return $value === null ? 'NULL' : "'" . $value . "'";
	}
}
