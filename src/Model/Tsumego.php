<?php

class Tsumego extends AppModel {

	 public $validate = [
		 'title' => [
			 'rule' => 'notBlank',
		 ],
		 'sgf1' => [
			 'rule' => 'notBlank',
		 ],
	 ];

}
