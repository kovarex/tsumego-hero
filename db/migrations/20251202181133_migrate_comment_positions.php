<?php

use Phinx\Migration\AbstractMigration;

/**
 * Migrate all position data into comment messages.
 *
 * This migration consolidates position data from two sources:
 * 1. Existing position column data → embedded as [data] tags
 * 2. User-typed freetext coordinates → converted to [data] tags where they match SGF
 *
 * Steps:
 * 1. Expand message column from VARCHAR(2048) to TEXT
 * 2. Migrate position column data into message
 * 3. Drop position column
 * 4. Convert freetext coordinate sequences to position buttons
 *
 * Position format: [x/y/pX/pY/cX/cY/moveNumber/childrenCount/orientation|path]
 * Example: [3/1/5/1/3/1/5/0/bottom-right|3/1+5/1+1/1+2/2+2/1]
 * 
 * Freetext examples:
 *   Before: "Try R17-Q17-Q16 for life"
 *   After:  "Try [17/16/17/17/17/16/3/0/bottom-right|17/16+17/17+18/17] for life"
 */
class MigrateCommentPositions extends AbstractMigration
{
	public function up()
	{
		// Step 0: Add message_backup column for retry support (temporary, non-invasive)
		// This allows re-running migration without re-importing 15min prod DB
		$table = $this->table('tsumego_comment');
		if (!$table->hasColumn('message_backup'))
		{
			$table->addColumn('message_backup', 'text', ['null' => true, 'after' => 'message'])
			      ->update();
			$this->execute("UPDATE tsumego_comment SET message_backup = message WHERE message_backup IS NULL");
		}
		else
		{
			// Restore from backup for retry
			$this->execute("UPDATE tsumego_comment SET message = message_backup WHERE message_backup IS NOT NULL");
		}

		// Step 1: Change message to TEXT (from VARCHAR(2048))
		$this->execute("ALTER TABLE tsumego_comment MODIFY message TEXT NOT NULL");

		// Step 2: Migrate existing positions into message (no pos: prefix)
		// Append [data] to messages that have position data
		// Skip if position column doesn't exist (already migrated)
		$hasPositionColumn = $this->hasTable('tsumego_comment') && 
		                     $this->table('tsumego_comment')->hasColumn('position');
		if ($hasPositionColumn)
		{
			$this->execute("
				UPDATE tsumego_comment 
				SET message = CONCAT(message, ' [', position, ']')
				WHERE position IS NOT NULL AND position != ''
			");

			// Step 3: Drop position column (now embedded in message)
			$this->table('tsumego_comment')
				->removeColumn('position')
				->update();
		}

		// Step 4: Migrate freetext coordinate sequences to position buttons
		$this->migrateFreetextCoordinates();
	}

	/**
	 * Migrate user-typed freetext coordinate sequences to clickable position buttons.
	 * 
	 * Finds patterns like "R17-Q17-Q16", "C18, D18, C17", "W-F18, B-F19"
	 * and converts them to embedded position data if they match the SGF.
	 */
	public function migrateFreetextCoordinates()
	{
		$this->output->writeln("\nMigrating freetext coordinate sequences...");
		
		// Get comments with coordinate patterns (not already migrated)
		$sql = "SELECT id, tsumego_id, message, created 
				FROM tsumego_comment 
				WHERE message REGEXP '[A-HJ-T][0-9]{1,2}'
				AND message NOT LIKE '%[%/%/%]%'";
		
		$comments = $this->fetchAll($sql);
		$this->output->writeln('Found ' . count($comments) . ' comments with potential coordinates');
		
		$migrated = 0;
		$skipped = 0;
		$errors = 0;
		
		foreach ($comments as $comment)
		{
			try
			{
				// Extract coordinate sequences
				$sequences = $this->extractCoordinateSequences($comment['message']);
				
			if (empty($sequences))
			{
				$this->output->writeln("  [SKIP] Comment {$comment['id']}: No coordinate sequences extracted");
				$skipped++;
				continue;
			}			// Load tsumego SGF - use the version closest to (but before) the comment date
			// This handles historical comments that reference older SGF versions
			$sgfs = $this->fetchAll("
				SELECT sgf 
				FROM sgf 
				WHERE tsumego_id = {$comment['tsumego_id']} 
				  AND created <= '{$comment['created']}'
				ORDER BY created DESC 
				LIMIT 1
			");
			
			// Fallback: if no SGF before comment date, use most recent
			if (empty($sgfs) || empty($sgfs[0]['sgf']))
			{
				$sgfs = $this->fetchAll("
					SELECT sgf 
					FROM sgf 
					WHERE tsumego_id = {$comment['tsumego_id']} 
					ORDER BY created ASC 
					LIMIT 1
				");
			}
			
			if (empty($sgfs) || empty($sgfs[0]['sgf']))
			{
				$this->output->writeln("  [SKIP] Comment {$comment['id']}: No SGF found for tsumego {$comment['tsumego_id']}");
				$skipped++;
				continue;
			}				$sgf = $sgfs[0]['sgf'];
				
				$newMessage = $comment['message'];
				$replacements = 0;
				
			// Process each sequence
			foreach ($sequences as $sequence)
			{
				// Generate position data from coordinates
				// This works for ALL valid coordinate sequences (SGF-matched or user-suggested)
				$positionData = $this->generatePositionDataFromCoordinates($sgf, $sequence);
				
				if ($positionData)
				{
					$originalText = $this->findSequenceText($newMessage, $sequence);
					
					if ($originalText)
					{
						$pos = strpos($newMessage, $originalText);
						if ($pos !== false)
						{
							$newMessage = substr_replace($newMessage, "[$positionData]", $pos, strlen($originalText));
							$replacements++;
						}
					}
				}
			}				// Update if replacements made
				if ($replacements > 0)
				{
					$this->execute("UPDATE tsumego_comment SET message = ? WHERE id = ?", [$newMessage, $comment['id']]);
					$migrated++;
					
					if ($migrated % 100 == 0)
						$this->output->writeln("  Migrated $migrated comments...");
				}
				else
				{
					$this->output->writeln("  [SKIP] Comment {$comment['id']}: Extracted " . count($sequences) . " sequence(s) but no position data generated");
					$skipped++;
				}
			}
			catch (Exception $e)
			{
				$this->output->writeln("  [ERROR] Comment {$comment['id']}: {$e->getMessage()}");
				$errors++;
			}
		}
		
		$this->output->writeln("Freetext migration complete: $migrated migrated, $skipped skipped, $errors errors");
	}

	/**
	 * Extract coordinate sequences from text.
	 */
	public function extractCoordinateSequences(string $text): array
	{
		$sequences = [];
		// Updated: allows optional color (WBwb) with optional space/dash after it
		$coordPattern = '(?:[WBwb][ -]?\s?)?([A-HJ-T][1-9][0-9]?)';
		$pattern = '/' . $coordPattern . '(?:\s*[-,\s]+\s*' . $coordPattern . ')*/i';
		
		if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER))
		{
			foreach ($matches as $match)
			{
				$sequence = [];
				if (preg_match_all('/' . $coordPattern . '/i', $match[0], $coords))
					$sequence = array_map('strtoupper', $coords[1]);
				
				// Include all sequences (1+ coords), not just 2+
				if (count($sequence) >= 1)
					$sequences[] = $sequence;
			}
		}
		
		return $sequences;
	}

	/**
	 * Generate position data from SGF and Western coordinates.
	 */
	public function generatePositionData(string $sgf, array $westernCoords): ?string
	{
		$sgfMoves = $this->parseSgfMoves($sgf);
		$targetCoords = [];
		
		foreach ($westernCoords as $western)
		{
			$coord = $this->parseWesternCoordinate($western);
			if (!$coord)
				return null;
			$targetCoords[] = $coord;
		}
		
		$matchIndex = $this->findMatchingSequence($sgfMoves, $targetCoords);
		if ($matchIndex === null)
			return null;
		
		$targetIndex = $matchIndex + count($targetCoords) - 1;
		$target = $sgfMoves[$targetIndex];
		
		$pX = $pY = -1;
		if ($targetIndex > 0)
		{
			$pX = $sgfMoves[$targetIndex - 1]['x'];
			$pY = $sgfMoves[$targetIndex - 1]['y'];
		}
		
		$cX = $target['x'];
		$cY = $target['y'];
		$moveNum = $targetIndex + 1;
		$children = 0;
		$orientation = 'bottom-right';
		
		// Path in REVERSE order
		$pathParts = [];
		for ($i = $targetIndex; $i >= $matchIndex; $i--)
			$pathParts[] = $sgfMoves[$i]['x'] . '/' . $sgfMoves[$i]['y'];
		
		$path = implode('+', $pathParts);
		
		return "{$target['x']}/{$target['y']}/$pX/$pY/$cX/$cY/$moveNum/$children/$orientation|$path";
	}

	/**
	 * Generate position data from coordinates WITHOUT requiring SGF tree match.
	 * Used for user-suggested alternate solutions that are not in the official SGF.
	 * 
	 * @param string $sgf SGF string (used for initial position context)
	 * @param array $westernCoords Array of Western coordinates (e.g., ['Q16', 'R16', 'S16'])
	 * @return string|null Position data string or null on error
	 */
	public function generatePositionDataFromCoordinates(string $sgf, array $westernCoords): ?string
	{
		if (empty($westernCoords))
			return null;
		
		$targetCoords = [];
		foreach ($westernCoords as $western)
		{
			$coord = $this->parseWesternCoordinate($western);
			if (!$coord)
				return null;
			$targetCoords[] = $coord;
		}
		
		// Target is the last coordinate in the sequence
		$target = end($targetCoords);
		
		// For non-SGF sequences: no parent, no move number, no children
		$pX = -1;
		$pY = -1;
		$cX = $target['x'];
		$cY = $target['y'];
		$moveNum = 0;
		$children = 0;
		$orientation = 'bottom-right';
		
		// Path in REVERSE order (target first, then parents)
		$pathParts = [];
		for ($i = count($targetCoords) - 1; $i >= 0; $i--)
			$pathParts[] = $targetCoords[$i]['x'] . '/' . $targetCoords[$i]['y'];
		
		$path = implode('+', $pathParts);
		
		return "{$target['x']}/{$target['y']}/$pX/$pY/$cX/$cY/$moveNum/$children/$orientation|$path";
	}

	/**
	 * Parse SGF moves.
	 */
	public function parseSgfMoves(string $sgf): array
	{
		$moves = [];
		preg_match_all('/;([WB])\[([a-s])([a-s])\]/', $sgf, $matches, PREG_SET_ORDER);
		
		foreach ($matches as $match)
		{
			$x = ord($match[2]) - ord('a') + 1;
			$y = ord($match[3]) - ord('a') + 1;
			$moves[] = ['x' => $x, 'y' => $y, 'color' => $match[1]];
		}
		
		return $moves;
	}

	/**
	 * Parse Western coordinate (e.g., "R17" → ['x' => 17, 'y' => 17]).
	 */
	public function parseWesternCoordinate(string $western): ?array
	{
		$western = strtoupper(trim($western));
		
		if (!preg_match('/^([A-Z]+)(\d+)$/', $western, $matches))
			return null;
		
		$col = $matches[1];
		$row = (int)$matches[2];
		
		if ($row < 1 || $row > 19)
			return null;
		
		$letters = 'ABCDEFGHJKLMNOPQRST';
		$pos = strpos($letters, $col);
		
		if ($pos === false)
			return null;
		
		return ['x' => $pos + 1, 'y' => $row];
	}

	/**
	 * Find matching sequence in SGF moves.
	 */
	public function findMatchingSequence(array $sgfMoves, array $targetCoords): ?int
	{
		$targetCount = count($targetCoords);
		$moveCount = count($sgfMoves);
		
		for ($i = 0; $i <= $moveCount - $targetCount; $i++)
		{
			$match = true;
			for ($j = 0; $j < $targetCount; $j++)
			{
				if ($sgfMoves[$i + $j]['x'] !== $targetCoords[$j]['x'] ||
					$sgfMoves[$i + $j]['y'] !== $targetCoords[$j]['y'])
				{
					$match = false;
					break;
				}
			}
			if ($match)
				return $i;
		}
		
		return null;
	}

	/**
	 * Find original text for coordinate sequence in message.
	 */
	public function findSequenceText(string $message, array $sequence): ?string
	{
		// Try common separator patterns
		$patterns = [
			implode('-', $sequence),
			implode(' - ', $sequence),
			implode(', ', $sequence),
			implode(' ', $sequence),
		];
		
		foreach ($patterns as $pattern)
		{
			$regex = '/' . preg_quote($pattern, '/') . '/i';
			if (preg_match($regex, $message, $matches))
				return $matches[0];
		}
		
		// Try with color prefixes
		foreach (['-', ' ', ''] as $sep)
		{
			$patternWithColors = [];
			foreach ($sequence as $coord)
			{
				$found = false;
				foreach (['W', 'B', 'w', 'b'] as $color)
				{
					$testPattern = $color . $sep . $coord;
					if (stripos($message, $testPattern) !== false)
					{
						$patternWithColors[] = $testPattern;
						$found = true;
						break;
					}
				}
				if (!$found)
					$patternWithColors[] = $coord;
			}
			
			if (count($patternWithColors) == count($sequence))
			{
				$fullPattern = implode('[, -]+', array_map(function($p) { return preg_quote($p, '/'); }, $patternWithColors));
				if (preg_match('/' . $fullPattern . '/i', $message, $matches))
					return $matches[0];
			}
		}
		
		return null;
	}

	// Note: No down() migration - this is a one-way data transformation.
	// To retry: Just re-run up() - it will restore from message_backup column.
	// To cleanup backup column after successful migration:
	//   ALTER TABLE tsumego_comment DROP COLUMN message_backup;
}
