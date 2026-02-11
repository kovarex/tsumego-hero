<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Replace [b] placeholder with "Black" in tsumego descriptions.
 *
 * The [b] placeholder was intended to be replaced at display time with "Black" or "White"
 * depending on board orientation. With the new Black<->White swap logic in play.ctp,
 * we replace [b] with "Black" and let the swap handle visual inversion.
 *
 * Why always "Black" even for White-first SGFs?
 * - besogoPlayerColor controls board INVERSION, not the player's stone color
 * - For White-first SGFs with besogoPlayerColor="black" (no inversion):
 *   player places WHITE stones → swap fires → "Black" → "White" → matches ✓
 * - For White-first SGFs with besogoPlayerColor="white" (inversion):
 *   player places BLACK stones (inverted) → no swap → "Black" stays → matches ✓
 * - The swap logic always converts "Black" to match the actual visual stone color
 */
final class ReplaceBPlaceholderWithBlack extends AbstractMigration
{
	public function up(): void
	{
		// Replace [b] with "Black" for all puzzles.
		// Two passes: first "[b] " (with space), then "[b]" (without, adds trailing space).
		$this->execute("UPDATE tsumego SET description = REPLACE(description, '[b] ', 'Black ') WHERE description LIKE '%[b] %'");
		$this->execute("UPDATE tsumego SET description = REPLACE(description, '[b]', 'Black ') WHERE description LIKE '%[b]%'");
	}

	public function down(): void
	{
		throw new \RuntimeException('This migration cannot be rolled back. [b] -> Black replacement is irreversible.');
	}
}
