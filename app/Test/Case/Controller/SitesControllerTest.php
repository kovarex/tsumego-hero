<?php
class SitesControllerTest extends ControllerTestCase  {

	public function testIndex(){
		$result = $this->testAction('/sites/index');
        debug($result);
		
	}
}




