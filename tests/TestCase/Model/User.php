<?php
class UserTest extends CakeTestCase {

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->User = ClassRegistry::init('User');
	}

	/**
	 * @return void
	 */
	public function testPublished(): void {
		$result = $this->User->checkUnique(['id' => 1, 'name' => 'Name'], 'id');
		$expected = [
			['User' => ['id' => 1, 'name' => 'First Article']],
			['User' => ['id' => 2, 'name' => 'Second Article']],
			['User' => ['id' => 3, 'name' => 'Third Article']],
		];

		$this->assertEquals($expected, $result);
	}

}
