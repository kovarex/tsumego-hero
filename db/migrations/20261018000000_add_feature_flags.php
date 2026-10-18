<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

/**
 * Feature flags: one row per feature that can be switched without a deploy.
 * The state is off, admins or everyone, and a missing row counts as off.
 */
final class AddFeatureFlags extends AbstractMigration
{
	public function up(): void
	{
		$this->table('feature_flags')
			->addColumn('flag_key', 'string', ['limit' => 64, 'null' => false])
			->addColumn('state', 'string', ['limit' => 10, 'null' => false, 'default' => 'off'])
			// A flag is switched by hand in SQL, so the row keeps its own timestamp
			// instead of relying on every caller to write `modified = NOW()`. Same
			// shape as `tsumego_status.updated`.
			->addColumn('modified', 'datetime', [
				'null' => false,
				'default' => Literal::from('CURRENT_TIMESTAMP'),
				'update' => 'CURRENT_TIMESTAMP',
			])
			->addIndex(['flag_key'], ['unique' => true, 'name' => 'idx_flag_key'])
			->create();

		// The training ships usable for the admins only. Releasing it to players is
		// one UPDATE away, keeping the feature off without a deploy.
		$this->execute(
			"INSERT INTO feature_flags (flag_key, state) VALUES ('mistake-training', 'admins')"
		);
	}

	public function down(): void
	{
		$this->table('feature_flags')->drop()->save();
	}
}
