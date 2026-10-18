<?php

use App\Utility\Feature;
use App\Utility\FeatureKey;

class FeatureTest extends TestCaseWithAuth
{
	public function tearDown(): void
	{
		ClassRegistry::init('FeatureFlag')->deleteAll(['flag_key' => 'probe']);
		Feature::reset();
		parent::tearDown();
	}

	/**
	 * A flag row for the duration of one test. The key is not one any feature
	 * asks for, so the test cannot be confused with the seeded rows.
	 */
	private function setFakeFlag(string $state): void
	{
		$this->setFeatureFlag('probe', $state);
	}

	public function testFlagWithoutRowIsOff(): void
	{
		new ContextPreparator(['user' => ['admin' => true]]);

		$this->assertFalse(Feature::enabled('never-seeded'));
		$this->assertSame(Feature::OFF, Feature::state('never-seeded'));
	}

	public function testOffIsOffEvenForAdmins(): void
	{
		new ContextPreparator(['user' => ['admin' => true]]);
		$this->setFakeFlag(Feature::OFF);

		$this->assertFalse(Feature::enabled('probe'));
	}

	public function testAdminsStateIsForAdminsOnly(): void
	{
		new ContextPreparator(['user' => ['admin' => true]]);
		$this->setFakeFlag(Feature::ADMINS);
		$this->assertTrue(Feature::enabled('probe'), 'An admin sees a flag that is open to admins');

		new ContextPreparator(['user' => ['name' => 'player']]);
		$this->assertFalse(Feature::enabled('probe'), 'A player does not see a flag that is open to admins');
	}

	public function testEveryoneStateIsOnForEverybody(): void
	{
		new ContextPreparator(['user' => ['name' => 'player']]);
		$this->setFakeFlag(Feature::EVERYONE);

		$this->assertTrue(Feature::enabled('probe'), 'A player sees a released flag');
	}

	public function testAFlagExistsOnlyWhileItHasARow(): void
	{
		$this->setFakeFlag(Feature::OFF);

		$this->assertTrue(Feature::exists('probe'), 'A flag that is switched off still has a row');
		$this->assertFalse(Feature::exists('never-seeded'), 'A feature whose flag was deleted is permanent');
	}

	/**
	 * FeatureKey is the list of flags that can still be switched; a row alone does not
	 * make one.
	 */
	public function testAKeyIsDeclaredOnlyWhileFeatureKeyNamesIt(): void
	{
		$this->setFakeFlag(Feature::OFF);

		$this->assertTrue(Feature::known(FeatureKey::MistakeTraining));
		$this->assertTrue(Feature::known('mistake-training'));
		$this->assertFalse(Feature::known('probe'), 'A row whose key FeatureKey does not name belongs to a retired flag');
	}

	public function testStateIsReadPerRequestAndResettable(): void
	{
		new ContextPreparator(['user' => ['admin' => true]]);
		$this->setFakeFlag(Feature::OFF);
		$this->assertFalse(Feature::enabled('probe'));

		$row = ClassRegistry::init('FeatureFlag')->find('first', ['conditions' => ['flag_key' => 'probe']]);
		$Model = ClassRegistry::init('FeatureFlag');
		$Model->save([
			'id' => $row['FeatureFlag']['id'],
			'flag_key' => 'probe',
			'state' => Feature::EVERYONE,
			'modified' => date('Y-m-d H:i:s'),
		]);
		$this->assertFalse(Feature::enabled('probe'), 'The row is read once per request');

		Feature::reset();
		$this->assertTrue(Feature::enabled('probe'), 'A new request reads the changed row');
	}

	public function testSeededTrainingFlagIsOpenToAdmins(): void
	{
		new ContextPreparator(['user' => ['admin' => true]]);

		$this->assertSame(Feature::ADMINS, Feature::state('mistake-training'));
		$this->assertTrue(Feature::enabled('mistake-training'));
	}
}
