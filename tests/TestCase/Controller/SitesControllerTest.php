<?php
class SitesControllerTest extends ControllerTestCase {

	/**
	 * @return void
	 */
	public function testIndex(): void {
		$result = $this->testAction('/sites/index');
		debug($result);
	}

}
