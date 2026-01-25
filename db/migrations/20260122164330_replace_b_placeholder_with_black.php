<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Replace [b] placeholder with "Black" in tsumego descriptions.
 *
 * The [b] placeholder was intended to be replaced at display time with "Black " or "White "
 * depending on board orientation. However, with the new Black<->White swap logic in play.ctp,
 * the placeholder is now redundant - we can just use "Black" directly and it will be swapped
 * to "White" when the board is inverted.
 */
final class ReplaceBPlaceholderWithBlack extends AbstractMigration
{
	public function up(): void
	{
		// Replace [b] with "Black " (with trailing space to ensure proper word separation)
		// Handles: "[b] to play" -> "Black to play" (most common)
		// Handles: "[b]to play" -> "Black to play" (missing space - adds space)
		// Handles: "should [b]continue" -> "should Black continue" (mid-sentence missing space)
		$this->execute("UPDATE tsumego SET description = REPLACE(description, '[b] ', 'Black ') WHERE description LIKE '%[b] %'");
		$this->execute("UPDATE tsumego SET description = REPLACE(description, '[b]', 'Black ') WHERE description LIKE '%[b]%'");
	}

	public function down(): void
	{
		throw new \RuntimeException('This migration cannot be rolled back. [b] -> Black replacement is irreversible.');
	}
}
