<?php

class TsumegoControllerTestCase extends ControllerTestCase {
	public function setUp(): void {
		parent::setUp();
		CakeSession::destroy();
	}
}
