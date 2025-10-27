<?php

class Schedule extends AppModel {
	public function __construct() {
		parent::__construct(false, 'schedule');
	}
	public $name = 'Schedule';
}
