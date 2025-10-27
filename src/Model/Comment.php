<?php

class Comment extends AppModel {
	public function __construct() {
		parent::__construct(false, 'comment');
	}
	public $name = 'Comment';
}
