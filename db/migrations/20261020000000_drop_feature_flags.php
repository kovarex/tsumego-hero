<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The mistake training shipped to everybody, and it was the only feature behind a
 * flag, so the flags table goes. Its migration was dropped from the code too; this
 * removes the table where it already ran, and does nothing anywhere else.
 */
final class DropFeatureFlags extends AbstractMigration
{
	public function up(): void
	{
		if ($this->hasTable('feature_flags'))
			$this->table('feature_flags')->drop()->save();
	}

	public function down(): void {}
}
