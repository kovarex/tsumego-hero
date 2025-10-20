<?php

class Solve extends AppModel {

	 public $validate = [
		 'img' => [
			 'rule' => 'notBlank',
		 ],
	 ];

}
