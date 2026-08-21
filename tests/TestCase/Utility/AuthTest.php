<?php

App::uses('Auth', 'Utility');

/**
 * Auth::saveUserField() / saveUserFields() persist only the changed fields,
 * so a single user write cannot clobber other columns.
 */
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

		$saved = ClassRegistry::init('User')->findById($userId)['User'];
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

		$saved = ClassRegistry::init('User')->findById($userId)['User'];
		$this->assertSame(150, (int) $saved['xp']);
		$this->assertSame(2, (int) $saved['level']);
		$this->assertSame(1500.0, (float) $saved['rating']);
		$this->assertSame('bulk', $saved['name']);
	}

	public function testIncrementUserFieldIfIncrementsOnlyWithinBound()
	{
		$context = new ContextPreparator([
			'user' => ['name' => 'bounded', 'used_revelation' => 0],
		]);
		$userId = $context->user['id'];

		$incremented = Auth::incrementUserFieldIf('used_revelation', 1, ['used_revelation <' => 1]);
		$this->assertTrue($incremented);
		$this->assertSame(1, (int) ClassRegistry::init('User')->findById($userId)['User']['used_revelation']);

		$incremented = Auth::incrementUserFieldIf('used_revelation', 1, ['used_revelation <' => 1]);
		$this->assertFalse($incremented);
		$this->assertSame(1, (int) ClassRegistry::init('User')->findById($userId)['User']['used_revelation']);
	}
}
