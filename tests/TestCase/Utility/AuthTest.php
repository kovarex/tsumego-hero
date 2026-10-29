<?php


//Auth::saveUserField() / saveUserFields() persist only the changed fields,
//so a single user write cannot clobber other columns.
use App\Model\Table\UserTable;
use App\Utility\Auth;

class AuthTest extends CakeTestCase
{
	public function testSaveUserFieldPersistsOnlyThatField()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'targeted', 'rating' => 1500, 'xp' => 100],
		]);
		$userId = $context->user['id'];

		Auth::saveUserField('rating', 1600);
		$this->assertSame(1600.0, (float) Auth::getUser()['rating']);

		$saved = UserTable::row((int) $userId);
		$this->assertSame(1600.0, (float) $saved['rating']);
		$this->assertSame(100, (int) $saved['xp']);
		$this->assertSame('targeted', $saved['name']);
	}

	public function testSaveUserFieldsPersistsMultipleFields()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'bulk', 'rating' => 1500, 'xp' => 100, 'level' => 1],
		]);
		$userId = $context->user['id'];

		Auth::saveUserFields([
			'xp' => 150,
			'level' => 2,
		]);

		$saved = UserTable::row((int) $userId);
		$this->assertSame(150, (int) $saved['xp']);
		$this->assertSame(2, (int) $saved['level']);
		$this->assertSame(1500.0, (float) $saved['rating']);
		$this->assertSame('bulk', $saved['name']);
	}

	//A float written into an integer column is rounded by MySQL, as Cake 2 sent it, not cut off the way
	//the ORM would; a float column keeps its fraction.
	public function testSaveUserFieldsRoundsAFloatIntoAnIntegerColumn()
	{
		$context = new ContextPreparator(['user' => ['name' => 'rounded', 'xp' => 100, 'rating' => 1500]]);

		Auth::saveUserFields(['xp' => 150.6, 'daily_xp' => 20.4, 'rating' => 1600.5]);

		$saved = UserTable::row($context->user['id']);
		$this->assertSame(151, $saved['xp']);
		$this->assertSame(20, $saved['daily_xp']);
		$this->assertSame(1600.5, $saved['rating']);
	}

	//The flags are written as numbers, which MySQL takes for a boolean column, and read back as bools.
	public function testSaveUserFieldWritesABoolean()
	{
		$context = new ContextPreparator(['user' => ['name' => 'flagged', 'sound' => false]]);

		Auth::saveUserField('sound', true);
		$this->assertTrue(UserTable::row($context->user['id'])['sound']);

		Auth::saveUserField('sound', false);
		$this->assertFalse(UserTable::row($context->user['id'])['sound']);
	}

	public function testIncrementUserFieldIfIncrementsOnlyWithinBound()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'bounded', 'used_revelation' => 0],
		]);
		$userId = $context->user['id'];

		$incremented = Auth::incrementUserFieldIf('used_revelation', 1, ['used_revelation <' => 1]);
		$this->assertTrue($incremented);
		$this->assertSame(1, (int) UserTable::row((int) $userId)['used_revelation']);

		$incremented = Auth::incrementUserFieldIf('used_revelation', 1, ['used_revelation <' => 1]);
		$this->assertFalse($incremented);
		$this->assertSame(1, (int) UserTable::row((int) $userId)['used_revelation']);
	}

	public function testSaveUserFieldThrowsOnUnknownColumn()
	{
		new ContextPreparator(['user' => ['name' => 'guard']]);
		$this->expectException(Exception::class);
		Auth::saveUserField('nonexistent_column', 1);
	}

	public function testSaveUserFieldsThrowsOnUnknownColumn()
	{
		new ContextPreparator(['user' => ['name' => 'guard']]);
		$this->expectException(Exception::class);
		Auth::saveUserFields(['xp' => 1, 'nonexistent_column' => 1]);
	}

	public function testIncrementUserFieldThrowsOnUnknownColumn()
	{
		new ContextPreparator(['user' => ['name' => 'guard']]);
		$this->expectException(Exception::class);
		Auth::incrementUserField('nonexistent_column', 1);
	}
}
