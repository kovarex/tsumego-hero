<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Normalize tsumego descriptions to "Black = solver" convention.
 *
 * Two steps (order matters!):
 * 1. Normalize White-first SGF descriptions: swap White↔Black so "solver" is always "Black".
 *    This runs BEFORE [b] replacement to avoid swapping freshly-inserted "Black" back to "White".
 * 2. Replace [b] placeholder with "Black" in all descriptions.
 *
 * At display time, play.ctp swaps all color words (Black↔White) when the visual stone color
 * differs from the stored convention, i.e. when $pl != $startingPlayer.
 */
final class ReplaceBPlaceholderWithBlack extends AbstractMigration
{
	public function up(): void
	{
		// Step 1: Normalize White-first SGF descriptions from true-color to "Black = solver".
		// Must run BEFORE [b]→"Black" replacement, otherwise [b] descriptions on W-first puzzles
		// would get converted to "Black" then immediately swapped to "White" (393 puzzles affected).
		//
		// Swap White↔Black using marker approach with COLLATE utf8mb4_bin for case-sensitive matching:
		//   1. White → <<<W>>> (marker), white → <<<w>>>
		//   2. Black → White, black → white
		//   3. <<<W>>> → Black, <<<w>>> → black
		//
		// No REGEXP filter needed: descriptions without color words are unaffected by REPLACE (no-op),
		// and [b] descriptions have no color words yet (Step 2 hasn't run).
		// Excludes descriptions already using Black=solver convention for W-first SGFs.
		// "Black to play" (4 puzzles) and "How does Black answer here?" (1) use "Black" as the solver.
		// "How should Black attack the marked white stone?" (1) uses "Black"=solver + "white"=literal stone color.
		// Other Black-only descriptions ("Black's three stones...", "Black maps out...") use "Black"
		// as the OPPONENT (who IS Black in true-color on W-first), so they are correctly swapped.
		$this->execute("
			UPDATE tsumego t
			JOIN sgf s ON s.tsumego_id = t.id AND s.accepted = 1
			SET t.description = CONVERT(
				REPLACE(
					REPLACE(
						REPLACE(
							REPLACE(
								REPLACE(
									REPLACE(t.description COLLATE utf8mb4_bin,
										'White', '<<<W>>>'),
									'white', '<<<w>>>'),
								'Black', 'White'),
							'black', 'white'),
						'<<<W>>>', 'Black'),
					'<<<w>>>', 'black')
				USING utf8mb4)
			WHERE LOCATE(';W[', s.sgf) > 0
			  AND (LOCATE(';B[', s.sgf) = 0 OR LOCATE(';W[', s.sgf) < LOCATE(';B[', s.sgf))
			  AND t.description != ''
			  AND t.description NOT IN (
			      'How should Black attack the marked white stone?',
			      'Black to play',
			      'How does Black answer here?'
			  )
		");

		// Step 2: Replace [b] with "Black" in all descriptions.
		// Pass 1: "[b] " → "Black " (preserves existing space after placeholder).
		// Pass 2: "[b]"  → "Black " (inserts space for cases like "[b]to play").
		$this->execute("
			UPDATE tsumego
			SET description = REPLACE(REPLACE(description, '[b] ', 'Black '), '[b]', 'Black ')
			WHERE description LIKE '%[b]%'
		");
	}

	public function down(): void
	{
		throw new \RuntimeException('This migration cannot be rolled back. Description normalization is irreversible.');
	}
}
