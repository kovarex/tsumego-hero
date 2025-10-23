<?php

App::uses('Model', 'Model');
App::uses('AppModel', 'Model');

/**
 * EmptyIntegerBehavior Test Case
 */
class EmptyIntegerBehaviorTest extends CakeTestCase {

	/**
	 * setUp method
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->Model = new EmptyIntegerTestModel();

		$this->skipIf(true, '//FIXME');
	}

	/**
	 * tearDown method
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unset($this->Model);
		parent::tearDown();
	}

	/**
	 * Test that empty string is converted to null for nullable integer field
	 *
	 * @return void
	 */
	public function testEmptyStringToNullForNullableInt(): void {
		$this->Model->data = [
			'EmptyIntegerTestModel' => [
				'nullable_int' => '',
			],
		];

		$result = $this->Model->beforeSave();
		var_dump($this->Model->data);
		die();
		$this->assertTrue($result);
		$this->assertNull($this->Model->data['EmptyIntegerTestModel']['nullable_int']);
	}

	/**
	 * Test that empty string is converted to 0 for NOT NULL integer field
	 *
	 * @return void
	 */
	public function testEmptyStringToZeroForNotNullInt(): void {
		$this->Model->data = [
			'EmptyIntegerTestModel' => [
				'not_null_int' => '',
			],
		];

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertSame(0, $this->Model->data['EmptyIntegerTestModel']['not_null_int']);
	}

	/**
	 * Test that actual integer values are preserved
	 *
	 * @return void
	 */
	public function testActualIntegerValuesPreserved(): void {
		$this->Model->data = [
			'EmptyIntegerTestModel' => [
				'nullable_int' => 42,
				'not_null_int' => 100,
			],
		];

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertSame(42, $this->Model->data['EmptyIntegerTestModel']['nullable_int']);
		$this->assertSame(100, $this->Model->data['EmptyIntegerTestModel']['not_null_int']);
	}

	/**
	 * Test that zero values are preserved
	 *
	 * @return void
	 */
	public function testZeroValuesPreserved(): void {
		$this->Model->data = [
			'EmptyIntegerTestModel' => [
				'nullable_int' => 0,
				'not_null_int' => 0,
			],
		];

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertSame(0, $this->Model->data['EmptyIntegerTestModel']['nullable_int']);
		$this->assertSame(0, $this->Model->data['EmptyIntegerTestModel']['not_null_int']);
	}

	/**
	 * Test that string fields are not affected
	 *
	 * @return void
	 */
	public function testStringFieldsNotAffected(): void {
		$this->Model->data = [
			'EmptyIntegerTestModel' => [
				'string_field' => '',
			],
		];

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertSame('', $this->Model->data['EmptyIntegerTestModel']['string_field']);
	}

	/**
	 * Test multiple fields at once
	 *
	 * @return void
	 */
	public function testMultipleFields(): void {
		$this->Model->data = [
			'EmptyIntegerTestModel' => [
				'nullable_int' => '',
				'not_null_int' => '',
				'bigint_field' => '',
				'string_field' => '',
			],
		];

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertNull($this->Model->data['EmptyIntegerTestModel']['nullable_int']);
		$this->assertSame(0, $this->Model->data['EmptyIntegerTestModel']['not_null_int']);
		$this->assertSame(0, $this->Model->data['EmptyIntegerTestModel']['bigint_field']);
		$this->assertSame('', $this->Model->data['EmptyIntegerTestModel']['string_field']);
	}

	/**
	 * Test that behavior handles missing schema fields gracefully
	 *
	 * @return void
	 */
	public function testNonExistentFieldIgnored(): void {
		$this->Model->data = [
			'EmptyIntegerTestModel' => [
				'non_existent_field' => '',
			],
		];

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertSame('', $this->Model->data['EmptyIntegerTestModel']['non_existent_field']);
	}

	/**
	 * Test that non-numeric strings are converted to 0 or null
	 *
	 * @return void
	 */
	public function testNonNumericStringConversion(): void {
		$this->Model->data = [
			'EmptyIntegerTestModel' => [
				'nullable_int' => 'light',
				'not_null_int' => 'dark',
			],
		];

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertNull($this->Model->data['EmptyIntegerTestModel']['nullable_int']);
		$this->assertSame(0, $this->Model->data['EmptyIntegerTestModel']['not_null_int']);
	}

	/**
	 * Test that numeric strings are preserved
	 *
	 * @return void
	 */
	public function testNumericStringsPreserved(): void {
		$this->Model->data = [
			'EmptyIntegerTestModel' => [
				'nullable_int' => '42',
				'not_null_int' => '100',
			],
		];

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertSame('42', $this->Model->data['EmptyIntegerTestModel']['nullable_int']);
		$this->assertSame('100', $this->Model->data['EmptyIntegerTestModel']['not_null_int']);
	}

}

/**
 * Test model for EmptyIntegerBehavior
 */
class EmptyIntegerTestModel extends AppModel {

	public $useTable = false;

	public $actsAs = ['EmptyInteger'];

	/**
	 * Mock schema for testing
	 *
	 * @param string|false $field
	 *
	 * @return array
	 */
	public function schema($field = false): array {
		$schema = [
			'id' => ['type' => 'integer', 'null' => false, 'key' => 'primary'],
			'nullable_int' => ['type' => 'integer', 'null' => true],
			'not_null_int' => ['type' => 'integer', 'null' => false],
			'bigint_field' => ['type' => 'biginteger', 'null' => false],
			'string_field' => ['type' => 'string', 'null' => true],
		];

		if ($field === false) {
			return $schema;
		}

		return $schema[$field] ?? [];
	}

}
