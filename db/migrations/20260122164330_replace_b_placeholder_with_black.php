<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Replace [b] placeholder with the correct color name in tsumego descriptions.
 *
 * The [b] placeholder was intended to be replaced at display time with "Black " or "White "
 * depending on board orientation. With the new Black<->White swap logic in play.ctp,
 * we replace [b] with the literal first-move color and let the swap handle visual inversion.
 *
 * - For Black-first SGFs (vast majority): [b] -> "Black"
 * - For White-first SGFs (~169 puzzles): [b] -> "White"
 *
 * This ensures the swap logic works correctly for all puzzles.
 */
final class ReplaceBPlaceholderWithBlack extends AbstractMigration
{
	public function up(): void
	{
		// Step 1: Replace [b] with "White" for White-first SGFs (169 puzzles).
		// Must run BEFORE the bulk Black replacement.
		// Uses SGF parsing: if ;W[ appears before ;B[ (or ;B[ is absent), it's White-first.
		$this->execute("
			UPDATE tsumego t
			JOIN sgf s ON s.id = (
				SELECT MAX(s2.id) FROM sgf s2 WHERE s2.tsumego_id = t.id AND s2.accepted = 1
			)
			SET t.description = REPLACE(REPLACE(t.description, '[b] ', 'White '), '[b]', 'White ')
			WHERE t.description LIKE '%[b]%'
			AND LOCATE(';W[', s.sgf) > 0
			AND (LOCATE(';B[', s.sgf) = 0 OR LOCATE(';W[', s.sgf) < LOCATE(';B[', s.sgf))
		");

		// Step 2: Replace remaining [b] with "Black" for Black-first SGFs (13,896 puzzles).
		// Two passes: first "[b] " (with space), then "[b]" (without, adds trailing space).
		$this->execute("UPDATE tsumego SET description = REPLACE(description, '[b] ', 'Black ') WHERE description LIKE '%[b] %'");
		$this->execute("UPDATE tsumego SET description = REPLACE(description, '[b]', 'Black ') WHERE description LIKE '%[b]%'");
	}

	public function down(): void
	{
		throw new \RuntimeException('This migration cannot be rolled back. [b] -> Black replacement is irreversible.');
	}
}
