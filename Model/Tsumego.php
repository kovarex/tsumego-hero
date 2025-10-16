<?php

class Tsumego extends AppModel {
	 public $validate = array(
        'title' => array(
            'rule' => 'notBlank'
        ),
        'sgf1' => array(
            'rule' => 'notBlank'
        )
    );
	
	
}

?>