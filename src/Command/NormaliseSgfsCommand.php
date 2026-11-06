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
//A rewrite leaves out what besogo merges anyway (a line transposing into another, the moves after a ko comes round)
//and what the board editor doesn't write (AP, CA, game info, + of a status problem). A row is kept as it is and named
//when besogo would not play its rewrite the same (every move, transposition and correct or wrong end), or when the count
//of a property changes in a way a save isn't expected to change it (propertyChanges of scripts/sgfCorrectMoves.mjs:
//a G written on every node, a stone, label or comment lost on a node that stays). What was on a node besogo merges
//into a transposition goes with it (its move, status, markup, comment); the report counts the rows stored with such a
//loss and what went. Setup is written as besogo shows it, a point listed twice once: the play check compares the
//stones shown, and the report counts the rows whose setup is written so. The report sums the change of every property.
//Everything changed is kept first in sgf_before_normalisation and tsumego_comment_before_normalisation.
//--restore <sgf ids> puts rows back as they were from there (text, preview, values), undoes their turn in the
//transformations from source that took it in (their own, and those of the rows made from them), and, for the version
//shown, puts back the comments of its problem; their backups are removed, so a later run rewrites them again.
//A status besogo can't read keeps its row as it is; --damaged-statuses lists the S values old saves damaged (a +
//written as a space) with the repair proposed, and writes nothing.
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
			->addOption('show', ['default' => '20', 'help' => 'How many of the changes to list'])
			->addOption('sgf', ['default' => '', 'help' => 'Comma separated sgf ids whose old and new text to print, for checking them'])
			->addOption('restore', ['default' => '', 'help' => 'Comma separated sgf ids to put back as they were before a --write, from sgf_before_normalisation'])
			->addOption('damaged-statuses', ['boolean' => true, 'help' => 'Only list the S values old saves damaged (a + written as a space), with the repair proposed; writes nothing']);
	}

	private bool $write;
	private int $show;
	private ConsoleIo $console;
	//sgf id => its transformation_from_source as changed in a dry run, which writes nothing to read it back from
	/** @var array<int, string|null> */
	private array $transformationsFromSource = [];
	/** @var array<string, int> */
	private array $counts = [];
	//property => its count in the rows rewritten, before and after, and the first of those rows
	/** @var array<string, array{rows: int, before: int, after: int, ids: list<int>}> */
	private array $propertyChanges = [];
	//property => what went with the merged away nodes of the rows rewritten, and in how many rows
	/** @var array<string, array{rows: int, count: int}> */
	private array $mergedAway = [];
	/** @var list<int> rows rewritten that lose something with their merged away nodes */
	private array $losingMergedLines = [];
	/** @var array<int, array<string, int>> sgf id => the change of its setup properties, written as besogo shows them */
	private array $setupRewritten = [];
	//sgf id => what changes unexpectedly in its rewrite, which keeps it as it is
	/** @var array<int, list<string>> */
	private array $changingUnexpectedly = [];
	/** @var array<string, list<int>> kind of turn => tsumego ids, for looking at them after a run */
	private array $examples = [];
	/** @var list<int> */
	private array $unreadable = [];
	/** @var list<int> rows whose rewrite besogo would play differently, kept as they are */
	private array $playingDifferently = [];
	//sgf id => its problem and whether it is the current version, of the rows kept as they are, which the report names
	/** @var array<int, array{tsumego: int, current: bool}> */
	private array $kept = [];
	private int $listed = 0;
	/** @var list<int> */
	private array $printed = [];

	public function execute(Arguments $args, ConsoleIo $io): int
	{
		$start = microtime(true);
		$this->console = $io;
		$restore = array_map('intval', array_filter(explode(',', (string) $args->getOption('restore'))));
		if ($restore)
			return $this->restore($restore);
		if ($args->getOption('damaged-statuses'))
			return $this->reportDamagedStatuses(max(1, (int) $args->getOption('batch')));
		$this->write = (bool) $args->getOption('write');
		$this->show = max(0, (int) $args->getOption('show'));
		$batch = max(1, (int) $args->getOption('batch'));
		$limit = max(0, (int) $args->getOption('limit'));
		$this->printed = array_map('intval', array_filter(explode(',', (string) $args->getOption('sgf'))));
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

			if (in_array($id, $this->printed, true))
				$this->console->out("sgf $id before:\n" . $row['sgf'] . "\nsgf $id after:\n" . $analysis->sgf);
			if (!$analysis->rewriteIsFaithful())
				$this->kept[$id] = ['tsumego' => $tsumegoID, 'current' => $id === $currentID];
			if (!$analysis->samePlay)
			{
				$this->playingDifferently[] = $id;
				continue;
			}
			if (!$analysis->rewriteIsFaithful())
			{
				$this->changingUnexpectedly[$id] = $analysis->unexpectedChanges;
				continue;
			}
			$transformation = $analysis->transformation ?? BoardTransformation::identity();
			$turned = $transformation->movesPoints();
			$this->counts[$turned ? 'turned' : 'reordered only']++;
			$kind = $turned ? self::kind($transformation) : 'reordered only';
			if ($id === $currentID && count($this->examples[$kind] ?? []) < 5)
				$this->examples[$kind][] = $tsumegoID;
			$this->listChange($id, $tsumegoID, $kind, $row, $analysis);
			$this->countPropertyChanges($id, $analysis);

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

	//The S values old saves damaged, a + written as a space ("A 2KO " for "A+2KO+"), or that besogo can't read, row by
	//row with the repair proposed; a repair that is a guess ("SEKI " may be "SEKI+" or "SEKI") or that besogo can't read
	//either is flagged. Nothing is written: the repair is decided first.
	private function reportDamagedStatuses(int $batch): int
	{
		$rows = $values = $guesses = $unrepairable = 0;
		$lastID = 0;
		while ($chunk = Util::query('SELECT id, tsumego_id, sgf FROM sgf WHERE id > ? AND sgf LIKE ? ORDER BY id LIMIT ' . $batch, [$lastID, '%S[%']))
		{
			$lastID = (int) end($chunk)['id'];
			$damaged = SgfAnalysis::damagedStatuses(array_map(fn($row) => (string) $row['sgf'], array_column($chunk, null, 'id')), SgfAnalysis::TIMEOUT * 5);
			foreach ($chunk as $row)
			{
				$statuses = $damaged[(int) $row['id']] ?? [];
				if (!$statuses)
					continue;
				$rows++;
				$current = (int) $row['id'] === SgfTable::findCurrentVersionID((int) $row['tsumego_id']) ? 'current version' : 'old version';
				$listed = [];
				foreach ($statuses as $status)
				{
					$values++;
					$note = '';
					if ($status['repaired'] === null)
					{
						$unrepairable++;
						$note = ' (no repair besogo reads)';
					}
					elseif ($status['trimmedReads'])
					{
						$guesses++;
						$note = ' (a guess: ' . json_encode(trim($status['value'])) . ' reads too)';
					}
					$listed[] = json_encode($status['value']) . ' -> ' . ($status['repaired'] === null ? '?' : json_encode($status['repaired'])) . $note;
				}
				$this->console->out("sgf {$row['id']} (tsumego {$row['tsumego_id']}, $current): " . implode(', ', $listed));
			}
		}
		$this->console->out("$rows rows with $values damaged statuses: " . ($values - $guesses - $unrepairable) . " with one repair, $guesses a guess, $unrepairable with none besogo reads. Nothing was written.");
		return static::CODE_SUCCESS;
	}

	//Puts the rows back as they were before they were rewritten, each in a transaction of its own
	/** @param list<int> $ids */
	private function restore(array $ids): int
	{
		foreach ($ids as $id)
		{
			$backup = Util::query('SELECT * FROM sgf_before_normalisation WHERE sgf_id = ?', [$id])[0] ?? null;
			if (!$backup)
			{
				$this->console->out("sgf $id: no backup, left as it is");
				continue;
			}
			$row = Util::query('SELECT tsumego_id, transformation_from_source FROM sgf WHERE id = ?', [$id])[0];
			$turn = BoardTransformation::fromJson($backup['transformation']);
			Util::execute('START TRANSACTION');
			try
			{
				Util::execute('UPDATE sgf SET sgf = ?, preview = ?, first_move_color = ?, correct_moves = ? WHERE id = ?',
					[$backup['sgf'], TsumegoButton::sgfToPreviewJson((string) $backup['sgf']), $backup['first_move_color'], $backup['correct_moves'], $id]);
				// the turn is taken out of the transformations it was put into, as normaliseProblem put it in
				if (!empty($row['transformation_from_source']))
					Util::execute('UPDATE sgf SET transformation_from_source = ? WHERE id = ?',
						[BoardTransformation::fromJson($row['transformation_from_source'])->then($turn->inverse())->toJson(), $id]);
				foreach (Util::query('SELECT id, transformation_from_source FROM sgf WHERE source_sgf_id = ? AND transformation_from_source IS NOT NULL', [$id]) as $dependent)
					Util::execute('UPDATE sgf SET transformation_from_source = ? WHERE id = ?',
						[$turn->then(BoardTransformation::fromJson($dependent['transformation_from_source']))->toJson(), $dependent['id']]);
				$comments = 0;
				if ($id === SgfTable::findCurrentVersionID((int) $row['tsumego_id']))
				{
					$comments = Util::execute('UPDATE tsumego_comment comment JOIN tsumego_comment_before_normalisation backup ON backup.tsumego_comment_id = comment.id
						SET comment.message = backup.message, comment.position = backup.position WHERE comment.tsumego_id = ?', [$row['tsumego_id']]);
					Util::execute('DELETE backup FROM tsumego_comment_before_normalisation backup JOIN tsumego_comment comment ON comment.id = backup.tsumego_comment_id
						WHERE comment.tsumego_id = ?', [$row['tsumego_id']]);
				}
				Util::execute('DELETE FROM sgf_before_normalisation WHERE sgf_id = ?', [$id]);
				Util::execute('COMMIT');
				$this->console->out("sgf $id: put back as it was" . ($comments ? ", with the comments of its problem" : ''));
			}
			catch (\Throwable $error)
			{
				Util::execute('ROLLBACK');
				throw $error;
			}
		}
		return static::CODE_SUCCESS;
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

	//The change of the count of each property in the rows rewritten, and what went with their merged away nodes
	private function countPropertyChanges(int $id, SgfAnalysis $analysis): void
	{
		if ($analysis->mergedAway)
			$this->losingMergedLines[] = $id;
		if ($analysis->setupRewritten)
			$this->setupRewritten[$id] = $analysis->setupRewritten;
		foreach ($analysis->mergedAway as $property => $count)
		{
			$this->mergedAway[$property]['rows'] = ($this->mergedAway[$property]['rows'] ?? 0) + 1;
			$this->mergedAway[$property]['count'] = ($this->mergedAway[$property]['count'] ?? 0) + $count;
		}
		foreach ($analysis->propertyChanges as $property => [$before, $after])
		{
			$change = $this->propertyChanges[$property] ?? ['rows' => 0, 'before' => 0, 'after' => 0, 'ids' => []];
			$change['rows']++;
			$change['before'] += $before;
			$change['after'] += $after;
			if (count($change['ids']) < 10)
				$change['ids'][] = $id;
			$this->propertyChanges[$property] = $change;
		}
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

	//The first ids of a list of rows, all of them that are kept are worth looking at, whatever --show says
	/** @param list<int> $ids */
	private static function ids(array $ids): string
	{
		return $ids ? ': ' . implode(', ', array_slice($ids, 0, 50)) . (count($ids) > 50 ? ', ...' : '') : '';
	}

	//A row kept as it is, with its problem and whether it is the version shown or one of its history
	private function keptRow(int $id): string
	{
		$kept = $this->kept[$id];
		return "sgf $id (tsumego {$kept['tsumego']}, " . ($kept['current'] ? 'current version' : 'old version, not current') . ')';
	}

	private function report(float $seconds): void
	{
		$this->console->out(implode(', ', array_map(fn($name, $count) => "$count $name", array_keys($this->counts), $this->counts))
			. ', ' . count($this->unreadable) . ' unreadable (kept as they are)' . self::ids($this->unreadable)
			. ', ' . count($this->playingDifferently) . ' that would play differently (kept as they are)' . self::ids($this->playingDifferently)
			. ', ' . count($this->changingUnexpectedly) . ' whose properties would change unexpectedly (kept as they are)' . self::ids(array_keys($this->changingUnexpectedly)));
		foreach (array_slice($this->playingDifferently, 0, 50) as $id)
			$this->console->out($this->keptRow($id) . ' would play differently');
		foreach (array_slice($this->changingUnexpectedly, 0, 50, true) as $id => $changes)
			$this->console->out($this->keptRow($id) . ' would change: ' . implode(', ', $changes));
		// every change left in the rows rewritten is one a save is expected to make
		uasort($this->propertyChanges, fn($a, $b) => $b['rows'] <=> $a['rows']);
		$this->console->out('Property counts of the rewritten rows, before -> after: ' . ($this->propertyChanges
			? implode(', ', array_map(fn($property, $change) => "$property {$change['before']} -> {$change['after']} in {$change['rows']} rows",
				array_keys($this->propertyChanges), $this->propertyChanges)) : 'no change'));
		foreach ($this->propertyChanges as $property => $change)
			$this->console->out("Rows that change $property: " . implode(', ', $change['ids']) . ($change['rows'] > 10 ? ', ...' : ''));
		uasort($this->mergedAway, fn($a, $b) => $b['rows'] <=> $a['rows']);
		$this->console->out(count($this->losingMergedLines) . ' rows rewritten lose what was on lines besogo merges into a transposition'
			. self::ids($this->losingMergedLines) . ($this->mergedAway ? '; what went: ' . implode(', ', array_map(
				fn($property, $lost) => "$property {$lost['count']} in {$lost['rows']} rows", array_keys($this->mergedAway), $this->mergedAway)) : ''));
		$this->console->out(count($this->setupRewritten) . ' rows rewritten have their setup written as besogo shows it (a point listed twice, or set to the stone it has, written once; the stones shown are the same)'
			. self::ids(array_keys($this->setupRewritten)));
		foreach (array_slice($this->setupRewritten, 0, 50, true) as $id => $changes)
			$this->console->out("sgf $id setup: " . implode(', ', array_map(fn($property, $change) => "$property " . sprintf('%+d', $change), array_keys($changes), $changes)));
		ksort($this->examples);
		foreach ($this->examples as $kind => $tsumegoIDs)
			$this->console->out("Problems turned $kind (current version): " . implode(', ', $tsumegoIDs));
		$this->console->out(sprintf('Took %.1f s, at most %.0f MB of memory.', $seconds, memory_get_peak_usage(true) / 1048576));
	}
}
