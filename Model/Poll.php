<?php

class Poll extends AppModel {
	 public $validate = array(
        'img' => array(
            'rule' => 'notBlank'
        )
    );
	
	
}

?>