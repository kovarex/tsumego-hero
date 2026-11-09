<?php

use App\Utility\Util;
use Phinx\Db\Adapter\AdapterFactory;

require_once ROOT . '/db/migrations/20261109004338_empty_goals_to_null.php';

//An empty or blank description becomes NULL, any other stays as it is
class EmptyGoalsToNullMigrationTest extends AppTestCase
{
	public function testEmptyDescriptionsBecomeNull(): void
	{
		$context = new ContextPreparator(['tsumegos' => [
			['set_order' => 1, 'description' => ''],
			['set_order' => 2, 'description' => '  '],
			['set_order' => 3, 'description' => 'Black to kill']]]);

		self::migration()->up();

		$descriptions = array_map(fn($tsumego) => Util::query('SELECT description FROM tsumego WHERE id = ?', [$tsumego['id']])[0]['description'], $context->tsumegos);
		$this->assertSame([null, null, 'Black to kill'], $descriptions);
	}

	private static function migration(): EmptyGoalsToNull
	{
		$config = require ROOT . '/phinx.php';
		$migration = new EmptyGoalsToNull('test', 20261109004338);
		$migration->setAdapter(AdapterFactory::instance()->getAdapter('mysql', $config['environments']['test']));
		return $migration;
	}
}
