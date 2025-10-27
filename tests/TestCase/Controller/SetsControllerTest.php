<?php

require_once(__DIR__ . '/TestCaseWithAuth.php');

class SetsControllerTest extends TestCaseWithAuth {
	public function testIndexLoggedIn(): void {
		new ContextPreparator(['tsumego_sets' => [
			['name' => 'tsumego set 1', 'num' => '666'],
			['name' => 'tsumego set 2', 'num' => '777']]], );
		$this->login('kovarex');
		$this->testAction('sets', ['return' => 'view']);
		$this->assertTextContains("tsumego set 1", $this->view);
		$this->assertTextContains("tsumego set 2", $this->view);
	}

	public function testIndexLoggedOff(): void {
		new ContextPreparator(['tsumego_sets' => [
			['name' => 'tsumego set 1', 'num' => '666'],
			['name' => 'tsumego set 2', 'num' => '777']]], );
		$this->testAction('sets', ['return' => 'view']);
		$this->assertTextContains("tsumego set 1", $this->view);
		$this->assertTextContains("tsumego set 2", $this->view);
	}
}
