<?php
class User extends AppModel {

	var $name = 'User';
    
	var $validate = array(
		
		'name' => array(
		
			'notempty' => array(
				'rule' => 'notBlank',
				'required' => true,
				'message' => 'Please Insert Name'
			),
		
			'minmax' => array(
                'rule' => array('between', 3, 30),
                'message' => 'The length of the name should have at least 3 characters'
            ),

			'checkUnique' => array(
				'rule' => array('checkUnique', 'name'),
				'message' => 'Name already exists'
			),

		),
		
		'email' => array(
			'notempty' => array(
                'rule' => 'notBlank',
                'message' => 'Insert Email'
            ),
            
            'email' => array(
				'rule' => array('email', false),
				'message' => 'Enter a valid email-address'
			),
			
			'checkUnique' => array(
				'rule' => array('checkUnique', 'email'),
				'message' => 'Email already exists'
			),

		),
		
		'pw' => array(
			'notempty' => array(
				'rule' => 'notBlank',
				'message' => 'Please insert Password'
			),
			
			'minmax' => array(
                'rule' => array('between', 4, 40),
                'message' => 'The password should have at least 4 characters'
            ),
			'match' => array(
			     'rule' => 'checkPasswords',
			     'message' => 'Passwords do not match'
			),
			
		),
		
		
	);
	
	
	function checkUnique($data, $field){
		$valid = false;
		if($this->hasField($field)){
			$valid = $this->isUnique(array($field=>$data));
		}
		return $valid;
	}

	function checkPasswords() {
		if(isset($this->data['User']['pw2'])){
			return $this->data['User']['pw'] == $this->data['User']['pw2'];
		}else{
			return true;
		}
	}
	

}
?>