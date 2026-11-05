<?php

namespace App\Command;

use App\Model\Table\SgfTable;
use App\Utility\BoardTransformation;
use App\Utility\CommentCoordinates;
use App\Utility\SgfAnalysis;
use App\Utility\SgfTree;
use App\Utility\TsumegoButton;
use App\Utility\Util;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

//bin/cake normalise_sgfs [--write]: puts every stored SGF in the form the site keeps them in, as a save does now
//(scripts/sgfCorrectMoves.mjs): turned to the top-left corner and the correct variations first at every level, with
//first_move_color and correct_moves worked out again by besogo's rules, in the coordinates of the rewritten SGF.
//The rows are rewritten in place, every version and proposal, so the versions of a problem stay in one frame and the
//history compares like with like: no row is added, and the status, author, dates and review of a row stay as they
//are. Its preview is made again from the new text. What goes with the coordinates follows the turn:
//- the comments of a problem, kept in the coordinates of its current version, move with that version's turn;
//- transformation_from_source, from the source version to this one, takes in both turns (source^-1, then the old
//  transformation, then this one), each when that row is rewritten, so the result doesn't depend on the order.
//Everything changed is kept first in sgf_before_normalisation and tsumego_comment_before_normalisation.
//A problem is done in one transaction; a row already normalised comes back unchanged, so a run that stopped is
//simply run again, and a second run changes nothing. Without --write nothing is written and it reports what would
//change, with the time it took.
class NormaliseSgfsCommand extends Command
{
	public static function defaultName(): string
	{
		return 'normalise_sgfs';
	}

	public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
	{
		return $parser
			->setDescription('Rewrites every sgf row in place to the top-left with the correct variations first; a dry run unless --write.')
			->addOption('write', ['boolean' => true, 'help' => 'Write the changes; without it nothing is written'])
			->addOption('batch', ['default' => '200', 'help' => 'Problems analysed per run of node'])
			->addOption('limit', ['default' => '0', 'help' => 'Stop after this many problems (0: all); run again to go on'])
			->addOption('show', ['default' => '20', 'help' => 'How many of the changes to list']);
	}

	private bool $write;
	private int $show;
	private ConsoleIo $console;
	//sgf id => its transformation_from_source as changed in a dry run, which writes nothing to read it back from
	/** @var array<int, string|null> */
	private array $transformationsFromSource = [];
	/** @var array<string, int> */
	private array $counts = [];
	/** @var array<string, int> property => rewritten rows that lost it */
	private array $droppedProperties = [];
	/** @var array<string, list<int>> kind of turn => tsumego ids, for looking at them after a run */
	private array $examples = [];
	/** @var list<int> */
	private array $unreadable = [];
	private int $listed = 0;

	public function execute(Arguments $args, ConsoleIo $io): int
	{
		$start = microtime(true);
		$this->console = $io;
		$this->write = (bool) $args->getOption('write');
		$this->show = max(0, (int) $args->getOption('show'));
		$batch = max(1, (int) $args->getOption('batch'));
		$limit = max(0, (int) $args->getOption('limit'));
		$this->counts = array_fill_keys(['problems', 'rows', 'turned', 'reordered only', 'values only', 'comments moved', 'transformations from source fixed'], 0);
		$io->out($this->write ? 'Writing the normalised SGFs.' : 'Dry run: nothing is written (--write writes it).');

		$tsumegoIDs = array_map('intval', array_column(Util::query('SELECT DISTINCT tsumego_id FROM sgf ORDER BY tsumego_id'), 'tsumego_id'));
		if ($limit > 0)
			$tsumegoIDs = array_slice($tsumegoIDs, 0, $limit);
		foreach (array_chunk($tsumegoIDs, $batch) as $chunk)
			$this->normaliseProblems($chunk);

		$this->report(microtime(true) - $start);
		return static::CODE_SUCCESS;
	}

	/** @param list<int> $tsumegoIDs */
	private function normaliseProblems(array $tsumegoIDs): void
	{
		$placeholders = implode(',', array_fill(0, count($tsumegoIDs), '?'));
		$rows = Util::query("SELECT id, tsumego_id, sgf, first_move_color, correct_moves, source_sgf_id, transformation_from_source
			FROM sgf WHERE tsumego_id IN ($placeholders) ORDER BY id", $tsumegoIDs);
		$analyses = SgfAnalysis::ofMany(array_map(fn($row) => (string) $row['sgf'], array_column($rows, null, 'id')), SgfAnalysis::TIMEOUT * 5, true);
		$byProblem = [];
		foreach ($rows as $row)
			$byProblem[(int) $row['tsumego_id']][] = $row;
		foreach ($byProblem as $tsumegoID => $problemRows)
		{
			$this->counts['problems']++;
			if ($this->write)
				Util::execute('START TRANSACTION');
			try
			{
				$this->normaliseProblem($tsumegoID, $problemRows, $analyses);
				if ($this->write)
					Util::execute('COMMIT');
			}
			catch (\Throwable $error)
			{
				if ($this->write)
					Util::execute('ROLLBACK');
				throw $error;
			}
		}
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @param array<int|string, SgfAnalysis|string> $analyses
	 */
	private function normaliseProblem(int $tsumegoID, array $rows, array $analyses): void
	{
		$currentID = SgfTable::findCurrentVersionID($tsumegoID);
		foreach ($rows as $row)
		{
			$id = (int) $row['id'];
			$this->counts['rows']++;
			$analysis = $analyses[$id];
			if (is_string($analysis))
			{
				$this->unreadable[] = $id;
				continue;
			}
			$valuesDiffer = $analysis->firstMoveColor !== $row['first_move_color'] || $analysis->correctMoves !== $row['correct_moves'];
			if (!$analysis->changed)
			{
				if (!$valuesDiffer)
					continue;
				$this->counts['values only']++;
				$this->listChange($id, $tsumegoID, 'values', $row, $analysis);
				if ($this->write)
					Util::execute('UPDATE sgf SET first_move_color = ?, correct_moves = ? WHERE id = ?', [$analysis->firstMoveColor, $analysis->correctMoves, $id]);
				continue;
			}

			$transformation = $analysis->transformation ?? BoardTransformation::identity();
			$turned = $transformation->movesPoints();
			$this->counts[$turned ? 'turned' : 'reordered only']++;
			$kind = $turned ? self::kind($transformation) : 'reordered only';
			if ($id === $currentID && count($this->examples[$kind] ?? []) < 5)
				$this->examples[$kind][] = $tsumegoID;
			$this->listChange($id, $tsumegoID, $kind, $row, $analysis);
			$this->countDroppedProperties((string) $row['sgf'], (string) $analysis->sgf);

			if ($this->write)
			{
				Util::execute('INSERT INTO sgf_before_normalisation (sgf_id, sgf, first_move_color, correct_moves, transformation_from_source, transformation) VALUES (?, ?, ?, ?, ?, ?)',
					[$id, $row['sgf'], $row['first_move_color'], $row['correct_moves'], $row['transformation_from_source'], $transformation->toJson()]);
				Util::execute('UPDATE sgf SET sgf = ?, preview = ?, first_move_color = ?, correct_moves = ? WHERE id = ?',
					[$analysis->sgf, TsumegoButton::sgfToPreviewJson((string) $analysis->sgf), $analysis->firstMoveColor, $analysis->correctMoves, $id]);
			}
			if (!$turned)
				continue;
			// the transformation from its source to it ends in its turn now, and those from it start with its turn undone
			if (!empty($row['source_sgf_id']))
				$this->changeTransformationFromSource($id, $row['transformation_from_source'], fn(BoardTransformation $old) => $old->then($transformation));
			foreach (Util::query('SELECT id, transformation_from_source FROM sgf WHERE source_sgf_id = ?', [$id]) as $dependent)
				$this->changeTransformationFromSource((int) $dependent['id'], $dependent['transformation_from_source'], fn(BoardTransformation $old) => $transformation->inverse()->then($old));
			if ($id === $currentID)
				$this->moveComments($tsumegoID, $transformation, (string) $analysis->sgf);
		}
	}

	//$change applied to the transformation from the source of the row; one that was never recorded stays unknown
	private function changeTransformationFromSource(int $id, ?string $stored, callable $change): void
	{
		$json = array_key_exists($id, $this->transformationsFromSource) ? $this->transformationsFromSource[$id] : $stored;
		if ($json === null || $json === '')
			return;
		$new = $change(BoardTransformation::fromJson($json))->toJson();
		$this->transformationsFromSource[$id] = $new;
		$this->counts['transformations from source fixed']++;
		if ($this->write)
			Util::execute('UPDATE sgf SET transformation_from_source = ? WHERE id = ?', [$new, $id]);
	}

	private function moveComments(int $tsumegoID, BoardTransformation $transformation, string $sgf): void
	{
		$size = SgfTree::parse($sgf)?->boardSize() ?? 19;
		foreach (Util::query('SELECT id, message, position FROM tsumego_comment WHERE tsumego_id = ? ORDER BY id', [$tsumegoID]) as $comment)
		{
			$message = CommentCoordinates::transformTextBy((string) $comment['message'], $transformation, $size);
			$position = CommentCoordinates::transformPosition($comment['position'], $transformation, $size);
			if ($message === $comment['message'] && $position === $comment['position'])
				continue;
			$this->counts['comments moved']++;
			if (!$this->write)
				continue;
			Util::execute('INSERT IGNORE INTO tsumego_comment_before_normalisation (tsumego_comment_id, message, position) VALUES (?, ?, ?)',
				[$comment['id'], $comment['message'], $comment['position']]);
			Util::execute('UPDATE tsumego_comment SET message = ?, position = ? WHERE id = ?', [$message, $position, $comment['id']]);
		}
	}

	//The properties the rewritten SGF no longer has, which the board editor doesn't write either
	private function countDroppedProperties(string $old, string $new): void
	{
		foreach (array_diff(self::properties($old), self::properties($new)) as $property)
			$this->droppedProperties[$property] = ($this->droppedProperties[$property] ?? 0) + 1;
	}

	/** @return list<string> */
	private static function properties(string $sgf): array
	{
		$withoutValues = (string) preg_replace('/\[(?:\\\\.|[^\]\\\\])*\]/s', '[]', $sgf);
		preg_match_all('/([A-Z]+)\s*\[\]/', $withoutValues, $matches);
		return array_values(array_unique($matches[1]));
	}

	private static function kind(BoardTransformation $transformation): string
	{
		$parts = array_keys(array_filter(['transpose' => $transformation->transpose, 'flipX' => $transformation->flipX, 'flipY' => $transformation->flipY]));
		return implode('+', $parts);
	}

	/** @param array<string, mixed> $row */
	private function listChange(int $id, int $tsumegoID, string $kind, array $row, SgfAnalysis $analysis): void
	{
		if ($this->listed++ >= $this->show)
			return;
		$this->console->out(sprintf('sgf %d (tsumego %d): %s, first_move_color %s -> %s, correct_moves %s -> %s', $id, $tsumegoID, $kind,
			$row['first_move_color'] ?? 'NULL', $analysis->firstMoveColor, $row['correct_moves'] === null ? 'NULL' : "'" . $row['correct_moves'] . "'", "'" . $analysis->correctMoves . "'"));
	}

	private function report(float $seconds): void
	{
		$this->console->out(implode(', ', array_map(fn($name, $count) => "$count $name", array_keys($this->counts), $this->counts))
			. ', ' . count($this->unreadable) . ' unreadable (kept as they are)'
			. ($this->unreadable ? ': ' . implode(', ', array_slice($this->unreadable, 0, $this->show)) . (count($this->unreadable) > $this->show ? ', ...' : '') : ''));
		arsort($this->droppedProperties);
		$this->console->out('Properties the rewritten rows lose: ' . ($this->droppedProperties
			? implode(', ', array_map(fn($property, $count) => "$property $count", array_keys($this->droppedProperties), $this->droppedProperties)) : 'none'));
		ksort($this->examples);
		foreach ($this->examples as $kind => $tsumegoIDs)
			$this->console->out("Problems turned $kind (current version): " . implode(', ', $tsumegoIDs));
		$this->console->out(sprintf('Took %.1f s, at most %.0f MB of memory.', $seconds, memory_get_peak_usage(true) / 1048576));
	}
}
