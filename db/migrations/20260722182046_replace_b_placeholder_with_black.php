<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Normalize tsumego descriptions to true-color convention.
 *
 * True-color means descriptions describe the actual stones on the board:
 * - B-first SGF: "Black to play" (Black is the solver)
 * - W-first SGF: "White to play" (White is the solver)
 *
 * This matches how SGF comments already work (always true-color).
 *
 * At display time, when the board is inverted ($swapColors), ALL color words
 * (descriptions, comments, variant answers) are swapped Black<->White
 * so they match what the player sees.
 */
final class ReplaceBPlaceholderWithBlack extends AbstractMigration
{
	public function up(): void
	{
		// Replace [b] placeholder with the SGF's first move color.
		// The first-move color is taken from the latest accepted SGF per tsumego
		// (matching how Play.php picks the displayed SGF: order by id DESC).
		// B-first SGFs: [b] -> "Black"
		// W-first SGFs: [b] -> "White"
		//
		// Pass 1: "[b] " -> "Color " (preserves existing space after placeholder).
		// Pass 2: "[b]"  -> "Color " (inserts space for cases like "[b]to play").
		//
		// Descriptions with no accepted SGF are left untouched: no true color can
		// be inferred, and the app renders a placeholder SGF for those problems.

		// B-first: [b] -> Black
		$this->execute("
			UPDATE tsumego t
			JOIN (SELECT s.tsumego_id, MAX(s.id) AS latest_id FROM sgf s WHERE s.accepted = 1 GROUP BY s.tsumego_id) latest
			  ON latest.tsumego_id = t.id
			JOIN sgf s ON s.id = latest.latest_id
			SET t.description = REPLACE(REPLACE(t.description, '[b] ', 'Black '), '[b]', 'Black ')
			WHERE t.description LIKE '%[b]%'
			  AND NOT (
			      LOCATE(';W[', s.sgf) > 0
			      AND (LOCATE(';B[', s.sgf) = 0 OR LOCATE(';W[', s.sgf) < LOCATE(';B[', s.sgf))
			  )
		");

		// W-first: [b] -> White
		$this->execute("
			UPDATE tsumego t
			JOIN (SELECT s.tsumego_id, MAX(s.id) AS latest_id FROM sgf s WHERE s.accepted = 1 GROUP BY s.tsumego_id) latest
			  ON latest.tsumego_id = t.id
			JOIN sgf s ON s.id = latest.latest_id
			SET t.description = REPLACE(REPLACE(t.description, '[b] ', 'White '), '[b]', 'White ')
			WHERE t.description LIKE '%[b]%'
			  AND LOCATE(';W[', s.sgf) > 0
			  AND (LOCATE(';B[', s.sgf) = 0 OR LOCATE(';W[', s.sgf) < LOCATE(';B[', s.sgf))
		");
	}

	public function down(): void
	{
		throw new \RuntimeException('This migration cannot be rolled back. Description normalization is irreversible.');
	}
}
