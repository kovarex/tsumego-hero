<?php
App::uses('Model', 'Model');
App::uses('AppModel', 'Model');

/**
 * Test model for EmptyIntegerBehavior
 */
class EmptyIntegerTestModel extends AppModel {
	public $useTable = false;

	/**
	 * Mock schema for testing
	 *
	 * @return array
	 */
	public function schema($field = false) {
		$schema = array(
			'id' => array('type' => 'integer', 'null' => false, 'key' => 'primary'),
			'nullable_int' => array('type' => 'integer', 'null' => true),
			'not_null_int' => array('type' => 'integer', 'null' => false),
			'bigint_field' => array('type' => 'biginteger', 'null' => false),
			'string_field' => array('type' => 'string', 'null' => true),
		);

		if ($field === false) {
			return $schema;
		}

		return isset($schema[$field]) ? $schema[$field] : null;
	}
}

/**
 * EmptyIntegerBehavior Test Case
 */
class EmptyIntegerBehaviorTest extends CakeTestCase {

	/**
	 * setUp method
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();
		$this->Model = new EmptyIntegerTestModel();
	}

	/**
	 * tearDown method
	 *
	 * @return void
	 */
	public function tearDown() {
		unset($this->Model);
		parent::tearDown();
	}

	/**
	 * Test that empty string is converted to null for nullable integer field
	 *
	 * @return void
	 */
	public function testEmptyStringToNullForNullableInt() {
		$this->Model->data = array(
			'EmptyIntegerTestModel' => array(
				'nullable_int' => '',
			),
		);

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertNull($this->Model->data['EmptyIntegerTestModel']['nullable_int']);
	}

	/**
	 * Test that empty string is converted to 0 for NOT NULL integer field
	 *
	 * @return void
	 */
	public function testEmptyStringToZeroForNotNullInt() {
		$this->Model->data = array(
			'EmptyIntegerTestModel' => array(
				'not_null_int' => '',
			),
		);

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertSame(0, $this->Model->data['EmptyIntegerTestModel']['not_null_int']);
	}

	/**
	 * Test that actual integer values are preserved
	 *
	 * @return void
	 */
	public function testActualIntegerValuesPreserved() {
		$this->Model->data = array(
			'EmptyIntegerTestModel' => array(
				'nullable_int' => 42,
				'not_null_int' => 100,
			),
		);

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
	public function testZeroValuesPreserved() {
		$this->Model->data = array(
			'EmptyIntegerTestModel' => array(
				'nullable_int' => 0,
				'not_null_int' => 0,
			),
		);

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
	public function testStringFieldsNotAffected() {
		$this->Model->data = array(
			'EmptyIntegerTestModel' => array(
				'string_field' => '',
			),
		);

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertSame('', $this->Model->data['EmptyIntegerTestModel']['string_field']);
	}

	/**
	 * Test multiple fields at once
	 *
	 * @return void
	 */
	public function testMultipleFields() {
		$this->Model->data = array(
			'EmptyIntegerTestModel' => array(
				'nullable_int' => '',
				'not_null_int' => '',
				'bigint_field' => '',
				'string_field' => '',
			),
		);

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
	public function testNonExistentFieldIgnored() {
		$this->Model->data = array(
			'EmptyIntegerTestModel' => array(
				'non_existent_field' => '',
			),
		);

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertSame('', $this->Model->data['EmptyIntegerTestModel']['non_existent_field']);
	}

	/**
	 * Test that non-numeric strings are converted to 0 or null
	 *
	 * @return void
	 */
	public function testNonNumericStringConversion() {
		$this->Model->data = array(
			'EmptyIntegerTestModel' => array(
				'nullable_int' => 'light',
				'not_null_int' => 'dark',
			),
		);

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
	public function testNumericStringsPreserved() {
		$this->Model->data = array(
			'EmptyIntegerTestModel' => array(
				'nullable_int' => '42',
				'not_null_int' => '100',
			),
		);

		$result = $this->Model->beforeSave();
		$this->assertTrue($result);
		$this->assertSame('42', $this->Model->data['EmptyIntegerTestModel']['nullable_int']);
		$this->assertSame('100', $this->Model->data['EmptyIntegerTestModel']['not_null_int']);
	}
}
