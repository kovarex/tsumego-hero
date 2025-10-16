<?php
class UserTest extends CakeTestCase {

    public function setUp() {
        parent::setUp();
        $this->User = ClassRegistry::init('User');
    }

    public function testPublished() {
        $result = $this->User->checkUnique(array('id', 'name'));
        $expected = array(
            array('User' => array('id' => 1, 'name' => 'First Article')),
            array('User' => array('id' => 2, 'name' => 'Second Article')),
            array('User' => array('id' => 3, 'name' => 'Third Article'))
        );

        $this->assertEquals($expected, $result);
    }
}
?>